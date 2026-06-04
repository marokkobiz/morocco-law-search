<?php

namespace Tests\Feature;

use App\Models\Law;
use App\Models\LegalArticle;
use App\Models\LegalChunk;
use App\Models\LegalDocument;
use App\Models\LegalDocumentVersion;
use App\Models\LegalSource;
use App\Services\LawSearchService;
use App\Services\LegalDomainClassifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class CorpusSearchRetrievalTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
    }

    public function test_search_returns_corpus_chunks_with_source_metadata(): void
    {
        $this->createCorpusArticle([
            'source_name' => 'Adala',
            'source_type' => 'code',
            'source_url' => 'https://example.test/code-penal',
            'document_title' => 'Code penal',
            'law_reference' => 'Dahir 1-59-413',
            'domain' => 'criminal',
            'publication_date' => '2024-02-01',
            'article_number' => 'Article 505',
            'article_title' => 'Vol',
            'article_content' => 'La soustraction frauduleuse de la chose d autrui constitue un vol.',
            'chunk_content' => 'rare corpus theft chunk soustraction frauduleuse de la chose d autrui.',
        ]);

        $payload = app(LawSearchService::class)->search('rare corpus theft', 40, ['useCorpus' => true]);
        $result = $payload['results'][0] ?? [];

        $this->assertCount(1, $payload['results']);
        $this->assertSame('corpus', $result['source_table'] ?? null);
        $this->assertSame('Adala', $result['source_name'] ?? null);
        $this->assertSame('Code penal', $result['document_title'] ?? null);
        $this->assertSame('Article 505', $result['article_number'] ?? null);
        $this->assertSame('active', $result['version_status'] ?? null);
        $this->assertSame('2024-02-01', $result['publication_date'] ?? null);
        $this->assertSame('https://example.test/code-penal', $result['source_url'] ?? null);
        $this->assertArrayHasKey('legal_chunk_id', $result);
        $this->assertArrayHasKey('legal_article_id', $result);
        $this->assertArrayHasKey('legal_document_id', $result);
        $this->assertArrayHasKey('legal_document_version_id', $result);
    }

    public function test_normal_search_keeps_legacy_behavior_and_visible_detailed_categories(): void
    {
        Law::create([
            'title' => 'Financial market rule',
            'article_number' => 'Article 4',
            'content' => 'market visible phrase for the normal search engine.',
            'document_title' => 'Financial market source',
            'category' => 'financial-market',
            'source_name' => 'Legacy database',
            'language' => 'fr',
        ]);

        $this->createCorpusArticle([
            'document_title' => 'Broad tax source',
            'domain' => 'tax',
            'article_number' => 'Article 99',
            'article_title' => 'Internal corpus decoy',
            'article_content' => 'market visible phrase internal corpus metadata.',
            'chunk_content' => 'market visible phrase internal corpus metadata.',
        ]);

        $this->getJson('/api/laws/search?q=market visible phrase')
            ->assertOk()
            ->assertJsonPath('results.0.source_table', 'legacy_laws')
            ->assertJsonPath('results.0.category', 'financial-market')
            ->assertJsonMissing(['source_table' => 'corpus']);

        $this->getJson('/api/laws/overview')
            ->assertOk()
            ->assertJsonPath('totalCategories', 1)
            ->assertJsonPath('categories.0.category', 'financial-market');
    }

    public function test_ai_corpus_retrieval_uses_corpus_before_legacy_laws(): void
    {
        Law::create([
            'title' => 'Legacy commercial rule',
            'article_number' => 'Article 9',
            'content' => 'legacy priority phrase should not be primary.',
            'document_title' => 'Legacy law',
            'category' => 'commercial',
            'source_name' => 'Legacy database',
            'language' => 'fr',
        ]);

        $this->createCorpusArticle([
            'document_title' => 'Code de commerce',
            'domain' => 'commercial',
            'article_number' => 'Article 12',
            'article_title' => 'Corpus commercial rule',
            'article_content' => 'corpus priority phrase in the indexed legal corpus.',
            'chunk_content' => 'corpus priority phrase in the legal chunk.',
        ]);

        $payload = app(LawSearchService::class)->search('priority phrase', 40, ['useCorpus' => true]);

        $this->assertSame('corpus', $payload['results'][0]['source_table'] ?? null);
        $this->assertSame('Article 12', $payload['results'][0]['article_number'] ?? null);
        $this->assertNotContains('legacy_laws', collect($payload['results'])->pluck('source_table')->all());
    }

    public function test_search_boosts_query_domain_and_reranks_top_chunks_generically(): void
    {
        $laborSeed = $this->createCorpusArticle([
            'document_title' => 'Code du travail',
            'domain' => 'labor',
            'subdomain' => 'dismissal',
            'tags' => ['labor', 'dismissal', 'licenciement', 'motif_valable'],
            'article_number' => 'Article 35',
            'article_title' => 'Licenciement pour motif valable',
            'article_content' => 'Le licenciement du salarie doit etre fonde sur un motif valable.',
            'chunk_content' => 'licenciement salarie employeur motif valable contrat de travail.',
        ]);

        for ($index = 0; $index < 7; $index++) {
            $this->createCorpusArticleInDocument($laborSeed['document'], $laborSeed['version'], [
                'article_number' => 'Article '.(36 + $index),
                'article_title' => 'Procedure de licenciement '.$index,
                'article_content' => 'Procedure de licenciement salarie employeur motif valable '.$index,
                'chunk_content' => 'procedure licenciement salarie employeur motif valable '.$index,
                'domain' => 'labor',
                'subdomain' => 'dismissal',
                'tags' => ['labor', 'dismissal', 'licenciement'],
            ]);
        }

        $this->createCorpusArticle([
            'document_title' => 'Code penal',
            'domain' => 'criminal',
            'subdomain' => 'theft',
            'tags' => ['criminal', 'theft'],
            'article_number' => 'Article 505',
            'article_title' => 'Motif et preuve dans une infraction',
            'article_content' => 'motif preuve employeur salarie termes generiques sans regle de licenciement.',
            'chunk_content' => 'motif preuve employeur salarie termes generiques sans regle de licenciement.',
        ]);

        $payload = app(LawSearchService::class)->search(
            'Can an employer dismiss an employee without a valid reason?',
            40,
            ['useCorpus' => true]
        );
        $results = $payload['results'];

        $this->assertLessThanOrEqual(5, count($results));
        $this->assertSame('Code du travail', $results[0]['document_title']);
        $this->assertSame('labor', $results[0]['domain']);
        $this->assertSame('dismissal', $results[0]['subdomain']);
        $this->assertNotContains('Code penal', collect($results)->pluck('document_title')->take(3)->all());
    }

    public function test_ai_query_expansion_keeps_mixed_issue_concepts(): void
    {
        $terms = app(LegalDomainClassifier::class)->conceptTermsForQuery(
            'buyer paid the price took possession seller died heirs claim the car sale ownership',
            40
        );

        $this->assertContains('vente', $terms);
        $this->assertContains('delivrance', $terms);
        $this->assertContains('transfert de propriete', $terms);
        $this->assertContains('heritiers', $terms);
    }

    public function test_search_falls_back_to_legacy_laws_when_corpus_has_no_match(): void
    {
        Law::create([
            'title' => 'Legacy-only article',
            'article_number' => 'Article 77',
            'content' => 'legacy only phrase remains available as fallback.',
            'document_title' => 'Legacy fallback source',
            'category' => 'civil',
            'source_name' => 'Legacy database',
            'language' => 'fr',
        ]);

        $payload = app(LawSearchService::class)->search('legacy only phrase', 40, ['useCorpus' => true]);

        $this->assertSame('legacy_laws', $payload['results'][0]['source_table'] ?? null);
        $this->assertTrue($payload['results'][0]['is_legacy'] ?? false);
        $this->assertSame('legacy', $payload['results'][0]['version_status'] ?? null);
    }

    public function test_search_only_uses_active_current_versions(): void
    {
        $source = LegalSource::create([
            'name' => 'Official source',
            'source_type' => 'code',
            'source_url' => 'https://example.test/source',
            'language' => 'fr',
            'checksum' => hash('sha256', 'source'),
            'status' => 'active',
        ]);
        $document = LegalDocument::create([
            'legal_source_id' => $source->id,
            'document_title' => 'Versioned test document',
            'document_type' => 'code',
            'law_reference' => 'Loi 99-99',
            'publication_date' => '2024-01-01',
            'language' => 'fr',
            'domain' => 'civil',
            'source_url' => 'https://example.test/source',
            'checksum' => hash('sha256', 'document'),
            'status' => 'active',
        ]);
        $oldVersion = LegalDocumentVersion::create([
            'legal_document_id' => $document->id,
            'version_number' => 1,
            'source_url' => 'https://example.test/source-v1',
            'checksum' => hash('sha256', 'old-version'),
            'status' => 'replaced',
            'publication_date' => '2023-01-01',
            'imported_at' => now(),
        ]);
        $oldArticle = LegalArticle::create([
            'legal_document_id' => $document->id,
            'legal_document_version_id' => $oldVersion->id,
            'article_number' => 'Article 1',
            'article_title' => 'Old version',
            'content' => 'version preference old replaced content',
            'language' => 'fr',
            'checksum' => hash('sha256', 'old-article'),
            'sort_order' => 1,
            'status' => 'replaced',
        ]);
        LegalChunk::create([
            'legal_article_id' => $oldArticle->id,
            'legal_document_version_id' => $oldVersion->id,
            'chunk_index' => 0,
            'content' => 'version preference old replaced content',
            'token_count' => 5,
            'checksum' => hash('sha256', 'old-chunk'),
        ]);
        $activeVersion = LegalDocumentVersion::create([
            'legal_document_id' => $document->id,
            'version_number' => 2,
            'source_url' => 'https://example.test/source-v2',
            'checksum' => hash('sha256', 'active-version'),
            'status' => 'active',
            'publication_date' => '2024-01-01',
            'imported_at' => now(),
        ]);
        $activeArticle = LegalArticle::create([
            'legal_document_id' => $document->id,
            'legal_document_version_id' => $activeVersion->id,
            'article_number' => 'Article 1',
            'article_title' => 'Active version',
            'content' => 'version preference active current content',
            'language' => 'fr',
            'checksum' => hash('sha256', 'active-article'),
            'sort_order' => 1,
            'status' => 'active',
        ]);
        LegalChunk::create([
            'legal_article_id' => $activeArticle->id,
            'legal_document_version_id' => $activeVersion->id,
            'chunk_index' => 0,
            'content' => 'version preference active current content',
            'token_count' => 5,
            'checksum' => hash('sha256', 'active-chunk'),
        ]);
        $document->update(['current_version_id' => $activeVersion->id]);

        $payload = app(LawSearchService::class)->search('version preference', 40, ['useCorpus' => true]);

        $this->assertSame('corpus', $payload['results'][0]['source_table'] ?? null);
        $this->assertSame('active', $payload['results'][0]['version_status'] ?? null);
        $this->assertStringContainsString('active current content', $payload['results'][0]['content'] ?? '');
        $this->assertStringNotContainsString('old replaced content', json_encode($payload['results']));
    }

    public function test_chat_citations_use_corpus_chunks_as_context(): void
    {
        $this->createCorpusArticle([
            'document_title' => 'Code du travail',
            'law_reference' => 'Loi 65-99',
            'domain' => 'labor',
            'article_number' => 'Article 35',
            'article_title' => 'Licenciement',
            'article_content' => 'Regles de licenciement et contrat de travail.',
            'chunk_content' => 'corpus labor chunk says licenciement requires a motif valable.',
        ]);

        $this->postJson('/api/laws/chat', ['message' => 'licenciement travail'])
            ->assertOk()
            ->assertJsonPath('intent', 'legal_case_analysis')
            ->assertJsonPath('citations.0.sourceTable', 'corpus')
            ->assertJsonPath('citations.0.articleNumber', 'Article 35')
            ->assertJsonPath('citations.0.versionStatus', 'active')
            ->assertJsonPath('citations.0.isLegacy', false)
            ->assertJsonPath('citations.0.content', 'corpus labor chunk says licenciement requires a motif valable.')
            ->assertJsonStructure([
                'citations' => [
                    [
                        'legalChunkId',
                        'legalArticleId',
                        'legalDocumentId',
                        'legalDocumentVersionId',
                    ],
                ],
            ]);
    }

    public function test_french_greeting_gets_french_reply(): void
    {
        $response = $this->postJson('/api/laws/chat', ['message' => 'bonjour'])
            ->assertOk()
            ->assertJsonPath('intent', 'greeting_small_talk');

        $answer = (string) $response->json('answer');

        $this->assertStringContainsString('Bonjour', $answer);
        $this->assertStringNotContainsString('What are we looking into', $answer);
    }

    public function test_labor_dismissal_case_still_returns_a_relevant_answer(): void
    {
        $this->createCorpusArticle([
            'document_title' => 'Code du travail',
            'law_reference' => 'Loi 65-99',
            'domain' => 'labor',
            'article_number' => 'Article 35',
            'article_title' => 'Licenciement',
            'article_content' => 'Le licenciement doit etre fonde sur un motif valable.',
            'chunk_content' => 'licenciement travail motif valable procedure disciplinaire salarie.',
        ]);

        $this->postJson('/api/laws/chat', [
            'message' => 'Can my employer dismiss me without a valid reason in Morocco?',
        ])
            ->assertOk()
            ->assertJsonPath('intent', 'legal_case_analysis')
            ->assertJsonPath('citations.0.sourceTable', 'corpus')
            ->assertJsonPath('citations.0.documentTitle', 'Code du travail');
    }

    public function test_car_sale_contract_case_still_returns_a_relevant_answer(): void
    {
        $this->createCorpusArticle([
            'document_title' => 'Code des Obligations et des Contrats',
            'law_reference' => 'Dahir des obligations et contrats',
            'domain' => 'contracts',
            'article_number' => 'Article 230',
            'article_title' => 'Force obligatoire du contrat',
            'article_content' => 'Les obligations contractuelles valablement formees tiennent lieu de loi aux parties.',
            'chunk_content' => 'contrat vente voiture obligations paiement livraison parties.',
        ]);

        $this->postJson('/api/laws/chat', [
            'message' => 'car sale contract in Morocco, buyer refuses to pay',
        ])
            ->assertOk()
            ->assertJsonPath('intent', 'legal_case_analysis')
            ->assertJsonPath('citations.0.sourceTable', 'corpus')
            ->assertJsonPath('citations.0.documentTitle', 'Code des Obligations et des Contrats');
    }

    public function test_car_sale_ownership_and_heirs_case_uses_concept_queries_without_fake_article_numbers(): void
    {
        $article488 = $this->createCorpusArticle([
            'document_title' => 'Code des Obligations et des Contrats',
            'law_reference' => 'Dahir du 12 aout 1913',
            'domain' => 'civil',
            'article_number' => 'Article 488',
            'article_title' => 'Perfection de la vente',
            'article_content' => 'La vente est parfaite entre les parties des qu il y a consentement des contractants, chose et prix.',
            'chunk_content' => 'vente parfaite consentement contractants chose prix',
        ]);
        $document = $article488['document'];
        $version = $article488['version'];

        $this->createCorpusArticleInDocument($document, $version, [
            'article_number' => 'Article 491',
            'article_title' => 'Propriete de la chose vendue',
            'article_content' => 'L acheteur acquiert de plein droit la propriete de la chose vendue des que le contrat est parfait.',
            'chunk_content' => 'acheteur acquiert propriete chose vendue contrat parfait consentement',
        ]);
        $this->createCorpusArticleInDocument($document, $version, [
            'article_number' => 'Article 499',
            'article_title' => 'Delivrance de la chose vendue',
            'article_content' => 'La delivrance a lieu lorsque le vendeur met l acquereur en mesure de prendre possession de la chose vendue sans obstacle.',
            'chunk_content' => 'delivrance vendeur acquereur prendre possession chose vendue sans obstacle',
        ]);
        $this->createCorpusArticleInDocument($document, $version, [
            'article_number' => 'Article 500',
            'article_title' => 'Delivrance des choses mobilieres',
            'article_content' => 'La delivrance des choses mobilieres s opere par la tradition reelle ou par tout mode reconnu par l usage.',
            'chunk_content' => 'delivrance choses mobilieres tradition reelle usage',
        ]);
        $this->createCorpusArticleInDocument($document, $version, [
            'article_number' => 'Article 229',
            'article_title' => 'Effet envers heritiers',
            'article_content' => 'Les obligations ont effet entre les parties et aussi entre leurs heritiers ou ayants cause.',
            'chunk_content' => 'obligations effet parties heritiers ayants cause',
        ]);
        $this->createCorpusArticle([
            'document_title' => 'Immatriculation et vente forcee des aeronefs',
            'domain' => 'civil',
            'article_number' => 'Article 12',
            'article_title' => 'Vente forcee aeronefs',
            'article_content' => 'vente immatriculation acheteur vendeur prix propriete aeronefs',
            'chunk_content' => 'vente immatriculation acheteur vendeur prix propriete aeronefs',
        ]);
        $this->createCorpusArticle([
            'document_title' => 'Aliments pour animaux producteurs de produits alimentaires',
            'domain' => 'civil',
            'article_number' => 'Article 3',
            'article_title' => 'Vente aliments animaux',
            'article_content' => 'vente prix vendeur acheteur produits aliments pour animaux',
            'chunk_content' => 'vente prix vendeur acheteur produits aliments pour animaux',
        ]);
        $this->createCorpusArticle([
            'document_title' => 'Appels a la generosite publique',
            'domain' => 'civil',
            'article_number' => 'Article 7',
            'article_title' => 'Declaration appel',
            'article_content' => 'propriete possession prix declaration appel a la generosite publique',
            'chunk_content' => 'propriete possession prix declaration appel a la generosite publique',
        ]);

        $message = 'Ahmed sells his car to Youssef. Youssef pays the full price and takes possession of the car, but the registration is not updated. Ahmed later dies, and his heirs claim the car. Analyze who has the stronger legal position.';

        $response = $this->postJson('/api/laws/chat', ['message' => $message])
            ->assertOk()
            ->assertJsonPath('intent', 'legal_case_analysis')
            ->assertJsonPath('citations.0.sourceTable', 'corpus')
            ->assertSee('Article 488', false)
            ->assertSee('Article 491', false)
            ->assertSee('Article 499', false)
            ->assertSee('Article 229', false)
            ->assertSee('buyer has the stronger ownership argument', false)
            ->assertDontSee('Sources insuffisantes', false);

        $articleNumbers = collect($response->json('citations'))->pluck('articleNumber');
        $documentTitles = collect($response->json('citations'))->pluck('documentTitle')->implode(' | ');

        $this->assertTrue($articleNumbers->contains('Article 488'));
        $this->assertTrue($articleNumbers->contains('Article 499'));
        $this->assertStringNotContainsString('aeronefs', $documentTitles);
        $this->assertStringNotContainsString('Aliments pour animaux', $documentTitles);
        $this->assertStringNotContainsString('generosite publique', $documentTitles);

        $plan = app(\App\Services\AiReasoningService::class)->createSearchPlan($message);

        $this->assertNotEmpty($plan['searchQueries']);
        $this->assertFalse(collect($plan['searchQueries'])->contains(
            fn (string $query): bool => preg_match('/\b(?:article|art)\s*(premier|\d+)/i', $query)
        ));
    }

    public function test_french_sale_ownership_case_answers_in_french(): void
    {
        $article488 = $this->createCorpusArticle([
            'document_title' => 'Code des Obligations et des Contrats',
            'law_reference' => 'Dahir du 12 aout 1913',
            'domain' => 'civil',
            'article_number' => 'Article 488',
            'article_title' => 'Perfection de la vente',
            'article_content' => 'La vente est parfaite entre les parties des qu il y a consentement des contractants, chose et prix.',
            'chunk_content' => 'vente parfaite consentement contractants chose prix',
        ]);
        $document = $article488['document'];
        $version = $article488['version'];

        $this->createCorpusArticleInDocument($document, $version, [
            'article_number' => 'Article 491',
            'article_title' => 'Propriete de la chose vendue',
            'article_content' => 'L acheteur acquiert de plein droit la propriete de la chose vendue des que le contrat est parfait.',
            'chunk_content' => 'acheteur acquiert propriete chose vendue contrat parfait consentement',
        ]);
        $this->createCorpusArticleInDocument($document, $version, [
            'article_number' => 'Article 499',
            'article_title' => 'Delivrance de la chose vendue',
            'article_content' => 'La delivrance a lieu lorsque le vendeur met l acquereur en mesure de prendre possession de la chose vendue sans obstacle.',
            'chunk_content' => 'delivrance vendeur acquereur prendre possession chose vendue sans obstacle',
        ]);
        $this->createCorpusArticleInDocument($document, $version, [
            'article_number' => 'Article 500',
            'article_title' => 'Delivrance des choses mobilieres',
            'article_content' => 'La delivrance des choses mobilieres s opere par la tradition reelle ou par tout mode reconnu par l usage.',
            'chunk_content' => 'delivrance choses mobilieres tradition reelle usage',
        ]);
        $this->createCorpusArticleInDocument($document, $version, [
            'article_number' => 'Article 229',
            'article_title' => 'Effet envers heritiers',
            'article_content' => 'Les obligations ont effet entre les parties et aussi entre leurs heritiers ou ayants cause.',
            'chunk_content' => 'obligations effet parties heritiers ayants cause',
        ]);

        $response = $this->postJson('/api/laws/chat', [
            'message' => 'Ahmed vend sa voiture a Youssef. Youssef paie tout le prix et prend possession de la voiture, mais l immatriculation n est pas mise a jour. Ahmed decede ensuite et ses heritiers reclament la voiture. Analysez qui a la position juridique la plus forte.',
        ])->assertOk();

        $answer = (string) $response->json('answer');

        $this->assertStringContainsString('Youssef a la position', $answer);
        $this->assertStringContainsString('Article 488', $response->getContent());
        $this->assertStringNotContainsString('buyer has the stronger ownership argument', $answer);
    }

    private function createCorpusArticle(array $overrides = []): array
    {
        $sourceName = $overrides['source_name'] ?? 'Official indexed source';
        $sourceUrl = $overrides['source_url'] ?? 'https://example.test/legal-source';
        $documentTitle = $overrides['document_title'] ?? 'Indexed test document';
        $articleNumber = $overrides['article_number'] ?? 'Article 1';
        $articleContent = $overrides['article_content'] ?? ($overrides['chunk_content'] ?? 'Indexed corpus content.');
        $chunkContent = $overrides['chunk_content'] ?? $articleContent;

        $source = LegalSource::create([
            'name' => $sourceName,
            'source_type' => $overrides['source_type'] ?? 'code',
            'source_url' => $sourceUrl,
            'official_domain' => $overrides['official_domain'] ?? parse_url($sourceUrl, PHP_URL_HOST),
            'language' => $overrides['language'] ?? 'fr',
            'checksum' => hash('sha256', $sourceName.$sourceUrl),
            'status' => $overrides['source_status'] ?? 'active',
        ]);
        $document = LegalDocument::create([
            'legal_source_id' => $source->id,
            'document_title' => $documentTitle,
            'document_type' => $overrides['document_type'] ?? 'code',
            'law_reference' => $overrides['law_reference'] ?? 'Loi test',
            'bo_number' => $overrides['bo_number'] ?? null,
            'publication_date' => $overrides['publication_date'] ?? '2024-01-01',
            'effective_date' => $overrides['effective_date'] ?? null,
            'language' => $overrides['language'] ?? 'fr',
            'domain' => $overrides['domain'] ?? 'test',
            'subdomain' => $overrides['subdomain'] ?? null,
            'tags' => $overrides['tags'] ?? null,
            'source_url' => $sourceUrl,
            'checksum' => hash('sha256', $documentTitle.$sourceUrl),
            'status' => $overrides['document_status'] ?? 'active',
        ]);
        $version = LegalDocumentVersion::create([
            'legal_document_id' => $document->id,
            'version_number' => $overrides['version_number'] ?? 1,
            'source_url' => $sourceUrl,
            'checksum' => hash('sha256', $documentTitle.$articleContent),
            'status' => $overrides['version_status'] ?? 'active',
            'publication_date' => $overrides['publication_date'] ?? '2024-01-01',
            'effective_date' => $overrides['effective_date'] ?? null,
            'imported_at' => now(),
        ]);
        $document->update(['current_version_id' => $version->id]);
        $article = LegalArticle::create([
            'legal_document_id' => $document->id,
            'legal_document_version_id' => $version->id,
            'legacy_law_id' => $overrides['legacy_law_id'] ?? null,
            'article_number' => $articleNumber,
            'article_title' => $overrides['article_title'] ?? $articleNumber,
            'content' => $articleContent,
            'language' => $overrides['language'] ?? 'fr',
            'domain' => $overrides['domain'] ?? null,
            'subdomain' => $overrides['subdomain'] ?? null,
            'tags' => $overrides['tags'] ?? null,
            'checksum' => hash('sha256', $documentTitle.$articleNumber.$articleContent),
            'sort_order' => $overrides['sort_order'] ?? 1,
            'status' => $overrides['article_status'] ?? 'active',
        ]);
        $chunk = LegalChunk::create([
            'legal_article_id' => $article->id,
            'legal_document_version_id' => $version->id,
            'chunk_index' => 0,
            'content' => $chunkContent,
            'token_count' => str_word_count($chunkContent),
            'domain' => $overrides['chunk_domain'] ?? $overrides['domain'] ?? null,
            'subdomain' => $overrides['chunk_subdomain'] ?? $overrides['subdomain'] ?? null,
            'tags' => $overrides['chunk_tags'] ?? $overrides['tags'] ?? null,
            'checksum' => hash('sha256', $chunkContent),
        ]);

        return compact('source', 'document', 'version', 'article', 'chunk');
    }

    private function createCorpusArticleInDocument(LegalDocument $document, LegalDocumentVersion $version, array $overrides = []): array
    {
        $articleNumber = $overrides['article_number'] ?? 'Article 1';
        $articleContent = $overrides['article_content'] ?? ($overrides['chunk_content'] ?? 'Indexed corpus content.');
        $chunkContent = $overrides['chunk_content'] ?? $articleContent;

        $article = LegalArticle::create([
            'legal_document_id' => $document->id,
            'legal_document_version_id' => $version->id,
            'legacy_law_id' => $overrides['legacy_law_id'] ?? null,
            'article_number' => $articleNumber,
            'article_title' => $overrides['article_title'] ?? $articleNumber,
            'content' => $articleContent,
            'language' => $overrides['language'] ?? 'fr',
            'domain' => $overrides['domain'] ?? $document->domain,
            'subdomain' => $overrides['subdomain'] ?? $document->subdomain,
            'tags' => $overrides['tags'] ?? $document->tags,
            'checksum' => hash('sha256', $document->document_title.$articleNumber.$articleContent),
            'sort_order' => $overrides['sort_order'] ?? 1,
            'status' => $overrides['article_status'] ?? 'active',
        ]);
        $chunk = LegalChunk::create([
            'legal_article_id' => $article->id,
            'legal_document_version_id' => $version->id,
            'chunk_index' => $overrides['chunk_index'] ?? 0,
            'content' => $chunkContent,
            'token_count' => str_word_count($chunkContent),
            'domain' => $overrides['chunk_domain'] ?? $overrides['domain'] ?? $article->domain,
            'subdomain' => $overrides['chunk_subdomain'] ?? $overrides['subdomain'] ?? $article->subdomain,
            'tags' => $overrides['chunk_tags'] ?? $overrides['tags'] ?? $article->tags,
            'checksum' => hash('sha256', $chunkContent),
        ]);

        return compact('document', 'version', 'article', 'chunk');
    }
}
