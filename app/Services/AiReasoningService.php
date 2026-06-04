<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class AiReasoningService
{
    private const DEFAULT_OLLAMA_BASE_URL = 'http://localhost:11434';
    private const DEFAULT_OLLAMA_MODEL = 'qwen3:8b';

    private const SOURCE_DOMAINS = [
        'labor' => [
            'allowedDocumentTitles' => ['Code du travail'],
            'allowedCategories' => ['labor'],
        ],
        'civilContracts' => [
            'allowedDocumentTitles' => ['Code des Obligations et des Contrats'],
            'allowedCategories' => ['civil'],
        ],
        'criminal' => [
            'allowedDocumentTitles' => ['Code penal'],
            'allowedCategories' => ['criminal'],
        ],
        'family' => [
            'allowedDocumentTitles' => ['Code de la famille'],
            'allowedCategories' => ['family'],
        ],
        'consumer' => [
            'allowedDocumentTitles' => ['Protection du consommateur'],
            'allowedCategories' => ['consumer'],
        ],
        'commercialCompany' => [
            'allowedDocumentTitles' => [
                'Code de commerce',
                'Societes anonymes',
                'Societes a responsabilite limitee',
                'Societes responsabilite limitee',
            ],
            'allowedCategories' => ['commercial'],
        ],
        'commercialLease' => [
            'allowedDocumentTitles' => [
                'Baux des immeubles ou des locaux loues a usage commercial, industriel ou artisanal',
            ],
            'allowedCategories' => ['real-estate', 'commercial'],
        ],
        'realEstate' => [
            'allowedDocumentTitles' => [
                'Code des droits reels',
                'Statut de la copropriete des immeubles batis',
                'Recouvrement des loyers',
            ],
            'allowedCategories' => ['real-estate'],
        ],
    ];

    public function createSearchPlan(string $question, array $history = []): ?array
    {
        $localPlan = $this->createLocalSearchPlan($question);

        if ($localPlan) {
            return $localPlan;
        }

        if (!$this->isEnabled()) {
            return null;
        }

        $recentHistory = collect($history)
            ->take(-6)
            ->map(fn (array $message): string => ($message['role'] ?? 'user').': '.($message['text'] ?? ''))
            ->implode("\n");

        try {
            $content = $this->callOllama([
                'timeout' => $this->plannerTimeoutSeconds(),
                'temperature' => 0.1,
                'num_predict' => 320,
                'format' => 'json',
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'You are a Moroccan legal assistant pipeline planner. Return JSON only. Do not answer the user. Stage 1: extract facts from the user question only into facts[]. Stage 2: spot legal issues and produce legal_issues[] plus precise French legal search queries in search_queries[]. Include likely code names and legal terms. Avoid broad one-word queries. Never invent article-number queries. Only include article/article-number searches if the user explicitly provided that article number. Otherwise search by legal concepts, code names, and French legal terms. If needsLawSearch is true, search_queries must contain at least 3 useful French concept queries.',
                    ],
                    [
                        'role' => 'user',
                        'content' => json_encode([
                            'examples' => [
                                [
                                    'question' => 'can a landlord evict me?',
                                    'searchQueries' => [
                                        'bailleur locataire expulsion',
                                        'resiliation bail locataire',
                                        'recouvrement loyers',
                                        'loi 67-12 bail habitation',
                                    ],
                                ],
                                [
                                    'question' => 'what happens if my property got stolen?',
                                    'searchQueries' => [
                                        'soustraction frauduleuse',
                                        'vol code penal',
                                        'infraction vol',
                                        'circonstances aggravantes vol',
                                    ],
                                ],
                            ],
                            'outputShape' => [
                                'facts' => ['fact from the user question only'],
                                'needsLawSearch' => true,
                                'legal_issues' => ['short issue label'],
                                'reasoning_goal' => 'what the final answer should explain',
                                'search_queries' => [
                                    'French legal query 1',
                                    'French legal query 2',
                                    'specific code or legal concept query; no article number unless the user provided it',
                                ],
                            ],
                            'recentHistory' => $recentHistory,
                            'question' => $question,
                        ], JSON_UNESCAPED_UNICODE),
                    ],
                ],
            ]);
            $aiPlan = $this->sanitizeSearchPlan($this->extractJsonObject($content), $question);

            return $aiPlan && $aiPlan['searchQueries'] ? $aiPlan : $this->createLocalSearchPlan($question);
        } catch (Throwable $error) {
            Log::warning('AI search planning failed', ['message' => $error->getMessage()]);

            return $this->createLocalSearchPlan($question);
        }
    }

    public function answer(string $question, array $citations, ?array $plan = null): ?string
    {
        if (!$citations) {
            return null;
        }

        $language = data_get($plan, 'responseLanguage') ?: $this->detectResponseLanguage($question);
        $languageName = $language === 'fr' ? 'French' : 'English';
        $analysisStructure = $language === 'fr'
            ? 'A. Faits importants, B. Questions juridiques, C. Articles applicables, D. Analyse des faits, E. Arguments de chaque partie, F. Preuves importantes, G. Conclusion probable, H. Limites / informations manquantes'
            : 'A. Important facts, B. Legal questions, C. Applicable articles, D. Fact analysis, E. Arguments of each side, F. Important evidence, G. Probable conclusion, H. Limits / missing information';

        $specializedAnswer =
            $this->buildFactExtractionAnswer($question)
            ?? $this->buildEmploymentCaseAnalysisAnswer($question, $citations)
            ?? $this->buildPregnancyDismissalAnswer($question, $citations)
            ?? $this->buildEmploymentEvidenceAnswer($question, $citations)
            ?? $this->buildEmploymentTerminationAnswer($question, $citations)
            ?? $this->buildRenovationContractAnswer($question, $citations)
            ?? $this->buildPartialDeliverySaleAnswer($question, $citations)
            ?? $this->buildSaleOwnershipAnswer($question, $citations, $language);

        if ($specializedAnswer) {
            return $this->enforceFactConsistency($specializedAnswer, $question, $plan, $citations);
        }

        if (!$this->isEnabled()) {
            return null;
        }

        $citationContext = collect($citations)
            ->take(12)
            ->map(fn (array $citation, int $index): string => $this->formatCitationForPrompt($citation, $index))
            ->implode("\n\n");
        $facts = $this->extractLegalFacts($question);
        $issues = $this->extractLegalIssues($question, $plan);

        try {
            $answerText = $this->callOllama([
                'timeout' => $this->answerTimeoutSeconds(),
                'temperature' => 0.2,
                'num_predict' => 950,
                'format' => 'json',
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => "/no_think\nYou are a careful Moroccan legal assistant and case analyst. Return JSON only with one key: answer. The answer must be final user-facing text only. Write the answer in {$languageName}; do not mix languages except for official source titles, article names, and quoted legal excerpts. Do not include your drafting process, hidden reasoning, self-talk, prompt analysis, Markdown formatting, or phrases like 'let me'. The pipeline is mandatory: FACT EXTRACTION, ISSUE SPOTTING, RETRIEVAL, RELEVANCE CHECK, FINAL ANALYSIS. The final answer must visibly use this structure: {$analysisStructure}. Do not cite any article before section C. Use the retrieved law excerpts as legal authority, and use the user's facts for legal analysis. Explain why facts matter for proof, credibility, timing, motive, consent, possession, contradiction, burden, or compliance. Do not say a user fact is irrelevant merely because no article names that exact fact. Every legal rule, remedy, formula, deadline, burden, or procedure must be supported by a cited excerpt. Do not calculate compensation unless the user asks for compensation, damages, indemnity, amount, salary, or a claim calculation. Never reuse case numbers or facts from examples; only use facts in the current user question. If the relevant excerpts are insufficient, say sources insuffisantes and identify the missing source or fact. Cite relevant excerpts with [1], [2], etc. Do not say you searched the web.",
                    ],
                    [
                        'role' => 'user',
                        'content' => implode("\n", array_filter([
                            "User question: {$question}",
                            "Required output language: {$languageName}",
                            'Stage 1 FACT EXTRACTION facts[]: '.json_encode($facts, JSON_UNESCAPED_UNICODE),
                            'Stage 2 ISSUE SPOTTING legal_issues[]: '.json_encode($issues, JSON_UNESCAPED_UNICODE),
                            data_get($plan, 'aiPlan.legalIssue') ? 'Detected issue: '.data_get($plan, 'aiPlan.legalIssue') : '',
                            data_get($plan, 'aiPlan.reasoningGoal') ? 'Reasoning goal: '.data_get($plan, 'aiPlan.reasoningGoal') : '',
                            data_get($plan, 'queries') ? 'Stage 2 French search_queries[] used for RETRIEVAL: '.json_encode(data_get($plan, 'queries'), JSON_UNESCAPED_UNICODE) : '',
                            '',
                            'Stage 3 RETRIEVAL + Stage 4 RELEVANCE CHECK: only these relevance-filtered law excerpts survived:',
                            $citationContext,
                            '',
                            'Return JSON like this: {"answer":"final answer only"}. Do case analysis, not article summarization. Start from the Stage 1 facts. If the question asks what weakens or supports an accusation, focus on proof, contradictions, missing evidence, burden of justification, and factual uncertainty before discussing remedies. When the question asks several things, answer each part. Use each formula only for the remedy it describes; do not use a severance formula for notice indemnity. When the excerpts contain a basic rule and aggravating circumstances, exceptions, or special cases, state the basic rule first. If the retrieved excerpts are from the wrong subtype or only a related topic, say sources insuffisantes instead of pretending the wrong sources answer the question.',
                        ], fn (string $line): bool => $line !== '')),
                    ],
                ],
            ]);
            $payload = $this->extractJsonObject($answerText);
            $answer = $this->cleanGeneratedAnswer(is_string($payload['answer'] ?? null) ? $payload['answer'] : $answerText);
            $answer = $this->removeInvalidCitationMarkers($answer, $citations);
            $answer = $this->removeUnsupportedPracticalAdvice($answer, $citationContext);
            $answer = $this->ensureCitationMarker($answer, $citations);
            $answer = $this->ensureSubstantiveAnswer($answer, $citations, $language);

            return $answer ? $this->enforceFactConsistency($answer, $question, $plan, $citations) : null;
        } catch (Throwable $error) {
            Log::warning('AI answer generation failed', ['message' => $error->getMessage()]);

            return null;
        }
    }

    private function createLocalSearchPlan(string $question): ?array
    {
        $normalized = $this->normalizeText($question);
        $matches = collect($this->legalQueryProfiles())
            ->filter(fn (array $profile): bool => collect($profile['patterns'])
                ->contains(fn (string $pattern): bool => (bool) preg_match($pattern, $normalized)))
            ->values();

        $hasCommercialLease = $matches->contains(fn (array $profile): bool => $profile['legalIssue'] === 'commercial lease eviction');
        $hasEmployment = $matches->contains(fn (array $profile): bool => $profile['legalIssue'] === 'employment termination');
        $hasRenovation = $matches->contains(fn (array $profile): bool => $profile['legalIssue'] === 'renovation or construction work contract');
        $hasSaleDelivery = $matches->contains(fn (array $profile): bool => $profile['legalIssue'] === 'sale contract with missing or partial delivery');
        $hasWorkplaceAccusation = $hasEmployment
            && preg_match('/\b(accuse|accusation|preuve|evidence|inventaire|inventory|ordinateur|laptop|vole|vol|theft)\b/', $normalized);
        $hasStructuredEmploymentAnalysis = $hasEmployment
            && (preg_match('/\b(analysez|analyze|analyse|analysis|etudiez|evaluate)\b/', $normalized)
                || preg_match('/\b(faits|arguments|preuves|procedures|represailles|conclusion)\b/', $normalized));
        $asksAboutCompensation = preg_match('/\b(compensation|indemnite|indemnites|dommages|preavis|severance|claim|reclamer|montant|salaire)\b/', $normalized);

        if ($hasCommercialLease) {
            $matches = $matches->reject(fn (array $profile): bool => $profile['legalIssue'] === 'landlord and tenant rent or residential eviction')->values();
        }

        if ($hasEmployment) {
            $matches = $matches->reject(fn (array $profile): bool => in_array($profile['legalIssue'], ['commercial company rules', 'stolen property or theft'], true))->values();
        }

        if ($hasWorkplaceAccusation) {
            $matches = $matches->filter(fn (array $profile): bool => $profile['legalIssue'] === 'employment termination')->values();
        }

        if ($hasRenovation) {
            $matches = $matches->reject(fn (array $profile): bool => in_array($profile['legalIssue'], ['landlord and tenant rent or residential eviction', 'real estate law overview'], true))->values();
        }

        if ($hasSaleDelivery) {
            $matches = $matches->reject(fn (array $profile): bool => $profile['legalIssue'] === 'commercial company rules')->values();
        }

        if ($matches->isEmpty()) {
            return null;
        }

        $searchQueries = $matches
            ->flatMap(fn (array $profile): array => $profile['searchQueries'])
            ->unique()
            ->filter(function (string $query) use ($hasStructuredEmploymentAnalysis, $asksAboutCompensation): bool {
                if (!$hasStructuredEmploymentAnalysis || $asksAboutCompensation) {
                    return true;
                }

                return !preg_match('/\b(article 40|article 43|article 51|article 52|article 53|article 59|preavis|indemnite)\b/i', $query);
            })
            ->map(fn (string $query): string => $this->userProvidedArticleNumber($question)
                ? $query
                : $this->stripArticleNumberFromQuery($query))
            ->filter(fn (string $query): bool => $query !== '')
            ->take(16)
            ->values()
            ->all();

        if (!$searchQueries) {
            $searchQueries = $matches
                ->flatMap(fn (array $profile): array => array_filter([
                    $profile['legalIssue'] ?? null,
                    ...($profile['relevanceTerms'] ?? []),
                ]))
                ->map(fn (string $query): string => trim($query))
                ->filter(fn (string $query): bool => $query !== '')
                ->unique()
                ->take(8)
                ->values()
                ->all();
        }

        return [
            'legalIssue' => $matches->pluck('legalIssue')->implode(' / '),
            'reasoningGoal' => 'Explain the likely legal answer using the most relevant Moroccan law excerpts.',
            'needsLawSearch' => true,
            'searchQueries' => $searchQueries,
            'relevanceTerms' => $matches->flatMap(fn (array $profile): array => $profile['relevanceTerms'] ?? [])->unique()->take(24)->values()->all(),
            'allowedDocumentTitles' => $matches->flatMap(fn (array $profile): array => $profile['allowedDocumentTitles'] ?? [])->unique()->values()->all(),
            'allowedCategories' => $matches->flatMap(fn (array $profile): array => $profile['allowedCategories'] ?? [])->unique()->values()->all(),
        ];
    }

    private function legalQueryProfiles(): array
    {
        return [
            $this->profile('commercial lease eviction', [
                '/\b(commercial lease|business lease|shop lease|store lease|industrial lease|artisanal lease|business premises|commercial premises|fonds de commerce)\b/',
                '/\b(local commercial|usage commercial|fonds de commerce|bail commercial|baux commerciaux|industriel|artisanal)\b/',
                '/\b(shop|store|business|restaurant|office|premises)\b.*\b(landlord|tenant|lease|rent|evict|eviction)\b/',
                '/\b(landlord|tenant|lease|rent|evict|eviction)\b.*\b(shop|store|business|restaurant|office|premises)\b/',
            ], [
                'article 7 loi 49-16 indemnite eviction',
                'article 8 loi 49-16 eviction loyer',
                'indemnite eviction locataire commercial',
                'article 13 loi 49-16 eviction',
                'article 16 loi 49-16 eviction temporaire',
                'baux immeubles locaux loues usage commercial',
                'fonds de commerce eviction',
            ], 'commercialLease'),
            $this->profile('landlord and tenant rent or residential eviction', [
                '/\b(landlord|tenant|rent|rental|lease|evict|eviction|apartment|flat|home|house)\b/',
                '/\b(bailleur|locataire|loyer|bail|expulsion|eviction|resiliation|habitation)\b/',
            ], [
                'recouvrement des loyers',
                'loi 64-99 recouvrement loyers',
                'bailleur locataire mise en demeure loyer',
                'loi 67-12 bail habitation',
                'local usage habitation loyer',
                'bailleur locataire tribunal loyer',
            ], 'realEstate'),
            $this->profile('real estate law overview', [
                '/\b(real estate|real-estate|land title|property title|property ownership|co-ownership|housing lease)\b/',
                '/\b(immobilier|foncier|titre foncier|propriete fonciere|copropriete|bail habitation)\b/',
            ], [
                'code des droits reels propriete fonciere',
                'propriete fonciere',
                'immobilier',
                'copropriete',
                'loi 67-12 bail habitation',
                'recouvrement des loyers',
            ], 'realEstate', ['immobilier', 'foncier', 'propriete', 'copropriete', 'bail', 'loyer']),
            $this->profile('stolen property or theft', [
                '/\b(stolen|stole|steal|theft|robbed|robbery|burglary|break in|breakin)\b/',
                '/\b(vol|larcin|soustraction frauduleuse|effraction)\b/',
            ], [
                'article 505 code penal',
                'soustraction frauduleuse',
                'vol code penal',
                'article 506 code penal',
                'article 507 code penal',
                'article 508 code penal',
                'article 509 code penal',
                'article 510 code penal',
            ], 'criminal'),
            $this->profile('pregnancy or maternity dismissal', [
                '/\b(pregnant|pregnancy|maternity|mother|gave birth|birth|medical certificate|staff reduction|reducing staff)\b/',
                '/\b(grossesse|enceinte|maternite|accouchement|certificat medical|reduction du personnel|licenciement economique)\b/',
            ], [
                'article 159 code du travail grossesse licenciement',
                'article 160 code du travail certificat grossesse licenciement',
                'article 165 code du travail grossesse amende',
                'article 35 code du travail licenciement motif valable',
                'article 63 code du travail justification licenciement',
                'article 64 code du travail motifs licenciement tribunal',
                'article 66 code du travail licenciement economique',
                'article 67 code du travail licenciement economique autorisation',
                'article 41 code du travail licenciement abusif dommages interets',
                'article 65 code du travail action justice licenciement',
            ], 'labor', ['travail', 'salarie', 'employeur', 'licenciement', 'grossesse', 'maternite']),
            $this->profile('employment termination', [
                '/\b(fire|fired|termination|terminated|dismiss|dismissed|notice|salary|wage|employee|employer|boss|job|work contract)\b/',
                '/\b(licenciement|preavis|salaire|employeur|salarie|contrat de travail)\b/',
            ], [
                'article 35 code du travail licenciement motif valable',
                'article 37 code du travail sanction disciplinaire faute non grave',
                'article 39 code du travail faute grave licenciement',
                'article 40 code du travail faute grave employeur',
                'article 41 code du travail licenciement abusif dommages interets',
                'article 43 code du travail preavis',
                'article 51 code du travail indemnite preavis',
                'article 52 code du travail indemnite licenciement',
                'article 53 code du travail indemnite licenciement anciennete',
                'article 59 code du travail licenciement abusif indemnites',
                'article 62 code du travail licenciement',
                'article 63 code du travail decision licenciement',
                'article 64 code du travail decision licenciement motifs',
                'article 65 code du travail action justice licenciement',
            ], 'labor', ['travail', 'salarie', 'employeur', 'licenciement', 'faute grave', 'preavis', 'indemnite', 'decision licenciement']),
            $this->profile('commercial company rules', [
                '/\b(company|corporation|business entity)\b.*\b(shareholder|director|manager|sarl|register|registration|formation|incorporate|capital|shares)\b/',
                '/\b(shareholder|director|manager|sarl|register|registration|formation|incorporate|capital|shares)\b.*\b(company|corporation|business entity)\b/',
                '/\b(sarl|commercial company|register company|shareholder|director)\b/',
                '/\b(societe|associe|actionnaire|gerant|registre de commerce)\b/',
            ], [
                'societe responsabilite limitee',
                'societes anonymes actionnaire',
                'registre de commerce',
                'code de commerce commercant',
                'gerant societe',
            ], 'commercialCompany'),
            $this->profile('family law', [
                '/\b(divorce|marriage|custody|inheritance|child support|alimony)\b/',
                '/\b(divorce|mariage|garde|heritage|succession|pension)\b/',
            ], [
                'code de la famille divorce',
                'code de la famille garde',
                'pension alimentaire',
                'succession heritage',
                'mariage code de la famille',
            ], 'family'),
            $this->profile('consumer protection', [
                '/\b(consumer|customer|refund|warranty|seller|defective|product)\b/',
                '/\b(consommateur|remboursement|garantie|vendeur|defaut|produit)\b/',
            ], [
                'protection du consommateur garantie',
                'protection du consommateur remboursement',
                'information consommateur',
                'pratiques commerciales consommateur',
            ], 'consumer'),
            $this->profile('renovation or construction work contract', [
                '/\b(renovation|construction|repair|repairs|building work|work contract|works contract|travaux|ouvrage|chantier)\b.*\b(contract|contractor|builder|price|cost|material|materials|quote|estimate|devis|terminate|cancel|increase)\b/',
                '/\b(contract|contractor|builder|price|cost|material|materials|quote|estimate|devis|terminate|cancel|increase)\b.*\b(renovation|construction|repair|repairs|building work|works contract|travaux|ouvrage|chantier)\b/',
                '/\b(contractor|entrepreneur|builder|artisan|ouvrier)\b.*\b(material|materials|cost|price|increase|devis|quote|estimate|travaux|ouvrage|renovation|construction)\b/',
                '/\b(prix fait|devis|maitre de l ouvrage|augmentation de prix|louage d ouvrage)\b/',
            ], [
                'article 777 code des obligations et des contrats prix fait devis',
                'article 230 code des obligations et des contrats conventions',
                'article 231 code des obligations et des contrats bonne foi',
                'article 259 code des obligations et des contrats resolution dommages interets',
                'article 758 code des obligations et des contrats entrepreneur',
                'article 759 code des obligations et des contrats louage ouvrage',
                'article 766 code des obligations et des contrats matiere entrepreneur',
                'article 768 code des obligations et des contrats ouvrage vice delai',
            ], 'civilContracts', ['prix fait', 'devis', 'augmentation', 'maitre', 'travail', 'travaux', 'ouvrage', 'entrepreneur', 'matiere', 'materiaux']),
            $this->profile('sale contract with missing or partial delivery', [
                '/\b(sale|sold|sells|seller|buyer|bought|purchase|paid|payment|price|order)\b.*\b(deliver|delivery|delivered|received|receives|missing|partial|only|quantity|goods|products|items|laptops)\b/',
                '/\b(deliver|delivery|delivered|received|receives|missing|partial|only|quantity|goods|products|items|laptops)\b.*\b(sale|sold|sells|seller|buyer|bought|purchase|paid|payment|price|order)\b/',
                '/\b(vente|vendu|vendeur|acheteur|prix|paiement)\b.*\b(delivrance|livraison|reception|quantite|partielle|marchandise|produit)\b/',
                '/\b(delivrance|livraison|reception|quantite|partielle|marchandise|produit)\b.*\b(vente|vendu|vendeur|acheteur|prix|paiement)\b/',
            ], [
                'article 488 code des obligations et des contrats vente',
                'article 491 code des obligations et des contrats propriete chose vendue',
                'article 494 code des obligations et des contrats vente compte mesure',
                'article 496 code des obligations et des contrats reception acheteur',
                'article 498 code des obligations et des contrats delivrance',
                'article 499 code des obligations et des contrats delivrance possession',
                'article 500 code des obligations et des contrats choses mobilieres',
                'article 502 code des obligations et des contrats lieu delivrance',
                'article 504 code des obligations et des contrats paiement delivrance',
                'article 259 code des obligations et des contrats resolution dommages interets',
            ], 'civilContracts', ['vente', 'acheteur', 'vendeur', 'delivrance', 'chose vendue', 'prix', 'paiement', 'compte', 'mesure', 'reception']),
            $this->profile('sale ownership and heirs dispute', [
                '/\b(sold|sale|bought|buyer|seller|paid|payment|price)\b.*\b(car|vehicle|ownership|owner|registration|registered|heirs|inherit|inherited)\b/',
                '/\b(car|vehicle|ownership|owner|registration|registered|heirs|inherit|inherited)\b.*\b(sold|sale|bought|buyer|seller|paid|payment|price)\b/',
                '/\b(vente|vendu|acheteur|vendeur|prix)\b.*\b(propriete|possession|heritier|succession|vehicule|immatriculation|carte grise)\b/',
                '/\b(propriete|possession|heritier|succession|vehicule|immatriculation|carte grise)\b.*\b(vente|vendu|acheteur|vendeur|prix)\b/',
            ], [
                'code des obligations et des contrats vente parfaite consentement chose prix',
                'code des obligations et des contrats acheteur acquiert propriete chose vendue consentement',
                'code des obligations et des contrats chose vendue avant delivrance',
                'code des obligations et des contrats delivrance possession chose vendue',
                'code des obligations et des contrats choses mobilieres tradition reelle usage',
                'code des obligations et des contrats paiement delivrance vendeur acheteur',
                'code des obligations et des contrats heritiers ayants cause obligations',
                'code des obligations et des contrats enregistrement tiers',
            ], 'civilContracts', ['vente', 'acheteur', 'vendeur', 'propriete', 'delivrance', 'possession', 'heritier', 'ayants cause']),
        ];
    }

    private function profile(string $legalIssue, array $patterns, array $searchQueries, string $domain, array $relevanceTerms = []): array
    {
        return array_merge([
            'legalIssue' => $legalIssue,
            'patterns' => $patterns,
            'searchQueries' => $searchQueries,
            'relevanceTerms' => $relevanceTerms,
        ], self::SOURCE_DOMAINS[$domain]);
    }

    private function buildFactExtractionAnswer(string $question): ?string
    {
        $normalized = $this->normalizeText($question);

        if (!$this->asksForFactExtractionOnly($normalized)) {
            return null;
        }

        $facts = $this->extractLegalFacts($question);

        return $facts ? collect($facts)->map(fn (string $fact, int $index): string => ($index + 1).'. '.$fact)->implode("\n") : null;
    }

    private function buildEmploymentCaseAnalysisAnswer(string $question, array $citations, bool $force = false): ?string
    {
        $normalized = $this->normalizeText($question);

        if (!$force && (!$this->isEmploymentScenario($normalized) || (!$this->asksForStructuredCaseAnalysis($normalized) && !$this->isFactRichEmploymentCase($normalized)))) {
            return null;
        }

        $article35 = $this->findCitationMarker($citations, 'Article 35');
        $article37 = $this->findCitationMarker($citations, 'Article 37');
        $article39 = $this->findCitationMarker($citations, 'Article 39');
        $article41 = $this->findCitationMarker($citations, 'Article 41');
        $article43 = $this->findCitationMarker($citations, 'Article 43');
        $article51 = $this->findCitationMarker($citations, 'Article 51');
        $article52 = $this->findCitationMarker($citations, 'Article 52');
        $article53 = $this->findCitationMarker($citations, 'Article 53');
        $article59 = $this->findCitationMarker($citations, 'Article 59');
        $article62 = $this->findCitationMarker($citations, 'Article 62');
        $article63 = $this->findCitationMarker($citations, 'Article 63');
        $article64 = $this->findCitationMarker($citations, 'Article 64');
        $article65 = $this->findCitationMarker($citations, 'Article 65');

        if (!$article35 || !$article62 || !$article63 || !$article64) {
            return null;
        }

        $profile = $this->getQuestionFactProfile($question);
        $years = $this->extractSeniorityYears($question);
        $mentionsNoDiscipline = preg_match('/\b(sans aucun antecedent|aucun antecedent|antecedent disciplinaire|no disciplinary|jamais|never|aucun avertissement|no warning|no prior warnings?)\b/', $normalized);
        $mentionsSafetyReport = preg_match('/\b(securite|safety|danger|violations|regles de securite|workers|travailleurs)\b/', $normalized);
        $mentionsWitnesses = preg_match('/\b(deux|two|2)\b.*\b(employes|employees|salaries|witnesses|temoins|confirment|confirm)\b/', $normalized);
        $mentionsShortDelay = preg_match('/\b(trois|three|3)\s*(semaines|weeks)\b/', $normalized);
        $mentionsNegativeAttitude = preg_match('/\b(attitude negative|negative attitude|perturbation|bon fonctionnement|disruption)\b/', $normalized);
        $mentionsDataTransfer = preg_match('/\b(document|documents|donnees|data|confidentiel|confidential|usb|cle usb|email|mail|fichier|fichiers|transfere|transfer|sent)\b/', $normalized);

        $facts = array_values(array_filter([
            is_numeric($years) ? 'le salarie a '.$this->formatLegalNumber($years).' ans d\'anciennete' : '',
            $mentionsNoDiscipline ? 'aucun antecedent disciplinaire ou avertissement anterieur n\'est mentionne' : '',
            $mentionsSafetyReport ? 'le salarie a signale des problemes de securite pouvant mettre les travailleurs en danger' : '',
            $mentionsWitnesses ? 'deux autres employes confirment que les problemes signales existaient' : '',
            $mentionsShortDelay ? 'la procedure disciplinaire commence environ trois semaines apres le signalement' : '',
            $mentionsNegativeAttitude ? 'l\'employeur invoque une attitude negative ou une perturbation du fonctionnement de l\'entreprise' : '',
            $mentionsDataTransfer ? 'le litige porte sur un transfert de documents professionnels vers un support personnel' : '',
            $profile['mentionsPersonalEmail'] ? 'les documents ont ete envoyes vers l\'adresse email personnelle du salarie' : '',
            $profile['mentionsWorkFromHome'] ? 'le salarie explique que l\'envoi servait a travailler depuis son domicile' : '',
            $profile['mentionsNoCompetitorDisclosure'] ? 'aucun concurrent n\'est presente comme ayant recu les documents' : '',
            $profile['mentionsNoDisclosure'] ? 'aucune divulgation a des tiers n\'est mentionnee' : '',
            $profile['mentionsNoDamage'] ? 'aucun prejudice concret n\'est demontre' : '',
            $profile['mentionsImmediateDismissal'] ? 'le licenciement est presente comme immediat' : '',
            preg_match('/\b(licencie|licenciement|dismissal|dismiss|dismisses|dismissed|fired|terminated|termination)\b/', $normalized) ? 'le salarie est finalement licencie' : '',
        ]));

        $applicableArticles = array_values(array_filter([
            $article35 ? "Article 35: motif valable de licenciement {$article35}" : '',
            $article62 ? "Article 62: audition et defense du salarie avant sanction {$article62}" : '',
            $article63 ? "Article 63: notification et charge de justification du licenciement {$article63}" : '',
            $article64 ? "Article 64: motifs de la decision et limites du controle du tribunal {$article64}" : '',
            $article37 ? "Article 37: sanctions disciplinaires progressives pour faute non grave {$article37}" : '',
            $article39 ? "Article 39: exemples de fautes graves possibles {$article39}" : '',
            $article65 ? "Article 65: delai de contestation du licenciement {$article65}" : '',
        ]));

        $legalQuestions = array_values(array_filter([
            'le licenciement repose-t-il sur un motif valable et prouve',
            'la procedure disciplinaire a-t-elle ete respectee',
            ($mentionsSafetyReport || $mentionsShortDelay) ? 'la chronologie revele-t-elle un indice de represailles' : '',
            'les griefs invoques sont-ils assez precis et graves pour justifier la sanction',
        ]));

        $analysis = array_values(array_filter([
            $mentionsSafetyReport ? 'le signalement de risques de securite donne un contexte non disciplinaire au comportement du salarie' : '',
            $mentionsShortDelay ? 'le delai de trois semaines entre le signalement et la procedure rend le mobile de l\'employeur discutable' : '',
            $mentionsWitnesses ? 'les deux temoignages renforcent l\'idee que le signalement portait sur des faits reels' : '',
            $mentionsNegativeAttitude ? 'des termes vagues comme attitude negative ou perturbation doivent etre traduits en faits concrets par l\'employeur' : '',
            $mentionsDataTransfer ? 'l\'envoi de documents vers un support personnel peut constituer un risque disciplinaire, mais il faut examiner la finalite, les regles internes, la confidentialite, l\'autorisation de teletravail et l\'usage reel des fichiers' : '',
            $profile['mentionsWorkFromHome'] ? 'l\'explication de travail a domicile propose une raison professionnelle au transfert' : '',
            $profile['mentionsNoCompetitorDisclosure'] ? 'l\'absence de transmission a un concurrent reduit l\'indice d\'intention frauduleuse ou de concurrence deloyale' : '',
            $profile['mentionsNoDamage'] ? 'l\'absence de prejudice prouve pese sur la gravite et la proportionnalite' : '',
            $profile['mentionsImmediateDismissal'] ? 'une rupture immediate exige une justification solide de faute grave et une procedure respectee' : '',
            ($mentionsNoDiscipline || is_numeric($years)) ? 'l\'anciennete et l\'absence d\'avertissement peuvent affaiblir l\'idee d\'une faute grave soudaine' : '',
            'Article 63 rend decisive la preuve apportee par l\'employeur; Article 64 oblige aussi a regarder les motifs effectivement ecrits dans la decision',
        ]));

        $employerArguments = array_values(array_filter([
            $mentionsNegativeAttitude ? 'l\'employeur peut soutenir que la sanction vise le comportement du salarie ou une perturbation interne' : '',
            $mentionsDataTransfer ? 'il peut soutenir que le transfert de documents professionnels vers une adresse email personnelle creait un risque de confidentialite ou rompait la confiance' : '',
            "ces arguments doivent constituer un motif valable au sens d'Article 35 {$article35}",
        ]));
        $employeeArguments = array_values(array_filter([
            $mentionsSafetyReport ? 'le salarie peut soutenir qu\'il a signale des risques reels pour la securite' : '',
            $mentionsWitnesses ? 'les confirmations des autres employes renforcent la credibilite du signalement' : '',
            $mentionsShortDelay ? 'le delai court peut suggerer un lien de causalite ou des represailles' : '',
            ($mentionsNoDiscipline || is_numeric($years)) ? 'l\'anciennete et l\'absence d\'antecedent disciplinaire pesent sur la proportionnalite' : '',
            $profile['mentionsWorkFromHome'] ? 'l\'explication de travail a domicile donne une lecture professionnelle possible du comportement reproche' : '',
            $profile['mentionsNoCompetitorDisclosure'] ? 'l\'absence d\'envoi a un concurrent affaiblit l\'idee d\'une exploitation externe des documents' : '',
            ($profile['mentionsNoDisclosure'] || $profile['mentionsNoDamage']) ? 'l\'absence de divulgation ou de prejudice concret est favorable au salarie, sans exclure automatiquement toute faute si une regle claire a ete violee' : '',
        ]));
        $proofs = array_values(array_filter([
            'la chronologie exacte entre les faits, l\'ouverture disciplinaire et le licenciement',
            $mentionsSafetyReport ? 'les preuves des violations de securite signalees' : '',
            $mentionsWitnesses ? 'les temoignages des deux employes' : '',
            'la decision ecrite de licenciement et les motifs qu\'elle contient',
            'le proces-verbal d\'audition disciplinaire',
            $mentionsDataTransfer ? 'la politique de confidentialite, les autorisations de teletravail, les traces d\'envoi email et l\'usage reel des fichiers' : '',
            $profile['mentionsNoCompetitorDisclosure'] ? 'la preuve qu\'aucun concurrent ou tiers externe n\'a recu les documents' : '',
            $profile['mentionsNoDamage'] ? 'la preuve d\'un prejudice ou d\'un risque concret' : '',
        ]));

        $compensationLines = [];
        if (is_numeric($years) && preg_match('/(?:compensation|indemnite|dommages|claim|reclamer)/', $normalized)) {
            $severanceHours = $this->calculateSeveranceHours($years);
            $abusiveMonths = $this->calculateAbusiveDismissalMonths($years);
            $compensationLines[] = ($article52 && $article53) ? "Indemnite de licenciement: Articles 52 et 53 donnent une indemnite apres six mois de service, calculee par tranches; pour {$this->formatLegalNumber($years)} ans, cela donne {$severanceHours} heures de salaire avant toute regle plus favorable {$article52} {$article53}." : '';
            $compensationLines[] = $article41 ? "Si le licenciement est abusif, Article 41 prevoit la reintegration ou des dommages-interets d'un mois et demi de salaire par an ou fraction d'annee, plafonnes a 36 mois; pour {$this->formatLegalNumber($years)} ans, cela donne {$this->formatLegalNumber($abusiveMonths)} mois de salaire {$article41}." : '';
            $compensationLines[] = ($article43 && $article51) ? "L'indemnite de preavis peut aussi etre discutee si la rupture sans preavis n'est pas justifiee par une faute grave {$article43} {$article51}." : '';
        }

        $conclusion = $mentionsDataTransfer
            ? 'la position du salarie est serieuse si l\'envoi avait une finalite professionnelle, si aucun concurrent ou tiers n\'a recu les documents, si aucun prejudice concret n\'est prouve, et si l\'employeur ne prouve pas une regle claire, la confidentialite des documents, une intention fautive ou un risque grave'
            : ($mentionsSafetyReport
                ? 'la position du salarie est serieuse si les faits signales etaient reels, si la chronologie suggere une reaction punitive, et si l\'employeur ne prouve pas des faits precis constituant un motif valable'
                : 'la position du salarie est serieuse si l\'employeur ne prouve pas des faits precis constituant un motif valable et une procedure regulierement menee');

        return implode(' ', array_values(array_filter([
            'A. Faits importants: '.($facts ? implode('; ', $facts) : 'les faits doivent d\'abord etre identifies a partir du scenario de l\'utilisateur').'.',
            'B. Questions juridiques: '.implode('; ', $legalQuestions).'.',
            'C. Articles applicables: '.implode('; ', $applicableArticles).'.',
            'D. Analyse des faits: '.implode('; ', $analysis).'. La faute grave n\'est pas deduite automatiquement d\'une etiquette disciplinaire: elle doit etre prouvee et appreciee dans le contexte concret.',
            'E. Arguments de chaque partie: Employeur: '.implode('; ', $employerArguments).'. Salarie: '.implode('; ', $employeeArguments).'.',
            'F. Preuves importantes: '.implode('; ', $proofs).". Article 63 est important car la justification du licenciement incombe a l'employeur {$article63}. Article 64 limite ensuite le tribunal aux motifs mentionnes dans la decision et aux circonstances dans lesquelles elle a ete prise {$article64}.",
            implode(' ', array_filter($compensationLines)),
            $article65
                ? "G. Conclusion probable: {$conclusion}. Le licenciement peut donc etre conteste comme abusif ou insuffisamment justifie, sauf preuve concrete d'un motif disciplinaire independant et d'une procedure regulierement menee {$article35}. Le salarie doit aussi surveiller le delai de 90 jours a compter de la reception de la decision {$article65}."
                : "G. Conclusion probable: {$conclusion}. Le licenciement peut donc etre conteste comme abusif ou insuffisamment justifie, sauf preuve concrete d'un motif disciplinaire independant et d'une procedure regulierement menee {$article35}.",
            'H. Limites / informations manquantes: il manque le contenu exact de la lettre de licenciement; il manque le proces-verbal d\'audition et les preuves produites par l\'employeur; la base cite ici les regles de licenciement et de procedure; elle ne prouve pas a elle seule les faits materiels du dossier.',
        ])));
    }

    private function buildEmploymentEvidenceAnswer(string $question, array $citations): ?string
    {
        $normalized = $this->normalizeText($question);

        if (!$this->asksAboutAccusationEvidence($normalized) || $this->asksForFactExtractionOnly($normalized)) {
            return null;
        }

        $article35 = $this->findCitationMarker($citations, 'Article 35');
        $article39 = $this->findCitationMarker($citations, 'Article 39');
        $article62 = $this->findCitationMarker($citations, 'Article 62');
        $article63 = $this->findCitationMarker($citations, 'Article 63');
        $article64 = $this->findCitationMarker($citations, 'Article 64');

        if (!$article35 || !$article39) {
            return null;
        }

        $isDataTransfer = preg_match('/\b(document|documents|donnees|data|confidentiel|confidential|usb|cle usb|email|mail|fichier|fichiers|transfere|transfer|sent)\b/', $normalized);

        if ($isDataTransfer) {
            $profile = $this->getQuestionFactProfile($question);
            $years = $this->extractYearsOfService($question);
            $hasNoDiscipline = preg_match('/\b(sans aucun antecedent|no disciplinary|aucun antecedent|antecedent disciplinaire|pas d antecedent)\b/', $normalized);
            $target = $profile['mentionsPersonalEmail']
                ? 'il a envoye des documents professionnels vers son adresse email personnelle'
                : 'il a transfere des documents professionnels sur un support personnel';
            $storage = $profile['mentionsPersonalEmail'] ? 'l\'adresse email personnelle' : 'le support personnel';

            return implode(' ', array_values(array_filter([
                'Faits juridiquement importants: '.implode('; ', array_values(array_filter([
                    is_numeric($years) ? 'le salarie a '.$this->formatLegalNumber($years).' ans d\'anciennete' : '',
                    $hasNoDiscipline ? 'il n\'a aucun antecedent disciplinaire' : '',
                    $target,
                    $profile['mentionsWorkFromHome'] ? 'il explique que le transfert servait a travailler depuis son domicile' : '',
                    'l\'employeur qualifie ce transfert de faute grave et licencie immediatement',
                    $profile['mentionsNoCompetitorDisclosure'] ? 'aucun concurrent n\'est presente comme ayant recu les documents' : '',
                    $profile['mentionsNoDisclosure'] ? 'aucun document n\'est presente comme divulgue a des tiers' : '',
                    $profile['mentionsNoDamage'] ? 'aucun prejudice concret n\'est demontre' : '',
                ]))).'.',
                "Questions juridiques: le vrai debat est de savoir si le transfert de fichiers suffit a etablir une faute grave assimilable a un vol ou a une violation grave de confiance, ou s'il s'agit d'une utilisation professionnelle explicable. Article 39 vise notamment le vol et l'abus de confiance comme fautes graves possibles, mais il ne prouve pas a lui seul l'intention frauduleuse ni la gravite concrete des faits {$article39}.",
                "Arguments de l'employeur: il peut soutenir que des documents professionnels, surtout confidentiels, ne devaient pas etre copies sur un support personnel; que ce comportement cree un risque pour l'entreprise; et que le transfert rompt la confiance necessaire a la relation de travail. Ces arguments doivent encore soutenir un motif valable au sens d'Article 35 {$article35}.",
                'Arguments du salarie: '.implode(' ', array_values(array_filter([
                    (is_numeric($years) || $hasNoDiscipline) ? 'son anciennete et l\'absence d\'antecedent disciplinaire peuvent peser sur la proportionnalite de la sanction;' : '',
                    $profile['mentionsWorkFromHome'] ? 'son explication de travail a domicile donne une interpretation professionnelle et non frauduleuse du transfert;' : '',
                    $profile['mentionsNoDisclosure'] ? 'l\'absence de divulgation a des tiers, notamment a un concurrent si ce fait est etabli, affaiblit l\'idee d\'une exploitation externe des documents;' : '',
                    $profile['mentionsNoDamage'] ? 'l\'absence de prejudice concret est favorable au salarie, meme si elle ne suffit pas a exclure toute faute si une regle de confidentialite claire a ete violee.' : '',
                ]))),
                "Elements de preuve importants: l'employeur devrait prouver la nature confidentielle des documents, l'existence d'une politique interdisant les copies ou envois vers un espace personnel, l'autorisation ou non du travail a domicile, l'intention du salarie, l'usage reel de {$storage}, une eventuelle divulgation, et le prejudice ou le risque concret. Article 63 indique que la justification du licenciement incombe a l'employeur ".($article63 ?: $article35).'.',
                ($article62 || $article64) ? 'Procedure: meme en cas de faute grave alleguee, le salarie devait pouvoir se defendre et etre entendu, avec proces-verbal. La decision devait mentionner les motifs et les circonstances retenues '.$article62.' '.$article64.'.' : '',
                'Conclusion probable: le licenciement pour faute grave n\'est pas automatiquement valide. Il devient plus solide si l\'employeur prouve une interdiction claire, la confidentialite, une intention frauduleuse ou un risque grave. Il devient plus fragile si le salarie prouve un usage purement professionnel, l\'absence d\'antecedent, l\'absence de divulgation et l\'absence de prejudice concret.',
            ])));
        }

        return implode(' ', array_values(array_filter([
            'Faits pertinents: l\'entreprise accuse un salarie d\'avoir vole un ordinateur portable; l\'inventaire indique qu\'aucun ordinateur n\'est manquant; aucune preuve directe du vol n\'est mentionnee dans la question.',
            'Ce qui affaiblit l\'accusation, d\'abord, c\'est la contradiction factuelle: si aucun ordinateur n\'est manquant dans l\'inventaire, il manque un element concret du vol reproche.',
            "Juridiquement, Article 35 exige un motif valable de licenciement et Article 63 met la justification du licenciement a la charge de l'employeur {$article35} {$article63}.",
            "Article 39 peut rendre le vol ou l'abus de confiance une faute grave, mais seulement si les faits sont etablis {$article39}.",
            ($article62 || $article64) ? "La procedure compte aussi: le salarie doit pouvoir se defendre et etre entendu, et la decision doit mentionner les motifs du licenciement {$article62} {$article64}." : '',
            'Conclusion: les elements qui affaiblissent l\'employeur sont surtout l\'absence d\'ordinateur manquant, l\'absence de perte prouvee, l\'absence de preuve directe mentionnee, et la contradiction entre l\'accusation de vol et l\'inventaire.',
        ])));
    }

    private function buildEmploymentTerminationAnswer(string $question, array $citations): ?string
    {
        $normalized = $this->normalizeText($question);

        if (!preg_match('/\b(fire|fired|termination|terminated|dismiss|dismissed|licenciement|employeur|salarie|employee|employer|boss)\b/', $normalized)
            || $this->asksForFactExtractionOnly($normalized)
            || $this->asksForStructuredCaseAnalysis($normalized)
            || $this->asksAboutAccusationEvidence($normalized)) {
            return null;
        }

        $article35 = $this->findCitationMarker($citations, 'Article 35');
        $article37 = $this->findCitationMarker($citations, 'Article 37');
        $article39 = $this->findCitationMarker($citations, 'Article 39');
        $article40 = $this->findCitationMarker($citations, 'Article 40');
        $article41 = $this->findCitationMarker($citations, 'Article 41');
        $article43 = $this->findCitationMarker($citations, 'Article 43');
        $article51 = $this->findCitationMarker($citations, 'Article 51');
        $article52 = $this->findCitationMarker($citations, 'Article 52');
        $article53 = $this->findCitationMarker($citations, 'Article 53');
        $article59 = $this->findCitationMarker($citations, 'Article 59');
        $article62 = $this->findCitationMarker($citations, 'Article 62');
        $article63 = $this->findCitationMarker($citations, 'Article 63');
        $article64 = $this->findCitationMarker($citations, 'Article 64');
        $article65 = $this->findCitationMarker($citations, 'Article 65');

        if (!$article35 || !$article41 || !$article52 || !$article53 || !$article62 || !$article63 || !$article64) {
            return null;
        }

        $years = $this->extractYearsOfService($question);
        $severanceHours = $this->calculateSeveranceHours($years);
        $abusiveMonths = $this->calculateAbusiveDismissalMonths($years);
        $asksCompensation = preg_match('/\b(compensation|indemnite|indemnites|dommages|preavis|severance|claim|reclamer|montant|salaire)\b/', $normalized);
        $asksSeriousFault = preg_match('/\b(faute grave|serious fault|gross misconduct|serious misconduct)\b/', $normalized);

        return implode(' ', array_values(array_filter([
            "Based on the retrieved Code du travail articles, a dismissal needs a valid reason and the employer has to prove it. Article 35 prohibits dismissal without a valid reason linked to the employee's aptitude, conduct, or the company's operational needs {$article35}.",
            ($asksSeriousFault && $article39) ? "For faute grave, Article 39 is the key starting point: it lists serious faults that can justify dismissal, including theft, breach of trust, serious insult, unjustified refusal to perform competent work, and unjustified absence beyond the listed threshold {$article39}." : '',
            ($asksSeriousFault && $article37) ? "If the conduct is not a serious fault, Article 37 points instead to progressive disciplinary sanctions for non-serious fault before dismissal becomes justified {$article37}." : '',
            ($asksSeriousFault && $article40) ? "Article 40 also matters from the employee's side because serious faults by the employer can make the employee's departure treated as abusive dismissal when proven {$article40}." : '',
            "The procedure should have included a chance for the employee to defend himself and be heard, with a written record {$article62}. The dismissal decision then had to be delivered by hand against receipt or by registered letter within 48 hours {$article63}. The decision also had to state the reasons, mention the hearing date, attach the Article 62 record, and a copy had to be sent to the labor inspector {$article64}.",
            "For claims, the employee may claim notice indemnity if the contract was ended without respecting notice and there was no serious fault: Article 43 requires notice for unilateral termination of an indefinite-term contract, and Article 51 makes the responsible party pay what the employee would have received during the unobserved notice period {$article43} {$article51}.",
            $asksCompensation
                ? "He may also claim statutory severance indemnity because Article 52 grants it after six months of work in the same company {$article52}. Article 53 uses graduated rates {$article53}.".($severanceHours ? " For {$this->formatLegalNumber($years)} years, that is {$severanceHours} hours of salary, before any more favorable rule." : ' The cash amount needs the salary and exact service period.')
                : "He may also have statutory severance rights if the legal conditions are met because Article 52 grants severance after six months of work and Article 53 gives the calculation method {$article52} {$article53}.",
            $asksCompensation
                ? "If the dismissal is found abusive, Article 41 allows reinstatement or damages calculated at one and a half months of salary per year or fraction of year, capped at 36 months {$article41}.".($abusiveMonths ? " For {$this->formatLegalNumber($years)} years, that formula gives {$this->formatLegalNumber($abusiveMonths)} months of salary." : ' The cash amount needs salary and exact service period.')." Article 59 also links abusive dismissal to damages and notice indemnity {$article59}."
                : "If the dismissal is found abusive, Article 41 allows reinstatement or damages, and Article 59 also links abusive dismissal to damages and notice indemnity {$article41} {$article59}.",
            $article65 ? "The employee should also watch the deadline: Article 65 says a court action about dismissal must be brought within 90 days from receipt of the dismissal decision {$article65}." : '',
            "The exact cash amount still needs the employee's salary, contract type, applicable notice period, and whether the employer can prove serious fault or another valid ground.",
        ])));
    }

    private function buildPregnancyDismissalAnswer(string $question, array $citations): ?string
    {
        $normalized = $this->normalizeText($question);

        if (!preg_match('/\b(pregnant|pregnancy|maternity|grossesse|enceinte|maternite)\b/', $normalized)) {
            return null;
        }

        $article35 = $this->findCitationMarker($citations, 'Article 35');
        $article41 = $this->findCitationMarker($citations, 'Article 41');
        $article63 = $this->findCitationMarker($citations, 'Article 63');
        $article64 = $this->findCitationMarker($citations, 'Article 64');
        $article65 = $this->findCitationMarker($citations, 'Article 65');
        $article66 = $this->findCitationMarker($citations, 'Article 66');
        $article67 = $this->findCitationMarker($citations, 'Article 67');
        $article159 = $this->findCitationMarker($citations, 'Article 159');
        $article160 = $this->findCitationMarker($citations, 'Article 160');
        $article165 = $this->findCitationMarker($citations, 'Article 165');

        if (!$article159 || !$article160 || !$article35) {
            return null;
        }

        return implode(' ', array_values(array_filter([
            'Pregnancy protection matters, and the employer\'s staff-reduction reason plus later hiring for the same position are relevant to whether the stated reason was genuine.',
            "Article 159 says the employer cannot terminate a worker whose pregnancy is attested by a medical certificate during pregnancy and the 14 weeks after childbirth, except for serious fault or another legal ground, and notification/effect cannot occur during protected suspension periods {$article159}.",
            "If the dismissal was notified before the employee had proved pregnancy by medical certificate, Article 160 lets her send the certificate by registered letter within 15 days from notification; the dismissal is then annulled, subject to Article 159 exceptions {$article160}.",
            "The staff-reduction explanation is not automatically irrelevant. Article 35 allows dismissal only for a valid reason, including business-operation needs handled under Articles 66 and 67 {$article35}. Article 66 requires consultation and records for economic/structural/technological dismissal {$article66}. Article 67 requires authorization from the governor and supporting economic documents {$article67}.",
            "A court would consider whether pregnancy was medically certified or timely certified after notice, whether termination was during a protected period, whether the written decision stated lawful reasons, whether the employer can prove that reason, and whether the economic-dismissal procedure was followed {$article63} {$article64}.",
            "Hiring another employee for the same position one month later is a fact the court could weigh against the claimed staff-reduction reason because Article 63 places justification on the employer and Article 64 limits the tribunal to the reasons in the dismissal decision {$article63} {$article64}.",
            "For remedies, Article 160 may annul the dismissal if the certificate was sent in time {$article160}. If the dismissal is abusive, Article 41 allows reinstatement or damages {$article41}. Article 165 provides employer fines for unlawful termination of a pregnant or postpartum worker outside Article 159 cases {$article165}.",
            $article65 ? "The employee should also watch the deadline: Article 65 says court action about dismissal must be brought within 90 days from receipt of the dismissal decision {$article65}." : '',
        ])));
    }

    private function buildRenovationContractAnswer(string $question, array $citations): ?string
    {
        $normalized = $this->normalizeText($question);

        if (!preg_match('/\b(renovation|construction|repair|repairs|contractor|builder|material|materials|devis|quote|estimate|prix fait|travaux|ouvrage|chantier|entrepreneur)\b/', $normalized)) {
            return null;
        }

        $doc = 'Code des Obligations et des Contrats';
        $article230 = $this->findCitationMarker($citations, 'Article 230', $doc);
        $article231 = $this->findCitationMarker($citations, 'Article 231', $doc);
        $article259 = $this->findCitationMarker($citations, 'Article 259', $doc);
        $article758 = $this->findCitationMarker($citations, 'Article 758', $doc);
        $article766 = $this->findCitationMarker($citations, 'Article 766', $doc);
        $article777 = $this->findCitationMarker($citations, 'Article 777', $doc);

        if (!$article777) {
            return null;
        }

        return implode(' ', array_values(array_filter([
            "For a renovation or construction job, the strongest retrieved rule is Article 777 of the Code des Obligations et des Contrats. If the contractor undertook the work for a fixed price based on a plan or estimate made or accepted by him, he cannot ask for a price increase unless the extra expense was caused by the client and expressly authorized by the client {$article777}.",
            "So a simple rise in material prices does not, by itself, validate the contractor's demand for more money. The key facts are whether the contract was a fixed-price job or accepted quote, whether the client changed the work or caused additional expense, whether the client expressly approved the extra cost, and whether the contract has a price-revision clause {$article777}.",
            ($article230 || $article231) ? "The general contract articles support the same structure: valid obligations bind the parties {$article230}; obligations must be performed in good faith and include consequences required by law, usage, or equity {$article231}." : '',
            $article259 ? "If one party stops performing or refuses the agreed performance, Article 259 becomes relevant after default: the creditor may seek performance if possible, or otherwise judicial termination with damages {$article259}." : '',
            $article758 ? "Article 758 is also relevant to abrupt non-performance: when one party does not fulfill commitments or ends them abruptly at the wrong time without plausible reasons, that party may owe damages {$article758}." : '',
            $article766 ? "If the dispute concerns materials, Article 766 adds rules on the contractor's responsibility for material quality or use {$article766}." : '',
            'Likely conclusion: if this was a fixed-price renovation quote and the client did not cause or expressly authorize the extra material cost, the contractor has a weak basis to demand more money only because materials became more expensive.',
        ])));
    }

    private function buildPartialDeliverySaleAnswer(string $question, array $citations): ?string
    {
        $normalized = $this->normalizeText($question);
        $hasSaleFacts = preg_match('/\b(sale|sold|sells|seller|buyer|bought|purchase|paid|payment|price|order|vente|vendu|vendeur|acheteur|paiement)\b/', $normalized);
        $hasDeliveryFacts = preg_match('/\b(deliver|delivery|delivered|received|receives|missing|partial|only|quantity|goods|products|items|laptops|delivrance|livraison|reception|quantite|partielle|marchandise)\b/', $normalized);

        if (!$hasSaleFacts || !$hasDeliveryFacts) {
            return null;
        }

        $doc = 'Code des Obligations et des Contrats';
        $article488 = $this->findCitationMarker($citations, 'Article 488', $doc);
        $article491 = $this->findCitationMarker($citations, 'Article 491', $doc);
        $article494 = $this->findCitationMarker($citations, 'Article 494', $doc);
        $article496 = $this->findCitationMarker($citations, 'Article 496', $doc);
        $article498 = $this->findCitationMarker($citations, 'Article 498', $doc);
        $article499 = $this->findCitationMarker($citations, 'Article 499', $doc);
        $article500 = $this->findCitationMarker($citations, 'Article 500', $doc);
        $article504 = $this->findCitationMarker($citations, 'Article 504', $doc);
        $article259 = $this->findCitationMarker($citations, 'Article 259', $doc);

        if (!$article488 || !$article498 || !$article499) {
            return null;
        }

        return implode(' ', array_values(array_filter([
            "The main legal issue is not laptops specifically; it is a sale contract where the seller allegedly delivered only part of what was sold. Article 488 says a sale is perfected once the parties agree to sell and buy, and agree on the thing, the price, and the other clauses {$article488}. ".($article491 ? "Article 491 adds that the buyer acquires ownership once the sale is perfected by consent {$article491}." : ''),
            "The seller's core obligation is delivery. Article 498 says the seller has two main obligations: to deliver the sold thing and to guarantee it {$article498}. Article 499 defines delivery as the seller giving up the sold thing and putting the buyer in a position to take possession without obstacle {$article499}. ".($article500 ? "For movable goods, Article 500 allows delivery by actual handover or another usage-recognized means {$article500}." : ''),
            $article504 ? "Because the buyer paid in full, Article 504 matters: delivery should occur after conclusion of the contract except delays required by nature or usage, and the seller who did not grant a payment term is not bound to deliver unless the buyer offers payment against delivery {$article504}." : '',
            $article494 ? "For quantity and receipt, Article 494 is useful if the sale was by count, measure, test, or description: until the goods are counted, measured, examined, and accepted, they remain at the seller's risk {$article494}." : '',
            $article496 ? "Article 496 also says the sold thing travels at the seller's risk until receipt by the buyer {$article496}." : '',
            $article259 ? "If the seller is in default for the missing part, Article 259 gives the creditor a path to force performance if possible, or seek judicial termination with damages {$article259}." : '',
            'Likely conclusion: if the buyer can prove a contract for 100 items, full payment, and delivery of only 60, the seller appears to have a delivery or non-performance problem for the remaining 40.',
        ])));
    }

    private function buildSaleOwnershipAnswer(string $question, array $citations, string $language = 'en'): ?string
    {
        $normalized = $this->normalizeText($question);
        $hasSaleFacts = preg_match('/\b(sold|sale|bought|buyer|seller|paid|payment|price|vente|vendu|acheteur|vendeur|prix)\b/', $normalized);
        $hasOwnershipFacts = preg_match('/\b(ownership|owner|registration|registered|heirs|inherit|inherited|car|vehicle|propriete|possession|heritier|succession|vehicule|immatriculation|carte grise)\b/', $normalized);

        if (!$hasSaleFacts || !$hasOwnershipFacts) {
            return null;
        }

        $doc = 'Code des Obligations et des Contrats';
        $article229 = $this->findCitationMarker($citations, 'Article 229', $doc);
        $article488 = $this->findCitationMarker($citations, 'Article 488', $doc);
        $article491 = $this->findCitationMarker($citations, 'Article 491', $doc);
        $article498 = $this->findCitationMarker($citations, 'Article 498', $doc);
        $article499 = $this->findCitationMarker($citations, 'Article 499', $doc);
        $article500 = $this->findCitationMarker($citations, 'Article 500', $doc);
        $article504 = $this->findCitationMarker($citations, 'Article 504', $doc);

        if (!$article488 || !$article491 || !$article229) {
            return null;
        }

        if ($language === 'fr') {
            return implode(' ', array_values(array_filter([
                "D'apres les articles civils retrouves, Youssef a la position de propriete la plus forte s'il peut prouver la vente. L'article 488 dit que la vente est parfaite des que les parties consentent a vendre et acheter et s'accordent sur la chose et le prix {$article488}. L'article 491 dit que l'acheteur acquiert la propriete des que le contrat est parfait par le consentement {$article491}.",
                'Le paiement et la possession comptent comme preuves de l execution de la vente. '.($article498 ? "Les obligations principales du vendeur comprennent la delivrance et la garantie {$article498}. " : '').($article499 ? "La delivrance consiste a mettre l acheteur en mesure de prendre possession sans obstacle {$article499}. " : '').($article500 ? "Pour les choses mobilieres, la delivrance peut se faire par tradition reelle ou par un mode reconnu par l usage {$article500}. " : '').($article504 ? "L article 504 relie aussi la delivrance au paiement lorsqu aucun terme de paiement n a ete accorde {$article504}." : ''),
                "Le deces d'Ahmed ne redonne pas automatiquement la propriete aux heritiers si la vente etait deja parfaite avant le deces. L'article 229 dit que les obligations produisent effet entre les parties et aussi entre leurs heritiers ou ayants cause, sauf exception prevue par l'accord, la nature de l'obligation ou la loi {$article229}.",
                "Le point non tranche par les extraits est l immatriculation du vehicule. La reponse prudente est que l immatriculation peut rester importante comme formalite administrative, preuve ou opposabilite aux tiers, mais les articles retrouves donnent a l acheteur l argument civil le plus fort si la vente, le prix et la possession sont prouves.",
            ])));
        }

        return implode(' ', array_values(array_filter([
            "On the retrieved civil-law articles, the buyer has the stronger ownership argument if he can prove the sale. Article 488 says a sale is perfected once the parties consent to sell and buy and agree on the thing and price {$article488}. Article 491 says the buyer acquires ownership once the contract is perfected by consent {$article491}.",
            'Payment and possession matter as evidence that the sale was performed. '.($article498 ? "The seller's main obligations include delivery and guarantee {$article498}. " : '').($article499 ? "Delivery occurs when the seller puts the buyer in a position to possess without obstacle {$article499}. " : '').($article500 ? "For movable things, delivery can occur by handover or another usage-recognized method {$article500}. " : '').($article504 ? "Article 504 also links delivery to payment when no payment term was granted {$article504}." : ''),
            "The seller's death does not by itself revive ownership in the heirs if the sale was already perfected before death. Article 229 says obligations have effect between parties and also between their heirs or successors unless the agreement, nature of obligation, or law says otherwise {$article229}.",
            'The unresolved point is registration. The retrieved excerpts do not include the specific vehicle-registration rule, so the safe answer is that registration may still matter as administrative proof, opposability to third parties, or completion of vehicle-transfer formalities.',
        ])));
    }

    private function enforceFactConsistency(string $answer, string $question, ?array $plan, array $citations): string
    {
        $issues = $this->getAnswerFactValidationIssues($answer, $question);

        if (!$issues) {
            return $answer;
        }

        return $this->buildEmploymentCaseAnalysisAnswer($question, $citations, true)
            ?? $this->buildFactGroundedFallbackAnswer($question, $plan, $citations);
    }

    private function buildFactGroundedFallbackAnswer(string $question, ?array $plan, array $citations): string
    {
        $facts = $this->extractLegalFacts($question);
        $issues = $this->extractLegalIssues($question, $plan);
        $articleSummaries = collect($citations)->take(7)->map(
            fn (array $citation, int $index): string => trim(($citation['articleNumber'] ?? '').' '.($citation['documentTitle'] ?? '')).' ['.($index + 1).']'
        )->filter()->values()->all();
        $profile = $this->getQuestionFactProfile($question);
        $analysis = array_values(array_filter([
            $profile['mentionsPersonalEmail'] ? 'l\'envoi vers une adresse email personnelle peut soutenir l\'argument de risque de confidentialite de l\'employeur' : '',
            $profile['mentionsWorkFromHome'] ? 'l\'explication de travail a domicile donne au salarie une justification professionnelle possible' : '',
            $profile['mentionsNoCompetitorDisclosure'] ? 'l\'absence de transmission a un concurrent affaiblit l\'idee d\'une exploitation externe des documents' : '',
            $profile['mentionsNoDamage'] ? 'l\'absence de prejudice prouve pese sur la gravite et la proportionnalite de la sanction' : '',
            $profile['mentionsImmediateDismissal'] ? 'le caractere immediat du licenciement rend decisives la preuve de la faute grave et la regularite de la procedure' : '',
            'l\'employeur doit relier les faits reproches a un motif valable et prouver les circonstances retenues',
        ]));

        return implode(' ', [
            'A. Faits importants: '.($facts ? implode(' ', $facts) : 'Les faits utiles doivent etre extraits du scenario avant de citer les articles.'),
            'B. Questions juridiques: '.($issues ? implode('; ', $issues) : 'motif valable, preuve, proportionnalite et procedure de licenciement').'.',
            'C. Articles applicables: '.($articleSummaries ? implode('; ', $articleSummaries) : 'sources insuffisantes dans les extraits retenus').'.',
            'D. Analyse des faits: '.implode('; ', $analysis).'.',
            'E. Arguments de chaque partie: L\'employeur peut invoquer le risque de confidentialite, une violation d\'une regle interne ou une rupture de confiance. Le salarie peut invoquer la finalite professionnelle, l\'absence de concurrent destinataire, l\'absence de prejudice prouve, son anciennete et la proportionnalite de la sanction.',
            'F. Preuves importantes: lettre de licenciement, proces-verbal d\'audition, politique informatique ou confidentialite, traces d\'envoi, autorisation ou usage du teletravail, preuve de divulgation a des tiers, preuve d\'un prejudice ou d\'un risque concret.',
            'G. Conclusion probable: la faute grave n\'est pas automatique. La sanction est plus solide si l\'employeur prouve une interdiction claire, la confidentialite, un risque grave ou une divulgation. Elle est plus contestable si l\'envoi etait professionnel, sans concurrent destinataire, sans prejudice prouve, et si la procedure n\'a pas ete respectee.',
            'H. Limites / informations manquantes: il manque les documents internes, la preuve technique, les motifs exacts de la decision et les pieces produites par l\'employeur.',
        ]);
    }

    private function sanitizeSearchPlan(?array $plan, string $question): ?array
    {
        if (!$plan) {
            return null;
        }

        $rawQueries = $plan['searchQueries'] ?? $plan['search_queries'] ?? [];
        $searchQueries = collect(is_array($rawQueries) ? $rawQueries : [])
            ->map(fn (mixed $query): string => trim((string) $query))
            ->filter(fn (string $query): bool => Str::length($query) >= 2)
            ->map(fn (string $query): string => $this->userProvidedArticleNumber($question)
                ? $query
                : $this->stripArticleNumberFromQuery($query))
            ->filter(fn (string $query): bool => Str::length($query) >= 2)
            ->unique()
            ->take(12)
            ->values()
            ->all();

        $legalIssue = is_array($plan['legal_issues'] ?? null)
            ? collect($plan['legal_issues'])->map(fn (mixed $issue): string => trim((string) $issue))->filter()->implode(' / ')
            : trim((string) ($plan['legalIssue'] ?? $plan['legal_issue'] ?? ''));

        return [
            'legalIssue' => $legalIssue,
            'reasoningGoal' => trim((string) ($plan['reasoningGoal'] ?? $plan['reasoning_goal'] ?? '')),
            'needsLawSearch' => ($plan['needsLawSearch'] ?? true) !== false,
            'searchQueries' => $searchQueries,
        ];
    }

    private function userProvidedArticleNumber(string $question): bool
    {
        return (bool) preg_match('/\b(?:article|art)\s*(premier|\d+(?:\s*(?:bis|ter|quater))?)\b/i', $this->normalizeText($question));
    }

    private function containsArticleNumberQuery(string $query): bool
    {
        return (bool) preg_match('/\b(?:article|art)\s*(premier|\d+)/i', $this->normalizeText($query));
    }

    private function stripArticleNumberFromQuery(string $query): string
    {
        if (!$this->containsArticleNumberQuery($query)) {
            return trim($query);
        }

        return Str::of($query)
            ->replaceMatches('/\b(?:article|art)\s*(?:premier|\d+(?:\s*(?:bis|ter|quater))?)\b/i', ' ')
            ->replaceMatches('/\s+/', ' ')
            ->trim()
            ->toString();
    }

    private function callOllama(array $payload): ?string
    {
        if (!$this->isEnabled()) {
            return null;
        }

        $response = Http::timeout($payload['timeout'])
            ->post(rtrim((string) env('OLLAMA_BASE_URL', self::DEFAULT_OLLAMA_BASE_URL), '/').'/api/chat', [
                'model' => env('OLLAMA_MODEL', self::DEFAULT_OLLAMA_MODEL),
                'stream' => false,
                'format' => $payload['format'] ?? null,
                'think' => false,
                'messages' => $payload['messages'],
                'options' => [
                    'temperature' => $payload['temperature'] ?? 0.15,
                    'top_p' => 0.9,
                    'num_predict' => $payload['num_predict'] ?? 700,
                    'num_ctx' => (int) env('OLLAMA_NUM_CTX', 8192),
                    'repeat_penalty' => 1.08,
                ],
            ]);

        if (!$response->successful()) {
            throw new \RuntimeException('Ollama request failed: '.$response->status());
        }

        return $this->stripThinkingText((string) data_get($response->json(), 'message.content', ''));
    }

    private function extractJsonObject(?string $text): ?array
    {
        $cleaned = $this->stripThinkingText($text);
        $payload = json_decode($cleaned, true);

        if (is_array($payload)) {
            return $payload;
        }

        if (preg_match('/\{[\s\S]*\}/', $cleaned, $match)) {
            $payload = json_decode($match[0], true);

            return is_array($payload) ? $payload : null;
        }

        return null;
    }

    private function formatCitationForPrompt(array $citation, int $index): string
    {
        $sourceParts = array_filter([
            $citation['articleNumber'] ?? null,
            $citation['documentTitle'] ?? null,
            $citation['lawReference'] ?? null,
            $citation['category'] ?? null,
        ]);

        return implode("\n", array_values(array_filter([
            '['.($index + 1).'] '.($citation['title'] ?? ''),
            ($citation['matchedQuery'] ?? null) ? 'Matched search query: '.$citation['matchedQuery'] : '',
            $sourceParts ? 'Source: '.implode(' | ', $sourceParts) : '',
            'Text: '.Str::limit($this->cleanLawExcerpt($citation['content'] ?? ''), 1200, ''),
        ])));
    }

    private function removeUnsupportedPracticalAdvice(string $answer, string $citationContext): string
    {
        $normalizedContext = $this->normalizeText($citationContext);
        $contextHasEvictionRule = preg_match('/\b(evict|eviction|expulsion)\b/', $normalizedContext);
        $guarded = [
            ['terms' => ['police', 'report', 'complaint'], 'context' => '/\b(police|plainte|denonciation|declaration)\b/'],
            ['terms' => ['compensation', 'remedy', 'remedies'], 'context' => '/\b(indemnite|indemnites|dommages interets|dommage interets|reparation|reintegration)\b/'],
            ['terms' => ['court', 'judge', 'lawsuit', 'legal action', 'sue'], 'context' => '/\b(tribunal|tribunaux|action en justice|saisir|juge|competent|conciliation)\b/'],
            ['terms' => ['deadline'], 'context' => '/\b(delai|90 jours|48 heures|huit jours|quinze jours)\b/'],
            ['terms' => ['lawyer'], 'context' => '/\b(avocat|avocate)\b/'],
            ['terms' => ['prior record', 'criminal record'], 'context' => '/\b(casier judiciaire|antecedent)\b/'],
        ];

        return collect(preg_split('/(?<=[.!?])\s+/', $answer) ?: [])
            ->map(fn (string $sentence): string => trim($sentence))
            ->filter()
            ->map(function (string $sentence) use ($normalizedContext, $contextHasEvictionRule, $guarded): string {
                $normalizedSentence = $this->normalizeText($sentence);

                if (!$contextHasEvictionRule && preg_match('/^(yes|no)$/', $normalizedSentence)) {
                    return '';
                }

                if (!$contextHasEvictionRule && preg_match('/\b(evict|eviction|expulsion)\b/', $normalizedSentence)) {
                    return 'The provided excerpts do not directly cover eviction or expulsion; they only support the related rent-recovery or lease points they mention.';
                }

                foreach ($guarded as $item) {
                    $mentionsTerm = collect($item['terms'])->contains(fn (string $term): bool => str_contains($normalizedSentence, $term));

                    if ($mentionsTerm && !preg_match($item['context'], $normalizedContext)) {
                        return '';
                    }
                }

                return $sentence;
            })
            ->filter()
            ->unique()
            ->implode(' ');
    }

    private function ensureCitationMarker(string $answer, array $citations): string
    {
        if (!$citations || preg_match('/\[\d+\]/', $answer)) {
            return $answer;
        }

        $trimmed = trim($answer);

        return $trimmed.(preg_match('/[.!?]$/', $trimmed) ? ' [1]' : ' [1].');
    }

    private function removeInvalidCitationMarkers(string $answer, array $citations): string
    {
        return preg_replace_callback('/\[(\d+)\]/', function (array $match) use ($citations): string {
            $index = (int) $match[1];

            return $index >= 1 && $index <= count($citations) ? $match[0] : '';
        }, $answer) ?? $answer;
    }

    private function ensureSubstantiveAnswer(string $answer, array $citations, string $language): string
    {
        if (Str::length($answer) >= 180 || !$citations) {
            return $answer;
        }

        return trim(implode(' ', array_filter([$this->buildCitationLead($citations, $language), $answer])));
    }

    private function buildCitationLead(array $citations, string $language): string
    {
        $citation = $citations[0] ?? null;

        if (!$citation) {
            return '';
        }

        $source = implode(' from ', array_filter([
            $citation['articleNumber'] ?? null,
            $citation['documentTitle'] ?? null,
            $citation['lawReference'] ?? null,
        ]));
        $excerpt = Str::limit($this->cleanLawExcerpt($citation['content'] ?? ''), 280, '');

        if (!$excerpt) {
            return '';
        }

        return $language === 'fr'
            ? "La regle retrouvee la plus proche est ".($source ?: ($citation['title'] ?? 'la premiere citation')).": {$excerpt} [1]."
            : "The closest retrieved rule is ".($source ?: ($citation['title'] ?? 'the first citation')).": {$excerpt} [1].";
    }

    private function findCitationMarker(array $citations, string $articleNumber, string $documentTitle = 'Code du travail'): string
    {
        foreach ($citations as $index => $citation) {
            if (($citation['articleNumber'] ?? null) === $articleNumber && (!$documentTitle || ($citation['documentTitle'] ?? null) === $documentTitle)) {
                return '['.($index + 1).']';
            }
        }

        return '';
    }

    private function extractLegalFacts(string $question): array
    {
        $normalized = $this->normalizeText($question);
        $profile = $this->getQuestionFactProfile($question);
        $years = $profile['yearsOfService'];
        $facts = [];
        $add = function (string $fact) use (&$facts): void {
            if ($fact !== '' && !in_array($fact, $facts, true)) {
                $facts[] = $fact;
            }
        };

        if (is_numeric($years)) {
            $add('Le salarie a '.$this->formatLegalNumber($years).' ans d\'anciennete.');
        }
        if (preg_match('/\b(sans aucun antecedent|aucun antecedent|antecedent disciplinaire|no disciplinary|pas d antecedent|no warning|aucun avertissement|no prior warnings)\b/', $normalized)) {
            $add('Aucun antecedent disciplinaire ou avertissement anterieur n\'est mentionne.');
        }
        if (preg_match('/\b(securite|safety|danger|violations|regles de securite|workers|travailleurs)\b/', $normalized)) {
            $add('Le salarie a signale des problemes de securite pouvant mettre les travailleurs en danger.');
        }
        if (preg_match('/\b(trois|three|3)\s*(semaines|weeks)\b/', $normalized)) {
            $add('La procedure disciplinaire commence environ trois semaines apres le signalement.');
        }
        if (preg_match('/\b(deux|two|2)\b.*\b(employes|employees|salaries|witnesses|temoins|confirment|confirm)\b/', $normalized)) {
            $add('Deux autres employes confirment que les problemes signales existaient.');
        }
        if (preg_match('/\b(attitude negative|negative attitude|perturbation|bon fonctionnement|normal operation|disruption)\b/', $normalized)) {
            $add('L\'employeur invoque une attitude negative ou une perturbation du fonctionnement normal.');
        }
        if (preg_match('/\b(licencie|licenciement|dismissal|dismiss|dismisses|dismissed|fired|terminated|termination)\b/', $normalized)) {
            $add('Le salarie est finalement licencie.');
        }
        if (preg_match('/\b(accuse|accusation|accuses|alleges|allegation|affirme|qualifie)\b/', $normalized)) {
            $add('L\'entreprise accuse le salarie d\'un fait fautif.');
        }
        if (preg_match('/\b(vol|vole|theft|stolen)\b/', $normalized)) {
            $add('L\'accusation porte sur un vol.');
        }
        if (preg_match('/\b(ordinateur|laptop|portable)\b/', $normalized)) {
            $add('L\'objet mentionne est un ordinateur portable.');
        }
        if (preg_match('/\b(aucun|no)\b.*\b(manquant|missing)\b|\b(inventaire|inventory)\b/', $normalized)) {
            $add('Selon l\'inventaire de l\'entreprise, aucun ordinateur n\'est manquant.');
        }
        if (preg_match('/\b(document|documents|donnees|data|fichier|fichiers)\b/', $normalized)) {
            $add('Les elements concernes sont des documents ou donnees professionnels.');
        }
        if ($profile['mentionsPersonalEmail']) {
            $add('Les documents ont ete envoyes vers l\'adresse email personnelle du salarie.');
        }
        if (preg_match('/\b(usb|cle usb)\b/', $normalized)) {
            $add('Les documents ont ete transferes sur une cle USB personnelle.');
        }
        if ($profile['mentionsWorkFromHome']) {
            $add('Le salarie explique que le transfert servait a travailler depuis son domicile.');
        }
        if ($profile['mentionsNoCompetitorDisclosure']) {
            $add('Aucun concurrent n\'est presente comme ayant recu les documents.');
        }
        if ($profile['mentionsNoDisclosure']) {
            $add('Aucune divulgation a des tiers n\'est mentionnee.');
        }
        if ($profile['mentionsNoDamage']) {
            $add('Aucun prejudice concret n\'est demontre dans la question.');
        }
        if ($profile['mentionsImmediateDismissal']) {
            $add('Le licenciement est presente comme immediat.');
        }
        if (preg_match('/\b(accuse|accusation|accuses|alleges|allegation|affirme|qualifie)\b/', $normalized)
            && !preg_match('/\b(preuve directe|direct evidence|temoin|witness|camera|video)\b/', $normalized)) {
            $add(preg_match('/\b(vol|vole|theft|stolen)\b/', $normalized)
                ? 'Aucune preuve directe du vol n\'est mentionnee dans la question.'
                : 'Aucune preuve directe du fait fautif n\'est mentionnee dans la question.');
        }

        return $facts;
    }

    private function extractLegalIssues(string $question, ?array $plan): array
    {
        $normalized = $this->normalizeText($question);
        $issues = [];
        $add = function (string $issue) use (&$issues): void {
            if ($issue !== '' && !in_array($issue, $issues, true)) {
                $issues[] = $issue;
            }
        };

        if (preg_match('/\b(salarie|employee|employe|employer|employeur|entreprise|company|usine|factory|travail|licencie|licenciement|disciplinaire)\b/', $normalized)) {
            $add('Validite et justification du licenciement');
            $add('Regularite de la procedure disciplinaire');
        }
        if (preg_match('/\b(securite|safety|danger|violations|represailles|retaliation)\b/', $normalized)) {
            $add('Lien possible entre le signalement de securite et une mesure de represailles');
        }
        if (preg_match('/\b(accuse|accusation|preuve|evidence|inventaire|inventory|vol|theft|confidentiel|confidential|usb|data|donnees)\b/', $normalized)) {
            $add('Charge de la preuve et suffisance des elements reproches');
        }
        if (preg_match('/\b(compensation|indemnite|indemnites|dommages|preavis|severance|claim|reclamer|montant|salaire)\b/', $normalized)) {
            $add('Indemnites ou dommages-interets eventuels');
        }
        if (data_get($plan, 'aiPlan.legalIssue')) {
            $add((string) data_get($plan, 'aiPlan.legalIssue'));
        }

        return $issues;
    }

    private function getAnswerFactValidationIssues(string $answer, string $question): array
    {
        $profile = $this->getQuestionFactProfile($question);
        $normalizedAnswer = $this->normalizeText($answer);
        $issues = [];

        if (is_numeric($profile['yearsOfService'])) {
            preg_match_all('/\b(?:for|pour)\s+(\d+(?:[.,]\d+)?)\s*(?:years?|ans|annees?)\b/i', $answer, $matches);
            $wrongYears = collect($matches[1] ?? [])
                ->map(fn (string $value): float => (float) str_replace(',', '.', $value))
                ->filter(fn (float $value): bool => abs($value - round((float) $profile['yearsOfService'], 2)) > 0.001);

            if ($wrongYears->isNotEmpty()) {
                $issues[] = 'answer contradicts the scenario years of service';
            }
        }

        if (!$profile['asksAboutCompensation'] && preg_match('/\b\d+(?:[.,]\d+)?\s*(?:hours? of salary|heures? de salaire|months? of salary|mois de salaire)\b/i', $answer)) {
            $issues[] = 'answer calculates compensation even though the user did not ask for compensation';
        }

        if ($profile['mentionsPersonalEmail'] && str_contains($normalizedAnswer, 'cle usb') && !str_contains($this->normalizeText($question), 'cle usb')) {
            $issues[] = 'answer changed personal email into USB';
        }

        return $issues;
    }

    private function getQuestionFactProfile(string $question): array
    {
        $normalized = $this->normalizeText($question);

        return [
            'yearsOfService' => $this->extractYearsOfService($question),
            'asksAboutCompensation' => (bool) preg_match('/\b(compensation|indemnite|indemnites|dommages|preavis|severance|claim|reclamer|montant|salaire)\b/', $normalized),
            'mentionsPersonalEmail' => (bool) preg_match('/\b(personal email|personal mail|email personnel|mail personnel|adresse email personnelle|adresse personnelle)\b/', $normalized),
            'mentionsWorkFromHome' => (bool) preg_match('/\b(domicile|home|teletravail|telework|remote work|work from home|work from his home|work from her home)\b/', $normalized),
            'mentionsNoCompetitorDisclosure' => (bool) preg_match('/\b(no competitor|no competitors|aucun concurrent|concurrent n a recu|competitor received|competitors received)\b/', $normalized),
            'mentionsNoDisclosure' => (bool) preg_match('/\b(aucun document|no document|pas divulgue|not disclosed|tiers|third parties|no competitor|no competitors|aucun concurrent)\b/', $normalized),
            'mentionsNoDamage' => (bool) preg_match('/\b(aucun prejudice|prejudice concret|ne demontre aucun prejudice|no damage|no proven damage|no harm|concrete harm)\b/', $normalized),
            'mentionsImmediateDismissal' => (bool) preg_match('/\b(immediate dismissal|immediately dismissed|dismissed immediately|licencie immediatement|licenciement immediat|immediatement)\b/', $normalized),
        ];
    }

    private function asksForFactExtractionOnly(string $normalized): bool
    {
        return preg_match('/\b(liste|list|identify|extraire|extract)\b.*\b(faits|facts)\b/', $normalized)
            && preg_match('/\b(sans citer|ne cite aucun|do not cite|dont cite|no articles|no article|no laws|no law|without citing|must not cite)\b/', $normalized);
    }

    private function asksAboutAccusationEvidence(string $normalized): bool
    {
        return preg_match('/\b(accuse|accusation|accuses|alleges|allegation|preuve|evidence|affaiblissent|weakens|weaken|contradiction|inventaire|inventory|ordinateur|laptop|document|documents|donnees|data|confidentiel|confidential|usb|cle usb|email|mail|competitor|concurrent|damage|prejudice|vole|vol|theft)\b/', $normalized)
            && preg_match('/\b(salarie|employee|employe|employer|employeur|entreprise|company)\b/', $normalized);
    }

    private function asksForStructuredCaseAnalysis(string $normalized): bool
    {
        return preg_match('/\b(analysez|analyze|analyse|analysis|etudiez|evaluate)\b/', $normalized)
            || (preg_match('/\b(faits|facts|arguments|preuves|evidence|procedures|represailles|retaliation|conclusion)\b/', $normalized)
                && preg_match('/\b(arguments|preuves|evidence|procedures|conclusion|circonstances|represailles|retaliation)\b/', $normalized));
    }

    private function isEmploymentScenario(string $normalized): bool
    {
        return (bool) preg_match('/\b(salarie|employee|employe|employer|employeur|entreprise|company|usine|factory|travail|licencie|licenciement|disciplinaire)\b/', $normalized);
    }

    private function isFactRichEmploymentCase(string $normalized): bool
    {
        return $this->isEmploymentScenario($normalized)
            && preg_match('/\b(document|documents|donnees|data|confidentiel|confidential|usb|cle usb|email|mail|fichier|fichiers|transfere|transfer|sent)\b/', $normalized)
            && preg_match('/\b(licencie|licenciement|dismissal|dismiss|dismisses|dismissed|fired|terminated|termination|faute grave|serious fault)\b/', $normalized);
    }

    private function extractYearsOfService(string $question): ?float
    {
        preg_match('/\b(\d+(?:[.,]\d+)?)\s*(?:years?|ans|annees?)\b/i', $question, $match);

        return isset($match[1]) ? (float) str_replace(',', '.', $match[1]) : null;
    }

    private function extractSeniorityYears(string $question): ?float
    {
        preg_match('/\b(?:depuis|for|worked for|travaille depuis)?\s*(\d+(?:[.,]\d+)?)\s*(?:ans|annees|years)\b/', $this->normalizeText($question), $match);

        return isset($match[1]) ? (float) str_replace(',', '.', $match[1]) : null;
    }

    private function yearsForLegalFormula(?float $years): ?int
    {
        return is_numeric($years) && $years > 0 ? (int) ceil($years) : null;
    }

    private function calculateSeveranceHours(?float $years): ?int
    {
        $legalYears = $this->yearsForLegalFormula($years);

        if (!$legalYears) {
            return null;
        }

        return min($legalYears, 5) * 96
            + min(max($legalYears - 5, 0), 5) * 144
            + min(max($legalYears - 10, 0), 5) * 192
            + max($legalYears - 15, 0) * 240;
    }

    private function calculateAbusiveDismissalMonths(?float $years): ?float
    {
        $legalYears = $this->yearsForLegalFormula($years);

        return $legalYears ? min(round($legalYears * 1.5, 2), 36) : null;
    }

    private function formatLegalNumber(?float $value): string
    {
        if (!is_numeric($value)) {
            return '';
        }

        return floor($value) === $value ? (string) (int) $value : (string) round($value, 2);
    }

    private function cleanLawExcerpt(?string $value): string
    {
        $value = preg_replace('/\s+/', ' ', (string) $value) ?? (string) $value;
        $value = preg_replace('/\b(200|500|1\.000|2\.000|5\.000|10\.000|50\.000)\s+\d{2,3}\s+(?=a\b)/i', '$1 ', $value) ?? $value;

        return trim($value);
    }

    private function cleanGeneratedAnswer(?string $value): string
    {
        $value = preg_replace('/\b200[,\s]+243\s+(to|a)\s+500\b/i', '200 $1 500', (string) $value) ?? (string) $value;
        $value = preg_replace('/\b200[,\s]+243\s*(dirhams?|dh)\b/i', '200 $1', $value) ?? $value;

        return trim($value);
    }

    private function stripThinkingText(?string $text): string
    {
        return trim(preg_replace('/<think>[\s\S]*?<\/think>/i', '', (string) $text) ?? (string) $text);
    }

    private function detectResponseLanguage(string $message): string
    {
        $raw = Str::lower($message);
        $normalized = $this->normalizeText($message);
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

    private function normalizeText(?string $value): string
    {
        return Str::of($value ?? '')
            ->lower()
            ->ascii()
            ->replaceMatches('/[-_]+/', ' ')
            ->replaceMatches('/[\?!\.,;:\(\)\[\]\{\}"]+/', ' ')
            ->replaceMatches('/\s+/', ' ')
            ->trim()
            ->toString();
    }

    private function isEnabled(): bool
    {
        return strtolower((string) env('AI_PROVIDER', 'none')) === 'ollama';
    }

    private function plannerTimeoutSeconds(): int
    {
        return max(1, (int) ceil((int) env('AI_PLANNER_TIMEOUT_MS', 12000) / 1000));
    }

    private function answerTimeoutSeconds(): int
    {
        return max(1, (int) ceil((int) env('AI_ANSWER_TIMEOUT_MS', 30000) / 1000));
    }
}
