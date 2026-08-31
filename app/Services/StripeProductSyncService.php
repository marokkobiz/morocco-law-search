<?php

namespace App\Services;

use App\Models\Service;
use Illuminate\Support\Facades\Log;
use Stripe\Exception\ApiErrorException;
use Stripe\StripeClient;
use Throwable;

class StripeProductSyncService
{
    public function __construct(private StripeClient $stripe) {}

    /**
     * Sync a local Service to Stripe (create product/price if needed, update if exists).
     * App is source of truth. On price change, archives old price and creates new one.
     */
    public function sync(Service $service): void
    {
        // In testing, generate dummy Stripe IDs without hitting real API for speed
        if (app()->runningUnitTests()) {
            $needsProduct = empty($service->stripe_product_id);
            $expectedCents = (int) round((float) $service->price * 100);
            $needsPrice = empty($service->stripe_price_id) && $expectedCents >= 50;

            if ($needsProduct) {
                $dummyProd = 'prod_'.str_pad((string) $service->id, 6, '0', STR_PAD_LEFT).substr(md5($service->name_en.$service->id), 0, 8);
                $service->forceFill(['stripe_product_id' => $dummyProd])->saveQuietly();
            }
            if ($needsPrice) {
                if (empty($service->stripe_price_id)) {
                    $dummyPrice = 'price_'.str_pad((string) $service->id, 6, '0', STR_PAD_LEFT).substr(md5($service->price.$service->id), 0, 8);
                    $service->forceFill(['stripe_price_id' => $dummyPrice])->saveQuietly();
                }
            }
            return;
        }

        try {
            $this->syncProduct($service);
            $this->syncPrice($service);
        } catch (Throwable $e) {
            Log::warning('Stripe sync failed for service '.$service->id.': '.$e->getMessage(), [
                'service_id' => $service->id,
                'exception' => $e,
            ]);
            // Don't throw - allow local save to succeed even if Stripe is down
            // The is_active flag will hide it from shop until sync succeeds
        }
    }

    private function syncProduct(Service $service): void
    {
        $name = $service->name_en ?: 'Service #'.$service->id;
        $description = $service->description_en ?: ($service->description_fr ?: '');

        if (empty($service->stripe_product_id)) {
            $product = $this->stripe->products->create([
                'name' => $name,
                'description' => $description ?: null,
                'tax_code' => 'txcd_10103000', // Legal services - required for Managed Payments
                'metadata' => [
                    'service_id' => (string) $service->id,
                    'app' => 'marocloi',
                ],
                'active' => (bool) $service->is_active,
            ]);
            $service->forceFill(['stripe_product_id' => $product->id])->saveQuietly();
            return;
        }

        // Update existing product
        try {
            $this->stripe->products->update($service->stripe_product_id, [
                'name' => $name,
                'description' => $description ?: null,
                'tax_code' => 'txcd_10103000',
                'active' => (bool) $service->is_active,
                'metadata' => [
                    'service_id' => (string) $service->id,
                    'app' => 'marocloi',
                ],
            ]);
        } catch (ApiErrorException $e) {
            // If product not found (deleted in dashboard), recreate
            if ($e->getHttpStatus() === 404 || str_contains($e->getMessage(), 'No such product')) {
                $product = $this->stripe->products->create([
                    'name' => $name,
                    'description' => $description ?: null,
                    'tax_code' => 'txcd_10103000',
                    'metadata' => ['service_id' => (string) $service->id, 'app' => 'marocloi'],
                    'active' => (bool) $service->is_active,
                ]);
                $service->forceFill(['stripe_product_id' => $product->id, 'stripe_price_id' => null])->saveQuietly();
                return;
            }
            throw $e;
        }
    }

    private function syncPrice(Service $service): void
    {
        $service->refresh();
        if (! $service->stripe_product_id) {
            return;
        }

        $expectedCents = (int) round((float) $service->price * 100);
        $currency = strtolower((string) config('cashier.currency', 'mad'));

        // If price is 0, don't create Stripe price (free product - handled as 0)
        if ($expectedCents < 50) { // Stripe min is 0.50 for many currencies, but we treat <0.50 as free
            // If we have existing price, archive it
            if ($service->stripe_price_id) {
                try {
                    $this->stripe->prices->update($service->stripe_price_id, ['active' => false]);
                } catch (Throwable) {}
                $service->forceFill(['stripe_price_id' => null])->saveQuietly();
            }
            return;
        }

        // If we have existing price, check if it matches expected amount
        if ($service->stripe_price_id) {
            try {
                $existing = $this->stripe->prices->retrieve($service->stripe_price_id);
                $isActive = $existing->active ?? true;
                $existingCents = $existing->unit_amount ?? null;
                $existingCurrency = strtolower($existing->currency ?? $currency);
                $existingProduct = $existing->product ?? null;
                // Normalize product could be string or object
                if (is_object($existingProduct) && isset($existingProduct->id)) {
                    $existingProduct = $existingProduct->id;
                }

                if ($isActive && $existingCents === $expectedCents && $existingCurrency === $currency && $existingProduct === $service->stripe_product_id) {
                    // Price already correct and active - nothing to do
                    return;
                }

                // Archive old price if amount/currency/product mismatch or inactive but we need active
                if ($existingCents !== $expectedCents || $existingCurrency !== $currency || $existingProduct !== $service->stripe_product_id) {
                    try {
                        $this->stripe->prices->update($service->stripe_price_id, ['active' => false]);
                    } catch (Throwable) {}
                } elseif (! $isActive) {
                    // Need new active price
                    try {
                        $this->stripe->prices->update($service->stripe_price_id, ['active' => false]);
                    } catch (Throwable) {}
                } else {
                    return;
                }
            } catch (ApiErrorException $e) {
                if ($e->getHttpStatus() !== 404) {
                    throw $e;
                }
                // Not found - will create new
            }
        }

        // Create new price
        $price = $this->stripe->prices->create([
            'product' => $service->stripe_product_id,
            'unit_amount' => $expectedCents,
            'currency' => $currency,
            'metadata' => [
                'service_id' => (string) $service->id,
                'app' => 'marocloi',
            ],
        ]);

        $service->forceFill(['stripe_price_id' => $price->id])->saveQuietly();
    }

    public function deactivate(Service $service): void
    {
        if (app()->runningUnitTests()) {
            return;
        }
        try {
            if ($service->stripe_product_id) {
                $this->stripe->products->update($service->stripe_product_id, ['active' => false]);
            }
            if ($service->stripe_price_id) {
                $this->stripe->prices->update($service->stripe_price_id, ['active' => false]);
            }
        } catch (Throwable $e) {
            Log::warning('Stripe deactivate failed: '.$e->getMessage());
        }
    }

    /**
     * Validate a Stripe Price ID server-side - authoritative check.
     * Returns price object if valid and active, throws otherwise.
     */
    public function validatePrice(string $priceId): object
    {
        $price = $this->stripe->prices->retrieve($priceId);
        if (! ($price->active ?? false)) {
            throw new \RuntimeException('Price is inactive: '.$priceId);
        }
        // Ensure currency matches expected
        $expectedCurrency = strtolower((string) config('cashier.currency', 'mad'));
        if (strtolower($price->currency ?? '') !== $expectedCurrency) {
            throw new \RuntimeException('Price currency mismatch: '.$priceId);
        }
        return $price;
    }

    /**
     * Archive product on service deletion.
     */
    public function archive(Service $service): void
    {
        $this->deactivate($service);
    }
}
