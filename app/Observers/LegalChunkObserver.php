<?php

namespace App\Observers;

use App\Models\LegalChunk;
use App\Services\LegalVectorStoreService;

class LegalChunkObserver
{
    public function __construct(private readonly LegalVectorStoreService $vectorStore)
    {
    }

    public function deleted(LegalChunk $chunk): void
    {
        $this->vectorStore->deleteChunk((int) $chunk->id);
    }
}
