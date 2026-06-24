<?php

namespace App\Jobs\Adala;

use App\Services\Adala\AdalaDocumentPipelineService;
use Throwable;

class BuildCorpusJob extends AdalaDocumentJob
{
    public function handle(AdalaDocumentPipelineService $pipeline): void
    {
        try {
            $pipeline->buildCorpus($this->loadDocument());
        } catch (Throwable $error) {
            $this->failDocument($error);
        }
    }
}
