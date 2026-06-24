<?php

namespace Tests\Unit;

use App\Models\LegalArticle;
use App\Models\LegalChunk;
use App\Models\LegalDocument;
use App\Models\LegalDocumentVersion;
use App\Models\LegalSource;
use App\Services\LegalVectorStoreService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class LegalVectorStoreServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_collection_and_searches_similar_chunks(): void
    {
        config([
            'qdrant.enabled' => true,
            'qdrant.url' => 'http://qdrant.test',
            'qdrant.collection' => 'legal_chunks',
            'qdrant.vector_size' => 3,
            'legal_ai.embeddings.model' => 'nomic-embed-text',
            'legal_ai.semantic_search.min_score' => 0.5,
        ]);

        Http::fake([
            'http://qdrant.test/collections/legal_chunks' => Http::response(['result' => ['status' => 'green']], 200),
            'http://qdrant.test/collections/legal_chunks/points?wait=true' => Http::response(['result' => ['status' => 'completed']], 200),
            'http://qdrant.test/collections/legal_chunks/points/search' => Http::response([
                'result' => [
                    ['id' => 42, 'score' => 0.91],
                    ['id' => 7, 'score' => 0.62],
                ],
            ], 200),
        ]);

        $service = app(LegalVectorStoreService::class);
        $chunk = $this->makeChunk();

        $this->assertTrue($service->ensureCollection());
        $this->assertTrue($service->upsertChunk($chunk, [0.1, 0.2, 0.3]));

        $scores = $service->searchSimilar([0.1, 0.2, 0.3], [
            'limit' => 10,
            'min_score' => 0.5,
            'embedding_model' => 'nomic-embed-text',
        ]);

        $this->assertSame([
            42 => 0.91,
            7 => 0.62,
        ], $scores);

        Http::assertSent(function ($request): bool {
            if (!str_contains($request->url(), '/points/search')) {
                return true;
            }

            $payload = $request->data();

            return ($payload['filter']['must'][0]['key'] ?? null) === 'corpus_active'
                && ($payload['filter']['must_not'][0]['key'] ?? null) === 'chat_only';
        });
    }

    public function test_it_marks_official_bulletin_chunks_as_chat_only(): void
    {
        config([
            'qdrant.enabled' => true,
            'qdrant.url' => 'http://qdrant.test',
            'qdrant.collection' => 'legal_chunks',
        ]);

        Http::fake([
            'http://qdrant.test/collections/legal_chunks' => Http::response(['result' => ['status' => 'green']], 200),
            'http://qdrant.test/collections/legal_chunks/points?wait=true' => Http::response(['result' => ['status' => 'completed']], 200),
        ]);

        $service = app(LegalVectorStoreService::class);
        $chunk = $this->makeChunk(documentDomain: 'official-bulletin');

        $this->assertTrue($service->upsertChunk($chunk, [0.4, 0.5, 0.6]));

        Http::assertSent(function ($request): bool {
            if (!str_contains($request->url(), '/points?wait=true')) {
                return true;
            }

            return data_get($request->data(), 'points.0.payload.chat_only') === true;
        });
    }

    private function makeChunk(?string $documentDomain = 'labor'): LegalChunk
    {
        $source = LegalSource::query()->create([
            'name' => 'SGG',
            'source_type' => 'code',
            'status' => 'active',
        ]);

        $document = LegalDocument::query()->create([
            'legal_source_id' => $source->id,
            'document_title' => 'Code du travail',
            'document_type' => 'code',
            'language' => 'fr',
            'domain' => $documentDomain,
            'status' => 'active',
        ]);

        $version = LegalDocumentVersion::query()->create([
            'legal_document_id' => $document->id,
            'version_number' => 1,
            'checksum' => hash('sha256', 'version-1'),
            'status' => 'active',
        ]);

        $document->update(['current_version_id' => $version->id]);

        $article = LegalArticle::query()->create([
            'legal_document_id' => $document->id,
            'legal_document_version_id' => $version->id,
            'article_number' => '47',
            'content' => 'Sample article content about termination notice.',
            'language' => 'fr',
            'domain' => $documentDomain,
            'checksum' => hash('sha256', 'article-47'),
            'status' => 'active',
        ]);

        return LegalChunk::query()->create([
            'legal_article_id' => $article->id,
            'legal_document_version_id' => $version->id,
            'chunk_index' => 0,
            'content' => 'Sample article content about termination notice.',
            'token_count' => 8,
            'domain' => $documentDomain,
            'checksum' => hash('sha256', 'chunk-0'),
            'embedding_model' => 'nomic-embed-text',
        ]);
    }
}
