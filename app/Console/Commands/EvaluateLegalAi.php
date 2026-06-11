<?php

namespace App\Console\Commands;

use App\Services\AiReasoningService;
use App\Services\ChatLawService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class EvaluateLegalAi extends Command
{
    protected $signature = 'legal-ai:evaluate
        {--case= : Run one benchmark case id}
        {--suite=benchmarks : Benchmark suite to run: benchmarks or holdout}
        {--json=legal-ai-evaluation.json : Store a JSON report under storage/app}
        {--csv=legal-ai-evaluation.csv : Store a flat CSV results table under storage/app}
        {--retrieval-only : Skip answer generation and evaluate retrieval only}';

    protected $description = 'Evaluate Moroccan legal retrieval, citations, and answer support against configured benchmark questions.';

    public function handle(ChatLawService $chat, AiReasoningService $ai): int
    {
        $suite = strtolower(trim((string) $this->option('suite')));
        $configKey = match ($suite) {
            'benchmarks', 'main', 'expanded' => 'legal_ai_benchmarks.cases',
            'holdout', 'blind', 'blind-holdout' => 'legal_ai_holdout_benchmarks.cases',
            'smoke', 'manual-smoke', 'field-failures' => 'legal_ai_smoke_benchmarks.cases',
            default => null,
        };

        if ($configKey === null) {
            $this->error("Unknown benchmark suite '{$suite}'. Use benchmarks, holdout, or smoke.");

            return self::FAILURE;
        }

        $cases = collect(config($configKey, []));
        $caseId = trim((string) $this->option('case'));

        if ($caseId !== '') {
            $cases = $cases->filter(fn (array $case): bool => ($case['id'] ?? '') === $caseId)->values();
        }

        if ($cases->isEmpty()) {
            $this->error($caseId !== '' ? "No benchmark case found for {$caseId}." : 'No legal AI benchmark cases configured.');

            return self::FAILURE;
        }

        $results = [];

        foreach ($cases as $case) {
            $this->line('Running '.($case['id'] ?? 'unknown').'...');
            $results[] = $this->evaluateCase($case, $chat, $ai);
        }

        $this->table(
            ['Case', 'Lang', 'Area', 'Top source', 'Support', 'Result'],
            collect($results)->map(fn (array $result): array => [
                $result['id'],
                $result['language'],
                $result['legalArea'],
                $result['retrievedSources'][0] ?? 'none',
                $result['answerSupport']['status'] ?? 'not_checked',
                $result['pass'] ? 'pass' : implode(', ', $result['errorTypes']),
            ])->all()
        );

        $report = [
            'generatedAt' => now()->toIso8601String(),
            'suite' => $suite,
            'configKey' => $configKey,
            'totalCases' => count($results),
            'passedCases' => collect($results)->where('pass', true)->count(),
            'failedCases' => collect($results)->where('pass', false)->count(),
            'summaryByLanguage' => $this->summaryBy($results, 'language'),
            'summaryByLegalArea' => $this->summaryBy($results, 'legalArea'),
            'summaryByErrorType' => collect($results)->flatMap(fn (array $result): array => $result['errorTypes'])->countBy()->all(),
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
            $this->line('CSV table written to storage/app/'.$csvPath);
        }

        if ($report['failedCases'] > 0) {
            $this->warn($report['failedCases'].' benchmark case(s) need attention.');

            return self::FAILURE;
        }

        $this->info('All benchmark cases passed.');

        return self::SUCCESS;
    }

    private function evaluateCase(array $case, ChatLawService $chat, AiReasoningService $ai): array
    {
        $message = (string) $case['message'];
        $intent = $chat->classifyIntent($message);
        $plan = $intent === ChatLawService::INTENT_CASE_ANALYSIS
            ? $ai->createSearchPlan($message)
            : null;
        $plan = array_merge($plan ?? [], ['disableSearchCache' => true]);
        $context = $chat->prepare($message, [], $plan, $intent);
        $citations = $context['citations'] ?? [];
        $answer = '';
        $support = ['status' => 'not_checked', 'warnings' => []];

        if (!$this->option('retrieval-only')) {
            $answer = $context['answer']
                ?? $ai->answer($message, $citations, $context['plan'] ?? ['aiPlan' => $plan])
                ?? $context['fallbackAnswer']
                ?? '';
            $support = $ai->verifyAnswerSupport($answer, $citations, $context['responseLanguage'] ?? ($case['language'] ?? 'en'));
        }

        $diagnostics = $context['diagnostics'] ?? [];
        $errorTypes = $this->caseErrors($case, $context['intent'] ?? $intent, $citations, $support, $answer, $diagnostics, $context);
        $likelyCauses = $this->likelyCauses($case, $errorTypes, $diagnostics);

        return [
            'id' => $case['id'] ?? 'unknown',
            'question' => $message,
            'language' => $case['language'] ?? 'unknown',
            'legalArea' => $case['legalArea'] ?? 'unknown',
            'expectedSourceType' => $case['expectedSourceType'] ?? null,
            'expectedDocuments' => $case['expectedDocuments'] ?? [],
            'expectedArticles' => array_values(array_unique([
                ...($case['expectedArticles'] ?? []),
                ...($case['expectedArticlesAny'] ?? []),
            ])),
            'expectedDomains' => $case['expectedDomains'] ?? [],
            'retrievedSources' => collect($citations)->map(fn (array $citation): string => implode(' | ', array_filter([
                $citation['documentTitle'] ?? null,
                $citation['articleNumber'] ?? null,
                $citation['domain'] ?? null,
            ])))->all(),
            'retrievedSourceDetails' => collect($citations)->map(fn (array $citation): array => [
                'documentTitle' => $citation['documentTitle'] ?? null,
                'documentType' => $citation['documentType'] ?? null,
                'articleNumber' => $citation['articleNumber'] ?? null,
                'domain' => $citation['domain'] ?? null,
                'supportLevel' => $citation['supportLevel'] ?? null,
                'sourceAuthorityLevel' => $citation['sourceAuthorityLevel'] ?? null,
                'matchedQuery' => $citation['matchedQuery'] ?? null,
            ])->all(),
            'intent' => $context['intent'] ?? $intent,
            'answerLanguage' => $context['responseLanguage'] ?? null,
            'answer' => $answer,
            'answerSupport' => $support,
            'pass' => $errorTypes === [],
            'errorTypes' => $errorTypes,
            'likelyCauses' => $likelyCauses,
            'fixApplied' => $case['fixApplied'] ?? null,
            'retestResult' => $errorTypes === [] ? 'pass' : 'needs_fix',
            'diagnostics' => $diagnostics,
        ];
    }

    private function caseErrors(
        array $case,
        string $intent,
        array $citations,
        array $support,
        string $answer,
        array $diagnostics,
        array $context
    ): array {
        $errors = [];
        $citationCollection = collect($citations);
        $expectedDomains = collect($case['expectedDomains'] ?? [])->map(fn (string $domain): string => $this->normalizeDomain($domain));
        $actualDomains = $citationCollection
            ->pluck('domain')
            ->filter()
            ->map(fn (string $domain): string => $this->normalizeDomain($domain));

        if (($case['expectedIntent'] ?? null) && $intent !== $case['expectedIntent']) {
            $errors[] = 'intent_mismatch';
        }

        if (!$citations) {
            $errors[] = 'no_sources_retrieved';
        }

        foreach ($case['expectedDocuments'] ?? [] as $documentTitle) {
            if (!$citationCollection->contains(fn (array $citation): bool => $this->documentTitleMatches($citation['documentTitle'] ?? '', $documentTitle))) {
                $errors[] = 'missing_expected_document';
            }
        }

        foreach ($case['expectedArticles'] ?? [] as $articleNumber) {
            if (!$citationCollection->contains('articleNumber', $articleNumber)) {
                $errors[] = 'missing_applicable_article';
            }
        }

        $expectedArticlesAny = $case['expectedArticlesAny'] ?? [];
        if ($expectedArticlesAny && !$citationCollection->contains(fn (array $citation): bool => in_array($citation['articleNumber'] ?? null, $expectedArticlesAny, true))) {
            $errors[] = 'missing_applicable_article';
        }

        if ($expectedDomains->isNotEmpty() && !$actualDomains->contains(fn (string $domain): bool => $expectedDomains->contains($domain))) {
            $errors[] = 'wrong_legal_domain';
        }

        if ($actualDomains->isNotEmpty() && $expectedDomains->isNotEmpty()) {
            $wrongDomainCount = $actualDomains->filter(fn (string $domain): bool => !$expectedDomains->contains($domain))->count();
            if ($wrongDomainCount > ($actualDomains->count() / 2)) {
                $errors[] = 'irrelevant_article_retrieval';
            }
        }

        $expectedArticles = [...($case['expectedArticles'] ?? []), ...$expectedArticlesAny];
        $expectedRanks = $citationCollection
            ->values()
            ->map(fn (array $citation, int $index): ?int => in_array($citation['articleNumber'] ?? null, $expectedArticles, true) ? $index + 1 : null)
            ->filter();
        if ($expectedRanks->isNotEmpty() && $expectedRanks->min() > 3) {
            $errors[] = 'poor_ranking';
        }

        if (!$this->option('retrieval-only') && in_array($support['status'] ?? null, ['partial_sources', 'insufficient_sources'], true)) {
            $errors[] = 'answer_unsupported_by_sources';
        }

        if (($case['language'] ?? null) === 'ar' && ($context['responseLanguage'] ?? null) !== 'ar') {
            $errors[] = 'arabic_french_language_mismatch';
        }

        if ($this->answerMentionsUncitedArticle($answer, $citations)) {
            $errors[] = 'hallucinated_or_uncited_article';
        }

        if (($case['language'] ?? null) === 'ar' && in_array('missing_applicable_article', $errors, true)) {
            $queries = collect($diagnostics['queries'] ?? []);
            if ($queries->isEmpty() || $queries->every(fn (string $query): bool => preg_match('/\p{Arabic}/u', $query) === 1)) {
                $errors[] = 'arabic_french_query_mismatch';
            }
        }

        return array_values(array_unique($errors));
    }

    private function likelyCauses(array $case, array $errors, array $diagnostics): array
    {
        $causes = [];

        foreach ($errors as $error) {
            $causes[] = match ($error) {
                'no_sources_retrieved' => 'indexing problem or overly restrictive source gate',
                'wrong_legal_domain', 'irrelevant_article_retrieval' => 'missing metadata/category boost or ranking/scoring issue',
                'missing_expected_document' => 'ranking/scoring issue or missing document-title expansion',
                'missing_applicable_article', 'poor_ranking' => 'ranking/scoring issue or missing article-level concept terms',
                'arabic_french_query_mismatch' => 'Arabic morphology/tokenization or translation issue',
                'arabic_french_language_mismatch' => 'response-language detection issue',
                'answer_unsupported_by_sources' => 'bad answer prompt or insufficient citation verification/repair',
                'hallucinated_or_uncited_article' => 'bad answer prompt or citation verifier gap',
                'intent_mismatch' => 'intent-classification problem',
                default => 'requires manual review',
            };
        }

        if (($case['language'] ?? null) === 'ar' && collect($diagnostics['expandedQueries'] ?? [])->isEmpty()) {
            $causes[] = 'Arabic morphology/tokenization or translation issue';
        }

        return array_values(array_unique($causes));
    }

    private function answerMentionsUncitedArticle(string $answer, array $citations): bool
    {
        if ($answer === '') {
            return false;
        }

        preg_match_all('/\bArticle\s+(?:premier|\d+(?:\s+(?:bis|ter|quater))?)/iu', $answer, $matches);
        $citedArticles = collect($citations)
            ->pluck('articleNumber')
            ->filter()
            ->map(fn (string $article): string => mb_strtolower(trim($article)));

        return collect($matches[0] ?? [])
            ->map(fn (string $article): string => mb_strtolower(trim($article)))
            ->contains(fn (string $article): bool => !$citedArticles->contains($article));
    }

    private function summaryBy(array $results, string $key): array
    {
        return collect($results)
            ->groupBy($key)
            ->map(fn ($items): array => [
                'total' => $items->count(),
                'passed' => $items->where('pass', true)->count(),
                'failed' => $items->where('pass', false)->count(),
            ])
            ->all();
    }

    private function csvReport(array $results): string
    {
        $columns = [
            'question', 'language', 'legal_area', 'expected_source_type', 'retrieved_sources',
            'pass_fail', 'error_type', 'likely_cause', 'fix_applied', 'retest_result',
        ];
        $lines = [implode(',', $columns)];

        foreach ($results as $result) {
            $lines[] = collect([
                $result['question'],
                $result['language'],
                $result['legalArea'],
                $result['expectedSourceType'],
                implode(' || ', $result['retrievedSources']),
                $result['pass'] ? 'pass' : 'fail',
                implode(' | ', $result['errorTypes']),
                implode(' | ', $result['likelyCauses']),
                $result['fixApplied'],
                $result['retestResult'],
            ])->map(fn (mixed $value): string => '"'.str_replace('"', '""', (string) $value).'"')->implode(',');
        }

        return implode(PHP_EOL, $lines).PHP_EOL;
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

    private function documentTitleMatches(string $actual, string $expected): bool
    {
        $actual = $this->normalizeTitle($actual);
        $expected = $this->normalizeTitle($expected);

        return $actual !== ''
            && $expected !== ''
            && ($actual === $expected || str_contains($actual, $expected) || str_contains($expected, $actual));
    }

    private function normalizeTitle(string $value): string
    {
        return str($value)
            ->lower()
            ->ascii()
            ->replaceMatches('/[^a-z0-9]+/', ' ')
            ->replaceMatches('/\s+/', ' ')
            ->trim()
            ->toString();
    }
}
