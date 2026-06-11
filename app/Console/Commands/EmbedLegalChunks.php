<?php

namespace App\Console\Commands;

use App\Models\LegalChunk;
use App\Services\LegalEmbeddingService;
use Illuminate\Console\Command;

class EmbedLegalChunks extends Command
{
    protected $signature = 'corpus:embed-chunks
        {--limit= : Embed only this many chunks}
        {--active-only : Embed only chunks belonging to the active corpus (current document versions)}
        {--force : Rebuild embeddings even when model and checksum match}';

    protected $description = 'Generate semantic embeddings for legal corpus chunks using the configured local provider.';

    public function handle(LegalEmbeddingService $embeddings): int
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
        $failed = false;

        $query = LegalChunk::query()
            ->select(['legal_chunks.id', 'legal_chunks.content', 'legal_chunks.embedding_model', 'legal_chunks.embedding_checksum'])
            ->orderBy('legal_chunks.id');

        if ($this->option('active-only')) {
            $query
                ->join('legal_articles', 'legal_articles.id', '=', 'legal_chunks.legal_article_id')
                ->join('legal_documents', 'legal_documents.id', '=', 'legal_articles.legal_document_id')
                ->where('legal_documents.status', 'active')
                ->where('legal_articles.status', 'active')
                ->whereColumn('legal_documents.current_version_id', 'legal_chunks.legal_document_version_id');
        }

        if ($limit) {
            $query->limit($limit);
        }

        $query->chunkById(50, column: 'legal_chunks.id', alias: 'id', callback: function ($chunks) use ($embeddings, &$processed, &$updated, &$skipped, &$failed): bool {
            $pending = [];

            foreach ($chunks as $chunk) {
                $processed++;
                $checksum = $embeddings->checksum($chunk->content);

                if (!$this->option('force')
                    && $chunk->embedding_model === $embeddings->model()
                    && $chunk->embedding_checksum === $checksum) {
                    $skipped++;

                    continue;
                }

                $pending[] = ['chunk' => $chunk, 'checksum' => $checksum];
            }

            foreach (array_chunk($pending, 16) as $batch) {
                $vectors = null;

                foreach (range(1, 3) as $attempt) {
                    $vectors = $embeddings->embedBatch(array_map(
                        fn (array $item): string => (string) $item['chunk']->content,
                        $batch
                    ));

                    if ($vectors !== null) {
                        break;
                    }

                    sleep($attempt * 2);
                }

                if ($vectors === null) {
                    $this->error('Embedding batch failed'.($embeddings->lastError() ? ': '.$embeddings->lastError() : ''));
                    $failed = true;

                    return false;
                }

                foreach ($batch as $index => $item) {
                    $vector = $vectors[$index] ?? null;

                    if (!$vector) {
                        $this->error('Embedding failed for chunk '.$item['chunk']->id);
                        $failed = true;

                        return false;
                    }

                    // Packed float32 + binary code replace the bulky JSON vector.
                    $item['chunk']->forceFill([
                        'embedding' => null,
                        'embedding_packed' => $embeddings->packVector($vector),
                        'embedding_binary' => $embeddings->binarizeVector($vector),
                        'embedding_model' => $embeddings->model(),
                        'embedding_checksum' => $item['checksum'],
                        'embedded_at' => now(),
                    ])->save();

                    $updated++;
                }
            }

            $this->line("Processed {$processed}; updated {$updated}; skipped {$skipped}.");

            return true;
        });

        if ($failed) {
            $this->warn("Embedding stopped early. Processed {$processed}; updated {$updated}; skipped {$skipped}.");

            return self::FAILURE;
        }

        $this->info("Embedding complete. Processed {$processed}; updated {$updated}; skipped {$skipped}.");

        return self::SUCCESS;
    }
}
