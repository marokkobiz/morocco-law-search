<?php

namespace Tests\Feature;

use App\Models\Law;
use App\Models\LegalArticle;
use App\Models\LegalChunk;
use App\Models\LegalDocument;
use App\Models\LegalDocumentVersion;
use App\Services\LegacyLawCorpusImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LegalCorpusTest extends TestCase
{
    use RefreshDatabase;

    public function test_legacy_laws_import_into_versioned_corpus_without_deleting_legacy_rows(): void
    {
        Law::create([
            'title' => 'Bulletin article one',
            'article_number' => 'Article 1',
            'content' => 'Premier article importe depuis une source officielle indexee.',
            'document_title' => 'Bulletin officiel n 7999 - Textes generaux',
            'law_reference' => 'BO n 7999',
            'category' => 'official-bulletin',
            'source_name' => 'Bulletin officiel',
            'source_url' => 'https://www.sgg.gov.ma/BO/BO_7999_Fr.pdf',
            'language' => 'fr',
        ]);
        Law::create([
            'title' => 'Bulletin article two',
            'article_number' => 'Article 2',
            'content' => 'Deuxieme article importe depuis la meme source officielle indexee.',
            'document_title' => 'Bulletin officiel n 7999 - Textes generaux',
            'law_reference' => 'BO n 7999',
            'category' => 'official-bulletin',
            'source_name' => 'Bulletin officiel',
            'source_url' => 'https://www.sgg.gov.ma/BO/BO_7999_Fr.pdf',
            'language' => 'fr',
        ]);

        $this->artisan('corpus:import-legacy-laws')->assertExitCode(0);

        $this->assertDatabaseCount('laws', 2);
        $this->assertDatabaseHas('legal_sources', [
            'name' => 'Bulletin officiel',
            'source_type' => 'BO',
            'official_domain' => 'www.sgg.gov.ma',
            'status' => 'active',
        ]);
        $this->assertDatabaseHas('legal_documents', [
            'document_title' => 'Bulletin officiel n 7999 - Textes generaux',
            'document_type' => 'BO',
            'bo_number' => '7999',
            'status' => 'active',
        ]);
        $this->assertDatabaseCount('legal_document_versions', 1);
        $this->assertDatabaseCount('legal_articles', 2);
        $this->assertDatabaseCount('legal_chunks', 2);
        $this->assertDatabaseHas('import_runs', [
            'import_type' => 'legacy_laws_to_versioned_corpus',
            'status' => 'completed',
            'documents_imported' => 1,
            'articles_extracted' => 2,
        ]);

        $this->artisan('corpus:import-legacy-laws')->assertExitCode(0);

        $this->assertDatabaseCount('legal_document_versions', 1);
        $this->assertSame('active', LegalDocumentVersion::query()->firstOrFail()->status);
        $this->assertDatabaseHas('import_runs', [
            'import_type' => 'legacy_laws_to_versioned_corpus',
            'status' => 'completed',
            'documents_imported' => 0,
        ]);
    }

    public function test_corpus_status_endpoint_reports_counts_and_coverage_warning(): void
    {
        Law::create([
            'title' => 'Labor article',
            'article_number' => 'Article 35',
            'content' => 'Regles de licenciement issues de la source disponible indexee.',
            'document_title' => 'Code du travail',
            'law_reference' => 'Loi 65-99',
            'category' => 'labor',
            'source_name' => 'Adala',
            'source_url' => 'https://adala.justice.gov.ma/code-travail',
            'language' => 'fr',
        ]);

        $this->artisan('corpus:import-legacy-laws')->assertExitCode(0);

        $this->getJson('/api/corpus/status')
            ->assertOk()
            ->assertJsonPath('totalSources', 1)
            ->assertJsonPath('totalDocuments', 1)
            ->assertJsonPath('totalArticles', 1)
            ->assertJsonPath('totalChunks', 1)
            ->assertJsonPath('warning', 'Coverage depends on indexed official sources.')
            ->assertJsonPath('coverageBySource.0.sourceType', 'Adala')
            ->assertJsonPath('coverageByDomain.0.domain', 'labor')
            ->assertJsonPath('latestRuns.0.status', 'completed');
    }

    public function test_legacy_import_infers_domain_subdomain_and_tags(): void
    {
        Law::create([
            'title' => 'Licenciement pour motif valable',
            'article_number' => 'Article 35',
            'content' => 'Le licenciement du salarie doit etre fonde sur un motif valable et suivre les regles du contrat de travail.',
            'tags' => json_encode(['emploi'], JSON_UNESCAPED_UNICODE),
            'document_title' => 'Code du travail',
            'law_reference' => 'Loi 65-99',
            'category' => 'legal-text',
            'source_name' => 'Adala',
            'source_url' => 'https://adala.justice.gov.ma/code-travail',
            'language' => 'fr',
        ]);

        app(LegacyLawCorpusImportService::class)->import();

        $document = LegalDocument::query()->firstOrFail();
        $article = LegalArticle::query()->firstOrFail();
        $chunk = LegalChunk::query()->firstOrFail();

        $this->assertSame('labor', $document->domain);
        $this->assertSame('dismissal', $article->subdomain);
        $this->assertSame('labor', $chunk->domain);
        $this->assertContains('licenciement', $article->tags);
        $this->assertContains('emploi', $document->tags);
    }

    public function test_reimporting_same_source_with_new_legacy_ids_does_not_create_new_version(): void
    {
        $sourceUrl = 'https://www.sgg.gov.ma/BO/FR/2873/2026/BO_8001_fr.pdf';
        $lawPayload = [
            'title' => 'Bulletin article',
            'article_number' => 'Article 1',
            'content' => 'Le meme contenu officiel indexe ne doit pas creer une nouvelle version.',
            'document_title' => 'Bulletin officiel n 8001 - Textes generaux',
            'law_reference' => 'BO n 8001',
            'category' => 'official-bulletin',
            'source_name' => 'Bulletin officiel',
            'source_url' => $sourceUrl,
            'language' => 'fr',
        ];

        Law::create($lawPayload);
        app(LegacyLawCorpusImportService::class)->import(null, [$sourceUrl]);

        Law::query()->where('source_url', $sourceUrl)->delete();
        Law::create($lawPayload);
        $summary = app(LegacyLawCorpusImportService::class)->import(null, [$sourceUrl]);

        $this->assertSame(0, $summary['documentsImported']);
        $this->assertSame(1, $summary['skippedVersions']);
        $this->assertDatabaseCount('legal_document_versions', 1);
        $this->assertSame('active', LegalDocumentVersion::query()->firstOrFail()->status);
    }
}
