<?php

namespace App\Services;

use App\Models\LegalChunk;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class LegalVectorStoreService
{
    private const CHAT_ONLY_CATEGORIES = ['official-bulletin', 'official_bulletin', 'Official Bulletin', 'Bulletins officiels'];

    private const CHAT_ONLY_SOURCE_TYPES = ['bo', 'official-bulletin', 'official_bulletin', 'official bulletin'];

    private ?string $lastError = null;

    public function isEnabled(): bool
    {
        return (bool) config('qdrant.enabled', true);
    }

    public function lastError(): ?string
    {
        return $this->lastError;
    }

    public function ensureCollection(?int $vectorSize = null): bool
    {
        if (!$this->isEnabled()) {
            return false;
        }

        $collection = $this->collection();
        $vectorSize = $vectorSize ?? (int) config('qdrant.vector_size', 768);

        try {
            $response = $this->client()->get('/collections/'.$collection);

            if ($response->successful()) {
                return true;
            }

            if ($response->status() !== 404) {
                return $this->recordFailure('Qdrant collection lookup failed', $response->status(), $response->body());
            }

            $create = $this->client()->put('/collections/'.$collection, [
                'vectors' => [
                    'size' => $vectorSize,
                    'distance' => 'Cosine',
                ],
            ]);

            if (!$create->successful()) {
                return $this->recordFailure('Qdrant collection creation failed', $create->status(), $create->body());
            }

            return true;
        } catch (Throwable $error) {
            return $this->recordThrowable('Qdrant collection setup unavailable', $error);
        }
    }

    public function upsertChunk(LegalChunk $chunk, array $vector): bool
    {
        return $this->upsertChunks([[
            'chunk' => $chunk,
            'vector' => $vector,
        ]]);
    }

    /**
     * @param  array<int, array{chunk: LegalChunk, vector: array<int, float>}>  $entries
     */
    public function upsertChunks(array $entries): bool
    {
        if (!$this->isEnabled() || $entries === []) {
            return false;
        }

        if (!$this->ensureCollection(count($entries[0]['vector'] ?? []))) {
            return false;
        }

        $points = [];

        foreach ($entries as $entry) {
            $chunk = $entry['chunk'];
            $vector = $this->normalizeVector($entry['vector']);

            if (!$vector) {
                continue;
            }

            $points[] = [
                'id' => (int) $chunk->id,
                'vector' => $vector,
                'payload' => $this->buildPayload($chunk),
            ];
        }

        if ($points === []) {
            return false;
        }

        try {
            $response = $this->client()->put(
                '/collections/'.$this->collection().'/points?wait=true',
                ['points' => $points],
            );

            if (!$response->successful()) {
                return $this->recordFailure('Qdrant upsert failed', $response->status(), $response->body());
            }

            return true;
        } catch (Throwable $error) {
            return $this->recordThrowable('Qdrant upsert unavailable', $error);
        }
    }

    /**
     * @param  array<int>  $chunkIds
     */
    public function countPointsForChunkIds(array $chunkIds): ?int
    {
        if (!$this->isEnabled()) {
            return null;
        }

        if ($chunkIds === []) {
            return 0;
        }

        try {
            if (!$this->ensureCollection()) {
                return null;
            }

            $response = $this->client()->post('/collections/'.$this->collection().'/points/count', [
                'exact' => true,
                'filter' => [
                    'must' => [
                        ['has_id' => array_values(array_map('intval', $chunkIds))],
                    ],
                ],
            ]);

            if (!$response->successful()) {
                $this->recordFailure('Qdrant count failed', $response->status(), $response->body());

                return null;
            }

            return (int) data_get($response->json(), 'result.count', 0);
        } catch (Throwable $error) {
            $this->recordThrowable('Qdrant count unavailable', $error);

            return null;
        }
    }

    public function deleteChunk(int $chunkId): bool
    {
        if (!$this->isEnabled()) {
            return false;
        }

        try {
            $response = $this->client()->post(
                '/collections/'.$this->collection().'/points/delete?wait=true',
                ['points' => [(int) $chunkId]],
            );

            if (!$response->successful()) {
                return $this->recordFailure('Qdrant delete failed', $response->status(), $response->body());
            }

            return true;
        } catch (Throwable $error) {
            return $this->recordThrowable('Qdrant delete unavailable', $error);
        }
    }

    /**
     * @return array<int, float>|null
     */
    public function searchSimilar(array $queryVector, array $options = []): ?array
    {
        if (!$this->isEnabled()) {
            return null;
        }

        $queryVector = $this->normalizeVector($queryVector);

        if (!$queryVector) {
            return null;
        }

        $limit = max(1, (int) ($options['limit'] ?? config('legal_ai.semantic_search.result_limit', 12)));
        $minimumScore = (float) ($options['min_score'] ?? config('legal_ai.semantic_search.min_score', 0.55));
        $embeddingModel = (string) ($options['embedding_model'] ?? config('legal_ai.embeddings.model', 'nomic-embed-text'));
        $includeChatOnlySources = (bool) ($options['include_chat_only_sources'] ?? false);

        $filter = [
            'must' => [
                ['key' => 'corpus_active', 'match' => ['value' => true]],
                ['key' => 'embedding_model', 'match' => ['value' => $embeddingModel]],
            ],
        ];

        if (!$includeChatOnlySources) {
            $filter['must_not'][] = ['key' => 'chat_only', 'match' => ['value' => true]];
        }

        try {
            if (!$this->ensureCollection(count($queryVector))) {
                return null;
            }

            $response = $this->client()->post('/collections/'.$this->collection().'/points/search', [
                'vector' => $queryVector,
                'limit' => $limit,
                'score_threshold' => $minimumScore,
                'with_payload' => false,
                'filter' => $filter,
            ]);

            if (!$response->successful()) {
                $this->recordFailure('Qdrant search failed', $response->status(), $response->body());

                return null;
            }

            $scores = [];

            foreach ((array) data_get($response->json(), 'result', []) as $hit) {
                $chunkId = (int) ($hit['id'] ?? 0);
                $score = (float) ($hit['score'] ?? 0.0);

                if ($chunkId > 0 && $score >= $minimumScore) {
                    $scores[$chunkId] = round($score, 6);
                }
            }

            arsort($scores);

            return $scores;
        } catch (Throwable $error) {
            $this->recordThrowable('Qdrant search unavailable', $error);

            return null;
        }
    }

    public function buildPayload(LegalChunk $chunk): array
    {
        $chunk->loadMissing([
            'article.document.source',
            'article.legacyLaw',
        ]);

        $article = $chunk->article;
        $document = $article?->document;
        $source = $document?->source;
        $legacyLaw = $article?->legacyLaw;

        $legacyCategory = $legacyLaw?->category;
        $documentDomain = $document?->domain;
        $articleDomain = $article?->domain;
        $chunkDomain = $chunk->domain;
        $sourceType = strtolower((string) ($source?->source_type ?? ''));
        $documentType = strtolower((string) ($document?->document_type ?? ''));

        $corpusActive = ($document?->status === 'active')
            && ($article?->status === 'active')
            && ($document?->current_version_id === $chunk->legal_document_version_id);

        return [
            'legal_chunk_id' => (int) $chunk->id,
            'legal_article_id' => (int) $chunk->legal_article_id,
            'legal_document_id' => (int) ($article?->legal_document_id ?? 0),
            'legal_document_version_id' => (int) $chunk->legal_document_version_id,
            'chunk_index' => (int) $chunk->chunk_index,
            'domain' => $chunkDomain ?? $articleDomain ?? $documentDomain,
            'subdomain' => $chunk->subdomain ?? $article?->subdomain ?? $document?->subdomain,
            'language' => $article?->language ?? $document?->language ?? 'fr',
            'embedding_model' => (string) ($chunk->embedding_model ?? config('legal_ai.embeddings.model', 'nomic-embed-text')),
            'corpus_active' => $corpusActive,
            'chat_only' => $this->isChatOnlyChunk(
                $legacyCategory,
                $documentDomain,
                $articleDomain,
                $chunkDomain,
                $sourceType,
                $documentType,
            ),
        ];
    }

    private function isChatOnlyChunk(
        ?string $legacyCategory,
        ?string $documentDomain,
        ?string $articleDomain,
        ?string $chunkDomain,
        string $sourceType,
        string $documentType,
    ): bool {
        if ($legacyCategory !== null && in_array($legacyCategory, self::CHAT_ONLY_CATEGORIES, true)) {
            return true;
        }

        foreach ([$documentDomain, $articleDomain, $chunkDomain] as $domain) {
            if ($domain !== null && in_array($domain, self::CHAT_ONLY_CATEGORIES, true)) {
                return true;
            }
        }

        if ($sourceType !== '' && in_array($sourceType, self::CHAT_ONLY_SOURCE_TYPES, true)) {
            return true;
        }

        return $documentType !== '' && in_array($documentType, self::CHAT_ONLY_SOURCE_TYPES, true);
    }

    private function collection(): string
    {
        return (string) config('qdrant.collection', 'legal_chunks');
    }

    private function client(): PendingRequest
    {
        $request = Http::timeout((int) config('qdrant.timeout_seconds', 10))
            ->acceptJson()
            ->asJson();

        $apiKey = config('qdrant.api_key');

        if (is_string($apiKey) && $apiKey !== '') {
            $request = $request->withHeaders(['api-key' => $apiKey]);
        }

        return $request->baseUrl((string) config('qdrant.url'));
    }

    /**
     * @param  array<int, mixed>|null  $vector
     * @return array<int, float>|null
     */
    private function normalizeVector(?array $vector): ?array
    {
        if (!$vector) {
            return null;
        }

        $normalized = collect($vector)
            ->filter(fn (mixed $value): bool => is_numeric($value))
            ->map(fn (mixed $value): float => (float) $value)
            ->values()
            ->all();

        return count($normalized) >= 2 ? $normalized : null;
    }

    private function recordFailure(string $message, int $status, string $body): bool
    {
        $body = \Illuminate\Support\Str::limit(trim($body), 500, '');
        $this->lastError = trim("{$message}; status={$status}; body={$body}");
        Log::warning($message, ['status' => $status, 'body' => $body]);

        return false;
    }

    private function recordThrowable(string $message, Throwable $error): bool
    {
        $this->lastError = $error->getMessage();
        Log::warning($message, ['message' => $error->getMessage()]);

        return false;
    }
}
