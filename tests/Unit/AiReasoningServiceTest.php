<?php

namespace Tests\Unit;

use App\Services\AiReasoningService;
use PHPUnit\Framework\TestCase;

class AiReasoningServiceTest extends TestCase
{
    public function test_local_plan_targets_employment_data_transfer_cases(): void
    {
        $plan = (new AiReasoningService())->createSearchPlan(
            'An employee was dismissed after sending company documents to his personal email.'
        );

        $this->assertNotNull($plan);
        $this->assertSame('employment termination', $plan['legalIssue']);
        $this->assertContains('Code du travail', $plan['allowedDocumentTitles']);
        $this->assertContains('labor', $plan['allowedCategories']);
        $this->assertStringContainsString('licenciement', implode(' ', $plan['searchQueries']));
        $this->assertStringNotContainsString('article 35', implode(' ', $plan['searchQueries']));
        $this->assertStringNotContainsString('article 63', implode(' ', $plan['searchQueries']));
    }

    public function test_local_plan_does_not_invent_article_number_queries(): void
    {
        $plan = (new AiReasoningService())->createSearchPlan(
            'What can I do if my phone got stolen?'
        );

        $this->assertNotNull($plan);
        $queries = implode(' ', $plan['searchQueries']);
        $this->assertStringContainsString('vol code penal', $queries);
        $this->assertStringNotContainsString('article 505', $queries);
        $this->assertStringNotContainsString('article 506', $queries);
    }

    public function test_specialized_employment_answer_preserves_user_facts(): void
    {
        $answer = (new AiReasoningService())->answer(
            'Analyze the case: an employee with 8 years of service and no disciplinary record sent documents to his personal email to work from home. No competitor received them, no damage is proven, and he was dismissed immediately. Cover facts, arguments, evidence, procedure, and conclusion.',
            $this->employmentCitations(),
            ['aiPlan' => ['legalIssue' => 'employment termination']]
        );

        $this->assertNotNull($answer);
        $this->assertStringContainsString('A. Faits importants', $answer);
        $this->assertStringContainsString('adresse email personnelle', $answer);
        $this->assertStringContainsString('travailler depuis son domicile', $answer);
        $this->assertStringContainsString('aucun concurrent', $answer);
        $this->assertStringNotContainsString('cle USB', $answer);
        $this->assertStringContainsString('Article 63', $answer);
    }

    private function employmentCitations(): array
    {
        return [
            $this->citation('Article 35', 'Valid reason for dismissal linked to aptitude, conduct, or company needs.'),
            $this->citation('Article 39', 'Serious faults include theft and breach of trust when established.'),
            $this->citation('Article 62', 'The employee must be heard and allowed to defend himself before disciplinary dismissal.'),
            $this->citation('Article 63', 'The employer must justify the dismissal decision.'),
            $this->citation('Article 64', 'The dismissal decision must state the reasons and circumstances.'),
            $this->citation('Article 65', 'Dismissal action must be filed within 90 days.'),
        ];
    }

    private function citation(string $articleNumber, string $content): array
    {
        return [
            'id' => crc32($articleNumber),
            'title' => "Code du travail - {$articleNumber}",
            'articleNumber' => $articleNumber,
            'content' => $content,
            'documentTitle' => 'Code du travail',
            'lawReference' => 'Loi n 65-99',
            'sourceName' => 'Test',
            'sourceUrl' => 'https://example.test/code-travail.pdf',
            'category' => 'labor',
            'relevanceScore' => 100,
            'sourceRelevanceScore' => 10,
            'matchedQuery' => strtolower($articleNumber).' code du travail',
        ];
    }
}
