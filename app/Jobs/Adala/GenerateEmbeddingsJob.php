<?php

namespace App\Jobs\Adala;

use App\Services\Adala\AdalaDocumentPipelineService;
use Throwable;

class GenerateEmbeddingsJob extends AdalaDocumentJob
{
    public function handle(AdalaDocumentPipelineService $pipeline): void
    {
        try {
            $pipeline->embed($this->loadDocument());
        } catch (Throwable $error) {
            $this->failDocument($error);
        }
    }
}
