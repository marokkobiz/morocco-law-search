<?php

namespace App\Jobs\Adala;

use App\Services\Adala\AdalaDocumentPipelineService;
use Throwable;

class SyncQdrantJob extends AdalaDocumentJob
{
    public function handle(AdalaDocumentPipelineService $pipeline): void
    {
        try {
            $pipeline->verifyAndComplete($this->loadDocument());
        } catch (Throwable $error) {
            $this->failDocument($error);
        }
    }
}
