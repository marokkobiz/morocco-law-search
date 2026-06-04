<?php

namespace Tests\Feature;

use App\Models\Law;
use App\Services\OfficialBulletinUpdateService;
use App\Services\OfficialSourceUpdateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use Tests\TestCase;

class OfficialSourceUpdatePipelineTest extends TestCase
{
    use RefreshDatabase;

    public function test_official_source_update_syncs_imported_bulletins_into_versioned_corpus(): void
    {
        $sourceUrl = 'https://www.sgg.gov.ma/BO/FR/2873/2026/BO_8002_fr.pdf';

        $this->mock(OfficialBulletinUpdateService::class, function (MockInterface $mock) use ($sourceUrl): void {
            $mock->shouldReceive('update')
                ->once()
                ->withArgs(fn (array $options): bool => $options['source'] === 'official-bulletins')
                ->andReturnUsing(function () use ($sourceUrl): array {
                    Law::create([
                        'title' => 'Official bulletin article',
                        'article_number' => 'Article 1',
                        'content' => 'Article importe automatiquement depuis une source officielle marocaine indexee.',
                        'document_title' => 'Bulletin officiel n 8002 - Textes generaux',
                        'law_reference' => 'BO n 8002',
                        'category' => 'official-bulletin',
                        'source_name' => 'Secretariat General du Gouvernement - Bulletin officiel',
                        'source_url' => $sourceUrl,
                        'language' => 'fr',
                    ]);

                    return [
                        'existingBulletinCount' => 0,
                        'candidateCount' => 1,
                        'discoveredSourceCount' => 1,
                        'importedSourceCount' => 1,
                        'importedArticleCount' => 1,
                        'sources' => [[
                            'bulletinId' => 8002,
                            'sourceUrl' => $sourceUrl,
                            'articleCount' => 1,
                        ]],
                        'failures' => [],
                    ];
                });
        });

        $summary = app(OfficialSourceUpdateService::class)->update([
            'source' => 'official-bulletins',
            'lookahead' => 1,
            'backfill' => 0,
            'recent' => 0,
            'timeoutMs' => 1000,
            'reimportExisting' => false,
        ]);

        $this->assertSame(1, $summary['importedArticleCount']);
        $this->assertSame(1, $summary['corpus']['documentsImported']);
        $this->assertSame(1, $summary['corpus']['articlesExtracted']);
        $this->assertDatabaseHas('import_runs', [
            'import_type' => 'official_sources_update',
            'status' => 'completed',
            'documents_imported' => 1,
            'articles_extracted' => 1,
        ]);
        $this->assertDatabaseHas('legal_sources', [
            'source_type' => 'BO',
            'source_url' => $sourceUrl,
            'official_domain' => 'www.sgg.gov.ma',
            'status' => 'active',
        ]);
        $this->assertDatabaseHas('legal_documents', [
            'document_title' => 'Bulletin officiel n 8002 - Textes generaux',
            'bo_number' => '8002',
            'status' => 'active',
        ]);
        $this->assertDatabaseCount('legal_document_versions', 1);
        $this->assertDatabaseCount('legal_articles', 1);
        $this->assertDatabaseCount('legal_chunks', 1);
    }
}
