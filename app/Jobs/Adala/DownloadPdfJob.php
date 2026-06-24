<?php

namespace App\Jobs\Adala;

use App\Services\Adala\AdalaDocumentPipelineService;
use Throwable;

class DownloadPdfJob extends AdalaDocumentJob
{
    public function handle(AdalaDocumentPipelineService $pipeline): void
    {
        try {
            $pipeline->download($this->loadDocument());
        } catch (Throwable $error) {
            $this->failDocument($error);
        }
    }
}
