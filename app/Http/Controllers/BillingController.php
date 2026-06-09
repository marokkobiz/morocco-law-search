<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\View\View;

class BillingController extends Controller
{
    public function show(Request $request): View
    {
        return view('billing', [
            'lang' => $this->language($request),
            'user' => $request->user(),
            'stripeReady' => config('billing.stripe.secret') && config('billing.stripe.price_id'),
        ]);
    }

    public function checkout(Request $request): RedirectResponse
    {
        $user = $request->user();

        if (!$user) {
            return redirect('/login?lang='.$this->language($request));
        }

        if (!config('billing.require_payment')) {
            $user->markBillingActive('local-billing-disabled');

            return redirect('/app?lang='.$this->language($request));
        }

        $secret = (string) config('billing.stripe.secret');
        $priceId = (string) config('billing.stripe.price_id');

        if ($secret === '' || $priceId === '') {
            return back()->withErrors([
                'billing' => 'Payment is not configured yet. Add Stripe keys before enabling paid access.',
            ]);
        }

        $successUrl = url('/billing/success').'?session_id={CHECKOUT_SESSION_ID}&lang='.$this->language($request);
        $cancelUrl = url('/billing').'?lang='.$this->language($request);

        $response = Http::asForm()
            ->withToken($secret)
            ->post('https://api.stripe.com/v1/checkout/sessions', [
                'mode' => 'subscription',
                'customer_email' => $user->email,
                'client_reference_id' => (string) $user->id,
                'line_items[0][price]' => $priceId,
                'line_items[0][quantity]' => 1,
                'success_url' => $successUrl,
                'cancel_url' => $cancelUrl,
                'metadata[user_id]' => (string) $user->id,
            ]);

        if (!$response->successful()) {
            return back()->withErrors([
                'billing' => 'Stripe checkout failed. Check the configured secret key and price id.',
            ]);
        }

        return redirect()->away((string) $response->json('url'));
    }

    public function success(Request $request): RedirectResponse
    {
        $user = $request->user();

        if (!$user) {
            return redirect('/login?lang='.$this->language($request));
        }

        $sessionId = trim((string) $request->query('session_id', ''));

        if ($sessionId !== '' && config('billing.stripe.secret')) {
            $response = Http::withToken((string) config('billing.stripe.secret'))
                ->get('https://api.stripe.com/v1/checkout/sessions/'.$sessionId);

            if ($response->successful() && (string) $response->json('payment_status') !== 'unpaid') {
                $user->markBillingActive(
                    (string) ($response->json('subscription') ?: $sessionId),
                    (string) ($response->json('customer') ?: null),
                );
            }
        }

        return redirect('/app?lang='.$this->language($request));
    }

    public function webhook(Request $request): JsonResponse
    {
        $secret = (string) config('billing.stripe.webhook_secret');

        if ($secret !== '' && !$this->hasValidStripeSignature($request, $secret)) {
            return response()->json(['message' => 'Invalid signature'], 400);
        }

        $payload = $request->json()->all();
        $type = (string) ($payload['type'] ?? '');
        $object = (array) data_get($payload, 'data.object', []);
        $userId = (int) data_get($object, 'metadata.user_id', data_get($object, 'client_reference_id', 0));
        $user = $userId > 0 ? User::query()->find($userId) : null;

        if (!$user && ($customerId = data_get($object, 'customer'))) {
            $user = User::query()->where('stripe_customer_id', $customerId)->first();
        }

        if ($user && $type === 'checkout.session.completed') {
            $user->markBillingActive(
                (string) (data_get($object, 'subscription') ?: data_get($object, 'id')),
                (string) data_get($object, 'customer', ''),
            );
        }

        if ($user && in_array($type, ['customer.subscription.deleted', 'customer.subscription.paused'], true)) {
            $user->forceFill([
                'access_status' => 'inactive',
                'billing_ends_at' => now(),
            ])->save();
        }

        return response()->json(['received' => true]);
    }

    private function hasValidStripeSignature(Request $request, string $secret): bool
    {
        $signatureHeader = (string) $request->header('Stripe-Signature', '');
        preg_match('/(?:^|,)t=(\d+)/', $signatureHeader, $timestamp);
        preg_match('/(?:^|,)v1=([a-f0-9]+)/', $signatureHeader, $signature);

        if (empty($timestamp[1]) || empty($signature[1])) {
            return false;
        }

        $signedPayload = $timestamp[1].'.'.$request->getContent();
        $expected = hash_hmac('sha256', $signedPayload, $secret);

        return hash_equals($expected, $signature[1]);
    }

    private function language(Request $request): string
    {
        $lang = (string) $request->input('lang', $request->query('lang', 'en'));

        return in_array($lang, ['en', 'fr', 'ar'], true) ? $lang : 'en';
    }
}
