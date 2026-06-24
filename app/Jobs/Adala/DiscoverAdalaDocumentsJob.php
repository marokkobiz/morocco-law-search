<?php

namespace App\Jobs\Adala;

use App\Models\AdalaCrawlRun;
use App\Models\AdalaDocument;
use App\Services\Adala\AdalaCrawlOrchestrator;
use App\Services\Adala\AdalaDiscoveryService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class DiscoverAdalaDocumentsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 900;

    public int $tries = 1;

    public function __construct(public int $runId)
    {
        $this->onQueue((string) config('adala.queue', 'adala'));
    }

    public function handle(
        AdalaDiscoveryService $discovery,
        AdalaCrawlOrchestrator $orchestrator,
    ): void {
        $run = AdalaCrawlRun::query()->find($this->runId);

        if (!$run || $run->status === AdalaCrawlRun::STATUS_PAUSED) {
            return;
        }

        if ($orchestrator->shouldStop($run)) {
            $orchestrator->finalizeIfComplete($run);

            return;
        }

        $resumeDocument = $this->nextIncompleteDocument($run);

        if ($resumeDocument) {
            $orchestrator->dispatchPipelineFromStatus($resumeDocument);

            return;
        }

        $pendingDocument = $this->nextPendingDiscoveredDocument($run);

        if ($pendingDocument) {
            $orchestrator->dispatchDocumentPipeline($pendingDocument);

            return;
        }

        if ((bool) data_get($run->metadata, 'retry_failed', false)) {
            $failed = AdalaDocument::query()
                ->where('adala_crawl_run_id', $run->id)
                ->where('status', AdalaDocument::STATUS_FAILED)
                ->where('retry_count', '<', (int) config('adala.processing.max_retries', 5))
                ->orderBy('id')
                ->first();

            if ($failed) {
                $failed->forceFill([
                    'status' => AdalaDocument::STATUS_DISCOVERED,
                    'error_message' => null,
                ])->save();

                $orchestrator->dispatchDocumentPipeline($failed->fresh());

                return;
            }
        }

        try {
            $document = $discovery->discoverNextDocument($run);
        } catch (Throwable $error) {
            Log::warning('Adala page crawl failed', [
                'run_id' => $run->id,
                'message' => $error->getMessage(),
            ]);

            self::dispatch($run->id)->delay(now()->addSeconds(30));

            return;
        }

        if ($document) {
            $orchestrator->dispatchDocumentPipeline($document);

            return;
        }

        if ($discovery->hasPendingPages($run)) {
            self::dispatch($run->id)->delay(now()->addSeconds(1));

            return;
        }

        $pendingDocument = $this->nextPendingDiscoveredDocument($run);

        if ($pendingDocument) {
            $orchestrator->dispatchDocumentPipeline($pendingDocument);

            return;
        }

        $orchestrator->finalizeIfComplete($run->fresh());
    }

    private function nextPendingDiscoveredDocument(AdalaCrawlRun $run): ?AdalaDocument
    {
        return AdalaDocument::query()
            ->where('adala_crawl_run_id', $run->id)
            ->where('status', AdalaDocument::STATUS_DISCOVERED)
            ->orderBy('id')
            ->first();
    }

    private function nextIncompleteDocument(AdalaCrawlRun $run): ?AdalaDocument
    {
        return AdalaDocument::query()
            ->where('adala_crawl_run_id', $run->id)
            ->whereIn('status', [
                AdalaDocument::STATUS_DOWNLOADING,
                AdalaDocument::STATUS_DOWNLOADED,
                AdalaDocument::STATUS_IMPORTED,
                AdalaDocument::STATUS_CHUNKED,
                AdalaDocument::STATUS_EMBEDDED,
                AdalaDocument::STATUS_VECTORIZED,
            ])
            ->orderBy('id')
            ->first();
    }
}
