<?php

namespace App\Services;

use App\Models\LegalChunk;
use App\Models\LegalDocument;
use Illuminate\Support\Collection;
use RuntimeException;

class CorpusEmbeddingService
{
    public function __construct(
        private readonly LegalEmbeddingService $embeddings,
        private readonly LegalVectorStoreService $vectorStore,
    ) {
    }

    /**
     * @return array{embedded: int, skipped: int, failed: int}
     */
    public function embedChunksForSourceUrl(string $sourceUrl, ?int $batchSize = null): array
    {
        if (!$this->embeddings->isEnabled()) {
            throw new RuntimeException('Semantic embeddings are disabled.');
        }

        $batchSize = max(1, $batchSize ?? (int) config('adala.processing.embedding_batch_size', 25));
        $embedded = 0;
        $skipped = 0;
        $failed = 0;
        $chunkIds = $this->chunkIdsForSource($sourceUrl);

        if ($chunkIds === []) {
            return compact('embedded', 'skipped', 'failed');
        }

        LegalChunk::query()
            ->whereIn('id', $chunkIds)
            ->orderBy('id')
            ->chunkById($batchSize, function (Collection $chunks) use (&$embedded, &$skipped, &$failed): bool {
                $pendingQdrant = [];

                foreach ($chunks as $chunk) {
                    $checksum = $this->embeddings->checksum($chunk->content);

                    if ($chunk->embedding_model === $this->embeddings->model()
                        && $chunk->embedding_checksum === $checksum
                        && $chunk->embedding !== null) {
                        $skipped++;

                        continue;
                    }

                    $vector = $this->embedWithRetry($chunk->content);

                    if (!$vector) {
                        $failed++;

                        throw new RuntimeException(
                            'Embedding failed for chunk '.$chunk->id.': '.($this->embeddings->lastError() ?? 'unknown error')
                        );
                    }

                    $chunk->forceFill([
                        'embedding' => $vector,
                        'embedding_model' => $this->embeddings->model(),
                        'embedding_checksum' => $checksum,
                        'embedded_at' => now(),
                    ])->save();

                    $embedded++;
                    $pendingQdrant[] = ['chunk' => $chunk->fresh(), 'vector' => $vector];
                }

                if ($pendingQdrant !== [] && $this->vectorStore->isEnabled()) {
                    if (!$this->vectorStore->upsertChunks($pendingQdrant)) {
                        throw new RuntimeException('Qdrant sync failed: '.($this->vectorStore->lastError() ?? 'unknown error'));
                    }
                }

                return true;
            });

        return compact('embedded', 'skipped', 'failed');
    }

    /**
     * @return array{synced: int, verified: int}
     */
    public function syncAndVerifySourceUrl(string $sourceUrl): array
    {
        if (!$this->vectorStore->isEnabled()) {
            return ['synced' => 0, 'verified' => 0];
        }

        if (!$this->vectorStore->ensureCollection()) {
            throw new RuntimeException('Unable to connect to Qdrant: '.($this->vectorStore->lastError() ?? 'unknown error'));
        }

        $chunks = LegalChunk::query()
            ->whereIn('id', $this->chunkIdsForSource($sourceUrl))
            ->whereNotNull('embedding')
            ->where('embedding_model', $this->embeddings->model())
            ->get();

        $entries = [];

        foreach ($chunks as $chunk) {
            $vector = $this->embeddings->decodeStoredVector($chunk->embedding);

            if (!$vector) {
                throw new RuntimeException('Invalid stored embedding for chunk '.$chunk->id);
            }

            $entries[] = ['chunk' => $chunk, 'vector' => $vector];
        }

        if ($entries !== [] && !$this->vectorStore->upsertChunks($entries)) {
            throw new RuntimeException('Qdrant sync failed: '.($this->vectorStore->lastError() ?? 'unknown error'));
        }

        $chunkIds = $chunks->pluck('id')->map(fn ($id) => (int) $id)->all();
        $verified = $this->vectorStore->countPointsForChunkIds($chunkIds);

        if ($verified !== count($chunkIds)) {
            throw new RuntimeException(
                'Qdrant verification failed: expected '.count($chunkIds).' vectors, found '.($verified ?? 0)
            );
        }

        return [
            'synced' => count($entries),
            'verified' => $verified ?? 0,
        ];
    }

    public function countEmbeddedChunksForSourceUrl(string $sourceUrl): int
    {
        $chunkIds = $this->chunkIdsForSource($sourceUrl);

        if ($chunkIds === []) {
            return 0;
        }

        return LegalChunk::query()
            ->whereIn('id', $chunkIds)
            ->whereNotNull('embedding')
            ->where('embedding_model', $this->embeddings->model())
            ->count();
    }

    public function countChunksForSourceUrl(string $sourceUrl): int
    {
        return count($this->chunkIdsForSource($sourceUrl));
    }

    /**
     * @return array<int, int>
     */
    private function chunkIdsForSource(string $sourceUrl): array
    {
        $document = LegalDocument::query()->where('source_url', $sourceUrl)->first();

        if (!$document?->current_version_id) {
            return [];
        }

        return LegalChunk::query()
            ->where('legal_document_version_id', $document->current_version_id)
            ->orderBy('id')
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    private function embedWithRetry(string $text, int $maxAttempts = 3): ?array
    {
        $backoff = [2, 5, 10];

        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            $vector = $this->embeddings->embed($text);

            if ($vector) {
                return $vector;
            }

            if ($attempt < $maxAttempts) {
                sleep($backoff[$attempt - 1] ?? 10);
            }
        }

        return null;
    }
}
