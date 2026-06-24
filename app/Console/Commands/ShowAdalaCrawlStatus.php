<?php

namespace App\Console\Commands;

use App\Models\AdalaCrawlRun;
use App\Services\Adala\AdalaCrawlStatisticsService;
use Illuminate\Console\Command;

class ShowAdalaCrawlStatus extends Command
{
    protected $signature = 'adala:status
        {--run= : Show statistics for a specific crawl run ID}
        {--watch : Refresh statistics every 5 seconds}
        {--failures : Show only failed documents and their error messages}';

    protected $description = 'Show Adala crawl discovery and indexing progress.';

    public function handle(AdalaCrawlStatisticsService $statistics): int
    {
        do {
            $runId = $this->option('run');
            $run = $runId
                ? AdalaCrawlRun::query()->find((int) $runId)
                : null;

            $stats = $statistics->forRun($run);

            if (!$stats['run']) {
                $this->warn($stats['message'] ?? 'No crawl run found.');

                return self::FAILURE;
            }

            if ($this->option('watch')) {
                $this->output->write("\033\2J\033[H");
            }

            if ($this->option('failures')) {
                $this->renderFailures($stats);

                if (!$this->option('watch')) {
                    break;
                }

                sleep(5);

                continue;
            }

            $this->info('Adala crawl run #'.$stats['run']['id'].' ('.$stats['run']['status'].')');
            $this->line('Started: '.($stats['run']['started_at'] ?? 'n/a'));
            $this->line('URLs discovered: '.($stats['run']['pages_discovered'] ?? $stats['index']['urls_discovered'] ?? 0));
            $this->line('Pages crawled: '.$stats['run']['pages_crawled'].' | Pending pages: '.$stats['run']['pending_pages']);
            $this->newLine();
            $this->line('Documents discovered: '.$stats['documents']['discovered']);
            $this->line('PDFs processed (completed): '.$stats['documents']['completed']);
            $this->line('PDFs failed: '.$stats['documents']['failed']);
            $this->line('PDFs pending/in progress: '.$stats['documents']['in_progress']);
            $this->newLine();
            $this->line('Embeddings generated: '.($stats['index']['embeddings_generated'] ?? 0));
            $this->line('Qdrant vectors synced: '.($stats['index']['vectors_synced'] ?? 0));
            $this->newLine();
            $this->line('Completed per hour: '.$stats['performance']['completed_per_hour']);
            $this->line('Average processing ms: '.($stats['performance']['avg_processing_ms'] ?? 'n/a'));
            $this->line('Estimated completion: '.($stats['performance']['eta'] ?? 'n/a'));

            if (!empty($stats['documents']['by_status'])) {
                $this->newLine();
                $this->line('Status breakdown:');

                foreach ($stats['documents']['by_status'] as $status => $count) {
                    $this->line("  {$status}: {$count}");
                }
            }

            $this->renderFailures($stats, limit: 5);

            if (!$this->option('watch')) {
                break;
            }

            sleep(5);
        } while (true);

        return self::SUCCESS;
    }

    private function renderFailures(array $stats, int $limit = 0): void
    {
        $failures = $stats['failures'] ?? [];

        if ($failures === []) {
            if ($this->option('failures')) {
                $this->info('No failed documents for this crawl run.');
            }

            return;
        }

        if ($limit > 0) {
            $failures = array_slice($failures, 0, $limit);
        }

        $this->newLine();
        $this->line($limit > 0 ? 'Recent failures:' : 'Failed documents:');

        foreach ($failures as $failure) {
            $step = $failure['failed_step'] ?? 'unknown';
            $title = $failure['title'] ?: 'Untitled document';
            $this->newLine();
            $this->line("<fg=red>#{$failure['id']}</> [{$step}] {$title}");
            $this->line('  URL: '.($failure['source_url'] ?? 'n/a'));
            $this->line('  Retries: '.($failure['retry_count'] ?? 0).' | Last attempt: '.($failure['last_attempt_at'] ?? 'n/a'));
            $this->line('  Reason: '.($failure['error_message'] ?? 'No error message recorded.'));
        }

        if ($limit > 0 && count($stats['failures'] ?? []) > $limit) {
            $remaining = count($stats['failures']) - $limit;
            $this->newLine();
            $this->line("{$remaining} more failed document(s). Run with --failures to see all.");
        }
    }
}
