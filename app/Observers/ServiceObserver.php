<?php

namespace App\Observers;

use App\Models\Service;
use App\Services\StripeProductSyncService;

class ServiceObserver
{
    public function __construct(private StripeProductSyncService $sync) {}

    public function saved(Service $service): void
    {
        // Avoid infinite loop: sync uses saveQuietly, so this won't re-trigger
        // Only sync if product/price missing or price changed
        if (app()->runningUnitTests()) {
            // In tests, sync generates dummy IDs quickly
            $this->sync->sync($service);
            return;
        }

        // In production/dev, queue sync to avoid slowing request?
        // For now sync synchronously but catch failures
        try {
            $this->sync->sync($service->fresh() ?? $service);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('ServiceObserver sync failed: '.$e->getMessage(), ['service_id' => $service->id]);
        }
    }

    public function deleted(Service $service): void
    {
        if (app()->runningUnitTests()) {
            return;
        }
        try {
            $this->sync->archive($service);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('ServiceObserver archive failed: '.$e->getMessage());
        }
    }
}
