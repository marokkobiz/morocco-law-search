<?php

namespace App\Jobs\Adala;

use App\Models\AdalaDocument;
use App\Services\Adala\AdalaCrawlOrchestrator;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessAdalaDocumentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public int $documentId)
    {
        $this->onQueue((string) config('adala.queue', 'adala'));
    }

    public function handle(AdalaCrawlOrchestrator $orchestrator): void
    {
        $document = AdalaDocument::query()->findOrFail($this->documentId);
        $orchestrator->dispatchPipelineFromStatus($document);
    }
}
