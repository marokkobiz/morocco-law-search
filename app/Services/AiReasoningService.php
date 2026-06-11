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
        'civilProcedure' => [
            'allowedDocumentTitles' => ['Code de procedure civile'],
            'allowedCategories' => ['civil_procedure'],
        ],
        'criminal' => [
            'allowedDocumentTitles' => ['Code penal'],
            'allowedCategories' => ['criminal'],
        ],
        'bankingFinance' => [
            'allowedDocumentTitles' => ['Etablissements de credit et organismes assimiles', 'Statut de Bank Al-Maghrib'],
            'allowedCategories' => ['banking_finance'],
        ],
        'tax' => [
            'allowedDocumentTitles' => ['Code de recouvrement des creances publiques', 'Application de la taxe sur la valeur ajoutee'],
            'allowedCategories' => ['tax'],
        ],
        'administrative' => [
            'allowedDocumentTitles' => ['Simplification des procedures et des formalites administratives', 'Communes', 'Tribunaux administratifs'],
            'allowedCategories' => ['administrative_urbanism'],
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
                'Societe en nom collectif et SARL',
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
                'Immatriculation fonciere',
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
            return $this->withDomainRoute($localPlan, $question);
        }

        $domainPlan = $this->createDomainSearchPlan($question);

        if ($domainPlan) {
            return $domainPlan;
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
                'model' => env('OLLAMA_PLANNER_MODEL'),
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

            return $aiPlan && $aiPlan['searchQueries']
                ? $this->withDomainRoute($aiPlan, $question)
                : $this->createDomainSearchPlan($question);
        } catch (Throwable $error) {
            Log::warning('AI search planning failed', ['message' => $error->getMessage()]);

            return $this->createDomainSearchPlan($question);
        }
    }

    private function createDomainSearchPlan(string $question): ?array
    {
        $classifier = new LegalDomainClassifier();
        $taxonomy = $classifier->classifyQuery($question);
        $domain = $taxonomy['domain'] ?? null;
        $scores = collect($taxonomy['scores'] ?? [])->sortDesc()->values();
        $topScore = (int) ($scores->get(0) ?? 0);
        $runnerUp = (int) ($scores->get(1) ?? 0);

        if (!$domain || $topScore < 6) {
            return null;
        }

        $terms = collect($classifier->conceptTermsForTaxonomy($taxonomy, 18))
            ->filter(fn (string $term): bool => Str::length($term) >= 4)
            ->values();
        $queries = $terms
            ->chunk(3)
            ->map(fn ($chunk): string => $chunk->implode(' '))
            ->filter()
            ->take(6)
            ->values()
            ->all();

        if (!$queries) {
            return null;
        }

        return [
            'legalIssue' => Str::headline(str_replace('_', ' ', $domain)),
            'reasoningGoal' => 'Identify and apply the relevant Moroccan legal rules in the detected domain.',
            'needsLawSearch' => true,
            'searchQueries' => $queries,
            'trustedArticleAnchors' => [],
            'relevanceTerms' => $terms->take(24)->all(),
            'allowedDocumentTitles' => [],
            'allowedCategories' => [],
            'dominantDomain' => $domain,
            'domainConfidence' => $this->domainConfidence($topScore, $runnerUp),
            'domainScores' => $taxonomy['scores'] ?? [],
            'facts' => $this->extractLegalFacts($question),
        ];
    }

    private function withDomainRoute(array $plan, string $question): array
    {
        $classifier = new LegalDomainClassifier();
        $rawTaxonomy = $classifier->classifyQuery($question);
        $rawScores = collect($rawTaxonomy['scores'] ?? [])->sortDesc()->values();
        $rawConfidence = $this->domainConfidence((int) ($rawScores->get(0) ?? 0), (int) ($rawScores->get(1) ?? 0));
        $taxonomy = $classifier->classifyQuery($question.' '.($plan['legalIssue'] ?? '').' '.implode(' ', $plan['searchQueries'] ?? []));
        $scores = collect($taxonomy['scores'] ?? [])->sortDesc()->values();
        $confidence = $this->domainConfidence((int) ($scores->get(0) ?? 0), (int) ($scores->get(1) ?? 0));

        if (($rawTaxonomy['domain'] ?? null)
            && ($rawTaxonomy['domain'] ?? null) !== ($taxonomy['domain'] ?? null)
            && $rawConfidence === 'strong') {
            $taxonomy = $rawTaxonomy;
            $confidence = $rawConfidence;
        }

        if (collect($plan['allowedCategories'] ?? [])->filter()->unique()->count() > 1) {
            $confidence = 'weak';
        }

        $dominantDomain = $taxonomy['domain'] ?? ($plan['dominantDomain'] ?? null);
        $allowedRoute = $classifier->classifyQuery(implode(' ', $plan['allowedCategories'] ?? []))['domain'] ?? null;
        $protectedSourceRoute = $this->protectedSourceRoute($plan);

        if ($protectedSourceRoute) {
            $dominantDomain = $protectedSourceRoute;
            $confidence = 'strong';
        }

        if (!$protectedSourceRoute && $dominantDomain && in_array($confidence, ['moderate', 'strong'], true) && $allowedRoute && $allowedRoute !== $dominantDomain) {
            $domainTerms = collect($classifier->conceptTermsForTaxonomy($taxonomy, 18))->filter()->values();
            $plan['searchQueries'] = $domainTerms->chunk(3)->map(fn ($chunk): string => $chunk->implode(' '))->take(6)->values()->all();
            $plan['relevanceTerms'] = $domainTerms->take(24)->all();
            $plan['allowedDocumentTitles'] = [];
            $plan['allowedCategories'] = [];
            $plan['legalIssue'] = Str::headline(str_replace('_', ' ', $dominantDomain));
        }

        return array_merge($plan, [
            'dominantDomain' => $dominantDomain,
            'domainConfidence' => $confidence,
            'domainScores' => $taxonomy['scores'] ?? [],
        ]);
    }

    private function protectedSourceRoute(array $plan): ?string
    {
        $sourceText = $this->normalizeText(implode(' ', [
            ...($plan['allowedDocumentTitles'] ?? []),
            ...($plan['searchQueries'] ?? []),
            ...($plan['trustedArticleAnchors'] ?? []),
        ]));

        if (str_contains($sourceText, 'code de recouvrement des creances publiques')) {
            return 'tax';
        }

        return null;
    }

    private function domainConfidence(int $topScore, int $runnerUp): string
    {
        $gap = $topScore - $runnerUp;

        return $topScore >= 14 && $gap >= 6 ? 'strong' : ($topScore >= 8 && $gap >= 3 ? 'moderate' : 'weak');
    }

    public function answer(string $question, array $citations, ?array $plan = null): ?string
    {
        if (!$citations) {
            return null;
        }

        $language = data_get($plan, 'responseLanguage') ?: $this->detectResponseLanguage($question);
        $languageName = match ($language) {
            'fr' => 'French',
            'ar' => 'Arabic',
            default => 'English',
        };

        if (!$this->isEnabled()) {
            return null;
        }

        $structured = $this->shouldUseStructuredAnswer($question, $plan);
        $formatInstruction = $structured
            ? match ($language) {
                'fr' => "La question décrit une situation concrète : structure la réponse en cinq parties — Faits, Questions juridiques, Articles applicables, Analyse, Conclusion (avec les limites ou informations manquantes).",
                'ar' => 'السؤال يصف وضعية ملموسة: نظم الجواب في خمسة أقسام — الوقائع، الأسئلة القانونية، المواد القابلة للتطبيق، التحليل، الخلاصة (مع الحدود أو المعلومات الناقصة).',
                default => 'The question describes a concrete situation: organize the answer in five parts — Facts, Legal questions, Applicable articles, Analysis, Conclusion (including limits or missing information).',
            }
            : match ($language) {
                'fr' => "Réponds directement et brièvement, sans sections ni titres : énonce d'abord la règle applicable avec sa source, puis les conditions ou exceptions utiles. Une à trois courts paragraphes.",
                'ar' => 'أجب مباشرة وباختصار، دون أقسام أو عناوين: اذكر أولا القاعدة القانونية المطبقة مع مصدرها، ثم الشروط أو الاستثناءات المفيدة. فقرة إلى ثلاث فقرات قصيرة.',
                default => 'Answer directly and briefly, without sections or headings: state the applicable rule with its source first, then any useful conditions or exceptions. One to three short paragraphs.',
            };

        // Keep the prompt small: prefill time dominates local-LLM latency.
        $citationContext = collect($citations)
            ->take(8)
            ->map(fn (array $citation, int $index): string => $this->formatCitationForPrompt($citation, $index))
            ->implode("\n\n");
        $facts = $this->extractLegalFacts($question);
        $planFacts = collect(data_get($plan, 'aiPlan.facts', []))
            ->map(fn (mixed $fact): string => trim((string) $fact))
            ->filter()
            ->values()
            ->all();
        $facts = collect([...$planFacts, ...$facts])
            ->unique(fn (string $fact): string => $this->normalizeText($fact))
            ->values()
            ->all();
        $issues = $this->extractLegalIssues($question, $plan);

        try {
            $answerText = $this->callOllama([
                'model' => env('OLLAMA_ANSWER_MODEL'),
                'timeout' => $this->answerTimeoutSeconds(),
                'temperature' => 0.2,
                'num_predict' => $structured ? 950 : 500,
                'think' => (bool) env('OLLAMA_ANSWER_THINKING', false),
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => "You are a careful Moroccan legal assistant. Write the answer in {$languageName} only; official source titles, article names, and quoted legal excerpts may stay in their original language. Use ONLY the provided law excerpts as legal authority and cite them with [1], [2], etc. Every legal rule, deadline, amount, or procedure you state must be supported by a cited excerpt. Apply the law to the user's facts; answer every part of the question. If the excerpts do not actually answer the question, say sources insuffisantes / المصادر غير كافية and name what is missing instead of stretching unrelated sources. {$formatInstruction}",
                    ],
                    [
                        'role' => 'user',
                        'content' => implode("\n", array_filter([
                            "User question: {$question}",
                            $facts ? 'Known facts: '.json_encode($facts, JSON_UNESCAPED_UNICODE) : '',
                            $issues ? 'Legal issues to address: '.json_encode($issues, JSON_UNESCAPED_UNICODE) : '',
                            '',
                            'Law excerpts:',
                            $citationContext,
                            '',
                            'Now write only the final answer for the user — no JSON, no markdown, no commentary about these instructions.',
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

            if (!$answer) {
                return null;
            }

            return $answer;
        } catch (Throwable $error) {
            Log::warning('AI answer generation failed', ['message' => $error->getMessage()]);

            return null;
        }
    }

    public function verifyAnswerSupport(string $answer, array $citations, string $language = 'en'): array
    {
        return $this->answerSupportVerifier()->audit($answer, $citations, $language);
    }

    private function answerSupportVerifier(): AnswerSupportVerifier
    {
        try {
            return app(AnswerSupportVerifier::class);
        } catch (Throwable) {
            return new AnswerSupportVerifier();
        }
    }

    /**
     * Long, fact-rich questions describe a personal situation and deserve the
     * structured case analysis; short focused questions get a direct answer.
     */
    private function shouldUseStructuredAnswer(string $question, ?array $plan): bool
    {
        $wordCount = count(preg_split('/\s+/u', trim($question)) ?: []);
        $factCount = count((array) data_get($plan, 'aiPlan.facts', []));

        return $wordCount >= 30 || $factCount >= 3;
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
        $hasSaleOwnershipHeirs = $matches->contains(fn (array $profile): bool => $profile['legalIssue'] === 'sale ownership and heirs dispute');
        $hasRealEstateDoubleSale = $matches->contains(fn (array $profile): bool => $profile['legalIssue'] === 'unregistered real estate double sale priority');
        $hasCoownershipSyndic = $matches->contains(fn (array $profile): bool => $profile['legalIssue'] === 'coownership syndic designation');
        $hasFamilyCustody = $matches->contains(fn (array $profile): bool => $profile['legalIssue'] === 'family custody after mother remarriage');
        $hasLandRegistration = $matches->contains(fn (array $profile): bool => $profile['legalIssue'] === 'land registration opposition');
        $hasPublicDebtCollection = $matches->contains(fn (array $profile): bool => $profile['legalIssue'] === 'public debt forced collection');
        $hasVatRefund = $matches->contains(fn (array $profile): bool => $profile['legalIssue'] === 'vat refund request');
        $hasCivilDebtProof = $matches->contains(fn (array $profile): bool => $profile['legalIssue'] === 'civil debt loan and proof dispute');
        $hasAdministrativeDelay = $matches->contains(fn (array $profile): bool => $profile['legalIssue'] === 'administrative maximum processing delay');
        $hasLaborDisciplinaryHearing = $matches->contains(fn (array $profile): bool => $profile['legalIssue'] === 'labor disciplinary hearing before dismissal');
        $hasTerritorialJurisdiction = $matches->contains(fn (array $profile): bool => $profile['legalIssue'] === 'civil procedure territorial jurisdiction');
        $hasBankingCreditApproval = $matches->contains(fn (array $profile): bool => $profile['legalIssue'] === 'banking credit institution approval');
        $hasSarlStatus = $matches->contains(fn (array $profile): bool => $profile['legalIssue'] === 'sarl commercial status and legal personality');
        $hasWorkplaceCriminalMisappropriation = $matches->contains(fn (array $profile): bool => $profile['legalIssue'] === 'workplace criminal theft or breach of trust');
        $hasCompanyTaxDispute = $matches->contains(fn (array $profile): bool => $profile['legalIssue'] === 'company tax declaration penalty or reassessment');
        $hasPropertyPossessionClaim = $matches->contains(fn (array $profile): bool => $profile['legalIssue'] === 'property possession or ownership claim');
        $criminalDominatesLabor = $hasWorkplaceCriminalMisappropriation
            && !preg_match('/\b(dismiss|dismissed|dismissal|licenciement|licencie|accuse|accusation|disciplinary|disciplinaire)\b/', $normalized);
        $hasWorkplaceAccusation = $hasEmployment
            && preg_match('/\b(accuse|accusation|preuve|evidence|inventaire|inventory|ordinateur|laptop|vole|vol|theft)\b/', $normalized);
        $hasWorkplaceTheftAccusation = $hasEmployment
            && preg_match('/\b(vol|vole|theft|stolen|steal|soustraction)\b/', $normalized);
        $hasStructuredEmploymentAnalysis = $hasEmployment
            && (preg_match('/\b(analysez|analyze|analyse|analysis|etudiez|evaluate)\b/', $normalized)
                || preg_match('/\b(faits|arguments|preuves|procedures|represailles|conclusion)\b/', $normalized));
        $asksAboutCompensation = preg_match('/\b(compensation|indemnite|indemnites|dommages|preavis|severance|claim|reclamer|montant|salaire)\b/', $normalized);
        $supplementalSearchQueries = collect();
        $supplementalRelevanceTerms = collect();
        $supplementalAllowedDocumentTitles = collect();
        $supplementalAllowedCategories = collect();

        if ($hasCommercialLease) {
            $matches = $matches->reject(fn (array $profile): bool => $profile['legalIssue'] === 'landlord and tenant rent or residential eviction')->values();
        }

        if ($hasFamilyCustody) {
            $matches = $matches->reject(fn (array $profile): bool => in_array($profile['legalIssue'], [
                'pregnancy or maternity dismissal',
                'employment termination',
                'civil debt loan and proof dispute',
            ], true))->values();
        }

        if ($hasLandRegistration) {
            $matches = $matches->reject(fn (array $profile): bool => in_array($profile['legalIssue'], [
                'administrative receipt for request',
                'administrative maximum processing delay',
                'civil debt loan and proof dispute',
            ], true))->values();
        }

        if ($hasRealEstateDoubleSale) {
            $matches = $matches->reject(fn (array $profile): bool => in_array($profile['legalIssue'], [
                'land registration opposition',
                'real estate law overview',
                'landlord and tenant rent or residential eviction',
                'sale contract with missing or partial delivery',
                'sale ownership and heirs dispute',
            ], true))->values();
        }

        if ($hasPropertyPossessionClaim) {
            $matches = $matches->reject(fn (array $profile): bool => in_array($profile['legalIssue'], [
                'land registration opposition',
                'sale delivery legal definition',
                'sale contract with missing or partial delivery',
            ], true))->values();
        }

        if ($hasCoownershipSyndic) {
            $matches = $matches->reject(fn (array $profile): bool => in_array($profile['legalIssue'], [
                'commercial company rules',
                'sarl commercial status and legal personality',
            ], true))->values();
        }

        if ($hasPublicDebtCollection) {
            $matches = $matches->reject(fn (array $profile): bool => in_array($profile['legalIssue'], [
                'administrative receipt for request',
                'administrative maximum processing delay',
                'civil debt loan and proof dispute',
            ], true))->values();
        }

        if ($hasVatRefund) {
            $matches = $matches->reject(fn (array $profile): bool => in_array($profile['legalIssue'], [
                'consumer protection',
                'civil debt loan and proof dispute',
            ], true))->values();
        }

        if ($hasAdministrativeDelay) {
            $matches = $matches->reject(fn (array $profile): bool => $profile['legalIssue'] === 'administrative receipt for request')->values();
        }

        if ($hasLaborDisciplinaryHearing) {
            $matches = $matches->reject(fn (array $profile): bool => $profile['legalIssue'] === 'employment termination')->values();
        }

        if ($hasTerritorialJurisdiction) {
            $matches = $matches->reject(fn (array $profile): bool => $profile['legalIssue'] === 'civil procedure appeal deadline')->values();
        }

        if ($hasBankingCreditApproval) {
            $matches = $matches->reject(fn (array $profile): bool => in_array($profile['legalIssue'], [
                'commercial company rules',
                'civil debt loan and proof dispute',
            ], true))->values();
        }

        if ($hasSarlStatus) {
            $matches = $matches->reject(fn (array $profile): bool => $profile['legalIssue'] === 'commercial company rules')->values();
        }

        if ($hasCompanyTaxDispute) {
            $matches = $matches->reject(fn (array $profile): bool => in_array($profile['legalIssue'], [
                'commercial company rules',
                'sarl commercial status and legal personality',
            ], true))->values();
        }

        if ($hasCivilDebtProof && !$hasBankingCreditApproval) {
            $matches = $matches->reject(fn (array $profile): bool => in_array($profile['legalIssue'], [
                'banking professional secrecy',
                'banking credit institution approval',
                'civil procedure appeal deadline',
            ], true))->values();
        }

        if ($hasEmployment && !$criminalDominatesLabor) {
            $matches = $matches->reject(fn (array $profile): bool => in_array($profile['legalIssue'], ['commercial company rules', 'stolen property or theft'], true))->values();
        }

        if ($criminalDominatesLabor) {
            $matches = $matches->filter(fn (array $profile): bool => $profile['legalIssue'] === 'workplace criminal theft or breach of trust')->values();
        } elseif ($hasWorkplaceAccusation) {
            $matches = $matches->filter(fn (array $profile): bool => in_array($profile['legalIssue'], [
                'employment termination',
                'workplace criminal theft or breach of trust',
            ], true))->values();
        }

        if ($hasWorkplaceTheftAccusation) {
            $supplementalSearchQueries = $supplementalSearchQueries->concat([
                'soustraction frauduleuse code penal',
                'vol code penal',
                'preuve vol code penal',
            ]);
            $supplementalRelevanceTerms = $supplementalRelevanceTerms->concat(['vol', 'soustraction frauduleuse', 'theft', 'preuve']);
            $supplementalAllowedDocumentTitles = $supplementalAllowedDocumentTitles->push('Code penal');
            $supplementalAllowedCategories = $supplementalAllowedCategories->push('criminal');
        }

        if ($hasSaleOwnershipHeirs && !$hasRealEstateDoubleSale) {
            $supplementalSearchQueries = $supplementalSearchQueries->concat([
                'succession heritiers code de la famille',
                'heritiers ayants droit succession',
                'article 488 code des obligations et des contrats vente consentement chose prix',
                'article 491 code des obligations et des contrats propriete chose vendue',
                'article 499 code des obligations et des contrats delivrance possession',
                'article 500 code des obligations et des contrats choses mobilieres tradition reelle',
                'article 229 code des obligations et des contrats heritiers ayants cause',
            ]);
            $supplementalRelevanceTerms = $supplementalRelevanceTerms->concat(['vente', 'consentement', 'chose', 'prix', 'propriete', 'delivrance', 'possession', 'heritier', 'heritiers', 'ayants cause']);
            $supplementalAllowedDocumentTitles = $supplementalAllowedDocumentTitles
                ->push('Code des Obligations et des Contrats')
                ->push('Code de la famille');
            $supplementalAllowedCategories = $supplementalAllowedCategories
                ->push('civil')
                ->push('family');
        }

        if ($hasEmployment && preg_match('/\b(procedure|procedural|disciplinaire|audition|entendu|defense|motif valable)\b/', $normalized)) {
            $supplementalSearchQueries = $supplementalSearchQueries->concat([
                'article 62 code du travail procedure disciplinaire audition defense',
                'article 35 code du travail licenciement motif valable',
            ]);
            $supplementalRelevanceTerms = $supplementalRelevanceTerms->concat(['procedure disciplinaire', 'audition', 'defense', 'motif valable']);
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

        $profileSearchQueries = $matches
            ->flatMap(fn (array $profile): array => $profile['searchQueries'])
            ->unique()
            ->values();
        $articleAnchors = $profileSearchQueries
            ->concat($supplementalSearchQueries)
            ->filter(fn (string $query): bool => (bool) preg_match('/\barticle\s+\d+(?:\s*(?:bis|ter|quater))?\b/i', $query))
            ->take(18)
            ->values()
            ->all();

        $searchQueries = $profileSearchQueries
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

        $searchQueries = collect($searchQueries)
            ->concat($supplementalSearchQueries)
            ->map(fn (string $query): string => $this->userProvidedArticleNumber($question)
                ? $query
                : $this->stripArticleNumberFromQuery($query))
            ->filter(fn (string $query): bool => $query !== '')
            ->unique()
            ->take(18)
            ->values()
            ->all();

        return [
            'legalIssue' => $matches->pluck('legalIssue')->implode(' / '),
            'reasoningGoal' => 'Explain the likely legal answer using the most relevant Moroccan law excerpts.',
            'needsLawSearch' => true,
            'searchQueries' => $searchQueries,
            'trustedArticleAnchors' => $articleAnchors,
            'relevanceTerms' => $matches->flatMap(fn (array $profile): array => $profile['relevanceTerms'] ?? [])->concat($supplementalRelevanceTerms)->unique()->take(28)->values()->all(),
            'allowedDocumentTitles' => $matches->flatMap(fn (array $profile): array => $profile['allowedDocumentTitles'] ?? [])->concat($supplementalAllowedDocumentTitles)->unique()->values()->all(),
            'allowedCategories' => $matches->flatMap(fn (array $profile): array => $profile['allowedCategories'] ?? [])->concat($supplementalAllowedCategories)->unique()->values()->all(),
            'facts' => $this->extractLegalFacts($question),
        ];
    }

    private function legalQueryProfiles(): array
    {
        return [
            $this->profile('family custody after mother remarriage', [
                '/\b(custody|child custody|mother remarried|remarried mother|hadana|garde)\b.*\b(remarry|remarried|remariage|mother|mere|divorce)\b/',
                '/\b(remarry|remarried|remariage|mother|mere|divorce)\b.*\b(custody|child custody|hadana|garde)\b/',
                '/\b(ar custody remarriage|article 175 code de la famille)\b/',
            ], [
                'article 175 code de la famille garde mere remariage',
                'article 173 code de la famille conditions garde',
                'article 171 code de la famille garde mere enfant',
                'code de la famille garde remariage mere enfant',
            ], 'family', ['garde', 'mere', 'remariage', 'enfant', 'decheance', 'interet enfant']),
            $this->profile('family child support after divorce', [
                '/\b(child support|alimony|maintenance|pension alimentaire|nafaqa)\b/',
                '/\b(divorce)\b.*\b(enfant|children|pension|support|alimony)\b/',
                '/\b(fixation|fixer|evaluer|evaluation)\b.*\b(pension|nafaka|enfants)\b/',
                '/\b(pension|nafaka)\b.*\b(enfant|enfants|children)\b/',
            ], [
                'article 85 code de la famille pension alimentaire enfants divorce',
                'article 190 code de la famille estimation pension alimentaire',
                'article 168 code de la famille logement enfant garde pension alimentaire',
                'code de la famille pension alimentaire enfants',
            ], 'family', ['pension alimentaire', 'enfants', 'juge', 'besoins', 'parents']),
            $this->profile('unregistered real estate double sale priority', [
                '/\b(double sale|sold .* two buyers|two buyers|competing buyers|priority between buyers|unregistered property)\b/',
                '/\b(vente|vendu|vendeur|acheteur|acquereur)\b.*\b(deux acheteurs|priorite|immeuble non immatricule|bien non immatricule|non immatricule)\b/',
                '/\b(ar real estate double sale priority|double vente immobiliere non immatriculee)\b/',
            ], [
                // Double sale priority is governed by the COC sale rules
                // (Articles 488/491), not the land-registration regime.
                'Code des Obligations et des Contrats vente meme chose deux acheteurs',
                'vente immeuble non immatricule date certaine priorite acheteur',
                'vente de la meme chose a deux personnes possession bonne foi',
                'immeuble non immatricule deux acquereurs priorite',
            ], 'civilContracts', ['vente', 'immeuble non immatricule', 'deux acquereurs', 'priorite', 'date certaine', 'possession']),
            $this->profile('property possession or ownership claim', [
                '/\b(property possession|ownership claim|possessory action|action en revendication|revendication immobiliere|possession immobiliere)\b/',
                '/\b(ar property possession|ar ownership claim|hiyaza property|istihqaq property)\b/',
            ], [
                'code des droits reels possession propriete immobiliere',
                'action en revendication propriete immobiliere',
                'revendication droit de propriete',
                'possession immeuble non immatricule',
            ], 'realEstate', ['possession', 'revendication', 'droit de propriete', 'propriete immobiliere', 'immeuble non immatricule']),
            $this->profile('property preemption source coverage check', [
                '/\b(property preemption source coverage|right of pre-emption|right of preemption|droit de preemption|preemption immobiliere)\b/',
            ], [
                'droit de preemption propriete immobiliere',
                'preemption immobiliere',
                'right of pre-emption property',
            ], 'realEstate', ['preemption', 'droit de preemption', 'propriete immobiliere']),
            $this->profile('land registration opposition', [
                '/\b(opposition|oppose|land registration|immatriculation fonciere|titre foncier|bornage)\b/',
                '/\b(article 24 immatriculation fonciere)\b/',
            ], [
                'article 24 immatriculation fonciere opposition delai deux mois bornage',
                'article 31 immatriculation fonciere opposition requérant',
                'immatriculation fonciere opposition avis cloture bornage',
            ], 'realEstate', ['opposition', 'immatriculation', 'bornage', 'titre foncier', 'delai deux mois']),
            $this->profile('coownership syndic designation', [
                '/\b(copropriete|coownership|co-ownership|syndic|assemblee generale)\b/',
            ], [
                'article 19 statut de la copropriete des immeubles batis syndic majorite trois quarts',
                'article 13 statut de la copropriete des immeubles batis syndicat personnalite morale',
                'article 16 ter statut de la copropriete assemblee generale',
                'statut de la copropriete des immeubles batis syndic',
            ], 'realEstate', ['copropriete', 'syndic', 'assemblee generale', 'majorite', 'trois quarts']),
            $this->profile('civil procedure appeal deadline', [
                '/\b(appeal deadline|civil appeal|appel|delai appel|jugement tribunal de premiere instance)\b/',
                '/\b(article 134 code de procedure civile)\b/',
            ], [
                'article 134 code de procedure civile appel delai trente jours',
                'code de procedure civile appel jugement tribunal premiere instance',
            ], 'civilProcedure', ['appel', 'delai', 'trente jours', 'tribunal de premiere instance']),
            $this->profile('civil procedure territorial jurisdiction', [
                '/\b(territorially competent|territorial jurisdiction|competence territoriale|defendeur|domicile)\b/',
                '/\b(procedure civile|matiere civile|poursuivre|poursuit|saisir)\b.*\b(defendeur|domicile|competence territoriale|tribunal)\b/',
            ], [
                'article 27 code de procedure civile competence territoriale domicile defendeur',
                'code de procedure civile tribunal domicile defendeur',
            ], 'civilProcedure', ['competence territoriale', 'domicile', 'defendeur', 'tribunal']),
            $this->profile('criminal fraud', [
                '/\b(fraud|scam|escroquerie|false statements|fausses affirmations|fausses qualites|trompe|tromperie|manoeuvres frauduleuses|obtenir de l argent|obtenir un profit)\b/',
            ], [
                'article 540 code penal escroquerie affirmations fallacieuses',
                'code penal escroquerie profit pecuniaire illegitime',
            ], 'criminal', ['escroquerie', 'affirmations fallacieuses', 'profit pecuniaire', 'erreur']),
            $this->profile('workplace criminal theft or breach of trust', [
                '/\b(employee|employer|worker|salarie|employe|employeur|travailleur)\b.*\b(vol|theft|abus de confiance|detournement|detourne|caisse|soustraction|misappropriation)\b/',
                '/\b(vol|theft|abus de confiance|detournement|detourne|caisse|soustraction|misappropriation)\b.*\b(employee|employer|worker|salarie|employe|employeur|travailleur)\b/',
                '/\b(ar workplace criminal misappropriation)\b/',
            ], [
                'abus de confiance code penal fonds remis',
                'detournement de fonds code penal',
                'soustraction frauduleuse code penal',
                'vol code penal',
            ], 'criminal', ['abus de confiance', 'detournement de fonds', 'soustraction frauduleuse', 'vol', 'code penal']),
            $this->profile('banking professional secrecy', [
                '/\b(bank secrecy|banking secrecy|professional secrecy|secret professionnel|client information|information client|confidentialite bancaire|information du client)\b/',
                '/\b(article 180 etablissements de credit)\b/',
            ], [
                'article 180 etablissements de credit organismes assimiles secret professionnel',
                'article 181 etablissements de credit secret professionnel exceptions',
                'etablissements de credit organismes assimiles secret professionnel',
            ], 'bankingFinance', ['secret professionnel', 'etablissement de credit', 'client', 'information']),
            $this->profile('banking credit institution approval', [
                '/\b(credit institution|etablissement de credit|agrement|agreement|approval|autorisation bancaire|bank approval|recevoir des fonds|accorder des credits)\b/',
            ], [
                'article 34 etablissements de credit organismes assimiles agrement avant exercer activite',
                'article 18 etablissements de credit personne non agreee',
                'etablissements de credit organismes assimiles agrement bank al maghrib',
            ], 'bankingFinance', ['agrement', 'etablissement de credit', 'bank al maghrib', 'exercer activite']),
            $this->profile('sarl commercial status and legal personality', [
                '/\b(sarl commercial personality|article 2 societe en nom collectif et sarl)\b/',
                '/\b(sarl|responsabilite limitee)\b.*\b(personnalite morale|commerciale par sa forme|forme commerciale|caractere commercial|commerciale|immatriculation)\b/',
                '/\b(personnalite morale|commerciale par sa forme|forme commerciale|caractere commercial|immatriculation)\b.*\b(sarl|responsabilite limitee)\b/',
            ], [
                'article 2 societe en nom collectif et sarl commerciales par forme personnalite morale immatriculation',
                'societe en nom collectif et sarl responsabilite limitee personnalite morale',
                'societe en nom collectif et sarl immatriculation registre commerce',
            ], 'commercialCompany', ['sarl', 'societe responsabilite limitee', 'personnalite morale', 'immatriculation', 'commerciale par forme']),
            $this->profile('public debt forced collection', [
                '/\b(public debt|forced collection|recouvrement force|recouvrement des creances publiques|commandement|saisie|dette publique|creance publique)\b/',
            ], [
                'article 39 code de recouvrement des creances publiques commandement saisie vente',
                'article 1 code de recouvrement des creances publiques definition recouvrement',
                'article 41 code de recouvrement des creances publiques commandement delai',
                'code de recouvrement des creances publiques recouvrement force',
            ], 'tax', ['recouvrement', 'creances publiques', 'commandement', 'saisie', 'vente']),
            $this->profile('vat refund request', [
                '/\b(vat refund|tva remboursement|remboursement de tva|remboursement de la tva|remboursement tva|demande de remboursement de tva|demande de remboursement de la tva|depot.*demande.*remboursement.*tva|remboursement.*taxe sur la valeur ajoutee|taxe sur la valeur ajoutee.*remboursement|recuperer.*taxe sur la valeur ajoutee|recuperation tva)\b/',
            ], [
                'article 25 application de la taxe sur la valeur ajoutee demande remboursement',
                'article 10 application de la taxe sur la valeur ajoutee demande remboursement',
                'application de la taxe sur la valeur ajoutee remboursement',
            ], 'tax', ['tva', 'remboursement', 'taxe sur la valeur ajoutee', 'demande']),
            $this->profile('company tax declaration penalty or reassessment', [
                '/\b(societe|sarl|company|entreprise)\b.*\b(dgi|impot|taxe|tva|declaration fiscale|penalite|majoration|redressement fiscal|sanction fiscale)\b/',
                '/\b(dgi|impot|taxe|tva|declaration fiscale|penalite|majoration|redressement fiscal|sanction fiscale)\b.*\b(societe|sarl|company|entreprise)\b/',
                '/\b(ar company tax penalty)\b/',
            ], [
                'declaration fiscale societe sanction fiscale',
                'penalite fiscale majoration societe',
                'redressement fiscal societe',
                'impot sur les societes declaration penalite',
            ], 'tax', ['declaration fiscale', 'sanction fiscale', 'penalite', 'majoration', 'redressement fiscal', 'impot sur les societes']),
            $this->profile('administrative receipt for request', [
                '/\b(administrative request|acte administratif|recepisse|receipt|deposit application|demande acte administratif)\b/',
            ], [
                'article 10 simplification des procedures formalites administratives recepisse demande acte administratif',
                'article 12 simplification des procedures formalites administratives recepisse silence administration',
                'simplification procedures formalites administratives recepisse',
            ], 'administrative', ['recepisse', 'acte administratif', 'demande', 'administration']),
            $this->profile('administrative maximum processing delay', [
                '/\b(maximum delay|processing delay|delai maximal|delai maximum|delai traitement|delai de principe|delai de reponse|traiter une demande|demande administrative|traitement administratif|60 jours|soixante jours|acte administratif)\b/',
            ], [
                'article 16 simplification des procedures formalites administratives delai maximum 60 jours',
                'article 19 simplification des procedures formalites administratives silence administration accord',
                'simplification procedures formalites administratives delai acte administratif',
            ], 'administrative', ['delai', '60 jours', 'acte administratif', 'administration']),
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
                'article 1 recouvrement des loyers',
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
            $this->profile('labor disciplinary hearing before dismissal', [
                '/\b(disciplinary hearing|hear the employee|hear the worker|before dismissal|audition du salarie|entendre le salarie|procedure disciplinaire|licenciement disciplinaire)\b/',
                '/\b(procedure)\b.*\b(sanctionner|licenciement|salarie)\b/',
                '/\b(sanctionner|licenciement)\b.*\b(procedure|audition|entretien|prealable|disciplinaire)\b/',
                '/\b(faute)\b.*\b(audition|entretien|prealable)\b/',
                '/\b(entretien prealable|audition prealable)\b/',
            ], [
                'article 62 code du travail procedure disciplinaire audition defense',
                'article 35 code du travail licenciement motif valable',
                'article 63 code du travail decision licenciement',
            ], 'labor', ['procedure disciplinaire', 'audition', 'defense', 'salarie', 'licenciement']),
            $this->profile('pregnancy or maternity dismissal', [
                '/\b(pregnant|pregnancy|maternity|gave birth|birth|medical certificate|staff reduction|reducing staff)\b/',
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
                'article 62 code du travail licenciement procedure disciplinaire audition',
                'article 63 code du travail decision licenciement',
                'article 64 code du travail decision licenciement motifs',
                'article 37 code du travail sanction disciplinaire faute non grave',
                'article 39 code du travail faute grave licenciement',
                'article 40 code du travail faute grave employeur',
                'article 41 code du travail licenciement abusif dommages interets',
                'article 43 code du travail preavis',
                'article 51 code du travail indemnite preavis',
                'article 52 code du travail indemnite licenciement',
                'article 53 code du travail indemnite licenciement anciennete',
                'article 59 code du travail licenciement abusif indemnites',
                'article 65 code du travail action justice licenciement',
            ], 'labor', ['travail', 'salarie', 'employeur', 'licenciement', 'faute grave', 'preavis', 'indemnite', 'decision licenciement']),
            $this->profile('commercial company rules', [
                '/\b(company|corporation|business entity)\b.*\b(shareholder|director|manager|sarl|register|registration|formation|incorporate|capital|shares)\b/',
                '/\b(shareholder|director|manager|sarl|register|registration|formation|incorporate|capital|shares)\b.*\b(company|corporation|business entity)\b/',
                '/\b(sarl|commercial company|register company|shareholder|director)\b/',
                '/\b(societe|associe|actionnaire|gerant|registre de commerce)\b/',
            ], [
                'article 37 code de commerce registre de commerce',
                'societe responsabilite limitee',
                'societes anonymes actionnaire',
                'registre de commerce',
                'code de commerce commercant',
                'gerant societe',
            ], 'commercialCompany'),
            $this->profile('family law', [
                '/\b(divorce|marriage|custody|inheritance|child support|alimony)\b/',
                '/\b(divorce|mariage|heritage|succession|pension alimentaire)\b/',
                '/\b(garde)\b.*\b(enfant|enfants|mere|pere|mineur)\b/',
            ], [
                'article 84 code de la famille pension alimentaire enfants divorce',
                'code de la famille divorce',
                'code de la famille garde',
                'pension alimentaire',
                'succession heritage',
                'mariage code de la famille',
            ], 'family'),
            $this->profile('sale delivery legal definition', [
                '/\b(delivery|delivrance|livraison|mise a disposition|mettre la chose|possession sans obstacle)\b.*\b(seller|buyer|sale|sold|vendeur|acheteur|vente|chose vendue)\b/',
                '/\b(seller|buyer|sale|sold|vendeur|acheteur|vente|chose vendue)\b.*\b(delivery|delivrance|livraison|mise a disposition|mettre la chose|possession sans obstacle)\b/',
            ], [
                'article 499 code des obligations et des contrats delivrance possession sans obstacle',
                'article 498 code des obligations et des contrats obligation de delivrance',
                'article 500 code des obligations et des contrats choses mobilieres tradition reelle',
                'code des obligations et des contrats delivrance chose vendue',
            ], 'civilContracts', ['delivrance', 'possession', 'sans obstacle', 'chose vendue', 'vendeur', 'acheteur']),
            $this->profile('consumer protection', [
                '/\b(consumer|customer|warranty|defective product|product defect|consumer refund)\b/',
                '/\b(consommateur|garantie|defaut du produit|produit defectueux|remboursement consommateur)\b/',
            ], [
                'protection du consommateur garantie',
                'protection du consommateur remboursement',
                'information consommateur',
                'pratiques commerciales consommateur',
            ], 'consumer'),
            $this->profile('civil debt loan and proof dispute', [
                '/\b(loan|lent|lend|borrowed|debt|owes|repay|repayment|gift|bank transfer|transfer|whatsapp|message|messages|receipt|proof|evidence)\b/',
                '/\b(pret|prete|emprunt|dette|creance|rembourser|remboursement|don|virement|preuve|ecrit|messages|whatsapp|reconnaissance de dette)\b/',
            ], [
                'article 443 code des obligations et des contrats obligations plus de dix mille dirhams preuve ecrite electronique',
                'article 448 code des obligations et des contrats preuve testimoniale exceptions',
                'article 447 code des obligations et des contrats commencement de preuve par ecrit',
                'article 404 code des obligations et des contrats moyens de preuve aveu preuve ecrite presomption',
                'article 401 code des obligations et des contrats preuve obligations forme ecrite',
                'article 399 code des obligations et des contrats preuve obligation',
                'preuve obligation dette reconnaissance de dette',
                'pret remboursement preuve virement bancaire messages whatsapp',
                'don ou pret charge de la preuve obligation restituer',
            ], 'civilContracts', ['preuve', 'obligation', 'dette', 'creance', 'pret', 'remboursement', 'virement', 'ecrit', 'aveu', 'commencement de preuve']),
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
                '/\b(sale|sell|sells|sold|seller|buyer|bought|purchase|contract)\b.*\b(refuses? to pay|refuse payment|does not pay|did not pay|unpaid|payment refused|price)\b/',
                '/\b(refuses? to pay|refuse payment|does not pay|did not pay|unpaid|payment refused)\b.*\b(sale|sell|sells|sold|seller|buyer|bought|purchase|contract)\b/',
                '/\b(vente|vendu|vendeur|acheteur|prix|paiement)\b.*\b(delivrance|livraison|reception|quantite|partielle|marchandise|produit)\b/',
                '/\b(delivrance|livraison|reception|quantite|partielle|marchandise|produit)\b.*\b(vente|vendu|vendeur|acheteur|prix|paiement)\b/',
                '/\b(vente|vendeur|acheteur|contrat)\b.*\b(refus de payer|ne paie pas|impaye|prix)\b/',
            ], [
                'article 230 code des obligations et des contrats force obligatoire contrat',
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
            ], 'civilContracts', ['vente', 'acheteur', 'vendeur', 'delivrance', 'chose vendue', 'prix', 'paiement', 'contrat', 'obligations', 'compte', 'mesure', 'reception']),
            $this->profile('sale ownership and heirs dispute', [
                '/\b(sell|sells|sold|sale|bought|buyer|seller|paid|payment|price)\b.*\b(car|vehicle|ownership|owner|registration|registered|heirs|inherit|inherited)\b/',
                '/\b(car|vehicle|ownership|owner|registration|registered|heirs|inherit|inherited)\b.*\b(sell|sells|sold|sale|bought|buyer|seller|paid|payment|price)\b/',
                '/\b(vente|vendu|achete|acheteur|vendeur|prix|paye|paiement)\b.*\b(propriete|possession|heritier|heritiers|succession|decede|deces|vehicule|voiture|immatriculation|carte grise)\b/',
                '/\b(propriete|possession|heritier|heritiers|succession|decede|deces|vehicule|voiture|immatriculation|carte grise)\b.*\b(vente|vendu|achete|acheteur|vendeur|prix|paye|paiement)\b/',
            ], [
                'article 488 code des obligations et des contrats vente',
                'article 491 code des obligations et des contrats propriete chose vendue',
                'article 499 code des obligations et des contrats delivrance possession',
                'article 500 code des obligations et des contrats choses mobilieres',
                'article 229 code des obligations et des contrats heritiers ayants cause',
                'code des obligations et des contrats vente parfaite consentement chose prix',
                'code des obligations et des contrats acheteur acquiert propriete chose vendue consentement',
                'code des obligations et des contrats chose vendue avant delivrance',
                'code des obligations et des contrats delivrance possession chose vendue',
                'code des obligations et des contrats choses mobilieres tradition reelle usage',
                'code des obligations et des contrats paiement delivrance vendeur acheteur',
                'code des obligations et des contrats heritiers ayants cause obligations',
                'code des obligations et des contrats enregistrement tiers',
            ], 'civilContracts', ['vente', 'consentement', 'chose', 'prix', 'acheteur', 'vendeur', 'propriete', 'delivrance', 'possession', 'heritier', 'heritiers', 'ayants cause']),
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
        $facts = collect(is_array($plan['facts'] ?? null) ? $plan['facts'] : [])
            ->map(fn (mixed $fact): string => trim((string) $fact))
            ->filter(fn (string $fact): bool => Str::length($fact) >= 3)
            ->unique(fn (string $fact): string => $this->normalizeText($fact))
            ->take(12)
            ->values()
            ->all();

        return [
            'legalIssue' => $legalIssue,
            'reasoningGoal' => trim((string) ($plan['reasoningGoal'] ?? $plan['reasoning_goal'] ?? '')),
            'needsLawSearch' => ($plan['needsLawSearch'] ?? true) !== false,
            'searchQueries' => $searchQueries,
            'facts' => $facts,
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
                'model' => ($payload['model'] ?? null) ?: env('OLLAMA_MODEL', self::DEFAULT_OLLAMA_MODEL),
                'stream' => false,
                'format' => $payload['format'] ?? null,
                'think' => (bool) ($payload['think'] ?? false),
                'keep_alive' => env('OLLAMA_KEEP_ALIVE', '30m'),
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
            ($citation['supportLevel'] ?? null) ? 'Support audit: '.($citation['supportLevel']).' source'.(!empty($citation['supportSignals']) ? ' | matched terms: '.implode(', ', array_slice((array) $citation['supportSignals'], 0, 8)) : '') : '',
            ($citation['sourceAuthorityLevel'] ?? null) ? 'Source authority: '.$citation['sourceAuthorityLevel'].(!empty($citation['sourceAuthoritySignals']) ? ' | signals: '.implode(', ', array_slice((array) $citation['sourceAuthoritySignals'], 0, 8)) : '') : '',
            ($citation['contextScope'] ?? null) ? 'Context scope: '.$citation['contextScope'] : '',
            $sourceParts ? 'Source: '.implode(' | ', $sourceParts) : '',
            'Text: '.Str::limit($this->cleanLawExcerpt($citation['contextContent'] ?? $citation['content'] ?? ''), 1800, ''),
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

        return match ($language) {
            'fr' => "La regle retrouvee la plus proche est ".($source ?: ($citation['title'] ?? 'la premiere citation')).": {$excerpt} [1].",
            'ar' => "أقرب قاعدة قانونية مسترجعة هي ".($source ?: ($citation['title'] ?? 'الإحالة الأولى')).": {$excerpt} [1].",
            default => "The closest retrieved rule is ".($source ?: ($citation['title'] ?? 'the first citation')).": {$excerpt} [1].",
        };
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

    private function extractYearsOfService(string $question): ?float
    {
        preg_match('/\b(\d+(?:[.,]\d+)?)\s*(?:years?|ans|annees?)\b/i', $question, $match);

        return isset($match[1]) ? (float) str_replace(',', '.', $match[1]) : null;
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
        if (preg_match('/\p{Arabic}/u', $message) === 1) {
            return 'ar';
        }

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
        $value = (string) ($value ?? '');

        return Str::of($value.' '.$this->arabicLegalSignals($value))
            ->lower()
            ->ascii()
            ->replaceMatches('/[-_]+/', ' ')
            ->replaceMatches('/[\?!\.,;:\(\)\[\]\{\}"]+/', ' ')
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
            $add(['sarl commercial personality', 'article 2 societe en nom collectif et sarl']);
        }

        if ($this->containsArabicAny($arabic, ['مؤسسة ائتمان', 'مؤسسات الائتمان', 'اعتماد مسبق', 'اعتماد'])
            && $this->containsArabicAny($arabic, ['ائتمان', 'بنك', 'بنكي', 'نشاط'])) {
            $add(['banking credit institution approval', 'article 34 etablissements de credit organismes assimiles agrement avant exercer activite']);
        }

        if ($this->containsArabicAny($arabic, ['الضريبة على القيمة المضافة', 'ضريبة القيمة المضافة', 'القيمة المضافة', 'استرجاع الضريبة', 'استرداد الضريبة'])
            && $this->containsArabicAny($arabic, ['استرجاع', 'استرداد', 'استردادها', 'استرجاعها', 'طلب'])) {
            $add(['vat refund request', 'article 25 application de la taxe sur la valeur ajoutee demande remboursement']);
        }

        if ($this->containsArabicAny($arabic, ['تسليم', 'التسليم', 'حيازة المبيع', 'حيازة', 'المبيع'])
            && $this->containsArabicAny($arabic, ['البائع', 'المشتري', 'عقد البيع', 'البيع', 'قبض الثمن', 'الثمن'])) {
            $add(['sale delivery legal definition', 'article 499 code des obligations et des contrats delivrance possession sans obstacle']);
        }

        if ($this->containsArabicAny($arabic, ['باع', 'بيع', 'البائع', 'مشتري', 'مشترين', 'لشخصين', 'شخصين مختلفين'])
            && $this->containsArabicAny($arabic, ['عقار', 'غير محفظ', 'ملكية', 'الاولويه', 'الأولوية', 'اولوية', 'له الاولويه'])) {
            $add([
                'ar real estate double sale priority',
                'double vente immobiliere non immatriculee',
                'property ownership dispute competing purchasers unregistered property',
                'action en revendication propriete immobiliere',
            ]);
        }

        if ($this->containsArabicAny($arabic, ['حيازة', 'الحيازة'])
            && !$this->containsArabicAny($arabic, ['البائع', 'المشتري', 'المبيع', 'عقد البيع', 'البيع'])) {
            $add(['ar property possession', 'possession propriete immobiliere', 'code des droits reels possession']);
        }

        if ($this->containsArabicAny($arabic, ['دعوى الاستحقاق', 'الاستحقاق', 'استحقاق العقار'])) {
            $add(['ar ownership claim', 'action en revendication propriete immobiliere', 'revendication droit de propriete']);
        }

        if ($this->containsArabicAny($arabic, ['شفعة', 'الشفعة', 'شفيع'])) {
            $add(['property preemption source coverage', 'droit de preemption propriete immobiliere', 'preemption immobiliere']);
        }

        if ($this->containsArabicAny($arabic, ['عامل', 'أجير', 'اجير', 'مشغل'])
            && $this->containsArabicAny($arabic, ['اختلاس', 'سرقة', 'السرقة', 'خيانة الأمانة', 'خيانة الامانة', 'صندوق', 'مال الشركة'])) {
            $add(['ar workplace criminal misappropriation', 'abus de confiance code penal', 'detournement de fonds code penal', 'vol code penal']);
        }

        if ($this->containsArabicAny($arabic, ['شركة', 'سارل'])
            && $this->containsArabicAny($arabic, ['ضريبة', 'الضريبة', 'تصريح ضريبي', 'غرامة', 'مراجعة ضريبية', 'جزاءات ضريبية', 'القيمة المضافة'])) {
            $add(['ar company tax penalty', 'declaration fiscale societe sanction fiscale', 'penalite fiscale majoration societe', 'redressement fiscal societe']);
        }

        if ($this->containsArabicAny($arabic, ['مختصة ترابيا', 'اختصاص ترابي', 'الاختصاص الترابي', 'موطن المدعى عليه', 'مدعى عليه', 'مقاضاة'])
            && $this->containsArabicAny($arabic, ['محكمة', 'موطن', 'ترابيا', 'مقاضاة'])) {
            $add(['civil procedure territorial jurisdiction', 'article 27 code de procedure civile competence territoriale domicile defendeur']);
        }

        if (preg_match('/حضان|الأم|الام|تزوج|طلاق|الطلاق/u', $value)) {
            $add(['ar custody remarriage', 'custody mother remarried divorce', 'article 175 code de la famille garde mere remariage']);
        }

        if (preg_match('/نفقة|النفق/u', $value)) {
            $add(['child support alimony pension alimentaire', 'article 85 code de la famille pension alimentaire', 'article 190 code de la famille pension alimentaire']);
        }

        if (preg_match('/تحفيظ|تعرض|التحديد|الرسم العقاري|مطلب التحفيظ|إعلان انتهاء التحديد|اعلان انتهاء التحديد/u', $value)) {
            $add(['land registration opposition immatriculation fonciere', 'article 24 immatriculation fonciere opposition bornage']);
        }

        if (preg_match('/الملكية المشتركة|اتحاد الملاك|سانديك|السنديك|الملكية المشتركة|الجمع العام/u', $value)) {
            $add(['copropriete syndic assemblee generale', 'article 19 statut de la copropriete syndic']);
        }

        if (preg_match('/مشغل|أجير|اجير|طرد|فصل من العمل|فصله من العمل|الشغل|مبرر|سبب مشروع/u', $value)) {
            $add(['employment termination labor dismissal', 'article 35 code du travail licenciement motif valable']);
        }

        if (preg_match('/استماع|الاستماع|مسطرة تأديبية|مسطره تاديبيه|إجراء تأديبي|اجراء تاديبي|تأديب|تاديب|حق الدفاع/u', $value)) {
            $add(['labor disciplinary hearing before dismissal', 'article 62 code du travail procedure disciplinaire audition defense']);
        }

        if (preg_match('/شركة|الشركة|السجل التجاري|نشاط تجاري|تجاري/u', $value)) {
            $add(['commercial company registration registre de commerce', 'article 37 code de commerce registre de commerce']);
        }

        if (preg_match('/سارل|ذات المسؤولية المحدودة|الشخصية المعنوية/u', $value)) {
            $add(['sarl commercial personality', 'article 2 societe en nom collectif et sarl']);
        }

        if (preg_match('/دين|قرض|سلف|هبة|واتساب|تحويل|حوالة|إثبات|اثبات|100000|100\.000/u', $value)) {
            $add(['debt loan proof bank transfer whatsapp gift', 'article 443 code des obligations et des contrats preuve ecrite electronique', 'article 448 code des obligations et des contrats preuve testimoniale']);
        }

        if (preg_match('/استئناف|حكم|المحكمة الابتدائية|ابتدائية/u', $value)) {
            $add(['civil appeal deadline', 'article 134 code de procedure civile appel delai trente jours']);
        }

        if (preg_match('/اختصاص|محكمة مختصة|موطن المدعى عليه|المدعى عليه/u', $value)) {
            $add(['territorial jurisdiction defendant domicile', 'article 27 code de procedure civile competence territoriale domicile defendeur']);
        }

        if (preg_match('/سرقة|السرقة|مال الغير|مال غيره|أخذ مال|اخذ مال|اختلاس|مملوك للغير|سوء نية/u', $value)) {
            $add(['criminal theft', 'article 505 code penal vol']);
        } elseif (preg_match('/احتيالية|احتيال|نصب/u', $value)) {
            $add(['criminal fraud', 'article 540 code penal escroquerie']);
        }

        if (preg_match('/السر المهني|سرية|زبناء|الزبناء|كتمان|معلومات الزبون|معطيات زبون|معطيات الزبون/u', $value)) {
            $add(['banking professional secrecy', 'article 180 etablissements de credit organismes assimiles secret professionnel']);
        }

        if (preg_match('/تحصيل|جبري|الديون العمومية|ديون عمومية|دين عمومي|استخلاص|الجبائي|جباية|ضرائب/u', $value)) {
            $add(['public debt forced collection tax', 'article 39 code de recouvrement des creances publiques commandement saisie vente']);
        }

        if (preg_match('/وصل|وصلا|إيداع|ايداع|تسلم|تسلمني/u', $value)) {
            $add(['administrative request receipt', 'article 10 simplification des procedures formalites administratives recepisse']);
        }

        if (preg_match('/أجل|اجل|مدة|مده|مهلة|مهله|60|ستين|معالجة|دراسة الطلب/u', $value)
            && preg_match('/قرار إداري|قرار اداري|إداري|اداري|الإدارة|الادارة/u', $value)) {
            $add(['administrative maximum processing delay', 'article 16 simplification des procedures formalites administratives delai maximum 60 jours']);
        }

        if (preg_match('/قرار إداري|قرار اداري|إداري|اداري|الإدارة|الادارة/u', $value)) {
            $add(['administrative act simplification procedures formalites administratives']);
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
