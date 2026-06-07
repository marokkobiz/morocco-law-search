<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ChatLawService
{
    public const INTENT_GREETING = 'greeting_small_talk';
    public const INTENT_DEFINITION = 'legal_definition';
    public const INTENT_PRACTICAL = 'practical_advice';
    public const INTENT_ARTICLE_LOOKUP = 'article_lookup';
    public const INTENT_CASE_ANALYSIS = 'legal_case_analysis';
    public const INTENT_UNSUPPORTED = 'unsupported_unclear';

    private const FILLER_WORDS = [
        'a', 'about', 'all', 'any', 'are', 'article', 'articles', 'can', 'code', 'could', 'find',
        'for', 'give', 'i', 'in', 'is', 'law', 'laws', 'legal', 'legislation', 'looking', 'me',
        'moroccan', 'morocco', 'need', 'of', 'on', 'please', 'related', 'search', 'show', 'tell',
        'the', 'to', 'want', 'what', 'with',
    ];

    private const TOPIC_PROFILES = [
        [
            'key' => 'real-estate',
            'label' => 'real estate',
            'aliases' => ['real estate', 'property', 'land', 'rent', 'rental', 'lease', 'tenant', 'landlord', 'immobilier', 'propriete', 'foncier', 'bail', 'loyer', 'location', 'locataire', 'proprietaire', 'terrain', 'appartement', 'maison', 'copropriete', 'titre foncier', 'عقار', 'العقار', 'عقارات', 'العقارات', 'كراء', 'إيجار', 'ايجار', 'ملكية', 'الملكية', 'محفظ', 'الرسم العقاري'],
            'queries' => ['immobilier', 'bail', 'propriete fonciere', 'copropriete', 'urbanisme'],
        ],
        [
            'key' => 'business',
            'label' => 'business and companies',
            'aliases' => ['business', 'company', 'companies', 'corporate', 'commerce', 'commercial', 'societe', 'societes', 'entreprise', 'sarl', 'sa', 'actionnaire', 'registre de commerce', 'شركة', 'الشركة', 'شركات', 'تجارة', 'التجارة', 'السجل التجاري'],
            'queries' => ['societe', 'commerce', 'registre de commerce'],
        ],
        [
            'key' => 'labor',
            'label' => 'labor and employment',
            'aliases' => ['work', 'worker', 'employee', 'employer', 'employment', 'labor', 'labour', 'salary', 'termination', 'travail', 'salarie', 'employeur', 'contrat de travail', 'licenciement', 'salaire', 'شغل', 'الشغل', 'عمل', 'الأجير', 'اجير', 'مشغل', 'الطرد', 'فصل', 'الفصل', 'الأجر'],
            'queries' => ['travail', 'contrat de travail', 'licenciement'],
        ],
        [
            'key' => 'family',
            'label' => 'family law',
            'aliases' => ['family', 'marriage', 'divorce', 'custody', 'inheritance', 'succession', 'famille', 'mariage', 'divorce', 'garde', 'heritage', 'pension', 'أسرة', 'الأسرة', 'زواج', 'طلاق', 'حضانة', 'نفقة', 'إرث', 'ارث', 'تركة'],
            'queries' => ['famille', 'mariage', 'divorce', 'succession'],
        ],
        [
            'key' => 'tax',
            'label' => 'tax',
            'aliases' => ['tax', 'taxes', 'fiscal', 'vat', 'impot', 'impots', 'fiscalite', 'tva', 'taxe', 'ضريبة', 'ضرائب', 'الضريبة', 'الضرائب'],
            'queries' => ['fiscalite', 'impot', 'tva'],
        ],
        [
            'key' => 'banking',
            'label' => 'banking and finance',
            'aliases' => ['bank', 'banking', 'finance', 'credit', 'loan', 'banque', 'bancaire', 'pret', 'بنك', 'البنك', 'بنكي', 'قرض', 'تمويل'],
            'queries' => ['banque', 'credit', 'bancaire'],
        ],
        [
            'key' => 'contracts',
            'label' => 'contracts',
            'aliases' => ['contract', 'contracts', 'agreement', 'obligation', 'sale', 'sold', 'seller', 'buyer', 'purchase', 'paid', 'price', 'delivery', 'goods', 'contrat', 'contrats', 'vente', 'vendeur', 'acheteur', 'prix', 'delivrance', 'عقد', 'العقد', 'عقود', 'بيع', 'البيع', 'ثمن', 'التسليم', 'التزام'],
            'queries' => ['contrat', 'obligation', 'vente', 'delivrance'],
        ],
        [
            'key' => 'criminal',
            'label' => 'criminal law',
            'aliases' => ['criminal', 'crime', 'penal', 'prison', 'offence', 'infraction', 'criminel', 'جنائي', 'جريمة', 'عقوبة', 'سجن', 'سرقة'],
            'queries' => ['penal', 'infraction'],
        ],
        [
            'key' => 'administrative',
            'label' => 'administrative law',
            'aliases' => ['administrative', 'administration', 'administratif', 'acte administratif', 'formalites administratives', 'procedure administrative', 'delai administratif', 'recepisse', 'usager', 'إدارة', 'الإدارة', 'ادارة', 'الادارة', 'إداري', 'اداري', 'قرار إداري', 'قرار اداري', 'وصل'],
            'queries' => ['simplification des procedures formalites administratives', 'acte administratif', 'recepisse', 'delai administratif'],
        ],
        [
            'key' => 'official-bulletin',
            'label' => 'recent official bulletins',
            'aliases' => ['recent laws', 'new laws', 'latest laws', 'legal updates', 'official bulletin', 'bulletin officiel', 'nouveaux textes', 'nouvelles lois', 'dernieres lois'],
            'queries' => ['bulletin officiel', 'textes generaux', 'loi de finances'],
        ],
    ];

    public function __construct(private readonly LawSearchService $laws)
    {
    }

    public function classifyIntent(string $question, array $history = []): string
    {
        $normalized = $this->normalizeChatText($question);
        $plainNormalized = $this->normalizePlainChatText($question);

        if ($normalized === '') {
            return self::INTENT_UNSUPPORTED;
        }

        if ($this->casualAnswer($normalized)) {
            return self::INTENT_GREETING;
        }

        if ($this->isArticleLookup($plainNormalized)) {
            return self::INTENT_ARTICLE_LOOKUP;
        }

        if ($this->isCivilDebtProofQuestion($normalized)) {
            return self::INTENT_CASE_ANALYSIS;
        }

        if ($this->hasSearchableLegalIntent($question, $normalized)) {
            return self::INTENT_CASE_ANALYSIS;
        }

        if ($this->isPracticalAdviceQuestion($normalized)) {
            return self::INTENT_PRACTICAL;
        }

        if ($this->isDefinitionQuestion($normalized)) {
            return self::INTENT_DEFINITION;
        }

        if ($this->isFollowUp($normalized) && $this->previousLegalQuestion($history)) {
            return self::INTENT_CASE_ANALYSIS;
        }

        if ($this->findTopic($normalized) || $this->hasLegalSignal($normalized)) {
            return self::INTENT_CASE_ANALYSIS;
        }

        return self::INTENT_UNSUPPORTED;
    }

    public function prepare(string $question, array $history = [], ?array $aiPlan = null, ?string $intent = null): array
    {
        $normalized = $this->normalizeChatText($question);
        $language = $this->detectResponseLanguage($question);
        $intent ??= $this->classifyIntent($question, $history);

        if ($intent === self::INTENT_GREETING) {
            $answer = $this->casualAnswer($normalized, $language) ?? match ($language) {
                'fr' => 'Bonjour. Quel sujet juridique voulez-vous analyser ?',
                'ar' => 'مرحبا. ما الموضوع القانوني الذي تريد تحليله؟',
                default => 'Hi. What are we looking into today?',
            };

            return ['intent' => $intent, 'answer' => $answer, 'citations' => [], 'fallbackAnswer' => $answer, 'shouldReason' => false, 'responseLanguage' => $language];
        }

        if ($answer = $this->factOnlyAnswer($question)) {
            return ['intent' => $intent, 'answer' => $answer, 'citations' => [], 'fallbackAnswer' => $answer, 'shouldReason' => false, 'responseLanguage' => $language];
        }

        if ($intent === self::INTENT_UNSUPPORTED) {
            $answer = $this->unsupportedAnswer($question, $language);

            return ['intent' => $intent, 'answer' => $answer, 'citations' => [], 'fallbackAnswer' => $answer, 'shouldReason' => false, 'responseLanguage' => $language];
        }

        if ($intent === self::INTENT_DEFINITION) {
            return $this->prepareDefinitionAnswer($question, $history, $language);
        }

        if ($intent === self::INTENT_PRACTICAL) {
            return $this->preparePracticalAdviceAnswer($question, $history, $language);
        }

        if ($intent === self::INTENT_ARTICLE_LOOKUP) {
            return $this->prepareArticleLookupAnswer($question, $history, $language);
        }

        if (!$this->shouldSearchLaws($question, $history, $aiPlan)) {
            $answer = $this->outOfScopeAnswer($question, $language);

            return ['intent' => $intent, 'answer' => $answer, 'citations' => [], 'fallbackAnswer' => $answer, 'shouldReason' => false, 'responseLanguage' => $language];
        }

        $plan = $this->buildPlan($question, $history, $aiPlan);
        $plan['responseLanguage'] = $language;

        if (!$plan['queries'] || !$this->shouldSearchLaws($question, $history, $aiPlan)) {
            $answer = $this->outOfScopeAnswer($question, $language);

            return ['intent' => $intent, 'answer' => $answer, 'citations' => [], 'fallbackAnswer' => $answer, 'shouldReason' => false, 'responseLanguage' => $language];
        }

        $rawResults = $this->searchForChat($plan, 12);
        $relevantResults = $this->filterByRelevance($question, $plan, $rawResults);
        $citations = $this->formatCitations($relevantResults);
        $diagnostics = $this->retrievalDiagnostics($plan, $rawResults, $relevantResults, $citations);

        if ($rawResults && !$citations) {
            $answer = $this->insufficientSourcesAnswer($question, $plan, $rawResults, $language);

            return ['intent' => $intent, 'answer' => $answer, 'citations' => [], 'fallbackAnswer' => $answer, 'shouldReason' => false, 'responseLanguage' => $language, 'diagnostics' => $diagnostics];
        }

        return [
            'intent' => $intent,
            'answer' => null,
            'citations' => $citations,
            'fallbackAnswer' => $this->chatFallbackAnswer($question, $citations, $plan, $language),
            'shouldReason' => true,
            'plan' => $plan,
            'responseLanguage' => $language,
            'diagnostics' => $diagnostics,
        ];
    }

    private function prepareDefinitionAnswer(string $question, array $history, string $language): array
    {
        $concept = $this->extractDefinitionConcept($question);
        $queries = $this->definitionQueries($question, $concept);
        $plan = $this->basicPlan($question, $queries, $this->definitionAiPlan($question, $concept, $queries));
        $plan['responseLanguage'] = $language;
        $rawResults = $this->searchForChat($plan, 8);
        $relevantResults = $this->filterByRelevance($question, $plan, $rawResults);
        $citations = $this->formatCitations($relevantResults);
        $diagnostics = $this->retrievalDiagnostics($plan, $rawResults, $relevantResults, $citations);
        $definition = $this->simpleDefinition($concept, $question, $language);
        $legalContext = '';

        if ($citations) {
            $source = $this->citationSourceLabel($citations[0]);
            $excerpt = Str::limit($this->cleanExcerpt($citations[0]['content'] ?? ''), 320, '');
            $legalContext = $language === 'fr'
                ? "\n\nContexte juridique dans le corpus indexe: {$source} est un point de depart pertinent. {$excerpt} [1]"
                : "\n\nLegal context from the indexed corpus: {$source} is a relevant starting point. {$excerpt} [1]";
        }

        $answer = $definition.$legalContext;

        return [
            'intent' => self::INTENT_DEFINITION,
            'answer' => $answer,
            'citations' => $citations,
            'fallbackAnswer' => $answer,
            'shouldReason' => false,
            'plan' => $plan,
            'responseLanguage' => $language,
            'diagnostics' => $diagnostics,
        ];
    }

    private function preparePracticalAdviceAnswer(string $question, array $history, string $language): array
    {
        $normalized = $this->normalizeChatText($question);
        $queries = $this->practicalAdviceQueries($question);
        $plan = $this->basicPlan($question, $queries, null);
        $plan['responseLanguage'] = $language;
        $rawResults = $this->searchForChat($plan, 8);
        $relevantResults = $this->filterByRelevance($question, $plan, $rawResults);
        $citations = $this->formatCitations($relevantResults);
        $diagnostics = $this->retrievalDiagnostics($plan, $rawResults, $relevantResults, $citations);
        $steps = $this->practicalSteps($normalized, $language);
        $answer = match ($language) {
            'fr' => "D'abord, les demarches pratiques:\n",
            'ar' => "أولا، الخطوات العملية:\n",
            default => "First, practical steps:\n",
        }.collect($steps)
            ->map(fn (string $step, int $index): string => ($index + 1).'. '.$step)
            ->implode("\n");

        if ($citations) {
            $source = $this->citationSourceLabel($citations[0]);
            $excerpt = Str::limit($this->cleanExcerpt($citations[0]['content'] ?? ''), 300, '');
            $answer .= match ($language) {
                'fr' => "\n\nSource juridique marocaine trouvee: {$source}. {$excerpt} [1]",
                'ar' => "\n\nالمصدر المغربي الأقرب الذي تم العثور عليه: {$source}. {$excerpt} [1]",
                default => "\n\nRelevant Moroccan law found: {$source}. {$excerpt} [1]",
            };
        } else {
            $answer .= match ($language) {
                'fr' => "\n\nJe n'ai pas encore trouve de citation solide dans le corpus indexe pour cette question pratique exacte, donc je n'ajoute pas de numero d'article.",
                'ar' => "\n\nلم أجد بعد إحالة قوية في corpus المفهرس لهذه المسألة العملية بالضبط، لذلك لن أضيف رقم مادة غير مؤكد.",
                default => "\n\nI did not find a strong citation in the indexed corpus for this exact practical question yet, so I am not adding an article number.",
            };
        }

        return [
            'intent' => self::INTENT_PRACTICAL,
            'answer' => $answer,
            'citations' => $citations,
            'fallbackAnswer' => $answer,
            'shouldReason' => false,
            'plan' => $plan,
            'responseLanguage' => $language,
            'diagnostics' => $diagnostics,
        ];
    }

    private function prepareArticleLookupAnswer(string $question, array $history, string $language): array
    {
        $normalized = $this->normalizeChatText($question);
        $articleQuery = $this->extractArticleQuery($normalized);
        $referenceQuery = $this->extractReferenceQuery($normalized);
        $documentHints = $this->documentScopeHints($question);
        $queries = array_values(array_filter(array_unique([
            trim($articleQuery.' '.$referenceQuery),
            ...array_map(fn (string $document): string => trim($articleQuery.' '.$document), $documentHints),
            $articleQuery,
        ])));
        $plan = $this->basicPlan($question, $queries, null);
        $plan['responseLanguage'] = $language;
        $rawResults = $this->searchForChat($plan, 10);
        $relevantResults = $this->filterByRelevance($question, $plan, $rawResults);
        $citations = $this->formatCitations($relevantResults);
        $diagnostics = $this->retrievalDiagnostics($plan, $rawResults, $relevantResults, $citations);

        if (!$citations) {
            $answer = match ($language) {
                'fr' => 'Sources insuffisantes: je n ai pas trouve cet article exact dans le corpus juridique marocain indexe. Ajoutez le nom du code ou la reference de la loi si vous l avez.',
                'ar' => 'المصادر غير كافية: لم أجد هذه المادة بالضبط داخل corpus القانوني المغربي المفهرس. أضف اسم المدونة أو مرجع القانون إذا كان متوفرا.',
                default => 'Sources insuffisantes: I could not find the exact article in the indexed Moroccan legal corpus. Please include the code name or law reference if you have it.',
            };

            return [
                'intent' => self::INTENT_ARTICLE_LOOKUP,
                'answer' => $answer,
                'citations' => [],
                'fallbackAnswer' => $answer,
                'shouldReason' => false,
                'plan' => $plan,
                'responseLanguage' => $language,
                'diagnostics' => $diagnostics,
            ];
        }

        $citation = $citations[0];
        $source = $this->citationSourceLabel($citation);
        $excerpt = $this->cleanExcerpt($citation['content'] ?? '');
        $answer = match ($language) {
            'fr' => "J'ai trouve {$source}.\n\nExtrait exact: ".Str::limit($excerpt, 700, '')." [1]\n\nEn termes simples: cet article est la regle pertinente pour la recherche precise que vous avez demandee. Utilisez la carte de citation ci-dessous pour ouvrir la source et verifier le texte officiel complet.",
            'ar' => "وجدت {$source}.\n\nالمقتطف المطابق: ".Str::limit($excerpt, 700, '')." [1]\n\nبعبارة بسيطة: هذه المادة هي القاعدة الأقرب للبحث المحدد الذي طلبته. استعمل بطاقة الإحالة أسفله لفتح المصدر والتحقق من النص الرسمي الكامل.",
            default => "I found {$source}.\n\nExact excerpt: ".Str::limit($excerpt, 700, '')." [1]\n\nIn simple terms: this article is the relevant rule for the specific article lookup you asked for. Use the citation card below to open the source and verify the full official wording.",
        };

        return [
            'intent' => self::INTENT_ARTICLE_LOOKUP,
            'answer' => $answer,
            'citations' => $citations,
            'fallbackAnswer' => $answer,
            'shouldReason' => false,
            'plan' => $plan,
            'responseLanguage' => $language,
            'diagnostics' => $diagnostics,
        ];
    }

    private function buildPlan(string $question, array $history, ?array $aiPlan = null): array
    {
        $normalized = $this->normalizeChatText($question);
        $previousQuestion = $this->previousLegalQuestion($history);
        $useContext = $previousQuestion && $this->isFollowUp($normalized) && !$this->findTopic($normalized) && !$this->extractReferenceQuery($normalized);
        $planningQuestion = $useContext ? "{$previousQuestion} {$question}" : $question;
        $normalizedPlanningQuestion = $this->normalizeChatText($planningQuestion);
        $plainPlanningQuestion = $this->normalizePlainChatText($planningQuestion);
        $topic = $this->findTopic($normalizedPlanningQuestion);
        $referenceQuery = $this->extractReferenceQuery($plainPlanningQuestion);
        $articleQuery = $this->extractArticleQuery($plainPlanningQuestion);
        $keywordQuery = $this->extractKeywordQuery($normalizedPlanningQuestion);
        $queries = [];
        $targetedAiQueries = $this->filterArticleQueriesByUserInput($aiPlan['searchQueries'] ?? [], $planningQuestion);
        $hasTargetedAiQueries = !$useContext
            && ($aiPlan['needsLawSearch'] ?? false)
            && !empty($targetedAiQueries);

        if ($hasTargetedAiQueries) {
            array_push($queries, ...$targetedAiQueries);
        }

        if ($referenceQuery && $articleQuery) {
            $queries[] = "{$articleQuery} {$referenceQuery}";
        }

        if ($referenceQuery) {
            $queries[] = $referenceQuery;
        }

        if ($articleQuery && !$referenceQuery) {
            $queries[] = $articleQuery;
        }

        if ($topic && !$hasTargetedAiQueries) {
            array_push($queries, ...$topic['queries']);
        }

        if ($keywordQuery && !$hasTargetedAiQueries) {
            $queries[] = $keywordQuery;
        }

        if (!$queries && $this->shouldSearchLaws($question, $history, $aiPlan)) {
            $queries[] = $planningQuestion;
        }

        return [
            'normalizedMessage' => $normalized,
            'planningQuestion' => $planningQuestion,
            'aiPlan' => $aiPlan ? array_merge($aiPlan, ['searchQueries' => $targetedAiQueries]) : null,
            'topic' => $topic,
            'query' => $queries[0] ?? $planningQuestion,
            'queries' => array_values(array_unique(array_filter($queries))),
            'isFollowUp' => $useContext,
        ];
    }

    private function basicPlan(string $question, array $queries, ?array $aiPlan): array
    {
        $normalized = $this->normalizeChatText($question);
        $topic = $this->findTopic($normalized);
        $queries = array_values(array_unique(array_filter(array_map(
            fn (string $query): string => trim($query),
            $queries
        ))));

        if (!$queries) {
            $keyword = $this->extractKeywordQuery($normalized);
            $queries = [$keyword ?: $question];
        }

        return [
            'normalizedMessage' => $normalized,
            'planningQuestion' => $question,
            'aiPlan' => $aiPlan,
            'topic' => $topic,
            'query' => $queries[0],
            'queries' => $queries,
            'isFollowUp' => false,
        ];
    }

    private function formatCitations(array $results): array
    {
        return collect($results)->map(fn (array $law) => [
            'id' => $law['id'],
            'legalChunkId' => $law['legal_chunk_id'] ?? null,
            'legalArticleId' => $law['legal_article_id'] ?? null,
            'legalDocumentId' => $law['legal_document_id'] ?? null,
            'legalDocumentVersionId' => $law['legal_document_version_id'] ?? null,
            'legacyLawId' => $law['legacy_law_id'] ?? null,
            'title' => $law['title'],
            'articleNumber' => $law['article_number'],
            'content' => $law['content'],
            'contextContent' => $this->contextContentForCitation($law),
            'contextScope' => $this->contextScopeForCitation($law),
            'documentTitle' => $law['document_title'],
            'documentType' => $law['document_type'] ?? null,
            'lawReference' => $law['law_reference'],
            'sourceName' => $law['source_name'],
            'sourceType' => $law['source_type'] ?? null,
            'sourceUrl' => $law['source_url'],
            'category' => $law['category'],
            'domain' => $law['domain'] ?? $law['category'] ?? null,
            'subdomain' => $law['subdomain'] ?? null,
            'tags' => $law['tags'] ?? [],
            'versionStatus' => $law['version_status'] ?? null,
            'publicationDate' => $law['publication_date'] ?? null,
            'effectiveDate' => $law['effective_date'] ?? null,
            'sourceTable' => $law['source_table'] ?? 'legacy_laws',
            'isLegacy' => (bool) ($law['is_legacy'] ?? false),
            'relevanceScore' => $law['relevance_score'],
            'sourceRelevanceScore' => $law['chatRelevanceScore'] ?? null,
            'sourceAuthorityScore' => $law['source_authority_score'] ?? null,
            'sourceAuthorityLevel' => $this->sourceAuthorityLevel($law),
            'sourceAuthoritySignals' => $this->sourceAuthoritySignals($law),
            'matchedQuery' => $law['matchedQuery'] ?? null,
            'supportLevel' => $this->supportLevelForCitation($law),
            'supportSignals' => $this->supportSignalsForCitation($law),
        ])->all();
    }

    private function retrievalDiagnostics(array $plan, array $rawResults, array $relevantResults, array $citations): array
    {
        $acceptedKeys = collect($relevantResults)
            ->map(fn (array $law): string => $this->diagnosticResultKey($law))
            ->filter()
            ->flip();
        $rawSummaries = collect($rawResults)
            ->map(function (array $law) use ($acceptedKeys): array {
                $key = $this->diagnosticResultKey($law);

                return [
                    'accepted' => $key !== '' && $acceptedKeys->has($key),
                    'matchedQuery' => $law['matchedQuery'] ?? null,
                    'matchedQueryIndex' => $law['matchedQueryIndex'] ?? null,
                    'documentTitle' => $law['document_title'] ?? null,
                    'documentType' => $law['document_type'] ?? null,
                    'articleNumber' => $law['article_number'] ?? null,
                    'domain' => $law['domain'] ?? $law['category'] ?? null,
                    'subdomain' => $law['subdomain'] ?? null,
                    'sourceTable' => $law['source_table'] ?? 'legacy_laws',
                    'searchScore' => isset($law['relevance_score']) ? round((float) $law['relevance_score'], 2) : null,
                    'chatScore' => isset($law['chatRelevanceScore']) ? round((float) $law['chatRelevanceScore'], 2) : null,
                    'sourceAuthorityScore' => isset($law['source_authority_score']) ? round((float) $law['source_authority_score'], 2) : null,
                ];
            })
            ->values();

        return [
            'planningQuestion' => $plan['planningQuestion'] ?? null,
            'queries' => array_values($plan['queries'] ?? []),
            'expandedQueries' => $this->expandedChatQueries($plan),
            'aiPlan' => $plan['aiPlan'] ?? null,
            'topic' => $plan['topic']['key'] ?? null,
            'rawResultCount' => count($rawResults),
            'acceptedResultCount' => count($relevantResults),
            'rejectedResultCount' => max(0, count($rawResults) - count($relevantResults)),
            'rawResults' => $rawSummaries->take(20)->all(),
            'acceptedCitations' => collect($citations)->map(fn (array $citation): array => [
                'articleNumber' => $citation['articleNumber'] ?? null,
                'documentTitle' => $citation['documentTitle'] ?? null,
                'documentType' => $citation['documentType'] ?? null,
                'domain' => $citation['domain'] ?? null,
                'subdomain' => $citation['subdomain'] ?? null,
                'supportLevel' => $citation['supportLevel'] ?? null,
                'supportSignals' => $citation['supportSignals'] ?? [],
                'contextScope' => $citation['contextScope'] ?? null,
                'matchedQuery' => $citation['matchedQuery'] ?? null,
                'sourceRelevanceScore' => $citation['sourceRelevanceScore'] ?? null,
                'sourceAuthorityScore' => $citation['sourceAuthorityScore'] ?? null,
                'sourceAuthorityLevel' => $citation['sourceAuthorityLevel'] ?? null,
                'sourceAuthoritySignals' => $citation['sourceAuthoritySignals'] ?? [],
            ])->all(),
        ];
    }

    private function diagnosticResultKey(array $law): string
    {
        return implode(':', array_filter([
            $law['source_table'] ?? 'legacy_laws',
            $law['legal_chunk_id'] ?? null,
            $law['legal_article_id'] ?? null,
            $law['id'] ?? null,
        ], fn (mixed $value): bool => $value !== null && $value !== ''));
    }

    private function contextContentForCitation(array $law): string
    {
        if (($law['source_table'] ?? 'legacy_laws') !== 'corpus' || empty($law['legal_chunk_id'])) {
            return (string) ($law['content'] ?? '');
        }

        $currentChunk = DB::table('legal_chunks')
            ->select('legal_article_id', 'legal_document_version_id', 'chunk_index')
            ->where('id', $law['legal_chunk_id'])
            ->first();

        if (!$currentChunk) {
            return (string) ($law['content'] ?? '');
        }

        $chunkContext = DB::table('legal_chunks')
            ->where('legal_article_id', $currentChunk->legal_article_id)
            ->where('legal_document_version_id', $currentChunk->legal_document_version_id)
            ->whereBetween('chunk_index', [
                max(0, (int) $currentChunk->chunk_index - 1),
                (int) $currentChunk->chunk_index + 1,
            ])
            ->orderBy('chunk_index')
            ->pluck('content')
            ->map(fn (string $content): string => trim($content))
            ->filter()
            ->unique()
            ->implode("\n");

        $article = DB::table('legal_articles')
            ->select('id', 'legal_document_id', 'legal_document_version_id', 'article_number', 'article_title', 'content', 'sort_order')
            ->where('id', $currentChunk->legal_article_id)
            ->where('legal_document_version_id', $currentChunk->legal_document_version_id)
            ->first();

        if (!$article) {
            return $chunkContext !== '' ? Str::limit($chunkContext, 1800, '') : (string) ($law['content'] ?? '');
        }

        $nearbyArticles = $this->nearbyArticlesForCitation($article);
        $sections = collect([
            $chunkContext !== '' ? "Matched and neighboring chunks:\n".$chunkContext : '',
            "Full cited article:\n".$this->formatArticleContext($article),
        ])
            ->concat($nearbyArticles->map(fn (object $nearby): string => "Neighboring article context:\n".$this->formatArticleContext($nearby)))
            ->filter()
            ->unique()
            ->implode("\n\n");

        return Str::limit($sections !== '' ? $sections : (string) ($law['content'] ?? ''), 6000, '');
    }

    private function contextScopeForCitation(array $law): string
    {
        if (($law['source_table'] ?? 'legacy_laws') !== 'corpus' || empty($law['legal_chunk_id'])) {
            return 'excerpt_only';
        }

        return 'matched_chunk_full_article_neighboring_articles';
    }

    private function nearbyArticlesForCitation(object $article): Collection
    {
        $base = DB::table('legal_articles')
            ->select('id', 'article_number', 'article_title', 'content', 'sort_order')
            ->where('legal_document_id', $article->legal_document_id)
            ->where('legal_document_version_id', $article->legal_document_version_id)
            ->where('status', 'active')
            ->where('id', '<>', $article->id);

        $previous = (clone $base)
            ->where(fn ($query) => $query
                ->where('sort_order', '<', $article->sort_order)
                ->orWhere(fn ($tie) => $tie
                    ->where('sort_order', $article->sort_order)
                    ->where('id', '<', $article->id)))
            ->orderByDesc('sort_order')
            ->orderByDesc('id')
            ->first();

        $next = (clone $base)
            ->where(fn ($query) => $query
                ->where('sort_order', '>', $article->sort_order)
                ->orWhere(fn ($tie) => $tie
                    ->where('sort_order', $article->sort_order)
                    ->where('id', '>', $article->id)))
            ->orderBy('sort_order')
            ->orderBy('id')
            ->first();

        return collect([$previous, $next])->filter()->values();
    }

    private function formatArticleContext(object $article): string
    {
        return trim(implode(' ', array_filter([
            trim(($article->article_number ?? '').' '.($article->article_title ?? '')),
            Str::limit($this->cleanExcerpt((string) ($article->content ?? '')), 2200, ''),
        ])));
    }

    private function supportLevelForCitation(array $law): string
    {
        $score = (float) ($law['chatRelevanceScore'] ?? 0);

        if ($score >= 10) {
            return 'strong';
        }

        if ($score >= 6) {
            return 'moderate';
        }

        return 'contextual';
    }

    private function supportSignalsForCitation(array $law): array
    {
        $matchedQuery = (string) ($law['matchedQuery'] ?? '');
        $text = $this->normalizeRelevanceText(implode(' ', [
            $law['title'] ?? '',
            $law['article_number'] ?? '',
            $law['content'] ?? '',
            $law['contextContent'] ?? '',
            $law['document_title'] ?? '',
            $law['law_reference'] ?? '',
            $law['category'] ?? '',
            $law['domain'] ?? '',
            $law['subdomain'] ?? '',
            is_array($law['tags'] ?? null) ? implode(' ', $law['tags']) : ($law['tags'] ?? ''),
        ]));

        return collect($this->relevanceTokens($matchedQuery))
            ->filter(fn (string $token): bool => str_contains($text, $token))
            ->take(8)
            ->values()
            ->all();
    }

    private function isArticleLookup(string $normalized): bool
    {
        return (bool) preg_match('/\b(?:article|art)\s*(premier|\d+(?:\s*(?:bis|ter|quater))?)\b/', $normalized)
            && (preg_match('/\b(show|find|get|lookup|search|text|explain|meaning|what does|cite|donne|cherche|explique)\b/', $normalized)
                || preg_match('/\b(code|loi|dahir|decret|arrete)\b/', $normalized));
    }

    private function isDefinitionQuestion(string $normalized): bool
    {
        return (bool) preg_match('/\b(what is|what does|define|definition|meaning of|explain|c est quoi|qu est ce que|que signifie)\b/', $normalized)
            && !$this->isPracticalAdviceQuestion($normalized);
    }

    private function isPracticalAdviceQuestion(string $normalized): bool
    {
        return (bool) preg_match('/\b(what can i do|what should i do|what do i do|how do i|steps?|practical|help me|i got|i was|someone got|somebody got|smn got|smon got|my .* was|report|complaint|plainte|que faire|quoi faire)\b/', $normalized)
            && (preg_match('/\b(robbed|robbery|stolen|theft|steal|vol|vole|agression|assault|shot|shooting|gunshot|gun|firearm|arme|arme a feu|wounded|injured|blesse|blessure|scam|fraud|dismissed|fired|licencie|landlord|tenant|rent|accident|contract|contrat)\b/', $normalized)
                || $this->hasLegalSignal($normalized));
    }

    private function isCivilDebtProofQuestion(string $normalized): bool
    {
        $moneyTransfer = preg_match('/\b(bank transfer|transfer|transferred|wire|sent money|paid|payment|virement|versement|paiement|envoye|envoyer|100\s*000|100000|mad|dirhams?)\b/', $normalized);
        $debtDispute = preg_match('/\b(loan|lent|lend|borrowed|debt|owes|repay|repayment|gift|donation|cousin|friend|family|whatsapp|message|messages|receipt|proof|evidence|pret|prete|emprunt|dette|creance|rembourser|remboursement|don|preuve|ecrit|reconnaissance de dette|famille|ami)\b/', $normalized);

        return (bool) ($moneyTransfer && $debtDispute);
    }

    private function extractDefinitionConcept(string $question): string
    {
        $normalized = $this->normalizeChatText($question);
        $concept = preg_replace('/\b(what is|what does|define|definition|meaning of|explain|c est quoi|qu est ce que|que signifie|mean|means|in law|legal)\b/', ' ', $normalized) ?? $normalized;
        $concept = trim(preg_replace('/\s+/', ' ', $concept) ?? $concept);

        return $concept !== '' ? $concept : $question;
    }

    private function definitionQueries(string $question, string $concept): array
    {
        $normalized = $this->normalizeChatText($question.' '.$concept);

        if (preg_match('/\b(licenciement|dismissal|termination)\b/', $normalized)) {
            return ['licenciement code du travail', 'contrat de travail licenciement', 'motif valable licenciement'];
        }

        if (preg_match('/\b(vol|theft|robbery|robbed|stolen)\b/', $normalized)) {
            return ['vol code penal', 'soustraction frauduleuse', 'infraction vol'];
        }

        if (preg_match('/\b(delivrance|delivery|livraison|mise a disposition|chose vendue|vendeur|acheteur)\b/', $normalized)
            && preg_match('/\b(vente|vendu|sale|sold|vendeur|seller|chose vendue)\b/', $normalized)) {
            return [
                'article 499 code des obligations et des contrats delivrance possession sans obstacle',
                'article 498 code des obligations et des contrats obligation de delivrance',
                'code des obligations et des contrats delivrance chose vendue',
            ];
        }

        if (preg_match('/\b(bail|lease|rent|tenant|landlord|locataire|bailleur)\b/', $normalized)) {
            return ['bail locataire bailleur', 'loyer bail', 'recouvrement des loyers'];
        }

        if (preg_match('/\b(societe|company|companies|commercial|commerce)\b/', $normalized)) {
            return ['societe commerce', 'registre de commerce', 'code de commerce'];
        }

        return array_values(array_filter([$concept, $this->extractKeywordQuery($normalized)]));
    }

    private function definitionAiPlan(string $question, string $concept, array $queries): ?array
    {
        $normalized = $this->normalizeChatText($question.' '.$concept.' '.implode(' ', $queries));

        if (preg_match('/\b(delivrance|delivery|livraison|mise a disposition|chose vendue|vendeur|acheteur)\b/', $normalized)
            && preg_match('/\b(vente|vendu|sale|sold|vendeur|seller|chose vendue)\b/', $normalized)) {
            return [
                'legalIssue' => 'sale delivery legal definition',
                'trustedArticleAnchors' => [
                    'article 499 code des obligations et des contrats delivrance possession sans obstacle',
                    'article 498 code des obligations et des contrats obligation de delivrance',
                    'article 500 code des obligations et des contrats choses mobilieres tradition reelle',
                ],
                'relevanceTerms' => ['delivrance', 'possession', 'sans obstacle', 'chose vendue', 'vendeur', 'acheteur'],
                'allowedDocumentTitles' => ['Code des Obligations et des Contrats'],
                'allowedCategories' => ['civil'],
            ];
        }

        return null;
    }

    private function simpleDefinition(string $concept, string $question, string $language): string
    {
        $normalized = $this->normalizeChatText($question.' '.$concept);

        if (preg_match('/\b(licenciement|dismissal|termination)\b/', $normalized)) {
            return match ($language) {
                'fr' => 'Le licenciement signifie que l employeur met fin au contrat de travail. En pratique, les questions principales sont le motif valable, le respect de la procedure et les recours possibles.',
                'ar' => 'الفصل يعني أن المشغل ينهي عقد الشغل. عمليا، الأسئلة الأساسية هي وجود سبب مشروع، احترام المسطرة، وما هي الآثار أو التعويضات الممكنة.',
                default => 'Licenciement means an employer ends an employment contract. In simple terms, the key questions are usually whether there is a valid reason, whether the required procedure was followed, and what remedy may apply.',
            };
        }

        if (preg_match('/\b(vol|theft|robbery|robbed|stolen)\b/', $normalized)) {
            return match ($language) {
                'fr' => 'Le vol signifie prendre le bien d autrui sans droit. En pratique, on regarde l acte de soustraction, le bien vise et les circonstances comme la force, la fraude ou l effraction.',
                'ar' => 'السرقة تعني أخذ مال الغير دون حق. عمليا، يتم النظر إلى فعل الأخذ، نوع المال، والظروف مثل العنف أو التدليس أو الكسر.',
                default => 'Vol/theft means taking someone else\'s property without the right to do so. In simple terms, the law looks at the act of taking, the property involved, and the circumstances such as force, fraud, or breaking in.',
            };
        }

        if (preg_match('/\b(bail|lease|rent|tenant|landlord|locataire|bailleur)\b/', $normalized)) {
            return match ($language) {
                'fr' => 'Un bail est un accord par lequel une personne laisse une autre utiliser un bien pendant une periode, en general contre un loyer. Les questions pratiques sont le loyer, la duree, les obligations, la resiliation et l action judiciaire possible.',
                'ar' => 'الكراء هو اتفاق يضع بموجبه شخص عقارا أو شيئا رهن استعمال شخص آخر لمدة معينة، غالبا مقابل واجب كرائي. المسائل العملية تكون حول الواجب، المدة، الالتزامات، الفسخ، والإجراءات القضائية الممكنة.',
                default => 'A lease/bail is an agreement where one person lets another use property for a period, usually in exchange for rent. The practical questions are rent, duration, obligations, termination, and possible court action.',
            };
        }

        if (preg_match('/\b(societe|company|companies)\b/', $normalized)) {
            return match ($language) {
                'fr' => 'Une societe est une structure juridique utilisee pour exercer une activite separement des personnes qui la composent. Les regles concernent souvent la creation, l immatriculation, la gestion, les associes ou actionnaires et la responsabilite.',
                'ar' => 'الشركة هي إطار قانوني لممارسة نشاط تجاري أو مهني بشكل منظم ومستقل عن الأشخاص المكونين لها. القواعد غالبا تتعلق بالتأسيس، التسجيل، التسيير، الشركاء أو المساهمين، والمسؤولية.',
                default => 'A company/societe is a legal structure used to run a business separately from the people behind it. The rules usually concern formation, registration, management, partners or shareholders, and liability.',
            };
        }

        return match ($language) {
            'fr' => "En termes simples, {$concept} est une notion ou un sujet juridique. La reponse exacte depend du code, du contrat, des faits et du domaine juridique concerne.",
            'ar' => "بعبارة بسيطة، {$concept} هو مفهوم أو موضوع قانوني. الجواب الدقيق يتوقف على المدونة أو العقد أو الوقائع أو المجال القانوني المعني.",
            default => "In simple terms, {$concept} is a legal concept or topic. The exact answer depends on the code, contract, facts, and legal area involved.",
        };
    }

    private function practicalAdviceQueries(string $question): array
    {
        $normalized = $this->normalizeChatText($question);

        if (preg_match('/\b(robbed|robbery|stolen|theft|steal|vol|vole)\b/', $normalized)) {
            return ['vol code penal', 'soustraction frauduleuse', 'infraction vol', 'plainte vol'];
        }

        if (preg_match('/\b(shot|shooting|gunshot|gun|firearm|arme|arme a feu|wounded|injured|blesse|blessure|agression|assault)\b/', $normalized)) {
            return ['agression code penal', 'coups blessures code penal', 'homicide code penal', 'violence arme feu code penal', 'plainte agression'];
        }

        if (preg_match('/\b(dismissed|fired|licencie|licenciement|termination)\b/', $normalized)) {
            return ['licenciement code du travail', 'procedure licenciement', 'motif valable licenciement'];
        }

        if (preg_match('/\b(landlord|tenant|rent|loyer|locataire|bailleur)\b/', $normalized)) {
            return ['bailleur locataire loyer', 'recouvrement des loyers', 'bail habitation'];
        }

        if (preg_match('/\b(loan|lent|lend|borrowed|debt|owes|repay|repayment|gift|bank transfer|transfer|whatsapp|message|messages|receipt|proof|evidence|pret|prete|emprunt|dette|creance|rembourser|remboursement|don|virement|preuve|ecrit|reconnaissance de dette)\b/', $normalized)) {
            return [
                'article 399 code des obligations et des contrats preuve obligation',
                'article 401 code des obligations et des contrats preuve obligations forme ecrite',
                'article 404 code des obligations et des contrats moyens de preuve aveu preuve ecrite presomption',
                'article 443 code des obligations et des contrats obligations plus de dix mille dirhams preuve ecrite electronique',
                'article 447 code des obligations et des contrats commencement de preuve par ecrit',
                'preuve obligation dette reconnaissance de dette',
                'pret remboursement preuve virement bancaire messages whatsapp',
            ];
        }

        return array_values(array_filter([$this->extractKeywordQuery($normalized), $question]));
    }

    private function practicalSteps(string $normalized, string $language): array
    {
        if ($language === 'ar') {
            if (preg_match('/\b(robbed|robbery|stolen|theft|steal|vol|vole)\b/', $normalized)) {
                return [
                    'بلغ الشرطة أو الدرك بسرعة واسأل عن طريقة وضع شكاية رسمية.',
                    'احتفظ بالأدلة: الصور، الرسائل، الوصولات، أرقام الأجهزة، الكاميرات، المكان والتوقيت.',
                    'أوقف البطاقات البنكية أو شرائح الهاتف أو الحسابات إذا تمت سرقة مال أو هاتف أو وثائق.',
                    'سجل أسماء الشهود ووسائل الاتصال بهم في أقرب وقت.',
                    'احتفظ بنسخ من الشكاية والمحاضر والإشعارات البنكية وأي دليل على الملكية.',
                ];
            }

            if (preg_match('/\b(dismissed|fired|licencie|licenciement|termination)\b/', $normalized)) {
                return [
                    'اجمع رسالة الفصل، عقد الشغل، كشوف الأجر، الإنذارات، البريد الإلكتروني ورسائل الموارد البشرية.',
                    'اكتب تسلسلا زمنيا واضحا للوقائع والأشخاص الحاضرين.',
                    'اطلب سبب الفصل مكتوبا واحتفظ بدليل التسليم أو الرفض.',
                    'لا توقع صلحا أو وصلا أو مخالصة لا تفهم آثارها.',
                    'تحقق بسرعة من الآجال لأن نزاعات الشغل قد تكون مقيدة بآجال قصيرة.',
                ];
            }

            if (preg_match('/\b(loan|lent|lend|borrowed|debt|owes|repay|repayment|gift|bank transfer|transfer|whatsapp|message|messages|receipt|proof|evidence|pret|prete|emprunt|dette|creance|rembourser|remboursement|don|virement|preuve|ecrit|reconnaissance de dette)\b/', $normalized)) {
                return [
                    'احتفظ بتحويلات البنك، رسائل واتساب، الوصولات وأي اعتراف كتابي.',
                    'اكتب تسلسلا زمنيا: المبلغ، تاريخ التحويل، السبب المصرح به، طلبات الإرجاع والردود.',
                    'تجنب إرسال رسالة غامضة قد تفهم كإقرار بأن المبلغ هبة إذا كان موقفك أنه قرض.',
                    'اطلب إقرارا كتابيا واضحا بالدين أو بجدول السداد قبل التصعيد.',
                ];
            }

            return [
                'اكتب تسلسلا زمنيا واضحا للوقائع والتواريخ.',
                'احتفظ بكل الوثائق والرسائل والصور والوصولات وبيانات الشهود.',
                'تجنب الإقرار بشيء أو توقيع وثيقة غير واضحة.',
                'استشر الجهة المختصة أو كتابة الضبط أو محاميا أو موثقا إذا كان الأمر مستعجلا.',
            ];
        }

        if (preg_match('/\b(robbed|robbery|stolen|theft|steal|vol|vole)\b/', $normalized)) {
            return $language === 'fr' ? [
                'Signalez rapidement les faits a la police ou a la gendarmerie et demandez comment deposer une plainte formelle.',
                'Conservez les preuves: photos, messages, recus, numeros de serie, pistes camera, lieu et horaires.',
                'Bloquez les cartes bancaires, cartes SIM, comptes ou appareils si de l argent, un telephone, des cartes ou des documents d identite ont ete pris.',
                'Notez les temoins et leurs coordonnees tant que les souvenirs sont frais.',
                'Gardez des copies de la plainte, des rapports, des avis bancaires et des preuves de propriete.',
            ] : [
                'Report it quickly to the police or gendarmerie and ask how to file a formal complaint.',
                'Preserve evidence: photos, messages, receipts, serial numbers, CCTV leads, location details, and timestamps.',
                'Block bank cards, SIM cards, accounts, or devices if money, phone, cards, or identity documents were taken.',
                'Write down witnesses and contact details while the memory is fresh.',
                'Keep copies of the complaint, reports, bank notices, and any proof of ownership.',
            ];
        }

        if (preg_match('/\b(shot|shooting|gunshot|gun|firearm|arme|arme a feu|wounded|injured|blesse|blessure|agression|assault)\b/', $normalized)) {
            return $language === 'fr' ? [
                'Mettez-vous en securite d abord et eloignez-vous du danger si vous pouvez le faire sans augmenter le risque.',
                'Appelez immediatement les secours, la police ou la gendarmerie en indiquant le lieu, le nombre de blesses et si l auteur est encore proche.',
                'Ne touchez pas aux armes, douilles, traces de sang, telephones, sacs ou autres preuves sauf si c est necessaire pour sauver une vie.',
                'Si c est sans danger, donnez les premiers secours de base ou demandez a quelqu un de vous aider en attendant les secours.',
                'Notez rapidement ce que vous avez vu: heure, lieu, description de l auteur, vehicule, direction de fuite, temoins et cameras possibles.',
                'Cooperez comme temoin et gardez les copies de toute plainte, declaration ou numero de rapport.',
            ] : [
                'Get to safety first and move away from the danger if you can do so without putting yourself or the victim at more risk.',
                'Call emergency services, police, or gendarmerie immediately and give the location, number of injured people, and whether the attacker is still nearby.',
                'Do not touch weapons, bullet casings, blood traces, phones, bags, or other evidence unless it is necessary to save a life.',
                'If it is safe, give basic first aid or ask someone nearby to help while waiting for emergency responders.',
                'Write down what you saw as soon as possible: time, place, description of the attacker, vehicle, direction of escape, witnesses, and CCTV locations.',
                'Cooperate as a witness and keep copies of any complaint, statement, or report number you receive.',
            ];
        }

        if (preg_match('/\b(dismissed|fired|licencie|licenciement|termination)\b/', $normalized)) {
            return $language === 'fr' ? [
                'Rassemblez la lettre de licenciement, le contrat, les bulletins de paie, avertissements, emails et messages RH.',
                'Ecrivez une chronologie des faits et des personnes presentes.',
                'Demandez le motif ecrit et gardez la preuve de remise ou de refus.',
                'Ne signez pas un solde, accord ou recu que vous ne comprenez pas.',
                'Verifiez vite les delais, car les litiges du travail peuvent etre soumis a des delais courts.',
            ] : [
                'Collect the dismissal letter, contract, payslips, warnings, emails, and any HR messages.',
                'Write a timeline of what happened and who was present.',
                'Ask for the written reason and keep proof of delivery or refusal.',
                'Do not sign a settlement or receipt you do not understand.',
                'Check deadlines quickly because labor claims can be time-sensitive.',
            ];
        }

        if (preg_match('/\b(loan|lent|lend|borrowed|debt|owes|repay|repayment|gift|bank transfer|transfer|whatsapp|message|messages|receipt|proof|evidence|pret|prete|emprunt|dette|creance|rembourser|remboursement|don|virement|preuve|ecrit|reconnaissance de dette)\b/', $normalized)) {
            return $language === 'fr' ? [
                'Conservez les virements bancaires, messages WhatsApp, recus et toute reconnaissance ecrite.',
                'Ecrivez une chronologie: montant, date du transfert, raison indiquee, demandes de remboursement et reponses.',
                'Evitez d envoyer un message ambigu qui pourrait confirmer un don si vous soutenez qu il s agit d un pret.',
                'Demandez une confirmation ecrite claire de la dette ou du calendrier de remboursement avant d agir.',
            ] : [
                'Keep the bank-transfer records, WhatsApp messages, receipts, and any written acknowledgement.',
                'Write a timeline: amount, transfer date, stated reason, repayment requests, and replies.',
                'Avoid sending an ambiguous message that could confirm a gift if your position is that it was a loan.',
                'Ask for a clear written acknowledgement of the debt or repayment schedule before escalating.',
            ];
        }

        return $language === 'fr' ? [
            'Ecrivez une chronologie claire des faits et des dates.',
            'Gardez tous les documents, messages, photos, recus et coordonnees de temoins.',
            'Evitez les aveux ou la signature d un document ambigu.',
            'Contactez l autorite competente, le greffe, un avocat, un notaire ou un conseiller professionnel si la situation est urgente.',
        ] : [
            'Write a clear timeline of the facts and dates.',
            'Keep all documents, messages, photos, receipts, and witness details.',
            'Avoid making admissions or signing anything unclear.',
            'Ask the competent authority, court clerk, lawyer, notary, or professional adviser if the matter is urgent.',
        ];
    }

    private function citationSourceLabel(array $citation): string
    {
        return trim(implode(' from ', array_filter([
            $citation['articleNumber'] ?? null,
            $citation['documentTitle'] ?? null,
        ]))) ?: ($citation['title'] ?? 'the retrieved source');
    }

    private function cleanExcerpt(?string $value): string
    {
        return trim(preg_replace('/\s+/', ' ', (string) $value) ?? (string) $value);
    }

    private function unsupportedAnswer(string $question, string $language): string
    {
        $normalized = $this->normalizeChatText($question);

        if (preg_match('/\b(weather|forecast|temperature|rain|recipe|cook|movie|music|song|game|sports|football|joke|poem)\b/', $normalized)) {
            return $this->outOfScopeAnswer($question, $language);
        }

        return match ($language) {
            'fr' => 'Pouvez-vous preciser le sujet juridique ou les faits essentiels a analyser ?',
            'ar' => 'هل يمكن أن توضح الموضوع القانوني أو الوقائع الأساسية التي تريد تحليلها؟',
            default => 'Can you clarify the legal topic or the key facts you want me to analyze?',
        };
    }

    private function filterArticleQueriesByUserInput(array $queries, string $question): array
    {
        if ($this->userProvidedArticleNumber($question)) {
            return $queries;
        }

        return collect($queries)
            ->map(fn (string $query): string => $this->stripArticleNumberFromQuery($query))
            ->filter(fn (string $query): bool => Str::length($query) >= 2)
            ->unique()
            ->values()
            ->all();
    }

    private function userProvidedArticleNumber(string $question): bool
    {
        return (bool) preg_match('/\b(?:article|art)\s*(premier|\d+(?:\s*(?:bis|ter|quater))?)\b/i', $this->normalizeChatText($question));
    }

    private function stripArticleNumberFromQuery(string $query): string
    {
        return Str::of($query)
            ->replaceMatches('/\b(?:article|art)\s*(?:premier|\d+(?:\s*(?:bis|ter|quater))?)\b/i', ' ')
            ->replaceMatches('/\s+/', ' ')
            ->trim()
            ->toString();
    }

    private function searchForChat(array $plan, int $limit): array
    {
        if (($plan['topic']['key'] ?? null) === 'official-bulletin') {
            $payload = $this->laws->latestOfficialBulletinArticles($limit);

            return collect($payload['results'])
                ->map(fn (array $law) => array_merge($law, ['matchedQuery' => 'latest official bulletins', 'matchedQueryIndex' => 0]))
                ->all();
        }

        $merged = [];
        $seen = [];
        $retrievalQueries = collect($this->expandedChatQueries($plan))
            ->take(20)
            ->values()
            ->all();
        $shouldCollectAllQueries = !empty($plan['aiPlan']['searchQueries'])
            || count($retrievalQueries) > count($plan['queries'] ?? []);
        $perQueryLimit = $shouldCollectAllQueries ? max($limit * 2, 12) : $limit;

        foreach ($retrievalQueries as $index => $query) {
            $payload = $this->laws->search($query, $perQueryLimit, [
                'includeChatOnlySources' => $this->shouldIncludeChatOnlySources($plan),
                'useCorpus' => true,
                'disableCache' => true,
                'disableSemanticSearch' => $this->shouldDisableSemanticForChatQuery($query, $index),
            ]);

            foreach ($payload['results'] as $law) {
                $seenKey = ($law['source_table'] ?? 'legacy_laws').':'.($law['legal_chunk_id'] ?? $law['legal_article_id'] ?? $law['id']);

                if (isset($seen[$seenKey])) {
                    continue;
                }

                $seen[$seenKey] = true;
                $merged[] = array_merge($law, ['matchedQuery' => $query, 'matchedQueryIndex' => $index]);

                if (!$shouldCollectAllQueries && count($merged) >= $limit) {
                    return $merged;
                }
            }
        }

        if (!$shouldCollectAllQueries) {
            return $merged;
        }

        return collect($merged)
            ->sortByDesc(fn (array $law): float => $this->chatResultRank($law))
            ->take(max($limit * 3, 30))
            ->values()
            ->all();
    }

    private function shouldDisableSemanticForChatQuery(string $query, int $index): bool
    {
        if ($this->articleScopeHint($query) !== '') {
            return true;
        }

        return $index >= 3;
    }

    private function expandedChatQueries(array $plan): array
    {
        $planningQuestion = $plan['planningQuestion'] ?? $plan['normalizedMessage'] ?? '';
        $baseQueries = collect($plan['queries'] ?? [])
            ->map(fn (string $query): string => trim($query))
            ->filter()
            ->values();

        if ($baseQueries->isEmpty()) {
            return [];
        }

        if ($this->userProvidedArticleNumber($planningQuestion)) {
            return $baseQueries
                ->unique(fn (string $query): string => $this->normalizeSearchText($query))
                ->values()
                ->all();
        }

        $classifier = app(LegalDomainClassifier::class);
        $taxonomyText = trim($planningQuestion.' '.$baseQueries->implode(' '));
        $conceptTerms = collect($classifier->conceptTermsForQuery($taxonomyText, 18));
        $documentHints = collect([
            ...$this->documentScopeHints($taxonomyText),
            ...($plan['aiPlan']['allowedDocumentTitles'] ?? []),
        ])
            ->map(fn (string $document): string => trim($document))
            ->filter()
            ->unique(fn (string $document): string => $this->normalizeSearchText($document))
            ->values();

        $expanded = $baseQueries
            ->concat($this->frenchLegalQueryExpansions($taxonomyText))
            ->concat($conceptTerms)
            ->concat($documentHints);

        foreach ($documentHints as $document) {
            foreach ($conceptTerms->take(8) as $term) {
                $expanded->push(trim($term.' '.$document));
            }
        }

        $trustedArticleAnchors = collect($plan['aiPlan']['trustedArticleAnchors'] ?? [])
            ->map(fn (string $query): string => trim($query))
            ->filter()
            ->values();

        return $trustedArticleAnchors
            ->concat($this->filterArticleQueriesByUserInput($expanded->all(), $planningQuestion))
            ->map(fn (string $query): string => trim($query))
            ->filter()
            ->unique(fn (string $query): string => $this->normalizeSearchText($query))
            ->take(34)
            ->values()
            ->all();
    }

    private function frenchLegalQueryExpansions(string $text): array
    {
        $normalized = $this->normalizeChatText($text);
        $queries = collect();

        $add = fn (array $values) => $queries->push(...$values);

        if (preg_match('/\b(employer|employee|worker|job|work contract|dismiss|dismissed|fire|fired|termination|terminated|wrongful dismissal|notice|salary|wage|disciplinary|misconduct)\b/', $normalized)) {
            $add([
                'licenciement code du travail',
                'licenciement motif valable',
                'procedure disciplinaire licenciement',
                'decision licenciement motifs',
                'preuve licenciement employeur',
            ]);
        }

        if (preg_match('/\b(landlord|tenant|lease|rent|rental|evict|eviction|apartment|housing|commercial premises|shop lease|business lease)\b/', $normalized)) {
            $add([
                'bailleur locataire loyer',
                'resiliation bail locataire',
                'expulsion locataire',
                'recouvrement des loyers',
                'bail commercial indemnite eviction',
            ]);
        }

        if (preg_match('/\b(contract|agreement|sale|sold|seller|buyer|purchase|paid|payment|price|delivery|deliver|delivered|goods|ownership|possession|vehicle|car)\b/', $normalized)) {
            $add([
                'contrat obligations parties',
                'vente acheteur vendeur prix',
                'delivrance chose vendue',
                'paiement delivrance vendeur acheteur',
                'transfert propriete chose vendue',
            ]);
        }

        if (preg_match('/\b(loan|lent|lend|borrowed|debt|owes|repay|repayment|gift|bank transfer|transfer|wire|whatsapp|message|messages|receipt|proof|evidence|cousin|friend|family)\b/', $normalized)
            || preg_match('/\b(pret|prete|emprunt|dette|creance|rembourser|remboursement|don|virement|preuve|ecrit|message|messages|whatsapp|famille|cousin|ami|reconnaissance de dette)\b/', $normalized)) {
            $add([
                'preuve obligation code des obligations et des contrats',
                'moyens de preuve obligation aveu preuve ecrite presomption',
                'obligation superieure dix mille dirhams preuve ecrite',
                'commencement de preuve par ecrit obligation',
                'pret dette remboursement virement bancaire messages',
            ]);
        }

        if (preg_match('/\b(heir|heirs|inheritance|estate|successor|successors|death|died)\b/', $normalized)) {
            $add([
                'succession heritiers',
                'heritiers ayants droit',
                'ayants cause obligations',
            ]);
        }

        if (preg_match('/\b(theft|stolen|robbed|robbery|steal|fraud|break in|burglary|criminal|offence|offense)\b/', $normalized)) {
            $add([
                'vol code penal',
                'soustraction frauduleuse',
                'infraction vol',
                'plainte vol',
            ]);
        }

        if (preg_match('/\b(employee|employer|worker|salarie|employe|employeur|travailleur)\b.*\b(vol|theft|abus de confiance|detournement|detourne|caisse|soustraction|misappropriation)\b/', $normalized)
            || preg_match('/\b(vol|theft|abus de confiance|detournement|detourne|caisse|soustraction|misappropriation)\b.*\b(employee|employer|worker|salarie|employe|employeur|travailleur)\b/', $normalized)) {
            $add([
                'abus de confiance code penal fonds remis',
                'detournement de fonds code penal',
                'soustraction frauduleuse code penal',
                'vol code penal',
            ]);
        }

        if (preg_match('/\b(company|companies|shareholder|director|manager|corporate|business registration|commercial register|llc|limited liability)\b/', $normalized)) {
            $add([
                'societe code de commerce',
                'registre de commerce',
                'societe responsabilite limitee',
                'gerant societe',
                'associe actionnaire societe',
            ]);
        }

        if ((preg_match('/\b(company|societe|sarl|entreprise)\b/', $normalized)
                && preg_match('/\b(dgi|impot|taxe|tva|declaration fiscale|penalite|majoration|redressement fiscal|sanction fiscale)\b/', $normalized))) {
            $add([
                'declaration fiscale societe sanction fiscale',
                'penalite fiscale majoration societe',
                'redressement fiscal societe',
                'impot sur les societes declaration penalite',
            ]);
        }

        if (preg_match('/\b(property possession|possession immobiliere|action en revendication|revendication immobiliere|ownership claim|unregistered property dispute|competing purchasers)\b/', $normalized)) {
            $add([
                'code des droits reels possession propriete immobiliere',
                'action en revendication propriete immobiliere',
                'revendication droit de propriete',
                'immeuble non immatricule deux acquereurs priorite',
            ]);
        }

        if (preg_match('/\b(property preemption source coverage|right of pre-emption|right of preemption|droit de preemption|preemption immobiliere)\b/', $normalized)) {
            $add([
                'droit de preemption propriete immobiliere',
                'preemption immobiliere',
            ]);
        }

        if (preg_match('/\b(consumer|customer|refund|warranty|defective|defect|product|seller guarantee)\b/', $normalized)) {
            $add([
                'protection du consommateur garantie',
                'defaut produit consommateur',
                'remboursement consommateur',
                'information consommateur vendeur',
            ]);
        }

        return $queries
            ->map(fn (string $query): string => trim($query))
            ->filter()
            ->unique(fn (string $query): string => $this->normalizeSearchText($query))
            ->values()
            ->all();
    }

    private function filterByRelevance(string $question, array $plan, array $results): array
    {
        $citationLimit = $this->citationLimitForQuestion($question, $plan);
        $filtered = collect($results)
            ->map(function (array $law) use ($question, $plan): array {
                $signals = $this->scoreRelevance($question, $plan, $law);

                return array_merge($law, [
                    'chatRelevanceScore' => round($signals['score'], 2),
                    'rejectedByScope' => $signals['rejectedByScope'],
                    'rejectedBySource' => $signals['rejectedBySource'],
                ]);
            })
            ->pipe(fn (Collection $laws): Collection => $this->applyDomainAwareRankingPriority($laws, $question, $plan))
            ->filter(fn (array $law) => !$law['rejectedByScope'] && !$law['rejectedBySource'] && $law['chatRelevanceScore'] >= 2.8)
            ->sortByDesc(fn (array $law): float => $law['chatRelevanceScore'] + $this->chatResultRank($law) / 100000)
            ->values();

        $filtered = $this->addRequiredCompanionCitations($filtered, $question, $plan)
            ->take($citationLimit)
            ->map(function (array $law): array {
                unset($law['rejectedByScope'], $law['rejectedBySource']);

                return $law;
            });

        return $filtered->all();
    }

    private function addRequiredCompanionCitations(Collection $results, string $question, array $plan): Collection
    {
        $normalized = $this->normalizeChatText($question.' '.($plan['aiPlan']['legalIssue'] ?? ''));
        $targets = [];

        if (preg_match('/\b(sell|sells|sold|sale|bought|buyer|seller|paid|payment|price|car|vehicle|ownership|registration|heirs|inherit|vente|vendu|acheteur|vendeur|prix|paye|paiement|voiture|vehicule|propriete|immatriculation|heritiers|succession)\b/', $normalized)) {
            $targets['Code des Obligations et des Contrats'] = ['Article 488', 'Article 491', 'Article 499', 'Article 500', 'Article 229'];
        }

        if (preg_match('/\b(procedure|procedural|disciplinaire|audition|entendu|defense|licencier|licenciement|motif valable)\b/', $normalized)) {
            $targets['Code du travail'] = ['Article 35', 'Article 62'];
        }

        if (!$targets) {
            return $results;
        }

        $seen = $results
            ->map(fn (array $law): string => ($law['document_title'] ?? '').'|'.($law['article_number'] ?? ''))
            ->flip();

        $augmented = $results;

        foreach ($targets as $documentTitle => $articleNumbers) {
            $anchor = $results->first(fn (array $law): bool => ($law['document_title'] ?? '') === $documentTitle);
            $anchor ??= $this->directCorpusAnchorForDocument($documentTitle);

            if (!$anchor || empty($anchor['legal_document_id']) || empty($anchor['legal_document_version_id'])) {
                continue;
            }

            foreach ($this->corpusArticlesAsResults((int) $anchor['legal_document_id'], (int) $anchor['legal_document_version_id'], $articleNumbers, $anchor) as $companion) {
                $key = ($companion['document_title'] ?? '').'|'.($companion['article_number'] ?? '');

                if ($seen->has($key)) {
                    continue;
                }

                $seen[$key] = true;
                $augmented->push($companion);
            }
        }

        return $augmented->sortByDesc(fn (array $law): float => ($law['chatRelevanceScore'] ?? 0) + $this->chatResultRank($law) / 100000)->values();
    }

    private function directCorpusAnchorForDocument(string $documentTitle): ?array
    {
        $document = DB::table('legal_documents')
            ->leftJoin('legal_document_versions', function ($join): void {
                $join->on('legal_document_versions.legal_document_id', '=', 'legal_documents.id')
                    ->where('legal_document_versions.status', 'active');
            })
            ->where('legal_documents.document_title', $documentTitle)
            ->where('legal_documents.status', 'active')
            ->select([
                'legal_documents.id AS legal_document_id',
                DB::raw('COALESCE(legal_documents.current_version_id, legal_document_versions.id) AS legal_document_version_id'),
                'legal_documents.document_title',
                'legal_documents.document_type',
            ])
            ->first();

        if (!$document || !$document->legal_document_version_id) {
            return null;
        }

        return [
            'legal_document_id' => $document->legal_document_id,
            'legal_document_version_id' => $document->legal_document_version_id,
            'document_title' => $document->document_title,
            'relevance_score' => 7000,
            'source_authority_score' => 550,
            'chatRelevanceScore' => 12,
            'matchedQueryIndex' => 0,
        ];
    }

    private function corpusArticlesAsResults(int $documentId, int $versionId, array $articleNumbers, array $anchor): array
    {
        $articles = DB::table('legal_articles')
            ->join('legal_documents', 'legal_documents.id', '=', 'legal_articles.legal_document_id')
            ->join('legal_document_versions', 'legal_document_versions.id', '=', 'legal_articles.legal_document_version_id')
            ->leftJoin('legal_sources', 'legal_sources.id', '=', 'legal_documents.legal_source_id')
            ->leftJoin('legal_chunks', function ($join): void {
                $join->on('legal_chunks.legal_article_id', '=', 'legal_articles.id')
                    ->on('legal_chunks.legal_document_version_id', '=', 'legal_articles.legal_document_version_id')
                    ->where('legal_chunks.chunk_index', 0);
            })
            ->where('legal_articles.legal_document_id', $documentId)
            ->where('legal_articles.legal_document_version_id', $versionId)
            ->whereIn('legal_articles.article_number', $articleNumbers)
            ->where('legal_articles.status', 'active')
            ->select([
                'legal_articles.id AS legal_article_id',
                'legal_chunks.id AS legal_chunk_id',
                'legal_documents.id AS legal_document_id',
                'legal_document_versions.id AS legal_document_version_id',
                'legal_articles.article_title AS title',
                'legal_articles.article_number',
                DB::raw('COALESCE(legal_chunks.content, legal_articles.content) AS content'),
                'legal_documents.document_title',
                'legal_documents.document_type',
                'legal_documents.law_reference',
                'legal_sources.name AS source_name',
                'legal_sources.source_type',
                DB::raw('COALESCE(legal_document_versions.source_url, legal_documents.source_url, legal_sources.source_url) AS source_url'),
                DB::raw('COALESCE(legal_documents.domain, legal_articles.domain) AS domain'),
                DB::raw('COALESCE(legal_documents.domain, legal_articles.domain) AS category'),
                DB::raw('COALESCE(legal_documents.subdomain, legal_articles.subdomain) AS subdomain'),
                DB::raw('COALESCE(legal_documents.tags, legal_articles.tags) AS tags'),
                'legal_document_versions.status AS version_status',
                DB::raw('COALESCE(legal_document_versions.publication_date, legal_documents.publication_date) AS publication_date'),
                DB::raw('COALESCE(legal_document_versions.effective_date, legal_documents.effective_date) AS effective_date'),
            ])
            ->get()
            ->keyBy('article_number');

        return collect($articleNumbers)
            ->map(fn (string $articleNumber) => $articles->get($articleNumber))
            ->filter()
            ->map(function (object $article, int $index) use ($anchor): array {
                return [
                    'id' => $article->legal_chunk_id ?? $article->legal_article_id,
                    'legal_chunk_id' => $article->legal_chunk_id,
                    'legal_article_id' => $article->legal_article_id,
                    'legal_document_id' => $article->legal_document_id,
                    'legal_document_version_id' => $article->legal_document_version_id,
                    'legacy_law_id' => null,
                    'title' => $article->title ?: $article->article_number,
                    'article_number' => $article->article_number,
                    'content' => $article->content,
                    'document_title' => $article->document_title,
                    'document_type' => $article->document_type,
                    'law_reference' => $article->law_reference,
                    'source_name' => $article->source_name,
                    'source_type' => $article->source_type,
                    'source_url' => $article->source_url,
                    'category' => $article->category,
                    'domain' => $article->domain,
                    'subdomain' => $article->subdomain,
                    'tags' => $article->tags ? (json_decode((string) $article->tags, true) ?: []) : [],
                    'version_status' => $article->version_status,
                    'publication_date' => $article->publication_date,
                    'effective_date' => $article->effective_date,
                    'source_table' => 'corpus',
                    'is_legacy' => false,
                    'relevance_score' => max(1, (float) ($anchor['relevance_score'] ?? 1) - ($index + 1)),
                    'source_authority_score' => $anchor['source_authority_score'] ?? null,
                    'chatRelevanceScore' => max(8.5, (float) ($anchor['chatRelevanceScore'] ?? 8) - (($index + 1) * 0.2)),
                    'matchedQuery' => 'required companion article',
                    'matchedQueryIndex' => $anchor['matchedQueryIndex'] ?? 0,
                ];
            })
            ->values()
            ->all();
    }

    private function citationLimitForQuestion(string $question, array $plan): int
    {
        $normalized = $this->normalizeChatText($question.' '.($plan['aiPlan']['legalIssue'] ?? ''));
        $issueSignals = 0;

        foreach ([
            '/\b(procedure|procedural|disciplinaire|hearing|entretien|notification)\b/',
            '/\b(preuve|evidence|proof|temoin|witness|contradiction|burden|charge)\b/',
            '/\b(indemnite|compensation|damages|dommages|preavis|severance)\b/',
            '/\b(vol|theft|criminal|penal|infraction|accusation)\b/',
            '/\b(contract|contrat|vente|ownership|propriete|possession|heirs|heritiers)\b/',
            '/\b(analyse|analyze|analysis|arguments|conclusion|facts|faits)\b/',
        ] as $pattern) {
            if (preg_match($pattern, $normalized)) {
                $issueSignals++;
            }
        }

        if ($issueSignals >= 3 || count($plan['queries'] ?? []) >= 6) {
            return 8;
        }

        if ($issueSignals >= 2 || count($plan['queries'] ?? []) >= 4) {
            return 6;
        }

        return 5;
    }

    private function scoreRelevance(string $question, array $plan, array $law): array
    {
        $matchedQuery = $law['matchedQuery'] ?? $plan['query'] ?? $question;
        $lawText = $this->normalizeRelevanceText(implode(' ', [
            $law['title'] ?? '',
            $law['article_number'] ?? '',
            $law['content'] ?? '',
            $law['document_title'] ?? '',
            $law['law_reference'] ?? '',
            $law['category'] ?? '',
            $law['domain'] ?? '',
            $law['subdomain'] ?? '',
            is_array($law['tags'] ?? null) ? implode(' ', $law['tags']) : ($law['tags'] ?? ''),
            $law['source_name'] ?? '',
        ]));
        $scope = $this->scopedRelevanceSignals($matchedQuery, $law);
        $source = $this->allowedSourceSignals($plan, $law);

        if ($scope['rejectedByScope'] || !$source['sourceMatches']) {
            return [
                'score' => 0,
                'rejectedByScope' => $scope['rejectedByScope'],
                'rejectedBySource' => !$source['sourceMatches'],
            ];
        }

        $score = 0.0;
        $score += $this->sourceAuthorityBonus($law);

        if ($source['hasSourceGate']) {
            $score += 4;
        }

        if ($scope['articleHint'] && $scope['articleMatches']) {
            $score += 30;
            $score += max(0, 12 - (int) ($law['matchedQueryIndex'] ?? 12));

            $trustedAnchorIndex = collect($plan['aiPlan']['trustedArticleAnchors'] ?? [])
                ->map(fn (string $anchor): string => $this->normalizeSearchText($anchor))
                ->search($this->normalizeSearchText($matchedQuery), true);

            if ($trustedAnchorIndex !== false) {
                $score += max(0, 36 - ((int) $trustedAnchorIndex * 14));
            }
        }

        if ($scope['documentHints'] && $scope['documentMatches']) {
            $score += 4;
        }

        if ($scope['referenceHints'] && $scope['referenceMatches']) {
            $score += 4;
        }

        $planTermMatches = collect($plan['aiPlan']['relevanceTerms'] ?? [])
            ->map(fn (string $term): string => $this->normalizeRelevanceText($term))
            ->filter(fn (string $term): bool => Str::length($term) >= 3)
            ->unique()
            ->filter(fn (string $term): bool => str_contains($lawText, $term))
            ->count();
        $score += min($planTermMatches * 2, 8);

        $queryMatches = collect($this->relevanceTokens($matchedQuery))->filter(fn (string $token): bool => str_contains($lawText, $token))->count();
        $score += min($queryMatches * 0.7, 4.2);

        $questionMatches = collect($this->relevanceTokens($question))->filter(fn (string $token): bool => str_contains($lawText, $token))->count();
        $score += min($questionMatches * 0.35, 2.1);

        $normalizedCategory = $this->normalizeRelevanceText($law['category'] ?? '');
        $normalizedDocument = $this->normalizeRelevanceText($law['document_title'] ?? '');
        $normalizedTopicKey = $this->normalizeRelevanceText($plan['topic']['key'] ?? '');
        $topicAliasMatches = collect($plan['topic']['aliases'] ?? [])
            ->contains(function (string $alias) use ($lawText, $normalizedCategory, $normalizedDocument): bool {
                $normalizedAlias = $this->normalizeRelevanceText($alias);

                return Str::length($normalizedAlias) >= 3
                    && (str_contains($lawText, $normalizedAlias)
                        || str_contains($normalizedCategory, $normalizedAlias)
                        || str_contains($normalizedDocument, $normalizedAlias));
            });

        if ($topicAliasMatches || ($normalizedTopicKey && (str_contains($normalizedCategory, $normalizedTopicKey) || str_contains($normalizedDocument, $normalizedTopicKey)))) {
            $score += 2.5;
        }

        $classifier = app(LegalDomainClassifier::class);
        $matchedQueryTaxonomy = $classifier->classifyQuery($matchedQuery.' '.$question);
        $lawTaxonomy = $classifier->classifyDocument([
            'document_title' => $law['document_title'] ?? '',
            'law_reference' => $law['law_reference'] ?? '',
            'category' => $law['category'] ?? '',
            'tags' => $law['tags'] ?? [],
            'text' => $law['content'] ?? '',
        ]);
        $lawDomain = $this->normalizeRelevanceText($lawTaxonomy['domain'] ?? ($law['domain'] ?? $law['category'] ?? ''));
        $lawSubdomain = $this->normalizeRelevanceText($lawTaxonomy['subdomain'] ?? ($law['subdomain'] ?? ''));
        $dominantDomain = $this->normalizeRelevanceText($plan['aiPlan']['dominantDomain'] ?? '');
        $domainConfidence = $plan['aiPlan']['domainConfidence'] ?? 'weak';

        if ($dominantDomain !== '' && $lawDomain !== '') {
            if ($lawDomain === $dominantDomain) {
                $score += $domainConfidence === 'strong' ? 8 : ($domainConfidence === 'moderate' ? 5 : 2);
            } elseif ($domainConfidence === 'strong') {
                $score -= 11;
            } elseif ($domainConfidence === 'moderate') {
                $score -= 6;
            }
        }

        if (($matchedQueryTaxonomy['domain'] ?? null) && $lawDomain === $this->normalizeRelevanceText($matchedQueryTaxonomy['domain'])) {
            $score += 3.5;
        } elseif (($matchedQueryTaxonomy['domain'] ?? null) && $lawDomain !== '') {
            $score -= 2.5;
        }

        if (($matchedQueryTaxonomy['subdomain'] ?? null) && $lawSubdomain === $this->normalizeRelevanceText($matchedQueryTaxonomy['subdomain'])) {
            $score += 1.5;
        }

        $conceptMatches = collect($classifier->conceptTermsForTaxonomy($matchedQueryTaxonomy, 18))
            ->map(fn (string $term): string => $this->normalizeRelevanceText($term))
            ->filter(fn (string $term): bool => Str::length($term) >= 3)
            ->unique()
            ->filter(fn (string $term): bool => str_contains($lawText, $term))
            ->count();
        $score += min($conceptMatches * 0.8, 5.6);
        $specificIssue = $this->specificIssueSignal($question, $matchedQuery);

        if ($specificIssue && !$this->sourceSupportsSpecificIssue($specificIssue, $lawText)) {
            $score -= $specificIssue['required'] ? 7.5 : 4.0;
        }

        if (count($this->relevanceTokens($matchedQuery)) <= 1 && $planTermMatches === 0 && $conceptMatches === 0 && !$scope['articleHint']) {
            $score -= 4.5;
        }

        return [
            'score' => $score,
            'rejectedByScope' => false,
            'rejectedBySource' => false,
        ];
    }

    private function applyDomainAwareRankingPriority(Collection $laws, string $question, array $plan): Collection
    {
        $preferredDomain = $this->rankingPreferredDomain($question, $plan);
        $hasLeaseIntent = $this->hasLeaseRankingIntent($question);
        $hasLeaseCandidate = $hasLeaseIntent && $laws->contains(function (array $law): bool {
            return !$law['rejectedByScope']
                && !$law['rejectedBySource']
                && $this->isLeaseSourceCandidate($law);
        });
        $hasPropertyTitleIntent = $this->hasPropertyTitleOccupationIntent($question);
        $hasPropertyTitleCandidate = $hasPropertyTitleIntent && $laws->contains(function (array $law): bool {
            return !$law['rejectedByScope']
                && !$law['rejectedBySource']
                && $this->isPropertyTitleSourceCandidate($law);
        });

        if ($preferredDomain === '' && !$hasLeaseCandidate && !$hasPropertyTitleCandidate) {
            return $laws;
        }

        $hasPreferredDomainCandidate = $preferredDomain !== '' && $laws->contains(function (array $law) use ($preferredDomain): bool {
            return !$law['rejectedByScope']
                && !$law['rejectedBySource']
                && $this->rankingLawDomain($law) === $preferredDomain;
        });

        if (!$hasPreferredDomainCandidate && !$hasLeaseCandidate && !$hasPropertyTitleCandidate) {
            return $laws;
        }

        return $laws->map(function (array $law) use ($preferredDomain, $hasPreferredDomainCandidate, $hasLeaseCandidate, $hasPropertyTitleCandidate): array {
            if ($law['rejectedByScope'] || $law['rejectedBySource']) {
                return $law;
            }

            $lawDomain = $this->rankingLawDomain($law);
            $articleNumber = $this->normalizeRelevanceText($law['article_number'] ?? '');
            $documentTitle = $this->normalizeRelevanceText($law['document_title'] ?? '');
            $score = (float) ($law['chatRelevanceScore'] ?? 0);

            if ($hasPreferredDomainCandidate && $lawDomain === $preferredDomain) {
                $score += 5.0;
            }

            if ($hasPreferredDomainCandidate
                && in_array($preferredDomain, ['banking finance', 'commercial company', 'tax'], true)
                && $lawDomain === 'civil procedure'
                && $articleNumber === 'article 134') {
                $score -= 18.0;
            }

            if ($hasPreferredDomainCandidate
                && $preferredDomain === 'real estate rent'
                && $lawDomain === 'civil obligations contracts'
                && str_contains($documentTitle, 'code des obligations et des contrats')
                && in_array($articleNumber, ['article 443', 'article 488', 'article 491', 'article 499'], true)) {
                $score -= 14.0;
            }

            if ($hasLeaseCandidate) {
                if ($this->isLeaseSourceCandidate($law)) {
                    $score += 12.0;
                }

                if ($lawDomain === 'civil obligations contracts'
                    && str_contains($documentTitle, 'code des obligations et des contrats')
                    && in_array($articleNumber, ['article 488', 'article 491', 'article 499', 'article 500'], true)) {
                    $score -= 20.0;
                }
            }

            if ($hasPropertyTitleCandidate) {
                if ($this->isPropertyTitleSourceCandidate($law)) {
                    $score += 12.0;
                }

                if ($lawDomain === 'civil obligations contracts'
                    && str_contains($documentTitle, 'code des obligations et des contrats')
                    && in_array($articleNumber, ['article 443', 'article 488', 'article 491', 'article 499', 'article 500'], true)) {
                    $score -= 18.0;
                }
            }

            $law['chatRelevanceScore'] = round($score, 2);

            return $law;
        });
    }

    private function hasLeaseRankingIntent(string $question): bool
    {
        $normalized = $this->normalizeRelevanceText($question);

        if (preg_match('/\b(bail|lease|loyer|rent|locataire|tenant|bailleur|landlord|expulsion|eviction|recouvrement des loyers)\b/', $normalized)) {
            return true;
        }

        return preg_match('/عقد\s*الكراء|كراء|إيجار|ايجار|إخلاء|اخلاء|المستأجر|مستأجر|المكري|تسليم\s*العقار/u', $question) === 1;
    }

    private function isLeaseSourceCandidate(array $law): bool
    {
        $documentTitle = $this->normalizeRelevanceText($law['document_title'] ?? '');
        $lawText = $this->normalizeRelevanceText(implode(' ', [
            $law['document_title'] ?? '',
            $law['law_reference'] ?? '',
            $law['category'] ?? '',
            $law['subdomain'] ?? '',
            $law['content'] ?? '',
        ]));

        if (str_contains($documentTitle, 'recouvrement des loyers')) {
            return true;
        }

        return $this->rankingLawDomain($law) === 'real estate rent'
            && preg_match('/\b(bail|loyer|locataire|bailleur|lease|rent|tenant|landlord|expulsion|eviction)\b/', $lawText) === 1;
    }

    private function hasPropertyTitleOccupationIntent(string $question): bool
    {
        $normalized = $this->normalizeRelevanceText($question);

        return preg_match('/\b(droit de propriete|preuve du droit de propriete|propriete fonciere|titre foncier|immatriculation fonciere|occupant sans contrat|expulser un occupant|expulsion occupant)\b/', $normalized) === 1;
    }

    private function isPropertyTitleSourceCandidate(array $law): bool
    {
        $documentTitle = $this->normalizeRelevanceText($law['document_title'] ?? '');

        return $this->rankingLawDomain($law) === 'real estate rent'
            && preg_match('/\b(immatriculation fonciere|code des droits reels|propriete fonciere|titre foncier)\b/', $documentTitle) === 1;
    }

    private function rankingPreferredDomain(string $question, array $plan): string
    {
        $domain = $this->normalizeRelevanceText($plan['aiPlan']['dominantDomain'] ?? '');
        $confidence = $plan['aiPlan']['domainConfidence'] ?? 'weak';

        if ($domain !== '' && in_array($confidence, ['moderate', 'strong'], true)) {
            return $domain === 'civil procedure' ? '' : $domain;
        }

        $taxonomy = app(LegalDomainClassifier::class)->classifyQuery($question);
        $scores = collect($taxonomy['scores'] ?? [])->sortDesc()->values();
        $topScore = (int) ($scores->get(0) ?? 0);
        $runnerUp = (int) ($scores->get(1) ?? 0);

        if (($taxonomy['domain'] ?? null) && $topScore >= 14 && ($topScore - $runnerUp) >= 6) {
            $classifiedDomain = $this->normalizeRelevanceText($taxonomy['domain']);

            return $classifiedDomain === 'civil procedure' ? '' : $classifiedDomain;
        }

        return '';
    }

    private function rankingLawDomain(array $law): string
    {
        $taxonomy = app(LegalDomainClassifier::class)->classifyDocument([
            'document_title' => $law['document_title'] ?? '',
            'law_reference' => $law['law_reference'] ?? '',
            'category' => $law['category'] ?? '',
            'tags' => $law['tags'] ?? [],
            'text' => $law['content'] ?? '',
        ]);

        return $this->normalizeRelevanceText($taxonomy['domain'] ?? ($law['domain'] ?? $law['category'] ?? ''));
    }

    private function specificIssueSignal(string $question, string $matchedQuery): ?array
    {
        $text = $this->normalizeRelevanceText($question.' '.$matchedQuery);
        $rules = [
            ['pattern' => '/\b(avis d imposition|contestation fiscale|conteste|contester|redressement fiscal|sanction fiscale)\b/', 'terms' => ['avis d imposition', 'contestation fiscale', 'conteste', 'contester', 'redressement fiscal', 'sanction fiscale', 'recours fiscal'], 'required' => true],
            ['pattern' => '/\b(cheque impaye|cheque sans provision|sans provision)\b/', 'terms' => ['cheque', 'provision'], 'required' => true],
            ['pattern' => '/\b(abus de confiance|detourne|detournement)\b/', 'terms' => ['abus de confiance', 'detournement', 'dissipation', 'remis'], 'required' => true],
            ['pattern' => '/\b(menace|menaces|threat)\b/', 'terms' => ['menace', 'ordre', 'condition'], 'required' => true],
            ['pattern' => '/\b(coups|blessures|violence|agression)\b/', 'terms' => ['coups', 'blessures', 'violence', 'incapacite'], 'required' => true],
            ['pattern' => '/\b(preuve du droit de propriete|droit de propriete|occupant sans contrat)\b/', 'terms' => ['droit de propriete', 'propriete fonciere', 'titre foncier', 'possession'], 'required' => false],
            ['pattern' => '/\b(loyer|locataire|bail habitation|recouvrement du loyer)\b/', 'terms' => ['loyer', 'bail', 'locataire', 'recouvrement des loyers'], 'required' => true],
        ];

        foreach ($rules as $rule) {
            if (preg_match($rule['pattern'], $text)) {
                return $rule;
            }
        }

        return null;
    }

    private function sourceSupportsSpecificIssue(array $issue, string $lawText): bool
    {
        foreach ($issue['terms'] as $term) {
            if (str_contains($lawText, $this->normalizeRelevanceText($term))) {
                return true;
            }
        }

        return false;
    }

    private function chatFallbackAnswer(string $question, array $citations, array $plan, string $language): string
    {
        if (!$citations) {
            $tried = '';

            if ($plan['queries']) {
                $tried = match ($language) {
                    'fr' => ' Recherches essayees: ',
                    'ar' => ' حاولت البحث عن: ',
                    default => ' I tried: ',
                }.implode(', ', $plan['queries']).'.';
            }

            return match ($language) {
                'fr' => "Je n'ai pas trouve d'articles correspondants dans le corpus juridique marocain indexe.{$tried} Essayez un mot-cle juridique plus large, un terme francais, ou une reference precise de loi/article.",
                'ar' => "لم أجد مواد مطابقة داخل corpus القانوني المغربي المفهرس.{$tried} جرب كلمة قانونية أوسع، أو مصطلحا فرنسيا، أو مرجعا دقيقا لقانون أو مادة.",
                default => "I did not find matching articles in the indexed Moroccan legal corpus.{$tried} Try a broader legal keyword, a French term, or a specific law/article reference.",
            };
        }

        $documents = collect($citations)->pluck('documentTitle')->filter()->unique()->take(3)->implode(', ');
        $searchedFor = $plan['topic']['label'] ?? $plan['query'] ?? $question;

        if ($language === 'fr') {
            return trim(sprintf(
                'D apres le corpus juridique marocain indexe, j ai trouve %d article%s pertinent%s pour %s.%s Activez AI_PROVIDER=ollama pour un raisonnement local complet.',
                count($citations),
                count($citations) === 1 ? '' : 's',
                count($citations) === 1 ? '' : 's',
                $searchedFor,
                $documents ? ' Sources les plus fortes: '.$documents.'.' : ''
            ));
        }

        if ($language === 'ar') {
            return trim(sprintf(
                'بناء على corpus القانوني المغربي المفهرس، وجدت %d مادة ذات صلة بموضوع %s.%s فعل AI_PROVIDER=ollama للحصول على تحليل محلي كامل.',
                count($citations),
                $searchedFor,
                $documents ? ' أقوى المصادر: '.$documents.'.' : ''
            ));
        }

        return trim(sprintf(
            'Based on the indexed Moroccan legal corpus, I found %d relevant article%s for %s.%s Enable AI_PROVIDER=ollama for full local AI reasoning.',
            count($citations),
            count($citations) === 1 ? '' : 's',
            $searchedFor,
            $documents ? ' Strongest sources: '.$documents.'.' : ''
        ));
    }

    private function insufficientSourcesAnswer(string $question, array $plan, array $rawResults, string $language): string
    {
        $issue = $plan['topic']['label'] ?? $plan['query'] ?? $question;
        $tried = '';

        if ($plan['queries']) {
            $tried = match ($language) {
                'fr' => ' Recherches essayees: ',
                'ar' => ' حاولت البحث عن: ',
                default => ' I tried: ',
            }.implode(', ', array_slice($plan['queries'], 0, 5)).'.';
        }
        $sources = collect($rawResults)
            ->map(fn (array $law) => $law['document_title'] ?? $law['source_name'] ?? $law['title'] ?? null)
            ->filter()
            ->unique()
            ->take(3)
            ->implode(', ');
        $sourceNote = $sources
            ? match ($language) {
                'fr' => " Les resultats bruts les plus proches venaient de {$sources}, mais ils ne semblaient pas suffisamment lies a vos faits.",
                'ar' => " أقرب النتائج الخام جاءت من {$sources}، لكنها لا تبدو مرتبطة بما يكفي بوقائع السؤال.",
                default => " The closest raw search hits came from {$sources}, but they did not look sufficiently tied to your facts.",
            }
            : '';

        return match ($language) {
            'fr' => "Sources insuffisantes: je n'ai pas trouve de sources marocaines suffisamment pertinentes dans le corpus indexe pour {$issue}.{$sourceNote} Je ne dois pas repondre a partir d articles sans lien.{$tried} Essayez un terme juridique francais, un nom de code, un numero de loi ou un article precis si vous en avez un.",
            'ar' => "المصادر غير كافية: لم أجد مصادر مغربية مرتبطة بما يكفي داخل corpus المفهرس بخصوص {$issue}.{$sourceNote} لا يجب أن أجيب اعتمادا على مواد غير مرتبطة.{$tried} جرب مصطلحا قانونيا فرنسيا، اسم مدونة، رقم قانون، أو رقم مادة إن كان متوفرا.",
            default => "Sources insuffisantes: I could not find sufficiently relevant Moroccan law sources in the indexed corpus for {$issue}.{$sourceNote} I should not answer from unrelated articles.{$tried} Try a specific French legal term, code name, law number, or article if you have one.",
        };
    }

    private function shouldSearchLaws(string $message, array $history = [], ?array $aiPlan = null): bool
    {
        $normalized = $this->normalizeChatText($message);

        if ($normalized === '' || $this->casualAnswer($normalized) || preg_match('/^(ok|okay|cool|fine|great|nice|yes|no|sure|alright|perfect|alr)$/', $normalized)) {
            return false;
        }

        if (preg_match('/\b(weather|forecast|temperature|rain|recipe|cook|movie|music|song|game|sports|football|joke|translate this|write a poem)\b/', $normalized)) {
            return false;
        }

        if ($this->isFollowUp($normalized) && $this->previousLegalQuestion($history)) {
            return true;
        }

        if (($aiPlan['needsLawSearch'] ?? false) && !empty($aiPlan['searchQueries'])) {
            return true;
        }

        if ($aiPlan && ($aiPlan['needsLawSearch'] ?? true) === false) {
            return false;
        }

        if ($this->findTopic($normalized) || $this->hasLegalSignal($normalized) || $this->hasSearchableLegalIntent($message, $normalized)) {
            return true;
        }

        return count(array_filter(explode(' ', $normalized))) > 2
            && preg_match('/\b(morocco|moroccan|maroc|marocain|marocaine)\b/', $normalized);
    }

    private function hasLegalSignal(string $normalized): bool
    {
        return (bool) preg_match('/\b(law|legal|legislation|regulation|article|code|statute|decree|dahir|loi|droit|juridique|decret|arrete|tribunal|court|contract|lease|tenant|landlord|property|real estate|company|tax|labor|employment|family|marriage|divorce|inheritance|criminal|civil|commerce|consumer|bank|insurance|investment|permit|notary|debt|loan|proof|evidence|repayment|gift|bank transfer|whatsapp|administrative|administration|administratif|acte administratif|formalites|formalite|procedure administrative|delai|usager|recepisse|recouvrement|creances publiques|tva|taxe sur la valeur ajoutee|nafaka|nafaqa|competence territoriale|defendeur|domicile|procedure civile|manoeuvres frauduleuses|tromperie|fausses qualites|agrement|credit|credits|fonds|delai de reponse|traitement administratif|demande administrative|immobilier|bail|locataire|proprietaire|societe|travail|famille|fiscalite|contrat|banque|assurance|mariage|heritage|succession|penal|consommation|dette|pret|preuve|virement|remboursement|creance|don|aakar|alaakar|alaakarat|choghl|alchoghl|aamal|charika|alcharika|tijara|kanoun|mahkama|aadl|jarima|jinaai|dariba)\b|\b(loi|dahir|decret)\s*(n|no|num|numero)?\s*\d{1,3}[-\/]\d{2,4}\b|\b(article|art)\s*\d+\b|\b\d{1,3}[-\/]\d{2,4}\b/', $normalized);
    }

    private function hasSearchableLegalIntent(string $original, string $normalized): bool
    {
        $hasResearchFrame = (bool) preg_match(
            '/\b(quels? textes? chercher|quel texte chercher|texte juridique|regle applicable|quelle regle|comment verifier|comment contester|quel recours|legal rule|applicable law|what law|which law|which texts?)\b/',
            $normalized
        ) || (bool) preg_match('/ما\s+(?:هو\s+)?(?:النص|القانون|القاعدة|المسطرة|الإجراء|الاجراء)\s+(?:الأقرب|المنظم|المطبق|حول)|ما\s+هي\s+(?:النصوص|القواعد|المسطرة)|كيف\s+(?:أتحقق|اتحقق|أطعن|اطعن)/u', $original);

        if (!$hasResearchFrame) {
            return false;
        }

        return (bool) (app(LegalDomainClassifier::class)->classifyQuery($original)['domain'] ?? null);
    }

    private function containsArabic(string $value): bool
    {
        return preg_match('/\p{Arabic}/u', $value) === 1;
    }

    private function detectResponseLanguage(string $message): string
    {
        if ($this->containsArabic($message)) {
            return 'ar';
        }

        $raw = Str::lower($message);
        $normalized = $this->normalizeChatText($message);
        $frenchScore = preg_match('/[a-z]*[àâçéèêëîïôùûüÿœ]/iu', $raw) ? 3 : 0;
        $englishScore = 0;

        foreach ([
            'bonjour', 'bonsoir', 'salut', 'merci', 'ca va', 'c est quoi', 'qu est ce que',
            'que faire', 'quoi faire', 'je', 'j ai', 'vous', 'peux', 'pouvez', 'droit',
            'loi', 'juridique', 'contrat', 'vente', 'vendeur', 'acheteur', 'prix',
            'delivrance', 'propriete', 'possession', 'heritier', 'succession', 'licenciement',
            'salarie', 'employeur', 'plainte', 'personne', 'utilise', 'manoeuvres',
            'frauduleuses', 'argent', 'texte', 'appliquer', 'quel', 'quelle', 'sans',
            'agrement', 'fonds', 'credits',
        ] as $term) {
            if (preg_match('/\b'.preg_quote($term, '/').'\b/', $normalized)) {
                $frenchScore++;
            }
        }

        foreach ([
            'hello', 'hi', 'thanks', 'thank you', 'what', 'should', 'can', 'could', 'do',
            'if', 'got', 'robbed', 'shot', 'seller', 'buyer', 'sold', 'sale', 'price',
            'ownership', 'heirs', 'registration', 'contract', 'dismissed', 'employer',
            'employee', 'analyze',
        ] as $term) {
            if (preg_match('/\b'.preg_quote($term, '/').'\b/', $normalized)) {
                $englishScore++;
            }
        }

        return $frenchScore > $englishScore ? 'fr' : 'en';
    }

    private function casualAnswer(string $normalized, string $language = 'en'): ?string
    {
        $isFrench = $language === 'fr';
        $isArabic = $language === 'ar';

        return match (true) {
            (bool) preg_match('/^(hi|hello|hey|yo|salam|salaam|salut|bonjour|bonsoir|good morning|good afternoon|good evening)(\s+(there|again|friend))?$/', $normalized) => $isArabic ? 'مرحبا. ما الموضوع القانوني الذي تريد تحليله؟' : ($isFrench ? 'Bonjour. Quel sujet juridique voulez-vous analyser ?' : 'Hi. What are we looking into today?'),
            (bool) preg_match('/^(how are you|how r u|how is it going|how\'s it going|ca va|labas|labass|labas 3lik|are you ok|you good)$/', $normalized) => $isArabic ? 'أنا بخير. أرسل الموضوع أو الوقائع وسأساعدك في تحديد البحث.' : ($isFrench ? 'Je vais bien. Envoyez-moi le sujet ou les faits et je vous aide a cadrer la recherche.' : 'I am good. Send me a topic or situation and I will help you narrow it down.'),
            (bool) preg_match('/^(thanks|thank you|thx|merci|choukran|shukran|ok thanks|okay thanks)$/', $normalized) => $isArabic ? 'بكل سرور.' : ($isFrench ? 'Avec plaisir.' : 'You are welcome.'),
            (bool) preg_match('/^(who are you|what are you|what can you do|help|can you help|can you help me|what do you do|how does this work)$/', $normalized) => $isArabic ? 'أستطيع الدردشة والبحث داخل corpus القانوني المغربي المفهرس. يمكنك أن تسأل بلغة عادية عن العقارات، الكراء التجاري، الفصل من العمل، أو قانون الأسرة.' : ($isFrench ? 'Je peux discuter normalement et rechercher dans le corpus juridique marocain indexe. Vous pouvez poser une question en langage simple, par exemple sur l immobilier, les baux commerciaux, le licenciement ou le droit de la famille.' : 'I can chat normally and help search the indexed Moroccan legal corpus. You can ask in plain language, for example: laws about real estate, commercial leases, labor termination, or family law.'),
            default => null,
        };
    }

    private function outOfScopeAnswer(string $message, string $language): string
    {
        $normalized = $this->normalizeChatText($message);

        if (preg_match('/^(ok|okay|cool|fine|great|nice|yes|no|sure|alright|perfect)$/', $normalized)) {
            return match ($language) {
                'fr' => 'Compris. Donnez-moi le sujet juridique ou la situation quand vous etes pret.',
                'ar' => 'مفهوم. أرسل الموضوع القانوني أو الوقائع عندما تكون مستعدا.',
                default => 'Got it. Tell me the legal topic or situation when you are ready.',
            };
        }

        if (preg_match('/\b(weather|forecast|temperature|rain)\b/', $normalized)) {
            return match ($language) {
                'fr' => 'Je ne suis pas connecte a la meteo en direct ici. Je suis surtout utile pour la recherche juridique marocaine a partir des sources indexees disponibles.',
                'ar' => 'لست متصلا بحالة الطقس المباشرة هنا. دوري الأساسي هو المساعدة في البحث القانوني المغربي اعتمادا على المصادر المفهرسة المتاحة.',
                default => 'I am not connected to live weather here. I am best at Moroccan legal research from available indexed sources.',
            };
        }

        return $language === 'fr'
            ? 'Je vous suis, mais il me faut un sujet juridique, un article, un code, une source ou une situation concrete avant de chercher dans le corpus indexe.'
            : 'I can follow you, but I need a legal topic, article, code, source, or real-world situation before searching the indexed corpus.';
    }

    private function factOnlyAnswer(string $message): ?string
    {
        $normalized = $this->normalizeChatText($message);

        if (!preg_match('/\b(list|liste|identify|extraire|extract)\b.*\b(facts|faits)\b/', $normalized)
            || !preg_match('/\b(no articles|no article|no laws|no law|without citing|sans citer|ne cite aucun|do not cite|dont cite)\b/', $normalized)) {
            return null;
        }

        $facts = [];
        if (preg_match('/\b(accuse|accusation|alleges|licencie|dismissed|fired|terminated)\b/', $normalized)) {
            $facts[] = "L'entreprise accuse le salarie d'un fait fautif.";
        }
        if (preg_match('/\b(vol|vole|theft|stolen)\b/', $normalized)) {
            $facts[] = "L'accusation porte sur un vol.";
        }
        if (preg_match('/\b(ordinateur|laptop|portable)\b/', $normalized)) {
            $facts[] = "L'objet mentionne est un ordinateur portable.";
        }
        if (preg_match('/\b\d+(?:[.,]\d+)?\s*(ans|annees|years)\b/', $normalized)) {
            $facts[] = "L'anciennete du salarie est mentionnee dans la question.";
        }
        if (preg_match('/\b(no disciplinary|aucun antecedent|pas d antecedent|aucun avertissement)\b/', $normalized)) {
            $facts[] = "Aucun antecedent disciplinaire n'est mentionne.";
        }

        return $facts ? collect($facts)->map(fn (string $fact, int $index) => ($index + 1).'. '.$fact)->implode("\n") : null;
    }

    private function extractKeywordQuery(string $normalized): string
    {
        $cleaned = preg_replace('/\b(i want|i need|can you|could you|please|show me|find me|give me|tell me about|search for)\b/', ' ', $normalized) ?? $normalized;
        $cleaned = preg_replace('/\b(laws?|legal|legislation|articles?|codes?|related to|about|regarding|on)\b/', ' ', $cleaned) ?? $cleaned;

        return collect(preg_split('/\s+/', $cleaned) ?: [])
            ->map(fn (string $word) => trim($word))
            ->filter(fn (string $word) => Str::length($word) > 2 && !in_array($word, self::FILLER_WORDS, true))
            ->unique()
            ->take(5)
            ->implode(' ');
    }

    private function extractReferenceQuery(string $normalized): string
    {
        preg_match('/\b(?:loi|dahir|decret)\s*(?:n|no|num|numero)?\s*\d{1,3}[-\/]\d{2,4}\b/', $normalized, $match);

        return $match[0] ?? '';
    }

    private function extractArticleQuery(string $normalized): string
    {
        preg_match('/\b(?:article|art)\s*\d+[a-z]?\b/', $normalized, $match);

        return $match[0] ?? '';
    }

    private function extractReferenceHints(string $query): array
    {
        preg_match_all('/\b\d{1,3}\s*[-\/]\s*\d{2,4}\b/', $this->normalizeReferenceText($query), $matches);

        return collect($matches[0] ?? [])
            ->map(fn (string $match) => preg_replace('/\s*[-\/]\s*/', '-', $match))
            ->filter()
            ->values()
            ->all();
    }

    private function chatResultRank(array $law): float
    {
        return (float) ($law['document_match_score'] ?? 0) * 4
            + (float) ($law['article_match_score'] ?? 0) * 3
            + (float) ($law['source_authority_score'] ?? 0) * 2
            + (float) ($law['relevance_score'] ?? 0)
            - (float) ($law['matchedQueryIndex'] ?? 0) * 120;
    }

    private function sourceAuthorityLevel(array $law): string
    {
        if (($law['is_legacy'] ?? false) || ($law['source_table'] ?? null) === 'legacy_laws') {
            return 'legacy';
        }

        $score = (float) ($law['source_authority_score'] ?? 0);
        $sourceType = $this->normalizeRelevanceText($law['source_type'] ?? '');
        $versionStatus = $this->normalizeRelevanceText($law['version_status'] ?? '');
        $documentStatus = $this->normalizeRelevanceText($law['document_status'] ?? '');

        if ($score >= 500 && $versionStatus === 'active' && $documentStatus === 'active') {
            return in_array($sourceType, ['code', 'dahir', 'loi', 'decret', 'arrete', 'bo', 'official', 'official bulletin'], true)
                ? 'official_current'
                : 'current_corpus';
        }

        if ($score >= 300) {
            return 'current_corpus';
        }

        return 'weak_or_unverified';
    }

    private function sourceAuthoritySignals(array $law): array
    {
        $signals = [];

        if (($law['source_table'] ?? null) === 'corpus') {
            $signals[] = 'versioned_corpus';
        }

        if (($law['version_status'] ?? null) === 'active') {
            $signals[] = 'active_version';
        }

        if (($law['document_status'] ?? null) === 'active') {
            $signals[] = 'active_document';
        }

        if (($law['article_status'] ?? null) === 'active') {
            $signals[] = 'active_article';
        }

        $sourceType = $this->normalizeRelevanceText($law['source_type'] ?? '');
        if (in_array($sourceType, ['code', 'dahir', 'loi', 'decret', 'arrete', 'bo', 'official', 'official bulletin'], true)) {
            $signals[] = 'official_source_type';
        }

        if (($law['is_legacy'] ?? false) || ($law['source_table'] ?? null) === 'legacy_laws') {
            $signals[] = 'legacy_source';
        }

        return array_values(array_unique($signals));
    }

    private function sourceAuthorityBonus(array $law): float
    {
        return match ($this->sourceAuthorityLevel($law)) {
            'official_current' => 2.2,
            'current_corpus' => 1.2,
            'legacy' => -2.5,
            default => -0.8,
        };
    }

    private function allowedSourceSignals(array $plan, array $law): array
    {
        $allowedDocumentTitles = collect($plan['aiPlan']['allowedDocumentTitles'] ?? [])
            ->map(fn (string $title): string => $this->normalizeRelevanceText($title))
            ->filter()
            ->unique()
            ->values();
        $allowedCategories = collect($plan['aiPlan']['allowedCategories'] ?? [])
            ->map(fn (string $category): string => $this->normalizeRelevanceText($category))
            ->filter()
            ->unique()
            ->values();

        if ($allowedDocumentTitles->isEmpty() && $allowedCategories->isEmpty()) {
            return [
                'hasSourceGate' => false,
                'sourceMatches' => true,
            ];
        }

        $normalizedDocument = $this->normalizeRelevanceText($law['document_title'] ?? '');
        $normalizedCategory = $this->normalizeRelevanceText($law['category'] ?? '');
        $documentMatches = $allowedDocumentTitles->contains(
            fn (string $title): bool => $normalizedDocument === $title
                || str_contains($normalizedDocument, $title)
                || ($normalizedDocument !== '' && str_contains($title, $normalizedDocument))
        );
        $categoryMatches = $allowedCategories->contains(fn (string $category): bool => $normalizedCategory === $category);
        $canUseCategoryFallback = $allowedDocumentTitles->isEmpty()
            || (bool) ($law['is_legacy'] ?? false)
            || $normalizedDocument === '';

        return [
            'hasSourceGate' => true,
            'sourceMatches' => $documentMatches || ($canUseCategoryFallback && $categoryMatches),
            'documentMatches' => $documentMatches,
            'categoryMatches' => $categoryMatches,
        ];
    }

    private function scopedRelevanceSignals(string $query, array $law): array
    {
        $articleHint = $this->articleScopeHint($query);
        $documentHints = $this->documentScopeHints($query);
        $referenceHints = $this->extractReferenceHints($query);
        $normalizedArticle = $this->normalizeSearchText($law['article_number'] ?? '');
        $normalizedDocument = $this->normalizeSearchText($law['document_title'] ?? '');
        $normalizedReference = $this->normalizeReferenceText($law['law_reference'] ?? '');
        $articleMatches = !$articleHint || $normalizedArticle === $articleHint;
        $documentMatches = !$documentHints || collect($documentHints)->contains(
            fn (string $hint): bool => $this->normalizeSearchText($hint) === $normalizedDocument
        );
        $referenceMatches = !$referenceHints || collect($referenceHints)->contains(
            fn (string $hint): bool => str_contains($normalizedReference, $hint)
        );

        return [
            'articleHint' => $articleHint,
            'documentHints' => $documentHints,
            'referenceHints' => $referenceHints,
            'articleMatches' => $articleMatches,
            'documentMatches' => $documentMatches,
            'referenceMatches' => $referenceMatches,
            'rejectedByScope' => !$articleMatches || !$documentMatches || !$referenceMatches,
        ];
    }

    private function articleScopeHint(string $query): string
    {
        preg_match('/\b(?:article|art)\s*(premier|\d+(?:\s*(?:bis|ter|quater))?)\b/', $this->normalizeSearchText($query), $match);

        if (!$match) {
            return '';
        }

        return $match[1] === 'premier' ? 'article 1' : 'article '.preg_replace('/\s+/', ' ', $match[1]);
    }

    private function documentScopeHints(string $query): array
    {
        $normalized = $this->normalizeSearchText($query);
        $hints = [
            ['title' => 'Code penal', 'aliases' => ['code penal', 'penal code']],
            ['title' => 'Code de commerce', 'aliases' => ['code de commerce', 'commercial code']],
            ['title' => 'Code du travail', 'aliases' => ['code du travail', 'labor code', 'labour code']],
            ['title' => 'Code de la famille', 'aliases' => ['code de la famille', 'family code']],
            ['title' => 'Code de procedure civile', 'aliases' => ['code de procedure civile', 'civil procedure code']],
            ['title' => 'Code des Obligations et des Contrats', 'aliases' => ['code des obligations et des contrats', 'obligations et contrats', 'obligations and contracts']],
            ['title' => 'Recouvrement des loyers', 'aliases' => ['recouvrement des loyers', 'recouvrement loyers']],
            ['title' => 'Immatriculation fonciere', 'aliases' => ['immatriculation fonciere', 'land registration']],
            ['title' => 'Statut de la copropriete des immeubles batis', 'aliases' => ['statut de la copropriete', 'copropriete des immeubles batis']],
            ['title' => 'Societe en nom collectif et SARL', 'aliases' => ['societe en nom collectif et sarl', 'sarl']],
            ['title' => 'Etablissements de credit et organismes assimiles', 'aliases' => ['etablissements de credit', 'organismes assimiles', 'credit institution']],
            ['title' => 'Code de recouvrement des creances publiques', 'aliases' => ['code de recouvrement des creances publiques', 'recouvrement des creances publiques']],
            ['title' => 'Application de la taxe sur la valeur ajoutee', 'aliases' => ['application de la taxe sur la valeur ajoutee', 'taxe sur la valeur ajoutee', 'tva']],
            ['title' => 'Simplification des procedures et des formalites administratives', 'aliases' => ['simplification des procedures', 'formalites administratives', 'acte administratif']],
        ];

        return collect($hints)
            ->filter(fn (array $hint): bool => collect($hint['aliases'])
                ->contains(fn (string $alias): bool => str_contains($normalized, $this->normalizeSearchText($alias))))
            ->pluck('title')
            ->all();
    }

    private function previousLegalQuestion(array $history): string
    {
        $messages = array_slice($history, -8);

        for ($index = count($messages) - 1; $index >= 0; $index--) {
            $message = $messages[$index] ?? [];
            $text = trim((string) ($message['text'] ?? ''));

            $normalized = $this->normalizeChatText($text);

            if (($message['role'] ?? 'user') === 'user' && ($this->findTopic($normalized) || $this->hasLegalSignal($normalized))) {
                return $text;
            }
        }

        return '';
    }

    private function isFollowUp(string $normalized): bool
    {
        return (bool) preg_match('/^(more|show more|continue|go on|next|again|more results|show other results)$|^(what about|and|also|for|same for)\b|\b(show|give|get|find)\s+(me\s+)?(more|other|another)\b/', $normalized);
    }

    private function findTopic(string $normalized): ?array
    {
        foreach (self::TOPIC_PROFILES as $profile) {
            foreach ($profile['aliases'] as $alias) {
                if ($this->hasAlias($normalized, $alias)) {
                    return $profile;
                }
            }
        }

        return null;
    }

    private function hasAlias(string $normalizedMessage, string $alias): bool
    {
        $normalizedAlias = $this->normalizeChatText($alias);

        if ($normalizedAlias === '') {
            return false;
        }

        if (str_contains($normalizedAlias, ' ')) {
            return str_contains($normalizedMessage, $normalizedAlias);
        }

        return (bool) preg_match('/\b'.preg_quote($normalizedAlias, '/').'\b/', $normalizedMessage);
    }

    private function shouldIncludeChatOnlySources(array $plan): bool
    {
        return ($plan['topic']['key'] ?? null) === 'official-bulletin'
            || preg_match('/\b(official bulletin|bulletin officiel|latest laws|new laws|recent laws|legal updates|dernieres lois|nouvelles lois)\b/', $plan['normalizedMessage']);
    }

    private function relevanceTokens(string $value): array
    {
        return collect(preg_split('/\s+/', $this->normalizeRelevanceText($value)) ?: [])
            ->filter(fn (string $token) => Str::length($token) >= 3 && !is_numeric($token) && !in_array($token, self::FILLER_WORDS, true))
            ->unique()
            ->values()
            ->all();
    }

    private function normalizeChatText(?string $value): string
    {
        $value = (string) ($value ?? '');

        return Str::of($value.' '.$this->arabicLegalSignals($value))
            ->lower()
            ->ascii()
            ->replaceMatches('/[\?!\.,;:\(\)\[\]\{\}"“”]+/u', ' ')
            ->replaceMatches('/\s+/', ' ')
            ->trim()
            ->toString();
    }

    private function normalizePlainChatText(?string $value): string
    {
        return Str::of((string) ($value ?? ''))
            ->lower()
            ->ascii()
            ->replaceMatches('/[\?!\.,;:\(\)\[\]\{\}"â€œâ€]+/u', ' ')
            ->replaceMatches('/\s+/', ' ')
            ->trim()
            ->toString();
    }

    private function normalizeSearchText(?string $value): string
    {
        $value = (string) ($value ?? '');

        return Str::of($value.' '.$this->arabicLegalSignals($value))
            ->lower()
            ->ascii()
            ->replaceMatches('/[-_]+/', ' ')
            ->replaceMatches('/\s+/', ' ')
            ->trim()
            ->toString();
    }

    private function arabicLegalSignals(string $value): string
    {
        if (preg_match('/\p{Arabic}/u', $value) !== 1) {
            return '';
        }

        $arabic = $this->normalizeArabicText($value);
        $signals = [];
        $add = function (array $terms) use (&$signals): void {
            array_push($signals, ...$terms);
        };

        if ($this->containsArabicAny($arabic, ['ذات المسؤولية المحدودة', 'مسؤولية محدودة', 'الشخصية المعنوية', 'شخصية معنوية'])
            || ($this->containsArabicAny($arabic, ['شركة']) && $this->containsArabicAny($arabic, ['تجارية بشكلها', 'بشكلها', 'معنوية']))) {
            $add(['sarl commercial personality article 2 societe en nom collectif et sarl']);
        }

        if ($this->containsArabicAny($arabic, ['مؤسسة ائتمان', 'مؤسسات الائتمان', 'اعتماد مسبق', 'اعتماد'])
            && $this->containsArabicAny($arabic, ['ائتمان', 'بنك', 'بنكي', 'نشاط'])) {
            $add(['banking credit institution approval article 34 etablissements de credit organismes assimiles agrement']);
        }

        if ($this->containsArabicAny($arabic, ['الضريبة على القيمة المضافة', 'ضريبة القيمة المضافة', 'القيمة المضافة', 'استرجاع الضريبة', 'استرداد الضريبة'])
            && $this->containsArabicAny($arabic, ['استرجاع', 'استرداد', 'استردادها', 'استرجاعها', 'طلب'])) {
            $add(['vat refund request article 25 application de la taxe sur la valeur ajoutee']);
        }

        if ($this->containsArabicAny($arabic, ['تسليم', 'التسليم', 'حيازة المبيع', 'حيازة', 'المبيع'])
            && $this->containsArabicAny($arabic, ['البائع', 'المشتري', 'عقد البيع', 'البيع', 'قبض الثمن', 'الثمن'])) {
            $add(['sale delivery legal definition article 499 code des obligations et des contrats delivrance possession']);
        }

        if ($this->containsArabicAny($arabic, ['باع', 'بيع', 'البائع', 'مشتري', 'مشترين', 'لشخصين', 'شخصين مختلفين'])
            && $this->containsArabicAny($arabic, ['عقار', 'غير محفظ', 'ملكية', 'الاولويه', 'الأولوية', 'اولوية', 'له الاولويه'])) {
            $add([
                'ar real estate double sale priority',
                'double vente immobiliere non immatriculee',
                'article 488 code des obligations et des contrats vente consentement chose prix',
                'article 491 code des obligations et des contrats propriete chose vendue',
            ]);
        }

        if ($this->containsArabicAny($arabic, ['مختصة ترابيا', 'اختصاص ترابي', 'الاختصاص الترابي', 'موطن المدعى عليه', 'مدعى عليه', 'مقاضاة'])
            && $this->containsArabicAny($arabic, ['محكمة', 'موطن', 'ترابيا', 'مقاضاة'])) {
            $add(['civil procedure territorial jurisdiction article 27 code de procedure civile domicile defendeur']);
        }

        if (preg_match('/حضان|الأم|الام|تزوج|طلاق|الطلاق/u', $value)) {
            $add(['family custody mother remarried divorce code de la famille']);
        }

        if (preg_match('/نفقة|النفق/u', $value)) {
            $add(['family child support alimony pension alimentaire code de la famille']);
        }

        if (preg_match('/تحفيظ|تعرض|التحديد|الرسم العقاري|مطلب التحفيظ|إعلان انتهاء التحديد|اعلان انتهاء التحديد/u', $value)) {
            $add(['real estate immatriculation fonciere opposition bornage']);
        }

        if (preg_match('/مشغل|أجير|اجير|طرد|فصل من العمل|فصله من العمل|الشغل|مبرر|سبب مشروع/u', $value)) {
            $add(['labor employment dismissal licenciement motif valable code du travail']);
        }

        if (preg_match('/استماع|الاستماع|مسطرة تأديبية|مسطره تاديبيه|إجراء تأديبي|اجراء تاديبي|تأديب|تاديب|حق الدفاع/u', $value)) {
            $add(['labor disciplinary hearing procedure disciplinaire audition code du travail']);
        }

        if (preg_match('/شركة|الشركة|السجل التجاري|نشاط تجاري|تجاري/u', $value)) {
            $add(['commercial company registre de commerce code de commerce']);
        }

        if (preg_match('/دين|قرض|سلف|هبة|واتساب|تحويل|حوالة|إثبات|اثبات|100000|100\.000/u', $value)) {
            $add(['civil debt loan proof bank transfer whatsapp code des obligations et des contrats']);
        }

        if (preg_match('/استئناف|حكم|المحكمة الابتدائية|ابتدائية/u', $value)) {
            $add(['civil procedure appeal deadline code de procedure civile']);
        }

        if (preg_match('/سرقة|السرقة|مال الغير|مال غيره|أخذ مال|اخذ مال|اختلاس|مملوك للغير|سوء نية/u', $value)) {
            $add(['criminal theft code penal']);
        } elseif (preg_match('/احتيالية|احتيال|نصب/u', $value)) {
            $add(['criminal fraud code penal']);
        }

        if (preg_match('/السر المهني|سرية|زبناء|الزبناء|كتمان|معلومات الزبون|معطيات زبون|معطيات الزبون/u', $value)) {
            $add(['banking finance secret professionnel etablissements de credit']);
        }

        if (preg_match('/تحصيل|جبري|الديون العمومية|ديون عمومية|دين عمومي|استخلاص|الجبائي|جباية|ضرائب/u', $value)) {
            $add(['tax recouvrement creances publiques commandement saisie vente']);
        }

        if (preg_match('/قرار إداري|قرار اداري|إداري|اداري|الإدارة|الادارة|وصل|وصلا|إيداع|ايداع|تسلم|تسلمني/u', $value)) {
            $add(['administrative act request receipt simplification procedures formalites administratives']);
        }

        return implode(' ', array_unique($signals));
    }

    private function containsArabicAny(string $text, array $terms): bool
    {
        foreach ($terms as $term) {
            if ($term !== '' && str_contains($text, $this->normalizeArabicText($term))) {
                return true;
            }
        }

        return false;
    }

    private function normalizeArabicText(string $value): string
    {
        $value = str_replace(
            ['أ', 'إ', 'آ', 'ٱ', 'ى', 'ة', 'ؤ', 'ئ'],
            ['ا', 'ا', 'ا', 'ا', 'ي', 'ه', 'و', 'ي'],
            $value
        );
        $value = preg_replace('/[\x{064B}-\x{065F}\x{0670}]/u', '', $value) ?? $value;
        $value = preg_replace('/[^\p{Arabic}\p{N}\s]+/u', ' ', $value) ?? $value;
        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;

        return trim($value);
    }

    private function normalizeReferenceText(?string $value): string
    {
        return Str::of($value ?? '')
            ->lower()
            ->ascii()
            ->replaceMatches('/[°º]/u', '')
            ->replaceMatches('/\s*[-\/]\s*/', '-')
            ->replaceMatches('/\s+/', ' ')
            ->trim()
            ->toString();
    }

    private function normalizeRelevanceText(?string $value): string
    {
        return Str::of($this->normalizeSearchText($value))
            ->replaceMatches('/[^a-z0-9\s]/', ' ')
            ->replaceMatches('/\s+/', ' ')
            ->trim()
            ->toString();
    }
}
