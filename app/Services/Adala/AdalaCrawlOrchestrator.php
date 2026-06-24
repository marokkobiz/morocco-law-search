<?php

namespace App\Services\Adala;

use App\Jobs\Adala\DiscoverAdalaDocumentsJob;
use App\Jobs\Adala\DownloadPdfJob;
use App\Jobs\Adala\ImportPdfJob;
use App\Jobs\Adala\BuildCorpusJob;
use App\Jobs\Adala\GenerateEmbeddingsJob;
use App\Jobs\Adala\SyncQdrantJob;
use App\Models\AdalaCrawlRun;
use App\Models\AdalaDocument;
use Illuminate\Support\Facades\Bus;
use RuntimeException;

class AdalaCrawlOrchestrator
{
    public function __construct(
        private readonly AdalaDiscoveryService $discovery,
        private readonly AdalaUrlNormalizer $urls,
    ) {
    }

    public function start(array $options = []): AdalaCrawlRun
    {
        $running = AdalaCrawlRun::query()
            ->where('status', AdalaCrawlRun::STATUS_RUNNING)
            ->latest('id')
            ->first();

        if ($running && !($options['force'] ?? false)) {
            throw new RuntimeException("Adala crawl run #{$running->id} is already running.");
        }

        $run = AdalaCrawlRun::query()->create([
            'status' => AdalaCrawlRun::STATUS_PENDING,
            'seed_urls' => $options['seed_urls'] ?? config('adala.seed_urls', []),
            'metadata' => [
                'retry_failed' => (bool) ($options['retry_failed'] ?? false),
                'document_limit' => isset($options['limit']) ? (int) $options['limit'] : null,
            ],
        ]);

        $this->discovery->seedRunPages($run);
        $run->markRunning();

        $this->dispatchDiscovery($run->id);

        return $run;
    }

    public function resume(int $runId, bool $retryFailed = false): AdalaCrawlRun
    {
        $run = AdalaCrawlRun::query()->findOrFail($runId);

        if ($retryFailed) {
            $failedDocuments = AdalaDocument::query()
                ->where('adala_crawl_run_id', $run->id)
                ->where('status', AdalaDocument::STATUS_FAILED)
                ->where('retry_count', '<', (int) config('adala.processing.max_retries', 5))
                ->orderBy('id')
                ->get();

            foreach ($failedDocuments as $document) {
                $normalized = $this->urls->normalize($document->source_url);
                $hash = $this->urls->hash($normalized);
                $duplicate = AdalaDocument::query()
                    ->where('adala_crawl_run_id', $run->id)
                    ->where('id', '!=', $document->id)
                    ->where('url_hash', $hash)
                    ->exists();

                if ($duplicate) {
                    $document->delete();

                    continue;
                }

                $document->forceFill([
                    'status' => AdalaDocument::STATUS_DISCOVERED,
                    'error_message' => null,
                    'source_url' => $normalized,
                    'normalized_url' => $normalized,
                    'url_hash' => $hash,
                    'language' => $document->language ?: $this->urls->languageFromUrl($document->source_url),
                ])->save();
            }
        }

        if ($run->pages()->where('status', 'pending')->doesntExist()) {
            $this->discovery->seedRunPages($run);
        }

        $run->markRunning();

        $pending = AdalaDocument::query()
            ->where('adala_crawl_run_id', $run->id)
            ->where('status', AdalaDocument::STATUS_DISCOVERED)
            ->orderBy('id')
            ->first();

        if ($pending) {
            $this->dispatchDocumentPipeline($pending);
        } else {
            $this->dispatchDiscovery($run->id);
        }

        return $run->fresh();
    }

    public function dispatchDocumentPipeline(AdalaDocument $document): void
    {
        $this->dispatchPipelineFromStatus($document, AdalaDocument::STATUS_DISCOVERED);
    }

    public function dispatchPipelineFromStatus(AdalaDocument $document, ?string $startingStatus = null): void
    {
        $status = $startingStatus ?? $document->status;
        $queue = (string) config('adala.queue', 'adala');
        $jobs = [];

        if (in_array($status, [AdalaDocument::STATUS_DISCOVERED, AdalaDocument::STATUS_DOWNLOADING], true)) {
            $jobs[] = new DownloadPdfJob($document->id);
        }

        if (in_array($status, [
            AdalaDocument::STATUS_DISCOVERED,
            AdalaDocument::STATUS_DOWNLOADING,
            AdalaDocument::STATUS_DOWNLOADED,
        ], true)) {
            $jobs[] = new ImportPdfJob($document->id);
        }

        if (in_array($status, [
            AdalaDocument::STATUS_DISCOVERED,
            AdalaDocument::STATUS_DOWNLOADING,
            AdalaDocument::STATUS_DOWNLOADED,
            AdalaDocument::STATUS_IMPORTED,
        ], true)) {
            $jobs[] = new BuildCorpusJob($document->id);
        }

        if (in_array($status, [
            AdalaDocument::STATUS_DISCOVERED,
            AdalaDocument::STATUS_DOWNLOADING,
            AdalaDocument::STATUS_DOWNLOADED,
            AdalaDocument::STATUS_IMPORTED,
            AdalaDocument::STATUS_CHUNKED,
        ], true)) {
            $jobs[] = new GenerateEmbeddingsJob($document->id);
        }

        $jobs[] = new SyncQdrantJob($document->id);
        $jobs[] = new DiscoverAdalaDocumentsJob($document->adala_crawl_run_id);

        Bus::chain($jobs)->onQueue($queue)->dispatch();
    }

    public function dispatchDiscovery(int $runId): void
    {
        DiscoverAdalaDocumentsJob::dispatch($runId)
            ->onQueue((string) config('adala.queue', 'adala'));
    }

    public function shouldStop(AdalaCrawlRun $run): bool
    {
        $limit = data_get($run->metadata, 'document_limit');

        if ($limit !== null && (int) $run->documents_completed >= (int) $limit) {
            return true;
        }

        return !$this->discovery->hasPendingPages($run)
            && !AdalaDocument::query()
                ->where('adala_crawl_run_id', $run->id)
                ->whereNotIn('status', [AdalaDocument::STATUS_COMPLETED, AdalaDocument::STATUS_FAILED])
                ->exists();
    }

    public function finalizeIfComplete(AdalaCrawlRun $run): void
    {
        $run->refresh();

        if ($this->shouldStop($run)) {
            $run->markCompleted();
        }
    }
}
