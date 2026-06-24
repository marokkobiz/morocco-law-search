<?php

namespace App\Console\Commands;

use App\Models\LegalChunk;
use App\Services\LegalEmbeddingService;
use App\Services\LegalVectorStoreService;
use Illuminate\Console\Command;

class EmbedLegalChunks extends Command
{
    protected $signature = 'corpus:embed-chunks
        {--limit= : Embed only this many chunks}
        {--force : Rebuild embeddings even when model and checksum match}';

    protected $description = 'Generate semantic embeddings for legal corpus chunks using the configured local provider.';

    public function handle(LegalEmbeddingService $embeddings, LegalVectorStoreService $vectorStore): int
    {
        if (!$embeddings->isEnabled()) {
            $this->warn('Semantic embeddings are disabled.');

            return self::SUCCESS;
        }

        $limit = $this->option('limit');
        $limit = $limit === null || $limit === '' ? null : max(1, (int) $limit);
        $processed = 0;
        $updated = 0;
        $skipped = 0;
        $synced = 0;
        $failed = false;

        $query = LegalChunk::query()
            ->select(['id', 'legal_article_id', 'legal_document_version_id', 'chunk_index', 'content', 'domain', 'subdomain', 'tags', 'embedding', 'embedding_model', 'embedding_checksum'])
            ->orderBy('id');

        if ($limit) {
            $query->limit($limit);
        }

        $query->chunkById(50, function ($chunks) use ($embeddings, $vectorStore, &$processed, &$updated, &$skipped, &$synced, &$failed): bool {
            $pendingQdrant = [];

            foreach ($chunks as $chunk) {
                $processed++;
                $checksum = $embeddings->checksum($chunk->content);

                if (!$this->option('force')
                    && $chunk->embedding_model === $embeddings->model()
                    && $chunk->embedding_checksum === $checksum) {
                    $skipped++;

                    continue;
                }

                $vector = $embeddings->embed($chunk->content);

                if (!$vector) {
                    $this->error('Embedding failed for chunk '.$chunk->id.($embeddings->lastError() ? ': '.$embeddings->lastError() : ''));
                    $failed = true;

                    return false;
                }

                $chunk->forceFill([
                    'embedding' => $vector,
                    'embedding_model' => $embeddings->model(),
                    'embedding_checksum' => $checksum,
                    'embedded_at' => now(),
                ])->save();

                $updated++;
                $pendingQdrant[] = [
                    'chunk' => $chunk->fresh(),
                    'vector' => $vector,
                ];
            }

            if ($pendingQdrant !== [] && $vectorStore->isEnabled()) {
                if (!$vectorStore->upsertChunks($pendingQdrant)) {
                    $this->error('Qdrant sync failed.'.($vectorStore->lastError() ? ' '.$vectorStore->lastError() : ''));
                    $failed = true;

                    return false;
                }

                $synced += count($pendingQdrant);
            }

            $this->line("Processed {$processed}; updated {$updated}; synced {$synced}; skipped {$skipped}.");

            return true;
        });

        if ($failed) {
            $this->warn("Embedding stopped early. Processed {$processed}; updated {$updated}; synced {$synced}; skipped {$skipped}.");

            return self::FAILURE;
        }

        $this->info("Embedding complete. Processed {$processed}; updated {$updated}; synced {$synced}; skipped {$skipped}.");

        return self::SUCCESS;
    }
}
