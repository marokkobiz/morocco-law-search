<?php

namespace App\Services\Ai;

use App\Models\LegalChunk;
use App\Services\LegalEmbeddingService;
use Illuminate\Support\Facades\Log;

class LegalRagService
{
    private const MAX_SEMANTIC_CHUNKS = 30;

    private const MAX_KEYWORD_CHUNKS = 10;

    private const MIN_SIMILARITY_SCORE = 0.45;

    private const CANDIDATE_LIMIT = 5000;

    public function __construct(
        private readonly LegalEmbeddingService $embeddings,
        private readonly AiProviderFactory $factory,
    ) {}

    public function ask(string $question): array
    {
        $chat = $this->factory->makeChatProvider();

        if (!$chat->isEnabled()) {
            return [
                'answer' => 'AI chat assistant is not enabled. Configure AI_CHAT_ENABLED=true in your .env',
                'citations' => [],
            ];
        }

        $chunks = $this->retrieveChunks($question);

        if (empty($chunks)) {
            Log::info('RAG: No relevant chunks found for question', ['question' => $question]);

            return [
                'answer' => 'I searched the legal corpus but found no documents matching your question. The database may not contain information on this specific topic yet.',
                'citations' => [],
            ];
        }

        Log::info('RAG: Found relevant chunks', [
            'question' => $question,
            'chunk_count' => count($chunks),
            'sources' => array_map(fn (LegalChunk $c) => $this->formatSource($c), $chunks),
        ]);

        $context = array_map(fn (LegalChunk $chunk) => [
            'content' => $chunk->content,
            'source' => $this->formatSource($chunk),
        ], $chunks);

        $answer = $chat->chat($question, $context);

        $citations = array_map(fn (LegalChunk $chunk) => [
            'content' => $chunk->content,
            'document_title' => $chunk->article?->document?->document_title
                ?? $chunk->version?->document?->document_title
                ?? '',
            'article_number' => $chunk->article?->article_number ?? '',
            'source_url' => $chunk->article?->document?->source_url
                ?? $chunk->version?->document?->source_url
                ?? '',
            'source_name' => $chunk->article?->document?->source?->name
                ?? $chunk->version?->document?->source?->name
                ?? '',
        ], $chunks);

        return [
            'answer' => $answer,
            'citations' => $citations,
        ];
    }

    private function retrieveChunks(string $question): array
    {
        $semanticIds = $this->semanticSearch($question);

        $keywordIds = $this->keywordSearch($question);

        $mergedIds = $semanticIds;
        foreach ($keywordIds as $id) {
            if (!in_array($id, $mergedIds, true)) {
                $mergedIds[] = $id;
            }
        }

        if (empty($mergedIds)) {
            return [];
        }

        return LegalChunk::whereIn('id', $mergedIds)
            ->with(['article.document.source', 'version.document.source'])
            ->get()
            ->sortBy(fn (LegalChunk $c) => array_search($c->id, $mergedIds))
            ->values()
            ->all();
    }

    private function semanticSearch(string $question): array
    {
        $queryVector = $this->embeddings->embed($question);

        if (!$queryVector) {
            Log::warning('RAG: Failed to generate embedding for question', ['question' => $question]);

            return [];
        }

        $scores = LegalChunk::query()
            ->select(['id', 'embedding'])
            ->whereNotNull('embedding')
            ->where('embedding_model', $this->embeddings->model())
            ->limit(self::CANDIDATE_LIMIT)
            ->get()
            ->mapWithKeys(fn (LegalChunk $chunk) => [
                $chunk->id => $this->embeddings->cosineSimilarity($queryVector, $chunk->embedding),
            ])
            ->filter(fn (float $score) => $score >= self::MIN_SIMILARITY_SCORE)
            ->sortDesc()
            ->take(self::MAX_SEMANTIC_CHUNKS)
            ->keys()
            ->all();

        Log::info('RAG: Semantic search results', ['count' => count($scores)]);

        return $scores;
    }

    private function keywordSearch(string $question): array
    {
        $stopWords = ['le', 'la', 'les', 'du', 'de', 'des', 'au', 'aux', 'en', 'un', 'une',
            'est', 'sont', 'pour', 'dans', 'sur', 'par', 'avec', 'pas', 'ne', 'que',
            'qui', 'quoi', 'dont', 'ou', 'où', 'il', 'elle', 'ce', 'cet', 'cette',
            'the', 'a', 'an', 'of', 'to', 'in', 'is', 'it', 'at', 'for', 'what',
            'how', 'maroc', 'morocco', 'au', 'cas', 'fait', 'loi', 'code', 'article'];

        $words = preg_split('/[\s,?]+/', mb_strtolower($question));

        $keywords = array_filter($words, fn ($w) => mb_strlen($w) > 3 && !in_array($w, $stopWords, true));

        if (empty($keywords)) {
            return [];
        }

        $query = LegalChunk::query()
            ->select('id')
            ->whereNotNull('embedding');

        foreach ($keywords as $keyword) {
            $query->where(function ($q) use ($keyword) {
                $q->where('content', 'like', '%'.$keyword.'%');
            });
        }

        $ids = $query->limit(self::MAX_KEYWORD_CHUNKS)
            ->pluck('id')
            ->all();

        Log::info('RAG: Keyword search results', [
            'keywords' => implode(', ', $keywords),
            'count' => count($ids),
        ]);

        return $ids;
    }

    private function formatSource(LegalChunk $chunk): string
    {
        $parts = [];

        $doc = $chunk->article?->document ?? $chunk->version?->document;
        if ($doc) {
            $parts[] = $doc->document_title;
            if ($doc->source) {
                $parts[] = $doc->source->name;
            }
        }

        if ($chunk->article?->article_number) {
            $parts[] = 'Article ' . $chunk->article->article_number;
        }

        return implode(' — ', array_filter($parts));
    }
}
