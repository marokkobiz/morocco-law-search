<?php

namespace App\Console\Commands;

use App\Services\Adala\AdalaCrawlOrchestrator;
use Illuminate\Console\Command;
use Throwable;

class CrawlAdalaSources extends Command
{
    protected $signature = 'adala:crawl
        {--resume= : Resume an existing crawl run by ID}
        {--retry-failed : Re-queue failed documents before continuing discovery}
        {--limit= : Stop after this many completed documents}
        {--force : Start a new run even if another run is marked running}';

    protected $description = 'Discover Adala PDFs sequentially and index each document through the full legal search pipeline.';

    public function handle(AdalaCrawlOrchestrator $orchestrator): int
    {
        try {
            if ($this->option('resume')) {
                $run = $orchestrator->resume(
                    (int) $this->option('resume'),
                    (bool) $this->option('retry-failed'),
                );
                $this->info("Resumed Adala crawl run #{$run->id}.");
            } else {
                $run = $orchestrator->start([
                    'retry_failed' => (bool) $this->option('retry-failed'),
                    'limit' => $this->option('limit'),
                    'force' => (bool) $this->option('force'),
                ]);
                $this->info("Started Adala crawl run #{$run->id}.");
            }
        } catch (Throwable $error) {
            $this->error($error->getMessage());

            return self::FAILURE;
        }

        $languages = implode(', ', (array) config('adala.allowed_languages', ['fr', 'en']));
        $this->line("Language filter: {$languages} only.");
        $this->line('Queue a worker on the adala queue, for example:');
        $this->line('  php artisan queue:work --queue=adala --timeout=0 --tries=1');
        $this->line('Monitor progress with:');
        $this->line('  php artisan adala:status --run='.$run->id);

        return self::SUCCESS;
    }
}
