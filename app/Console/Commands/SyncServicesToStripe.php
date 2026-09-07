<?php

namespace App\Console\Commands;

use App\Models\Service;
use App\Services\StripeProductSyncService;
use Illuminate\Console\Command;

class SyncServicesToStripe extends Command
{
    protected $signature = 'services:sync-stripe {--force : Force sync even if already synced}';
    protected $description = 'Sync all active services to Stripe (create products/prices where missing)';

    public function handle(StripeProductSyncService $sync): int
    {
        $query = Service::query();
        if (! $this->option('force')) {
            $query->where(function ($q) {
                $q->whereNull('stripe_product_id')->orWhereNull('stripe_price_id');
            });
        }

        $services = $query->get();
        if ($services->isEmpty()) {
            $this->info('All services already synced.');
            return self::SUCCESS;
        }

        $this->info("Syncing {$services->count()} service(s) to Stripe...");
        $bar = $this->output->createProgressBar($services->count());
        $bar->start();

        foreach ($services as $service) {
            try {
                $sync->sync($service);
                $service->refresh();
                $bar->advance();
            } catch (\Throwable $e) {
                $this->error("\nFailed service #{$service->id}: ".$e->getMessage());
                $bar->advance();
            }
        }

        $bar->finish();
        $this->newLine();
        $this->info('Done. Current Stripe IDs:');
        foreach (Service::all() as $s) {
            $this->line("  #{$s->id} {$s->name_en} | price={$s->price} | product=".($s->stripe_product_id ?: 'NULL')." | price_id=".($s->stripe_price_id ?: 'NULL')." | active=".($s->is_active ? 'yes' : 'no'));
        }

        return self::SUCCESS;
    }
}
