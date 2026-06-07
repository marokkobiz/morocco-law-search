<?php

namespace Tests\Feature;

use App\Models\Law;
use App\Models\LawTranslation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class LawApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        $this->actingAs(User::factory()->create([
            'access_status' => 'active',
        ]));
    }

    public function test_protected_law_api_requires_authentication(): void
    {
        Auth()->logout();

        $this->getJson('/api/laws/search?q=travail')->assertUnauthorized();
        $this->postJson('/api/laws/chat', ['message' => 'licenciement'])->assertUnauthorized();
    }

    public function test_search_workspace_includes_csrf_for_chat_requests(): void
    {
        $this->get('/app')
            ->assertOk()
            ->assertSee('name="csrf-token"', false)
            ->assertSee("headers.set('X-CSRF-TOKEN'", false);
    }

    public function test_search_returns_matching_laws(): void
    {
        Law::create([
            'title' => 'Commercial lease obligations',
            'article_number' => 'Article 6',
            'content' => 'Rules about bail commercial and tenant obligations.',
            'document_title' => 'Code de commerce',
            'law_reference' => 'Loi 15-95',
            'category' => 'commercial',
            'source_name' => 'Legacy database',
            'source_url' => 'https://example.test/source',
            'language' => 'fr',
        ]);

        $this->getJson('/api/laws/search?q=bail commercial&translation_mode=original')
            ->assertOk()
            ->assertJsonPath('count', 1)
            ->assertJsonPath('results.0.article_number', 'Article 6')
            ->assertJsonPath('results.0.document_title', 'Code de commerce');
    }

    public function test_search_defaults_to_fast_aliases_for_arabic_legal_terms(): void
    {
        Http::fake(['*' => Http::response([], 503)]);

        Law::create([
            'title' => 'Droit civil article',
            'article_number' => 'Article 1',
            'content' => 'Regles du droit civil et des obligations.',
            'document_title' => 'Code des Obligations et des Contrats',
            'category' => 'civil',
            'language' => 'fr',
        ]);

        $this->getJson('/api/laws/search?q='.rawurlencode('القانون المدني'))
            ->assertOk()
            ->assertJsonPath('searchMode', 'fast')
            ->assertJsonPath('searchedQueries.0', 'Code des Obligations et des Contrats')
            ->assertJsonPath('searchedQueries.5', 'القانون المدني')
            ->assertJsonPath('results.0.category', 'civil')
            ->assertJsonPath('results.0.matched_query', 'Code des Obligations et des Contrats')
            ->assertJsonPath('translationWarning', null);
    }

    public function test_search_can_expand_query_with_translation_mode(): void
    {
        Http::fake([
            'translate.googleapis.com/*' => Http::response([[['licenciement', 'dismissal', null, null]]], 200),
        ]);

        Law::create([
            'title' => 'Licenciement article',
            'article_number' => 'Article 35',
            'content' => 'Le licenciement doit etre fonde sur un motif valable.',
            'document_title' => 'Code du travail',
            'category' => 'labor',
            'language' => 'fr',
        ]);

        $this->getJson('/api/laws/search?q=dismissal&translation_mode=fr')
            ->assertOk()
            ->assertJsonPath('searchMode', 'fr')
            ->assertJsonPath('searchedQueries.0', 'Code du travail licenciement motif valable')
            ->assertJsonPath('searchedQueries.1', 'Code du travail procedure licenciement')
            ->assertJsonPath('translatedQueries.0.targetLanguage', 'fr')
            ->assertJsonPath('results.0.article_number', 'Article 35')
            ->assertJsonPath('results.0.matched_query', 'Code du travail licenciement motif valable');
    }

    public function test_overview_excludes_chat_only_sources(): void
    {
        Law::create([
            'title' => 'Real estate rule',
            'article_number' => 'Article 1',
            'content' => 'General real estate content.',
            'document_title' => 'Real estate law',
            'category' => 'real-estate',
            'language' => 'fr',
        ]);

        Law::create([
            'title' => 'Recent bulletin',
            'article_number' => 'Article 2',
            'content' => 'Bulletin-only content.',
            'document_title' => 'Bulletin officiel',
            'category' => 'official-bulletin',
            'language' => 'fr',
        ]);

        $this->getJson('/api/laws/overview')
            ->assertOk()
            ->assertJsonPath('totalArticles', 1)
            ->assertJsonPath('categories.0.category', 'real-estate');
    }

    public function test_chat_returns_search_backed_answer_and_citations(): void
    {
        Law::create([
            'title' => 'Labor termination article',
            'article_number' => 'Article 35',
            'content' => 'Rules about licenciement and employment termination.',
            'document_title' => 'Code du travail',
            'category' => 'labor',
            'language' => 'fr',
        ]);

        $this->postJson('/api/laws/chat', ['message' => 'licenciement travail'])
            ->assertOk()
            ->assertJsonPath('intent', 'legal_case_analysis')
            ->assertJsonPath('answerSupport.status', 'insufficient_sources')
            ->assertJsonPath('citations.0.articleNumber', 'Article 35')
            ->assertJsonFragment(['documentTitle' => 'Code du travail']);
    }

    public function test_chat_debug_exposes_retrieval_diagnostics_only_when_requested(): void
    {
        Law::create([
            'title' => 'Labor termination article',
            'article_number' => 'Article 35',
            'content' => 'Rules about licenciement and employment termination.',
            'document_title' => 'Code du travail',
            'category' => 'labor',
            'language' => 'fr',
        ]);

        $this->postJson('/api/laws/chat', ['message' => 'licenciement travail'])
            ->assertOk()
            ->assertJsonMissingPath('diagnostics');

        $this->postJson('/api/laws/chat?debug=1', ['message' => 'licenciement travail'])
            ->assertOk()
            ->assertJsonPath('diagnostics.rawResultCount', 1)
            ->assertJsonPath('diagnostics.acceptedResultCount', 1)
            ->assertJsonPath('diagnostics.acceptedCitations.0.articleNumber', 'Article 35')
            ->assertJsonStructure([
                'answerSupport' => [
                    'status',
                    'warnings',
                    'citationCoverage',
                    'unsupportedClaims',
                    'weaklySupportedClaims',
                    'citationAudits',
                ],
            ])
            ->assertJsonStructure([
                'diagnostics' => [
                    'queries',
                    'expandedQueries',
                    'rawResults',
                    'acceptedCitations',
                ],
            ]);
    }

    public function test_chat_handles_casual_message_without_searching(): void
    {
        $this->postJson('/api/laws/chat', ['message' => 'hello'])
            ->assertOk()
            ->assertJsonPath('intent', 'greeting_small_talk')
            ->assertJsonPath('answer', 'Hi. What are we looking into today?')
            ->assertJsonPath('citations', []);
    }

    public function test_chat_rejects_non_legal_message_without_searching(): void
    {
        $this->postJson('/api/laws/chat', ['message' => 'weather tomorrow'])
            ->assertOk()
            ->assertJsonPath('intent', 'unsupported_unclear')
            ->assertJsonPath('citations', [])
            ->assertJsonFragment(['answer' => 'I am not connected to live weather here. I am best at Moroccan legal research from available indexed sources.']);
    }

    public function test_chat_answers_legal_definition_before_context(): void
    {
        Law::create([
            'title' => 'Labor dismissal definition source',
            'article_number' => 'Article 35',
            'content' => 'Licenciement and contrat de travail rules require a motif valable.',
            'document_title' => 'Code du travail',
            'category' => 'labor',
            'language' => 'fr',
        ]);

        $this->postJson('/api/laws/chat', ['message' => 'what is licenciement?'])
            ->assertOk()
            ->assertJsonPath('intent', 'legal_definition')
            ->assertSee('Licenciement means', false);
    }

    public function test_chat_gives_practical_steps_before_law_for_robbery(): void
    {
        Law::create([
            'title' => 'Code penal theft article',
            'article_number' => 'Article 505',
            'content' => 'La soustraction frauduleuse de la chose d autrui constitue un vol selon le code penal.',
            'document_title' => 'Code penal',
            'category' => 'criminal',
            'language' => 'fr',
        ]);

        $this->postJson('/api/laws/chat', ['message' => 'what can I do if I got robbed?'])
            ->assertOk()
            ->assertJsonPath('intent', 'practical_advice')
            ->assertJsonPath('citations.0.articleNumber', 'Article 505')
            ->assertJsonPath('citations.0.matchedQuery', 'vol code penal')
            ->assertSee('Report it quickly', false)
            ->assertSee('Preserve evidence', false)
            ->assertSee('Block bank cards', false);
    }

    public function test_chat_gives_practical_steps_for_witnessed_shooting(): void
    {
        Law::create([
            'title' => 'Code penal violence article',
            'article_number' => 'Article 400',
            'content' => 'Les coups blessures agression violence arme a feu et homicide sont traites par le code penal.',
            'document_title' => 'Code penal',
            'category' => 'criminal',
            'language' => 'fr',
        ]);

        $this->postJson('/api/laws/chat', ['message' => 'what should i do if smn got shot infront of me'])
            ->assertOk()
            ->assertJsonPath('intent', 'practical_advice')
            ->assertSee('Get to safety first', false)
            ->assertSee('Call emergency services', false)
            ->assertSee('Do not touch weapons', false);
    }

    public function test_chat_retrieves_exact_article_when_user_provides_article_number(): void
    {
        Law::create([
            'title' => 'Code penal theft article',
            'article_number' => 'Article 505',
            'content' => 'La soustraction frauduleuse de la chose d autrui constitue un vol.',
            'document_title' => 'Code penal',
            'category' => 'criminal',
            'language' => 'fr',
        ]);

        $this->postJson('/api/laws/chat', ['message' => 'explain article 505 code penal'])
            ->assertOk()
            ->assertJsonPath('intent', 'article_lookup')
            ->assertJsonPath('citations.0.articleNumber', 'Article 505')
            ->assertSee('I found Article 505 from Code penal', false);
    }

    public function test_chat_asks_clarification_for_unclear_messages(): void
    {
        $this->postJson('/api/laws/chat', ['message' => 'blue banana maybe'])
            ->assertOk()
            ->assertJsonPath('intent', 'unsupported_unclear')
            ->assertJsonPath('citations', [])
            ->assertJsonPath('answer', 'Can you clarify the legal topic or the key facts you want me to analyze?');
    }

    public function test_chat_uses_previous_legal_question_for_follow_ups(): void
    {
        Law::create([
            'title' => 'Commercial company article',
            'article_number' => 'Article 2',
            'content' => 'Rules about societe and registre de commerce.',
            'document_title' => 'Code de commerce',
            'category' => 'business',
            'language' => 'fr',
        ]);

        $this->postJson('/api/laws/chat', [
            'message' => 'show more',
            'history' => [
                ['role' => 'user', 'text' => 'laws about companies in Morocco'],
                ['role' => 'assistant', 'text' => 'I found company sources.'],
            ],
        ])
            ->assertOk()
            ->assertJsonPath('citations.0.articleNumber', 'Article 2');
    }

    public function test_translate_returns_cached_translation(): void
    {
        $law = Law::create([
            'title' => 'Family code article',
            'article_number' => 'Article 10',
            'content' => 'Marriage and family provisions.',
            'document_title' => 'Code de la famille',
            'category' => 'family',
            'language' => 'fr',
        ]);

        LawTranslation::create([
            'law_id' => $law->id,
            'source_language' => 'fr',
            'target_language' => 'en',
            'translated_title' => 'Translated family code article',
            'translated_content' => 'Translated content.',
            'provider' => 'test',
        ]);

        $this->getJson("/api/laws/{$law->id}/translate?target=en")
            ->assertOk()
            ->assertJsonPath('cached', true)
            ->assertJsonPath('translatedTitle', 'Translated family code article');
    }

    public function test_translate_creates_and_caches_inline_translation(): void
    {
        Http::fake(function ($request) {
            parse_str(parse_url($request->url(), PHP_URL_QUERY) ?: '', $query);
            $text = $query['q'] ?? '';
            $translated = str_contains($text, 'Family')
                ? 'Translated title'
                : 'Translated legal content';

            return Http::response([[
                [$translated, $text, null, null],
            ]], 200);
        });

        $law = Law::create([
            'title' => 'Family code article',
            'article_number' => 'Article 11',
            'content' => 'Marriage and family provisions for spouses.',
            'document_title' => 'Code de la famille',
            'category' => 'family',
            'language' => 'fr',
        ]);

        $this->getJson("/api/laws/{$law->id}/translate?target=en")
            ->assertOk()
            ->assertJsonPath('cached', false)
            ->assertJsonPath('translatedTitle', 'Translated title')
            ->assertJsonPath('translatedContent', 'Translated legal content');

        $this->assertDatabaseHas('law_translations', [
            'law_id' => $law->id,
            'target_language' => 'en',
            'translated_title' => 'Translated title',
        ]);
    }
}
