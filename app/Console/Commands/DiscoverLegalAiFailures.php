<?php

namespace App\Console\Commands;

use App\Services\AiReasoningService;
use App\Services\ChatLawService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DiscoverLegalAiFailures extends Command
{
    protected $signature = 'legal-ai:discover-failures
        {--limit=300 : Number of generated candidate questions to evaluate}
        {--fresh-seed= : Generate a fresh paraphrased candidate set with new IDs}
        {--json=legal-ai-discovery-failures.json : Store JSON report under storage/app}
        {--csv=legal-ai-discovery-failures.csv : Store CSV failure table under storage/app}
        {--md=legal-ai-discovery-failures.md : Store Markdown summary under storage/app}';

    protected $description = 'Generate fresh Moroccan legal AI questions, run retrieval, and map suspicious failure patterns without tuning.';

    public function handle(ChatLawService $chat, AiReasoningService $ai): int
    {
        $limit = max(1, min(500, (int) $this->option('limit')));
        $freshSeed = trim((string) $this->option('fresh-seed'));
        $questions = collect($this->candidateQuestions($freshSeed))->take($limit)->values();

        if ($questions->isEmpty()) {
            $this->error('No discovery questions were generated.');

            return self::FAILURE;
        }

        $results = [];

        foreach ($questions as $index => $question) {
            $this->line(sprintf(
                'Discovery %d/%d: %s',
                $index + 1,
                $questions->count(),
                $question['id']
            ));

            $results[] = $this->evaluateCandidate($question, $chat, $ai);
        }

        $clusters = $this->clusters($results);
        $topPatterns = $this->topPatterns($results, 20);
        $report = [
            'generatedAt' => now()->toIso8601String(),
            'purpose' => 'failure_discovery_not_tuning',
            'freshSeed' => $freshSeed !== '' ? $freshSeed : null,
            'frozenSuites' => [
                'regression' => 'legal_ai_benchmarks.cases',
                'holdout' => 'legal_ai_holdout_benchmarks.cases',
            ],
            'candidateCount' => count($results),
            'suspiciousCount' => collect($results)->where('suspicious', true)->count(),
            'summaryByExpectedDomain' => $this->summaryBy($results, 'expectedDomain'),
            'summaryByErrorCategory' => collect($results)->flatMap(fn (array $result): array => $result['errorCategories'])->countBy()->sortDesc()->all(),
            'topFailurePatterns' => $topPatterns,
            'clusters' => $clusters,
            'results' => $results,
        ];

        $jsonPath = trim((string) $this->option('json'));
        if ($jsonPath !== '') {
            Storage::put($jsonPath, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            $this->line('JSON report written to storage/app/'.$jsonPath);
        }

        $csvPath = trim((string) $this->option('csv'));
        if ($csvPath !== '') {
            Storage::put($csvPath, $this->csvReport($results));
            $this->line('CSV failure table written to storage/app/'.$csvPath);
        }

        $markdownPath = trim((string) $this->option('md'));
        if ($markdownPath !== '') {
            Storage::put($markdownPath, $this->markdownReport($report));
            $this->line('Markdown summary written to storage/app/'.$markdownPath);
        }

        $this->table(
            ['Pattern', 'Failures', 'Impact', 'Estimated root cause'],
            collect($topPatterns)->map(fn (array $pattern): array => [
                $pattern['cluster'],
                $pattern['failureCount'],
                $pattern['impact'],
                $pattern['estimatedRootCause'],
            ])->all()
        );

        $this->warn('Discovery complete. No fixes were applied.');

        return self::SUCCESS;
    }

    private function evaluateCandidate(array $candidate, ChatLawService $chat, AiReasoningService $ai): array
    {
        $message = $candidate['question'];
        $expectedDomain = $candidate['expectedDomain'];
        $expectedTerms = $candidate['expectedTerms'];
        $intent = $chat->classifyIntent($message);
        $plan = $ai->createSearchPlan($message) ?? [];
        $plan = array_merge($plan, ['disableSearchCache' => true]);
        $context = $chat->prepare($message, [], $plan, $intent);
        $diagnostics = $context['diagnostics'] ?? [];
        $citations = $context['citations'] ?? [];
        $topSources = $this->topSources($citations, $diagnostics);
        $retrievedDomain = $topSources[0]['domain'] ?? null;
        $topSource = $topSources[0] ?? null;
        $errorCategories = $this->errorCategories($candidate, $intent, $context, $topSources);
        $confidence = $this->confidenceScore($candidate, $intent, $context, $topSources, $errorCategories);

        return [
            'id' => $candidate['id'],
            'question' => $message,
            'language' => $candidate['language'],
            'expectedDomain' => $expectedDomain,
            'expectedCluster' => $candidate['cluster'],
            'expectedTerms' => $expectedTerms,
            'intent' => $context['intent'] ?? $intent,
            'retrievedDomain' => $retrievedDomain,
            'topSource' => $topSource,
            'top10Sources' => $topSources,
            'queries' => $diagnostics['queries'] ?? [],
            'expandedQueries' => array_slice($diagnostics['expandedQueries'] ?? [], 0, 20),
            'confidenceScore' => $confidence,
            'suspicious' => $errorCategories !== [],
            'errorCategories' => $errorCategories,
            'estimatedRootCause' => $this->estimatedRootCause($candidate, $errorCategories, $topSources, $diagnostics),
        ];
    }

    private function topSources(array $citations, array $diagnostics): array
    {
        $accepted = collect($citations)
            ->map(fn (array $citation): array => [
                'documentTitle' => $citation['documentTitle'] ?? null,
                'articleNumber' => $citation['articleNumber'] ?? null,
                'domain' => $citation['domain'] ?? null,
                'matchedQuery' => $citation['matchedQuery'] ?? null,
                'supportLevel' => $citation['supportLevel'] ?? null,
                'sourceRelevanceScore' => $citation['sourceRelevanceScore'] ?? null,
                'accepted' => true,
            ]);

        $raw = collect($diagnostics['rawResults'] ?? [])
            ->map(fn (array $source): array => [
                'documentTitle' => $source['documentTitle'] ?? null,
                'articleNumber' => $source['articleNumber'] ?? null,
                'domain' => $source['domain'] ?? null,
                'matchedQuery' => $source['matchedQuery'] ?? null,
                'supportLevel' => null,
                'sourceRelevanceScore' => $source['chatScore'] ?? null,
                'accepted' => (bool) ($source['accepted'] ?? false),
            ]);

        return $accepted
            ->concat($raw)
            ->unique(fn (array $source): string => implode('|', [
                $source['documentTitle'] ?? '',
                $source['articleNumber'] ?? '',
                $source['domain'] ?? '',
            ]))
            ->take(10)
            ->values()
            ->all();
    }

    private function errorCategories(array $candidate, string $intent, array $context, array $topSources): array
    {
        $errors = [];
        $expectedDomain = $candidate['expectedDomain'];
        $top = $topSources[0] ?? null;
        $domains = collect($topSources)->pluck('domain')->filter()->values();
        $topText = $this->sourceText($top);
        $topOverlap = $this->termOverlap($candidate['expectedTerms'], $topText);

        if (($context['intent'] ?? $intent) === ChatLawService::INTENT_UNSUPPORTED) {
            $errors[] = 'unsupported_intent_for_legal_question';
        }

        if (!$topSources) {
            $errors[] = 'no_sources_retrieved';
        }

        if ($top && $this->normalizeDomain((string) ($top['domain'] ?? '')) !== $this->normalizeDomain($expectedDomain)) {
            $errors[] = 'top_domain_mismatch';
        }

        if ($domains->isNotEmpty() && !$domains->contains(fn (string $domain): bool => $this->normalizeDomain($domain) === $this->normalizeDomain($expectedDomain))) {
            $errors[] = 'expected_domain_absent_from_top10';
        }

        if ($top && $topOverlap < 0.18) {
            $errors[] = 'top_source_low_term_overlap';
        }

        $answer = (string) ($context['answer'] ?? $context['fallbackAnswer'] ?? '');
        if ($answer !== '' && preg_match('/sources insuffisantes|insufficient sources|لم أجد|المصادر غير كافية/u', $answer)
            && collect($topSources)->contains(fn (array $source): bool => $this->normalizeDomain((string) ($source['domain'] ?? '')) === $this->normalizeDomain($expectedDomain))) {
            $errors[] = 'insufficient_answer_despite_sources';
        }

        if ($this->hasUnrelatedSensitiveCode($expectedDomain, $topSources)) {
            $errors[] = 'unrelated_sensitive_code_intrusion';
        }

        if ($this->genericArticleOutranksSpecific($candidate, $topSources)) {
            $errors[] = 'generic_article_outranks_specific';
        }

        return array_values(array_unique($errors));
    }

    private function confidenceScore(array $candidate, string $intent, array $context, array $topSources, array $errors): int
    {
        $score = 100;
        $top = $topSources[0] ?? null;

        if (($context['intent'] ?? $intent) === ChatLawService::INTENT_UNSUPPORTED) {
            $score -= 35;
        }

        if (!$topSources) {
            $score -= 55;
        }

        if ($top && $this->normalizeDomain((string) ($top['domain'] ?? '')) !== $this->normalizeDomain($candidate['expectedDomain'])) {
            $score -= 30;
        }

        if ($top) {
            $score -= (int) round(max(0, 0.35 - $this->termOverlap($candidate['expectedTerms'], $this->sourceText($top))) * 60);
        }

        $score -= count($errors) * 8;

        return max(0, min(100, $score));
    }

    private function estimatedRootCause(array $candidate, array $errors, array $topSources, array $diagnostics): string
    {
        if (in_array('unsupported_intent_for_legal_question', $errors, true)) {
            return 'intent classifier lacks Arabic/French legal-signal coverage for this phrasing';
        }

        if (in_array('no_sources_retrieved', $errors, true)) {
            return 'query expansion did not bridge user language to indexed French legal terminology';
        }

        if (in_array('top_domain_mismatch', $errors, true) || in_array('expected_domain_absent_from_top10', $errors, true)) {
            $queries = implode(' ', $diagnostics['queries'] ?? []);

            if ($candidate['language'] === 'ar' && preg_match('/\p{Arabic}/u', $queries)) {
                return 'Arabic morphology/translation gap left retrieval in Arabic instead of French legal concepts';
            }

            return 'routing/scoring favored a neighboring legal domain over the expected domain';
        }

        if (in_array('top_source_low_term_overlap', $errors, true)) {
            return 'ranking accepted a source with weak lexical/concept support';
        }

        if (in_array('generic_article_outranks_specific', $errors, true)) {
            return 'generic article/title scoring is stronger than issue-specific article scoring';
        }

        if (in_array('unrelated_sensitive_code_intrusion', $errors, true)) {
            return 'source authority or broad code-title boost overpowered domain relevance';
        }

        return 'requires manual legal review';
    }

    private function clusters(array $results): array
    {
        return collect($results)
            ->where('suspicious', true)
            ->groupBy(fn (array $result): string => $this->clusterName($result))
            ->map(function ($items, string $cluster): array {
                $errorCounts = $items->flatMap(fn (array $item): array => $item['errorCategories'])->countBy()->sortDesc();

                return [
                    'cluster' => $cluster,
                    'failureCount' => $items->count(),
                    'averageConfidence' => round($items->avg('confidenceScore'), 1),
                    'impact' => $this->impactForCluster($cluster, $items->count(), $errorCounts->keys()->all()),
                    'errorCategories' => $errorCounts->all(),
                    'estimatedRootCause' => $this->clusterRootCause($cluster, $errorCounts->keys()->all()),
                    'representativeExamples' => $items
                        ->sortBy('confidenceScore')
                        ->take(4)
                        ->map(fn (array $item): array => [
                            'question' => $item['question'],
                            'expectedDomain' => $item['expectedDomain'],
                            'retrievedDomain' => $item['retrievedDomain'],
                            'topSource' => $item['topSource'],
                            'errorCategories' => $item['errorCategories'],
                            'confidenceScore' => $item['confidenceScore'],
                        ])
                        ->values()
                        ->all(),
                ];
            })
            ->sortByDesc(fn (array $cluster): int|float => ($cluster['failureCount'] * 10) + (100 - $cluster['averageConfidence']))
            ->values()
            ->all();
    }

    private function topPatterns(array $results, int $limit): array
    {
        return collect($results)
            ->where('suspicious', true)
            ->groupBy(function (array $result): string {
                $primaryError = $result['errorCategories'][0] ?? 'unknown_failure';
                $retrievedDomain = $this->normalizeDomain((string) ($result['retrievedDomain'] ?? 'none'));

                return implode('|', [$this->clusterName($result), $primaryError, $retrievedDomain]);
            })
            ->map(function ($items, string $key): array {
                [$cluster, $primaryError, $retrievedDomain] = explode('|', $key);
                $errorCounts = $items->flatMap(fn (array $item): array => $item['errorCategories'])->countBy()->sortDesc();

                return [
                    'cluster' => $cluster,
                    'pattern' => $primaryError.' via '.$retrievedDomain,
                    'failureCount' => $items->count(),
                    'averageConfidence' => round($items->avg('confidenceScore'), 1),
                    'impact' => $this->impactForCluster($cluster, $items->count(), $errorCounts->keys()->all()),
                    'errorCategories' => $errorCounts->all(),
                    'retrievedDomain' => $retrievedDomain,
                    'estimatedRootCause' => $this->clusterRootCause($cluster, $errorCounts->keys()->all()),
                    'representativeExamples' => $items
                        ->sortBy('confidenceScore')
                        ->take(3)
                        ->map(fn (array $item): array => [
                            'question' => $item['question'],
                            'expectedDomain' => $item['expectedDomain'],
                            'retrievedDomain' => $item['retrievedDomain'],
                            'topSource' => $item['topSource'],
                            'errorCategories' => $item['errorCategories'],
                            'confidenceScore' => $item['confidenceScore'],
                        ])
                        ->values()
                        ->all(),
                ];
            })
            ->sortByDesc(fn (array $pattern): int|float => ($pattern['failureCount'] * 10) + (100 - $pattern['averageConfidence']))
            ->take($limit)
            ->values()
            ->all();
    }

    private function clusterName(array $result): string
    {
        $expected = $this->normalizeDomain($result['expectedDomain']);

        return match ($expected) {
            'real_estate_rent' => 'Property-law routing failures',
            'family_marriage_divorce' => 'Family-law maintenance/custody failures',
            'banking_finance' => 'Banking authorization/confidentiality failures',
            'labor' => 'Labor dismissal failures',
            'civil_procedure' => 'Civil procedure failures',
            'tax' => 'Tax retrieval failures',
            'civil_obligations_contracts' => 'Civil obligations/contracts failures',
            'commercial_company' => 'Commercial/company failures',
            'criminal' => 'Criminal law failures',
            'administrative_urbanism' => 'Administrative law failures',
            default => Str::headline(str_replace('_', ' ', $expected)).' failures',
        };
    }

    private function impactForCluster(string $cluster, int $count, array $errors): string
    {
        if (in_array('top_domain_mismatch', $errors, true) || in_array('expected_domain_absent_from_top10', $errors, true)) {
            return $count >= 5 ? 'high' : 'medium';
        }

        if (in_array('generic_article_outranks_specific', $errors, true)) {
            return 'medium';
        }

        return $count >= 8 ? 'medium' : 'low';
    }

    private function clusterRootCause(string $cluster, array $errors): string
    {
        if (in_array('expected_domain_absent_from_top10', $errors, true)) {
            return 'domain routing/query expansion misses the correct legal code family';
        }

        if (in_array('top_domain_mismatch', $errors, true)) {
            return 'top-ranking domain boost is too weak or neighboring-domain aliases are too broad';
        }

        if (in_array('top_source_low_term_overlap', $errors, true)) {
            return 'relevance scoring allows low-overlap sources through';
        }

        if (in_array('generic_article_outranks_specific', $errors, true)) {
            return 'generic article scoring outranks article-specific legal concept terms';
        }

        return 'mixed retrieval quality issue; inspect representative examples';
    }

    private function hasUnrelatedSensitiveCode(string $expectedDomain, array $sources): bool
    {
        $expected = $this->normalizeDomain($expectedDomain);
        $sensitive = [
            'commercial_company', 'tax', 'banking_finance', 'labor', 'criminal',
            'family_marriage_divorce', 'real_estate_rent', 'administrative_urbanism',
        ];

        return collect($sources)->take(3)->contains(function (array $source) use ($expected, $sensitive): bool {
            $domain = $this->normalizeDomain((string) ($source['domain'] ?? ''));

            return in_array($domain, $sensitive, true) && $domain !== $expected;
        });
    }

    private function genericArticleOutranksSpecific(array $candidate, array $sources): bool
    {
        if (count($sources) < 2) {
            return false;
        }

        $top = $sources[0];
        $topArticle = strtolower((string) ($top['articleNumber'] ?? ''));
        $topOverlap = $this->termOverlap($candidate['expectedTerms'], $this->sourceText($top));
        $laterBetter = collect($sources)
            ->skip(1)
            ->take(5)
            ->contains(fn (array $source): bool => $this->termOverlap($candidate['expectedTerms'], $this->sourceText($source)) > ($topOverlap + 0.18));

        return preg_match('/article\s+(1|2|premier)\b/i', $topArticle) && $laterBetter;
    }

    private function termOverlap(array $terms, string $text): float
    {
        $tokens = collect($terms)
            ->flatMap(fn (string $term): array => preg_split('/\s+/', $this->normalizeText($term)) ?: [])
            ->filter(fn (string $token): bool => strlen($token) >= 4)
            ->unique()
            ->values();

        if ($tokens->isEmpty()) {
            return 0.0;
        }

        $normalizedText = $this->normalizeText($text);
        $matches = $tokens->filter(fn (string $token): bool => str_contains($normalizedText, $token))->count();

        return $matches / $tokens->count();
    }

    private function sourceText(?array $source): string
    {
        if (!$source) {
            return '';
        }

        return implode(' ', array_filter([
            $source['documentTitle'] ?? null,
            $source['articleNumber'] ?? null,
            $source['domain'] ?? null,
            $source['matchedQuery'] ?? null,
        ]));
    }

    private function normalizeDomain(string $domain): string
    {
        $normalized = str_replace('-', '_', strtolower(trim($domain)));

        return match ($normalized) {
            'civil', 'contracts', 'contract', 'civil_contracts' => 'civil_obligations_contracts',
            'family' => 'family_marriage_divorce',
            'real_estate', 'real_estate_law', 'real_estate_lease' => 'real_estate_rent',
            'commercial', 'commerce', 'company', 'business' => 'commercial_company',
            default => $normalized,
        };
    }

    private function normalizeText(string $value): string
    {
        return str($value)
            ->lower()
            ->ascii()
            ->replaceMatches('/[^a-z0-9]+/', ' ')
            ->replaceMatches('/\s+/', ' ')
            ->trim()
            ->toString();
    }

    private function summaryBy(array $results, string $key): array
    {
        return collect($results)
            ->groupBy($key)
            ->map(fn ($items): array => [
                'total' => $items->count(),
                'suspicious' => $items->where('suspicious', true)->count(),
                'averageConfidence' => round($items->avg('confidenceScore'), 1),
            ])
            ->all();
    }

    private function csvReport(array $results): string
    {
        $columns = [
            'question', 'language', 'expected_domain', 'retrieved_domain', 'top_source',
            'error_category', 'confidence_score', 'estimated_root_cause',
        ];
        $lines = [implode(',', $columns)];

        foreach ($results as $result) {
            if (!$result['suspicious']) {
                continue;
            }

            $top = $result['topSource'] ?? [];
            $lines[] = collect([
                $result['question'],
                $result['language'],
                $result['expectedDomain'],
                $result['retrievedDomain'],
                trim(($top['documentTitle'] ?? '').' '.($top['articleNumber'] ?? '')),
                implode(' | ', $result['errorCategories']),
                $result['confidenceScore'],
                $result['estimatedRootCause'],
            ])->map(fn (mixed $value): string => '"'.str_replace('"', '""', (string) $value).'"')->implode(',');
        }

        return implode(PHP_EOL, $lines).PHP_EOL;
    }

    private function markdownReport(array $report): string
    {
        $lines = [
            '# Legal AI Failure Discovery',
            '',
            '- Purpose: failure discovery, not benchmark tuning',
            '- Frozen regression suite: `legal_ai_benchmarks.cases`',
            '- Frozen holdout suite: `legal_ai_holdout_benchmarks.cases`',
            '- Candidates: '.$report['candidateCount'],
            '- Suspicious results: '.$report['suspiciousCount'],
            '',
            '## Top Failure Patterns',
            '',
        ];

        foreach ($report['topFailurePatterns'] as $index => $pattern) {
            $lines[] = ($index + 1).'. **'.$pattern['cluster'].'**';
            $lines[] = '   - Failures: '.$pattern['failureCount'];
            $lines[] = '   - Impact: '.$pattern['impact'];
            $lines[] = '   - Estimated root cause: '.$pattern['estimatedRootCause'];
            $lines[] = '   - Error categories: '.implode(', ', array_keys($pattern['errorCategories']));
            foreach (array_slice($pattern['representativeExamples'], 0, 2) as $example) {
                $top = $example['topSource'] ?? [];
                $lines[] = '   - Example: '.$example['question'];
                $lines[] = '     Expected: '.$example['expectedDomain'].' | Retrieved: '.$example['retrievedDomain'].' | Top: '.trim(($top['documentTitle'] ?? '').' '.($top['articleNumber'] ?? '')).' | Confidence: '.$example['confidenceScore'];
            }
            $lines[] = '';
        }

        return implode(PHP_EOL, $lines).PHP_EOL;
    }

    private function candidateQuestions(string $freshSeed = ''): array
    {
        $domains = $this->domainQuestionSeeds();
        $candidates = [];

        foreach ($domains as $domain => $spec) {
            $index = 1;
            foreach ($spec['topics'] as $topic) {
                foreach ($spec['templates'] as $template) {
                    if ($index > 30) {
                        break 2;
                    }

                    $question = str_replace(
                        ['{actor}', '{fact}', '{issue}', '{object}', '{amount}', '{authority}'],
                        [
                            $topic['actor'] ?? 'client',
                            $topic['fact'],
                            $topic['issue'],
                            $topic['object'] ?? 'dossier',
                            $topic['amount'] ?? '100000 dirhams',
                            $topic['authority'] ?? 'administration',
                        ],
                        $template
                    );
                    if ($freshSeed !== '') {
                        $question = $this->freshQuestionVariant($question, $index, $freshSeed);
                    }

                    $candidates[] = [
                        'id' => $domain.'_'.($freshSeed !== '' ? 'fresh_'.$freshSeed.'_' : 'candidate_').str_pad((string) $index, 2, '0', STR_PAD_LEFT),
                        'language' => preg_match('/\p{Arabic}/u', $question) ? 'ar' : 'fr',
                        'cluster' => $spec['cluster'],
                        'expectedDomain' => $spec['expectedDomain'],
                        'expectedTerms' => array_values(array_unique([...$spec['expectedTerms'], ...($topic['terms'] ?? [])])),
                        'question' => $question,
                    ];
                    $index++;
                }
            }
        }

        return array_slice($candidates, 0, 300);
    }

    private function freshQuestionVariant(string $question, int $index, string $seed): string
    {
        $variant = abs(crc32($seed.'|'.$index.'|'.$question)) % 5;

        if (preg_match('/\p{Arabic}/u', $question)) {
            $frames = [
                'في استشارة قانونية مغربية، %s ما المصدر الرسمي الذي ينبغي فحصه أولا؟',
                'لإعداد رأي قانوني، %s ما القاعدة القانونية الأكثر ارتباطا بهذه الوقائع؟',
                'أمام محام مغربي هذا الملف: %s ما النصوص التي يجب إعطاؤها الأولوية؟',
                'لتقييم هذا النزاع قانونيا، %s ما المجال والنص الرسميان الأكثر صلة؟',
                'يريد الموكل جوابا مدعما بالمصدر: %s ما النص القانوني الواجب البحث فيه؟',
            ];

            return sprintf($frames[$variant], $question);
        }

        $frames = [
            'Pour préparer une consultation au Maroc: %s Quelle source officielle faut-il examiner en priorité ?',
            'Dans un nouveau dossier marocain, %s Quelle règle juridique est la plus directement applicable ?',
            'Un avocat doit qualifier cette situation: %s Quels textes officiels faut-il prioriser ?',
            'Pour évaluer ce litige: %s Quel domaine et quelle source sont les plus pertinents ?',
            'Le client demande une réponse sourcée: %s Dans quel texte marocain faut-il rechercher ?',
        ];

        return sprintf($frames[$variant], $question);
    }

    private function domainQuestionSeeds(): array
    {
        return [
            'family' => [
                'cluster' => 'Family-law maintenance/custody failures',
                'expectedDomain' => 'family_marriage_divorce',
                'expectedTerms' => ['famille', 'divorce', 'garde', 'pension alimentaire', 'enfant', 'mere', 'pere'],
                'templates' => [
                    'بعد الطلاق، {fact}. ما حكم {issue}؟',
                    'في مدونة الأسرة، {fact}. ما هي القاعدة حول {issue}؟',
                    '{actor} veut savoir: {fact}. Quel article ou principe concerne {issue} ?',
                    'Cas pratique: {fact}. Comment analyser {issue} en droit marocain ?',
                    'محام يسأل: {fact}. ما الدليل أو النص المهم في {issue}؟',
                ],
                'topics' => [
                    ['actor' => 'La mère', 'fact' => 'الأم تزوجت بعد الطلاق والأب يطلب إسقاط الحضانة', 'issue' => 'الحضانة', 'terms' => ['custody', 'remariage']],
                    ['actor' => 'Le père', 'fact' => 'الأب لا يؤدي نفقة الأطفال منذ عدة أشهر', 'issue' => 'النفقة', 'terms' => ['nafaqa', 'pension']],
                    ['actor' => 'La mère', 'fact' => 'les revenus du père ont augmenté après le jugement de divorce', 'issue' => 'la révision de la pension alimentaire', 'terms' => ['revision pension']],
                    ['actor' => 'Le père', 'fact' => 'un enfant refuse de vivre avec le parent gardien', 'issue' => 'la garde et l intérêt de l enfant', 'terms' => ['garde enfant']],
                    ['actor' => 'La mère', 'fact' => 'الحاضنة تريد الانتقال بمدينة أخرى مع الطفل', 'issue' => 'مصلحة المحضون والتنقل', 'terms' => ['deplacement garde']],
                    ['actor' => 'Un époux', 'fact' => 'un divorce est demandé avec des enfants mineurs', 'issue' => 'la pension, le logement et la garde', 'terms' => ['divorce enfants logement']],
                ],
            ],
            'real_estate' => [
                'cluster' => 'Property-law routing failures',
                'expectedDomain' => 'real_estate_rent',
                'expectedTerms' => ['immobilier', 'foncier', 'copropriete', 'bail', 'loyer', 'titre foncier'],
                'templates' => [
                    '{fact}. Quelle règle marocaine concerne {issue} ?',
                    'في العقار، {fact}. ما المسطرة أو القاعدة حول {issue}؟',
                    'Un client demande: {fact}. Quels textes chercher pour {issue} ?',
                    '{fact}. Est-ce une question de propriété, bail, copropriété ou immatriculation ?',
                    'محام يسأل: {fact}. ما المصدر القانوني الأقرب؟',
                ],
                'topics' => [
                    ['fact' => 'بعد نشر إعلان انتهاء التحديد، يريد جار تقديم تعرض على مطلب التحفيظ', 'issue' => 'التعرض على التحفيظ', 'terms' => ['opposition', 'immatriculation']],
                    ['fact' => 'des copropriétaires contestent la désignation du syndic', 'issue' => 'la majorité de nomination du syndic', 'terms' => ['syndic', 'copropriete']],
                    ['fact' => 'un locataire ne paie plus le loyer d habitation', 'issue' => 'le recouvrement du loyer', 'terms' => ['loyer', 'locataire']],
                    ['fact' => 'باع شخص عقارا غير محفظ لشخصين مختلفين', 'issue' => 'الأولوية بين المشترين', 'terms' => ['vente', 'propriete', 'non immatricule']],
                    ['fact' => 'un propriétaire veut expulser un occupant sans contrat clair', 'issue' => 'la preuve du droit de propriété', 'terms' => ['propriete', 'occupation']],
                    ['fact' => 'un titre foncier contient une erreur sur la superficie', 'issue' => 'la rectification ou contestation foncière', 'terms' => ['titre foncier', 'rectification']],
                ],
            ],
            'labor' => [
                'cluster' => 'Labor dismissal failures',
                'expectedDomain' => 'labor',
                'expectedTerms' => ['travail', 'licenciement', 'salarie', 'employeur', 'faute grave', 'procedure disciplinaire'],
                'templates' => [
                    '{fact}. Quelle règle du Code du travail vise {issue} ?',
                    'في مدونة الشغل، {fact}. ما حكم {issue}؟',
                    'Un salarié demande: {fact}. Comment analyser {issue} ?',
                    'Cas de travail: {fact}. Quels articles chercher pour {issue} ?',
                    'محام يريد تقييم: {fact}. ما النصوص المهمة حول {issue}؟',
                ],
                'topics' => [
                    ['fact' => 'un salarié est licencié sans motif écrit', 'issue' => 'le motif valable', 'terms' => ['motif valable']],
                    ['fact' => 'أجير فصل بسبب خطأ دون الاستماع إليه', 'issue' => 'المسطرة التأديبية', 'terms' => ['audition', 'procedure disciplinaire']],
                    ['fact' => 'une salariée enceinte reçoit une lettre de licenciement', 'issue' => 'la protection maternité', 'terms' => ['grossesse', 'maternite']],
                    ['fact' => 'un employeur retarde le paiement du salaire', 'issue' => 'le salaire dû', 'terms' => ['salaire']],
                    ['fact' => 'un salarié signe une démission sous pression', 'issue' => 'la validité de la rupture', 'terms' => ['demission', 'rupture']],
                    ['fact' => 'عامل تعرض لحادث أثناء العمل', 'issue' => 'حادث الشغل', 'terms' => ['accident travail']],
                ],
            ],
            'commercial' => [
                'cluster' => 'Commercial/company failures',
                'expectedDomain' => 'commercial_company',
                'expectedTerms' => ['commerce', 'societe', 'sarl', 'registre de commerce', 'associe', 'gerant'],
                'templates' => [
                    '{fact}. Quel texte commercial concerne {issue} ?',
                    'في القانون التجاري، {fact}. ما حكم {issue}؟',
                    'Un associé demande: {fact}. Quels articles rechercher pour {issue} ?',
                    'Cas société: {fact}. Comment qualifier {issue} ?',
                    'محام يسأل: {fact}. ما المصدر التجاري الأقرب؟',
                ],
                'topics' => [
                    ['fact' => 'une SARL n est pas encore immatriculée mais signe un contrat', 'issue' => 'la personnalité morale', 'terms' => ['sarl', 'immatriculation']],
                    ['fact' => 'شركة تمارس التجارة دون قيد في السجل التجاري', 'issue' => 'القيد في السجل التجاري', 'terms' => ['registre commerce']],
                    ['fact' => 'un gérant de SARL vend un actif important sans accord des associés', 'issue' => 'les pouvoirs du gérant', 'terms' => ['gerant', 'associes']],
                    ['fact' => 'des associés veulent céder leurs parts sociales', 'issue' => 'la cession de parts', 'terms' => ['parts sociales']],
                    ['fact' => 'un commerçant cesse de payer ses dettes', 'issue' => 'les difficultés de l entreprise', 'terms' => ['difficultes entreprise']],
                    ['fact' => 'مقاول يستعمل اسما تجاريا قريب من شركة أخرى', 'issue' => 'الاسم التجاري والمنافسة', 'terms' => ['nom commercial']],
                ],
            ],
            'civil' => [
                'cluster' => 'Civil obligations/contracts failures',
                'expectedDomain' => 'civil_obligations_contracts',
                'expectedTerms' => ['obligations', 'contrats', 'vente', 'preuve', 'dette', 'responsabilite', 'delivrance'],
                'templates' => [
                    '{fact}. Quelle règle du DOC concerne {issue} ?',
                    'في قانون الالتزامات والعقود، {fact}. ما حكم {issue}؟',
                    'Un client explique: {fact}. Quels articles chercher pour {issue} ?',
                    'Cas civil: {fact}. Comment analyser {issue} ?',
                    'محام يسأل: {fact}. ما النص المدني الأقرب؟',
                ],
                'topics' => [
                    ['fact' => 'une personne transfère 120000 dirhams et l autre dit que c était un don', 'issue' => 'la preuve de la dette', 'terms' => ['preuve', 'dette']],
                    ['fact' => 'البائع قبض الثمن ولم يسلم المبيع', 'issue' => 'التسليم في البيع', 'terms' => ['delivrance', 'vente']],
                    ['fact' => 'un contrat est signé mais une partie refuse d exécuter', 'issue' => 'la force obligatoire du contrat', 'terms' => ['execution contrat']],
                    ['fact' => 'un acheteur découvre un vice caché après la livraison', 'issue' => 'la garantie', 'terms' => ['vice cache', 'garantie']],
                    ['fact' => 'une personne cause un dommage sans contrat', 'issue' => 'la responsabilité civile', 'terms' => ['responsabilite civile']],
                    ['fact' => 'رسائل واتساب تثبت اتفاقا على قرض', 'issue' => 'بداية الحجة بالكتابة', 'terms' => ['commencement preuve']],
                ],
            ],
            'civil_procedure' => [
                'cluster' => 'Civil procedure failures',
                'expectedDomain' => 'civil_procedure',
                'expectedTerms' => ['procedure civile', 'appel', 'competence', 'notification', 'execution', 'tribunal'],
                'templates' => [
                    '{fact}. Quelle règle de procédure civile concerne {issue} ?',
                    'في المسطرة المدنية، {fact}. ما القاعدة حول {issue}؟',
                    'Un avocat demande: {fact}. Quels articles chercher pour {issue} ?',
                    'Cas procédure: {fact}. Comment vérifier {issue} ?',
                    'محام يسأل: {fact}. ما النص المسطري الأقرب؟',
                ],
                'topics' => [
                    ['fact' => 'صدر حكم ابتدائي ويريد الموكل الاستئناف', 'issue' => 'أجل الاستئناف', 'terms' => ['appel', 'delai']],
                    ['fact' => 'le défendeur habite à Casablanca mais le contrat est signé à Rabat', 'issue' => 'la compétence territoriale', 'terms' => ['competence territoriale']],
                    ['fact' => 'une partie n a pas reçu la convocation', 'issue' => 'la notification', 'terms' => ['notification']],
                    ['fact' => 'un jugement définitif n est pas exécuté', 'issue' => 'l exécution forcée', 'terms' => ['execution forcee']],
                    ['fact' => 'un créancier veut pratiquer une saisie-arrêt', 'issue' => 'la saisie', 'terms' => ['saisie arret']],
                    ['fact' => 'المدعي يريد رفع دعوى ضد شخص مجهول الموطن', 'issue' => 'الاختصاص والتبليغ', 'terms' => ['domicile', 'notification']],
                ],
            ],
            'criminal' => [
                'cluster' => 'Criminal law failures',
                'expectedDomain' => 'criminal',
                'expectedTerms' => ['penal', 'vol', 'escroquerie', 'violence', 'infraction', 'peine'],
                'templates' => [
                    '{fact}. Quel article du Code pénal concerne {issue} ?',
                    'في القانون الجنائي، {fact}. ما التكييف الأقرب لـ {issue}؟',
                    'Une victime explique: {fact}. Quels textes chercher pour {issue} ?',
                    'Cas pénal: {fact}. Comment qualifier {issue} ?',
                    'محام يسأل: {fact}. ما النص الجنائي الأقرب؟',
                ],
                'topics' => [
                    ['fact' => 'شخص أخذ مال غيره بدون حق', 'issue' => 'السرقة', 'terms' => ['vol', 'soustraction']],
                    ['fact' => 'une personne utilise de fausses qualités pour obtenir de l argent', 'issue' => 'l escroquerie', 'terms' => ['escroquerie']],
                    ['fact' => 'une victime est frappée avec blessure', 'issue' => 'les violences', 'terms' => ['violence', 'blessures']],
                    ['fact' => 'un commerçant émet un chèque sans provision', 'issue' => 'le chèque impayé', 'terms' => ['cheque', 'provision']],
                    ['fact' => 'شخص يهدد آخر برسائل مكتوبة', 'issue' => 'التهديد', 'terms' => ['menace']],
                    ['fact' => 'un employé détourne l argent de son employeur', 'issue' => 'abus de confiance ou vol', 'terms' => ['abus confiance']],
                ],
            ],
            'banking' => [
                'cluster' => 'Banking authorization/confidentiality failures',
                'expectedDomain' => 'banking_finance',
                'expectedTerms' => ['banque', 'credit', 'agrement', 'secret professionnel', 'bank al maghrib', 'compte'],
                'templates' => [
                    '{fact}. Quelle règle bancaire concerne {issue} ?',
                    'في القانون البنكي، {fact}. ما حكم {issue}؟',
                    'Un client bancaire demande: {fact}. Quels textes chercher pour {issue} ?',
                    'Cas banque: {fact}. Comment analyser {issue} ?',
                    'محام يسأل: {fact}. ما النص البنكي الأقرب؟',
                ],
                'topics' => [
                    ['fact' => 'une société veut exercer comme établissement de crédit sans agrément', 'issue' => 'l agrément bancaire', 'terms' => ['agrement']],
                    ['fact' => 'موظف بنك كشف معطيات حساب زبون', 'issue' => 'السر المهني', 'terms' => ['secret professionnel']],
                    ['fact' => 'une banque refuse d ouvrir un compte sans expliquer sa décision', 'issue' => 'les obligations bancaires', 'terms' => ['compte bancaire']],
                    ['fact' => 'un client conteste des frais bancaires prélevés', 'issue' => 'les frais et information du client', 'terms' => ['frais bancaire']],
                    ['fact' => 'مؤسسة تمنح قروضا للجمهور بدون ترخيص', 'issue' => 'اعتماد مؤسسة الائتمان', 'terms' => ['credit institution']],
                    ['fact' => 'une banque communique un relevé à un tiers', 'issue' => 'la confidentialité bancaire', 'terms' => ['confidentialite']],
                ],
            ],
            'tax' => [
                'cluster' => 'Tax retrieval failures',
                'expectedDomain' => 'tax',
                'expectedTerms' => ['fiscal', 'taxe', 'tva', 'recouvrement', 'impot', 'creances publiques'],
                'templates' => [
                    '{fact}. Quelle règle fiscale concerne {issue} ?',
                    'في المجال الضريبي، {fact}. ما النص حول {issue}؟',
                    'Un contribuable demande: {fact}. Quels textes chercher pour {issue} ?',
                    'Cas fiscal: {fact}. Comment analyser {issue} ?',
                    'محام يسأل: {fact}. ما المصدر الضريبي الأقرب؟',
                ],
                'topics' => [
                    ['fact' => 'الإدارة بدأت التحصيل الجبري لدين عمومي', 'issue' => 'الإنذار والحجز والبيع', 'terms' => ['recouvrement', 'creances publiques']],
                    ['fact' => 'une société demande le remboursement de la TVA', 'issue' => 'le remboursement de TVA', 'terms' => ['tva', 'remboursement']],
                    ['fact' => 'un contribuable reçoit un avis d imposition contesté', 'issue' => 'la contestation fiscale', 'terms' => ['avis imposition']],
                    ['fact' => 'une entreprise importe des marchandises', 'issue' => 'les droits et taxes', 'terms' => ['douane', 'importation']],
                    ['fact' => 'شركة لم تصرح بالضريبة داخل الأجل', 'issue' => 'الجزاءات الضريبية', 'terms' => ['penalite fiscale']],
                    ['fact' => 'une commune réclame une taxe locale', 'issue' => 'le recouvrement local', 'terms' => ['taxe locale']],
                ],
            ],
            'administrative' => [
                'cluster' => 'Administrative law failures',
                'expectedDomain' => 'administrative_urbanism',
                'expectedTerms' => ['administration', 'acte administratif', 'recepisse', 'delai', 'autorisation', 'recours'],
                'templates' => [
                    '{fact}. Quelle règle administrative concerne {issue} ?',
                    'في القانون الإداري، {fact}. ما القاعدة حول {issue}؟',
                    'Un usager demande: {fact}. Quels textes chercher pour {issue} ?',
                    'Cas administratif: {fact}. Comment analyser {issue} ?',
                    'محام يسأل: {fact}. ما النص الإداري الأقرب؟',
                ],
                'topics' => [
                    ['fact' => 'الإدارة رفضت تسليم وصل إيداع الطلب', 'issue' => 'وصل الإيداع', 'terms' => ['recepisse']],
                    ['fact' => 'une administration dépasse le délai de traitement d une demande', 'issue' => 'le délai maximal', 'terms' => ['delai administratif']],
                    ['fact' => 'un citoyen conteste une décision administrative', 'issue' => 'le recours', 'terms' => ['recours administratif']],
                    ['fact' => 'une commune refuse une autorisation sans motivation', 'issue' => 'la motivation de la décision', 'terms' => ['autorisation', 'motivation']],
                    ['fact' => 'مقاول ينتظر رخصة إدارية منذ أشهر', 'issue' => 'سكوت الإدارة والأجل', 'terms' => ['silence administration']],
                    ['fact' => 'un dossier administratif est déclaré incomplet', 'issue' => 'les pièces demandées', 'terms' => ['dossier incomplet']],
                ],
            ],
        ];
    }
}
