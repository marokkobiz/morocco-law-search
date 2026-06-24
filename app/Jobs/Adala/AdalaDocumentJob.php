<?php

namespace App\Jobs\Adala;

use App\Models\AdalaDocument;
use App\Services\Adala\AdalaCrawlOrchestrator;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

abstract class AdalaDocumentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout;

    public int $tries = 1;

    public function __construct(public int $documentId)
    {
        $this->timeout = max(60, (int) config('adala.processing.job_timeout_seconds', 3600));
        $this->onQueue((string) config('adala.queue', 'adala'));
    }

    protected function loadDocument(): AdalaDocument
    {
        return AdalaDocument::query()->findOrFail($this->documentId);
    }

    protected function failDocument(Throwable $error): void
    {
        $document = AdalaDocument::query()->find($this->documentId);

        if (!$document || $document->status === AdalaDocument::STATUS_FAILED) {
            return;
        }

        $message = trim($error->getMessage());

        if ($message === '') {
            $message = class_basename($error);
        }

        $document->markFailed($message, $this->failureStep());

        app(AdalaCrawlOrchestrator::class)->dispatchDiscovery($document->adala_crawl_run_id);
    }

    protected function failureStep(): string
    {
        return match (static::class) {
            DownloadPdfJob::class => 'download',
            ImportPdfJob::class => 'import',
            BuildCorpusJob::class => 'build_corpus',
            GenerateEmbeddingsJob::class => 'embed',
            SyncQdrantJob::class => 'sync_qdrant',
            default => class_basename(static::class),
        };
    }

    public function failed(?Throwable $exception): void
    {
        if ($exception) {
            $this->failDocument($exception);
        }
    }
}
