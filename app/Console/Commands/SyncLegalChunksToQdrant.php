<?php

namespace App\Console\Commands;

use App\Models\LegalChunk;
use App\Services\LegalEmbeddingService;
use App\Services\LegalVectorStoreService;
use Illuminate\Console\Command;

class SyncLegalChunksToQdrant extends Command
{
    protected $signature = 'corpus:sync-qdrant
        {--limit= : Sync only this many chunks}
        {--force : Re-upsert chunks even when checksum matches the SQL embedding record}';

    protected $description = 'Sync embedded legal corpus chunks from SQL into the Qdrant vector store.';

    public function handle(LegalEmbeddingService $embeddings, LegalVectorStoreService $vectorStore): int
    {
        if (!$vectorStore->isEnabled()) {
            $this->warn('Qdrant is disabled. Set QDRANT_ENABLED=true to sync vectors.');

            return self::SUCCESS;
        }

        if (!$vectorStore->ensureCollection()) {
            $this->error('Unable to connect to Qdrant.'.($vectorStore->lastError() ? ' '.$vectorStore->lastError() : ''));

            return self::FAILURE;
        }

        $limit = $this->option('limit');
        $limit = $limit === null || $limit === '' ? null : max(1, (int) $limit);
        $processed = 0;
        $synced = 0;
        $skipped = 0;
        $failed = false;

        $query = LegalChunk::query()
            ->whereNotNull('embedding')
            ->orderBy('id');

        if (!$this->option('force')) {
            $query->where('embedding_model', $embeddings->model());
        }

        if ($limit) {
            $query->limit($limit);
        }

        $query->chunkById(50, function ($chunks) use ($embeddings, $vectorStore, &$processed, &$synced, &$skipped, &$failed): bool {
            $entries = [];

            foreach ($chunks as $chunk) {
                $processed++;
                $vector = $embeddings->decodeStoredVector($chunk->embedding);

                if (!$vector) {
                    $skipped++;
                    $this->warn('Skipping chunk '.$chunk->id.' because its stored embedding is invalid.');

                    continue;
                }

                $entries[] = [
                    'chunk' => $chunk,
                    'vector' => $vector,
                ];
            }

            if ($entries === []) {
                return true;
            }

            if (!$vectorStore->upsertChunks($entries)) {
                $this->error('Qdrant sync failed.'.($vectorStore->lastError() ? ' '.$vectorStore->lastError() : ''));
                $failed = true;

                return false;
            }

            $synced += count($entries);
            $this->line("Processed {$processed}; synced {$synced}; skipped {$skipped}.");

            return true;
        });

        if ($failed) {
            $this->warn("Qdrant sync stopped early. Processed {$processed}; synced {$synced}; skipped {$skipped}.");

            return self::FAILURE;
        }

        $this->info("Qdrant sync complete. Processed {$processed}; synced {$synced}; skipped {$skipped}.");

        return self::SUCCESS;
    }
}
