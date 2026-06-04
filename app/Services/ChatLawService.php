<?php

namespace App\Services;

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
            'aliases' => ['real estate', 'property', 'land', 'rent', 'rental', 'lease', 'tenant', 'landlord', 'immobilier', 'propriete', 'foncier', 'bail', 'loyer', 'location', 'locataire', 'proprietaire', 'terrain', 'appartement', 'maison', 'copropriete', 'titre foncier'],
            'queries' => ['immobilier', 'bail', 'propriete fonciere', 'copropriete', 'urbanisme'],
        ],
        [
            'key' => 'business',
            'label' => 'business and companies',
            'aliases' => ['business', 'company', 'companies', 'corporate', 'commerce', 'commercial', 'societe', 'societes', 'entreprise', 'sarl', 'sa', 'actionnaire', 'registre de commerce'],
            'queries' => ['societe', 'commerce', 'registre de commerce'],
        ],
        [
            'key' => 'labor',
            'label' => 'labor and employment',
            'aliases' => ['work', 'worker', 'employee', 'employer', 'employment', 'labor', 'labour', 'salary', 'termination', 'travail', 'salarie', 'employeur', 'contrat de travail', 'licenciement', 'salaire'],
            'queries' => ['travail', 'contrat de travail', 'licenciement'],
        ],
        [
            'key' => 'family',
            'label' => 'family law',
            'aliases' => ['family', 'marriage', 'divorce', 'custody', 'inheritance', 'succession', 'famille', 'mariage', 'divorce', 'garde', 'heritage', 'pension'],
            'queries' => ['famille', 'mariage', 'divorce', 'succession'],
        ],
        [
            'key' => 'tax',
            'label' => 'tax',
            'aliases' => ['tax', 'taxes', 'fiscal', 'vat', 'impot', 'impots', 'fiscalite', 'tva', 'taxe'],
            'queries' => ['fiscalite', 'impot', 'tva'],
        ],
        [
            'key' => 'banking',
            'label' => 'banking and finance',
            'aliases' => ['bank', 'banking', 'finance', 'credit', 'loan', 'banque', 'bancaire', 'pret'],
            'queries' => ['banque', 'credit', 'bancaire'],
        ],
        [
            'key' => 'contracts',
            'label' => 'contracts',
            'aliases' => ['contract', 'contracts', 'agreement', 'obligation', 'contrat', 'contrats'],
            'queries' => ['contrat', 'obligation'],
        ],
        [
            'key' => 'criminal',
            'label' => 'criminal law',
            'aliases' => ['criminal', 'crime', 'penal', 'prison', 'offence', 'infraction', 'criminel'],
            'queries' => ['penal', 'infraction'],
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

        if ($normalized === '') {
            return self::INTENT_UNSUPPORTED;
        }

        if ($this->casualAnswer($normalized)) {
            return self::INTENT_GREETING;
        }

        if ($this->isArticleLookup($normalized)) {
            return self::INTENT_ARTICLE_LOOKUP;
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
            $answer = $this->casualAnswer($normalized, $language) ?? ($language === 'fr' ? 'Bonjour. Quel sujet juridique voulez-vous analyser ?' : 'Hi. What are we looking into today?');

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

        if ($rawResults && !$citations) {
            $answer = $this->insufficientSourcesAnswer($question, $plan, $rawResults, $language);

            return ['intent' => $intent, 'answer' => $answer, 'citations' => [], 'fallbackAnswer' => $answer, 'shouldReason' => false, 'responseLanguage' => $language];
        }

        return [
            'intent' => $intent,
            'answer' => null,
            'citations' => $citations,
            'fallbackAnswer' => $this->chatFallbackAnswer($question, $citations, $plan, $language),
            'shouldReason' => true,
            'plan' => $plan,
            'responseLanguage' => $language,
        ];
    }

    private function prepareDefinitionAnswer(string $question, array $history, string $language): array
    {
        $concept = $this->extractDefinitionConcept($question);
        $queries = $this->definitionQueries($question, $concept);
        $plan = $this->basicPlan($question, $queries, null);
        $plan['responseLanguage'] = $language;
        $rawResults = $this->searchForChat($plan, 8);
        $citations = $this->formatCitations($this->filterByRelevance($question, $plan, $rawResults));
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
        ];
    }

    private function preparePracticalAdviceAnswer(string $question, array $history, string $language): array
    {
        $normalized = $this->normalizeChatText($question);
        $queries = $this->practicalAdviceQueries($question);
        $plan = $this->basicPlan($question, $queries, null);
        $plan['responseLanguage'] = $language;
        $rawResults = $this->searchForChat($plan, 8);
        $citations = $this->formatCitations($this->filterByRelevance($question, $plan, $rawResults));
        $steps = $this->practicalSteps($normalized, $language);
        $answer = ($language === 'fr' ? "D'abord, les demarches pratiques:\n" : "First, practical steps:\n").collect($steps)
            ->map(fn (string $step, int $index): string => ($index + 1).'. '.$step)
            ->implode("\n");

        if ($citations) {
            $source = $this->citationSourceLabel($citations[0]);
            $excerpt = Str::limit($this->cleanExcerpt($citations[0]['content'] ?? ''), 300, '');
            $answer .= $language === 'fr'
                ? "\n\nSource juridique marocaine trouvee: {$source}. {$excerpt} [1]"
                : "\n\nRelevant Moroccan law found: {$source}. {$excerpt} [1]";
        } else {
            $answer .= $language === 'fr'
                ? "\n\nJe n'ai pas encore trouve de citation solide dans le corpus indexe pour cette question pratique exacte, donc je n'ajoute pas de numero d'article."
                : "\n\nI did not find a strong citation in the indexed corpus for this exact practical question yet, so I am not adding an article number.";
        }

        return [
            'intent' => self::INTENT_PRACTICAL,
            'answer' => $answer,
            'citations' => $citations,
            'fallbackAnswer' => $answer,
            'shouldReason' => false,
            'plan' => $plan,
            'responseLanguage' => $language,
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
        $citations = $this->formatCitations($this->filterByRelevance($question, $plan, $rawResults));

        if (!$citations) {
            $answer = $language === 'fr'
                ? 'Sources insuffisantes: je n ai pas trouve cet article exact dans le corpus juridique marocain indexe. Ajoutez le nom du code ou la reference de la loi si vous l avez.'
                : 'Sources insuffisantes: I could not find the exact article in the indexed Moroccan legal corpus. Please include the code name or law reference if you have it.';

            return [
                'intent' => self::INTENT_ARTICLE_LOOKUP,
                'answer' => $answer,
                'citations' => [],
                'fallbackAnswer' => $answer,
                'shouldReason' => false,
                'plan' => $plan,
                'responseLanguage' => $language,
            ];
        }

        $citation = $citations[0];
        $source = $this->citationSourceLabel($citation);
        $excerpt = $this->cleanExcerpt($citation['content'] ?? '');
        $answer = $language === 'fr'
            ? "J'ai trouve {$source}.\n\nExtrait exact: ".Str::limit($excerpt, 700, '')." [1]\n\nEn termes simples: cet article est la regle pertinente pour la recherche precise que vous avez demandee. Utilisez la carte de citation ci-dessous pour ouvrir la source et verifier le texte officiel complet."
            : "I found {$source}.\n\nExact excerpt: ".Str::limit($excerpt, 700, '')." [1]\n\nIn simple terms: this article is the relevant rule for the specific article lookup you asked for. Use the citation card below to open the source and verify the full official wording.";

        return [
            'intent' => self::INTENT_ARTICLE_LOOKUP,
            'answer' => $answer,
            'citations' => $citations,
            'fallbackAnswer' => $answer,
            'shouldReason' => false,
            'plan' => $plan,
            'responseLanguage' => $language,
        ];
    }

    private function buildPlan(string $question, array $history, ?array $aiPlan = null): array
    {
        $normalized = $this->normalizeChatText($question);
        $previousQuestion = $this->previousLegalQuestion($history);
        $useContext = $previousQuestion && $this->isFollowUp($normalized) && !$this->findTopic($normalized) && !$this->extractReferenceQuery($normalized);
        $planningQuestion = $useContext ? "{$previousQuestion} {$question}" : $question;
        $normalizedPlanningQuestion = $this->normalizeChatText($planningQuestion);
        $topic = $this->findTopic($normalizedPlanningQuestion);
        $referenceQuery = $this->extractReferenceQuery($normalizedPlanningQuestion);
        $articleQuery = $this->extractArticleQuery($normalizedPlanningQuestion);
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
            'documentTitle' => $law['document_title'],
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
            'matchedQuery' => $law['matchedQuery'] ?? null,
        ])->all();
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

        if (preg_match('/\b(bail|lease|rent|tenant|landlord|locataire|bailleur)\b/', $normalized)) {
            return ['bail locataire bailleur', 'loyer bail', 'recouvrement des loyers'];
        }

        if (preg_match('/\b(societe|company|companies|commercial|commerce)\b/', $normalized)) {
            return ['societe commerce', 'registre de commerce', 'code de commerce'];
        }

        return array_values(array_filter([$concept, $this->extractKeywordQuery($normalized)]));
    }

    private function simpleDefinition(string $concept, string $question, string $language): string
    {
        $normalized = $this->normalizeChatText($question.' '.$concept);

        if (preg_match('/\b(licenciement|dismissal|termination)\b/', $normalized)) {
            return $language === 'fr'
                ? 'Le licenciement signifie que l employeur met fin au contrat de travail. En pratique, les questions principales sont le motif valable, le respect de la procedure et les recours possibles.'
                : 'Licenciement means an employer ends an employment contract. In simple terms, the key questions are usually whether there is a valid reason, whether the required procedure was followed, and what remedy may apply.';
        }

        if (preg_match('/\b(vol|theft|robbery|robbed|stolen)\b/', $normalized)) {
            return $language === 'fr'
                ? 'Le vol signifie prendre le bien d autrui sans droit. En pratique, on regarde l acte de soustraction, le bien vise et les circonstances comme la force, la fraude ou l effraction.'
                : 'Vol/theft means taking someone else\'s property without the right to do so. In simple terms, the law looks at the act of taking, the property involved, and the circumstances such as force, fraud, or breaking in.';
        }

        if (preg_match('/\b(bail|lease|rent|tenant|landlord|locataire|bailleur)\b/', $normalized)) {
            return $language === 'fr'
                ? 'Un bail est un accord par lequel une personne laisse une autre utiliser un bien pendant une periode, en general contre un loyer. Les questions pratiques sont le loyer, la duree, les obligations, la resiliation et l action judiciaire possible.'
                : 'A lease/bail is an agreement where one person lets another use property for a period, usually in exchange for rent. The practical questions are rent, duration, obligations, termination, and possible court action.';
        }

        if (preg_match('/\b(societe|company|companies)\b/', $normalized)) {
            return $language === 'fr'
                ? 'Une societe est une structure juridique utilisee pour exercer une activite separement des personnes qui la composent. Les regles concernent souvent la creation, l immatriculation, la gestion, les associes ou actionnaires et la responsabilite.'
                : 'A company/societe is a legal structure used to run a business separately from the people behind it. The rules usually concern formation, registration, management, partners or shareholders, and liability.';
        }

        return $language === 'fr'
            ? "En termes simples, {$concept} est une notion ou un sujet juridique. La reponse exacte depend du code, du contrat, des faits et du domaine juridique concerne."
            : "In simple terms, {$concept} is a legal concept or topic. The exact answer depends on the code, contract, facts, and legal area involved.";
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

        return array_values(array_filter([$this->extractKeywordQuery($normalized), $question]));
    }

    private function practicalSteps(string $normalized, string $language): array
    {
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

        return $language === 'fr'
            ? 'Pouvez-vous preciser le sujet juridique ou les faits essentiels a analyser ?'
            : 'Can you clarify the legal topic or the key facts you want me to analyze?';
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
        $retrievalQueries = $this->expandedChatQueries($plan);
        $shouldCollectAllQueries = !empty($plan['aiPlan']['searchQueries'])
            || count($retrievalQueries) > count($plan['queries'] ?? []);
        $perQueryLimit = $shouldCollectAllQueries ? max($limit * 2, 12) : $limit;

        foreach ($retrievalQueries as $index => $query) {
            $payload = $this->laws->search($query, $perQueryLimit, [
                'includeChatOnlySources' => $this->shouldIncludeChatOnlySources($plan),
                'useCorpus' => true,
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
            ->concat($conceptTerms)
            ->concat($documentHints);

        foreach ($documentHints as $document) {
            foreach ($conceptTerms->take(8) as $term) {
                $expanded->push(trim($term.' '.$document));
            }
        }

        return collect($this->filterArticleQueriesByUserInput($expanded->all(), $planningQuestion))
            ->map(fn (string $query): string => trim($query))
            ->filter()
            ->unique(fn (string $query): string => $this->normalizeSearchText($query))
            ->take(22)
            ->values()
            ->all();
    }

    private function filterByRelevance(string $question, array $plan, array $results): array
    {
        $filtered = collect($results)
            ->map(function (array $law) use ($question, $plan): array {
                $signals = $this->scoreRelevance($question, $plan, $law);

                return array_merge($law, [
                    'chatRelevanceScore' => round($signals['score'], 2),
                    'rejectedByScope' => $signals['rejectedByScope'],
                    'rejectedBySource' => $signals['rejectedBySource'],
                ]);
            })
            ->filter(fn (array $law) => !$law['rejectedByScope'] && !$law['rejectedBySource'] && $law['chatRelevanceScore'] >= 2.8)
            ->sortByDesc(fn (array $law): float => $law['chatRelevanceScore'] + $this->chatResultRank($law) / 100000)
            ->take(5)
            ->values()
            ->map(function (array $law): array {
                unset($law['rejectedByScope'], $law['rejectedBySource']);

                return $law;
            });

        return $filtered->all();
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

        if ($source['hasSourceGate']) {
            $score += 4;
        }

        if ($scope['articleHint'] && $scope['articleMatches']) {
            $score += 6;
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
        $lawTaxonomy = $classifier->classifyQuery(implode(' ', [
            $law['domain'] ?? '',
            $law['category'] ?? '',
            $law['subdomain'] ?? '',
            $law['document_title'] ?? '',
            is_array($law['tags'] ?? null) ? implode(' ', $law['tags']) : ($law['tags'] ?? ''),
        ]));
        $lawDomain = $this->normalizeRelevanceText($lawTaxonomy['domain'] ?? ($law['domain'] ?? $law['category'] ?? ''));
        $lawSubdomain = $this->normalizeRelevanceText($lawTaxonomy['subdomain'] ?? ($law['subdomain'] ?? ''));

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

        return [
            'score' => $score,
            'rejectedByScope' => false,
            'rejectedBySource' => false,
        ];
    }

    private function chatFallbackAnswer(string $question, array $citations, array $plan, string $language): string
    {
        if (!$citations) {
            $tried = $plan['queries']
                ? ($language === 'fr' ? ' Recherches essayees: ' : ' I tried: ').implode(', ', $plan['queries']).'.'
                : '';

            return $language === 'fr'
                ? "Je n'ai pas trouve d'articles correspondants dans le corpus juridique marocain indexe.{$tried} Essayez un mot-cle juridique plus large, un terme francais, ou une reference precise de loi/article."
                : "I did not find matching articles in the indexed Moroccan legal corpus.{$tried} Try a broader legal keyword, a French term, or a specific law/article reference.";
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
        $tried = $plan['queries']
            ? ($language === 'fr' ? ' Recherches essayees: ' : ' I tried: ').implode(', ', array_slice($plan['queries'], 0, 5)).'.'
            : '';
        $sources = collect($rawResults)
            ->map(fn (array $law) => $law['document_title'] ?? $law['source_name'] ?? $law['title'] ?? null)
            ->filter()
            ->unique()
            ->take(3)
            ->implode(', ');
        $sourceNote = $sources
            ? ($language === 'fr'
                ? " Les resultats bruts les plus proches venaient de {$sources}, mais ils ne semblaient pas suffisamment lies a vos faits."
                : " The closest raw search hits came from {$sources}, but they did not look sufficiently tied to your facts.")
            : '';

        return $language === 'fr'
            ? "Sources insuffisantes: je n'ai pas trouve de sources marocaines suffisamment pertinentes dans le corpus indexe pour {$issue}.{$sourceNote} Je ne dois pas repondre a partir d articles sans lien.{$tried} Essayez un terme juridique francais, un nom de code, un numero de loi ou un article precis si vous en avez un."
            : "Sources insuffisantes: I could not find sufficiently relevant Moroccan law sources in the indexed corpus for {$issue}.{$sourceNote} I should not answer from unrelated articles.{$tried} Try a specific French legal term, code name, law number, or article if you have one.";
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

        if ($this->findTopic($normalized) || $this->hasLegalSignal($normalized)) {
            return true;
        }

        return count(array_filter(explode(' ', $normalized))) > 2
            && preg_match('/\b(morocco|moroccan|maroc|marocain|marocaine)\b/', $normalized);
    }

    private function hasLegalSignal(string $normalized): bool
    {
        return (bool) preg_match('/\b(law|legal|legislation|regulation|article|code|statute|decree|dahir|loi|droit|juridique|decret|arrete|tribunal|court|contract|lease|tenant|landlord|property|real estate|company|tax|labor|employment|family|marriage|divorce|inheritance|criminal|civil|commerce|consumer|bank|insurance|investment|permit|notary|immobilier|bail|locataire|proprietaire|societe|travail|famille|fiscalite|contrat|banque|assurance|mariage|heritage|succession|penal|consommation)\b|\b(loi|dahir|decret)\s*(n|no|num|numero)?\s*\d{1,3}[-\/]\d{2,4}\b|\b(article|art)\s*\d+\b|\b\d{1,3}[-\/]\d{2,4}\b/', $normalized);
    }

    private function detectResponseLanguage(string $message): string
    {
        $raw = Str::lower($message);
        $normalized = $this->normalizeChatText($message);
        $frenchScore = preg_match('/[a-z]*[àâçéèêëîïôùûüÿœ]/iu', $raw) ? 3 : 0;
        $englishScore = 0;

        foreach ([
            'bonjour', 'bonsoir', 'salut', 'merci', 'ca va', 'c est quoi', 'qu est ce que',
            'que faire', 'quoi faire', 'je', 'j ai', 'vous', 'peux', 'pouvez', 'droit',
            'loi', 'juridique', 'contrat', 'vente', 'vendeur', 'acheteur', 'prix',
            'delivrance', 'propriete', 'possession', 'heritier', 'succession', 'licenciement',
            'salarie', 'employeur', 'plainte',
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

        return match (true) {
            (bool) preg_match('/^(hi|hello|hey|yo|salam|salaam|salut|bonjour|bonsoir|good morning|good afternoon|good evening)(\s+(there|again|friend))?$/', $normalized) => $isFrench ? 'Bonjour. Quel sujet juridique voulez-vous analyser ?' : 'Hi. What are we looking into today?',
            (bool) preg_match('/^(how are you|how r u|how is it going|how\'s it going|ca va|labas|labass|labas 3lik|are you ok|you good)$/', $normalized) => $isFrench ? 'Je vais bien. Envoyez-moi le sujet ou les faits et je vous aide a cadrer la recherche.' : 'I am good. Send me a topic or situation and I will help you narrow it down.',
            (bool) preg_match('/^(thanks|thank you|thx|merci|choukran|shukran|ok thanks|okay thanks)$/', $normalized) => $isFrench ? 'Avec plaisir.' : 'You are welcome.',
            (bool) preg_match('/^(who are you|what are you|what can you do|help|can you help|can you help me|what do you do|how does this work)$/', $normalized) => $isFrench ? 'Je peux discuter normalement et rechercher dans le corpus juridique marocain indexe. Vous pouvez poser une question en langage simple, par exemple sur l immobilier, les baux commerciaux, le licenciement ou le droit de la famille.' : 'I can chat normally and help search the indexed Moroccan legal corpus. You can ask in plain language, for example: laws about real estate, commercial leases, labor termination, or family law.',
            default => null,
        };
    }

    private function outOfScopeAnswer(string $message, string $language): string
    {
        $normalized = $this->normalizeChatText($message);

        if (preg_match('/^(ok|okay|cool|fine|great|nice|yes|no|sure|alright|perfect)$/', $normalized)) {
            return $language === 'fr'
                ? 'Compris. Donnez-moi le sujet juridique ou la situation quand vous etes pret.'
                : 'Got it. Tell me the legal topic or situation when you are ready.';
        }

        if (preg_match('/\b(weather|forecast|temperature|rain)\b/', $normalized)) {
            return $language === 'fr'
                ? 'Je ne suis pas connecte a la meteo en direct ici. Je suis surtout utile pour la recherche juridique marocaine a partir des sources indexees disponibles.'
                : 'I am not connected to live weather here. I am best at Moroccan legal research from available indexed sources.';
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
            + (float) ($law['relevance_score'] ?? 0)
            - (float) ($law['matchedQueryIndex'] ?? 0) * 120;
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
        return Str::of($value ?? '')
            ->lower()
            ->ascii()
            ->replaceMatches('/[\?!\.,;:\(\)\[\]\{\}"“”]+/u', ' ')
            ->replaceMatches('/\s+/', ' ')
            ->trim()
            ->toString();
    }

    private function normalizeSearchText(?string $value): string
    {
        return Str::of($value ?? '')
            ->lower()
            ->ascii()
            ->replaceMatches('/[-_]+/', ' ')
            ->replaceMatches('/\s+/', ' ')
            ->trim()
            ->toString();
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
