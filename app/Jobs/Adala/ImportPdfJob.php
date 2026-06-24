<?php

namespace App\Jobs\Adala;

use App\Services\Adala\AdalaDocumentPipelineService;
use Throwable;

class ImportPdfJob extends AdalaDocumentJob
{
    public function handle(AdalaDocumentPipelineService $pipeline): void
    {
        try {
            $pipeline->import($this->loadDocument());
        } catch (Throwable $error) {
            $this->failDocument($error);
        }
    }
}
