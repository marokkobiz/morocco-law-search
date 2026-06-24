<?php

namespace App\Console\Commands;

use App\Models\AdalaCrawlRun;
use App\Models\AdalaDocument;
use App\Services\Adala\AdalaCrawlOrchestrator;
use Illuminate\Console\Command;
use Throwable;

class RetryFailedAdalaDocuments extends Command
{
    protected $signature = 'adala:retry-failed
        {--run= : Crawl run ID to retry (defaults to the latest run)}
        {--reset-retry-count : Also retry documents that already exhausted their retries}';

    protected $description = 'Re-queue failed Adala documents back into the ingestion pipeline.';

    public function handle(AdalaCrawlOrchestrator $orchestrator): int
    {
        $run = $this->option('run')
            ? AdalaCrawlRun::query()->find((int) $this->option('run'))
            : AdalaCrawlRun::query()->latest('id')->first();

        if (!$run) {
            $this->error('No Adala crawl run found.');

            return self::FAILURE;
        }

        $failedQuery = AdalaDocument::query()
            ->where('adala_crawl_run_id', $run->id)
            ->where('status', AdalaDocument::STATUS_FAILED);

        $failedCount = (clone $failedQuery)->count();

        if ($failedCount === 0) {
            $this->info("No failed documents to retry for run #{$run->id}.");

            return self::SUCCESS;
        }

        if ($this->option('reset-retry-count')) {
            $reset = (clone $failedQuery)->update(['retry_count' => 0]);
            $this->line("Reset retry counters on {$reset} document(s).");
        }

        $maxRetries = (int) config('adala.processing.max_retries', 5);
        $eligible = (clone $failedQuery)->where('retry_count', '<', $maxRetries)->count();
        $skipped = $failedCount - $eligible;

        try {
            $run = $orchestrator->resume($run->id, true);
        } catch (Throwable $error) {
            $this->error($error->getMessage());

            return self::FAILURE;
        }

        $this->info("Re-queued {$eligible} failed document(s) for run #{$run->id}.");

        if ($skipped > 0) {
            $this->warn("Skipped {$skipped} document(s) at the retry limit ({$maxRetries}). Use --reset-retry-count to force them.");
        }

        $this->line('Ensure a worker is running:');
        $this->line('  php artisan queue:work --queue='.config('adala.queue', 'adala').' --timeout=0 --tries=1');
        $this->line('Monitor progress with:');
        $this->line('  php artisan adala:status --run='.$run->id);

        return self::SUCCESS;
    }
}
