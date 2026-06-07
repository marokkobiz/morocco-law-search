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

    public function test_local_plan_preserves_facts_and_adds_criminal_scope_for_workplace_theft(): void
    {
        $plan = (new AiReasoningService())->createSearchPlan(
            'An employee was dismissed after being accused of theft, but the company inventory shows no laptop is missing.'
        );

        $this->assertNotNull($plan);
        $this->assertContains('Code du travail', $plan['allowedDocumentTitles']);
        $this->assertContains('Code penal', $plan['allowedDocumentTitles']);
        $this->assertContains('labor', $plan['allowedCategories']);
        $this->assertContains('criminal', $plan['allowedCategories']);
        $this->assertStringContainsString('vol code penal', implode(' ', $plan['searchQueries']));
        $this->assertStringNotContainsString('article 505', implode(' ', $plan['searchQueries']));
        $this->assertNotEmpty($plan['facts']);
        $this->assertStringContainsString('ordinateur', implode(' ', $plan['facts']));
    }

    public function test_workplace_misappropriation_candidates_are_criminal_first(): void
    {
        $plan = (new AiReasoningService())->createSearchPlan(
            'Un employe detourne l argent de la caisse de son employeur. Est-ce un vol ou un abus de confiance ?'
        );

        $this->assertNotNull($plan);
        $this->assertSame('workplace criminal theft or breach of trust', $plan['legalIssue']);
        $this->assertSame('criminal', $plan['dominantDomain']);
        $this->assertContains('Code penal', $plan['allowedDocumentTitles']);
        $this->assertSame(['criminal'], $plan['allowedCategories']);
        $queries = implode(' ', $plan['searchQueries']);
        $this->assertStringContainsString('abus de confiance code penal', $queries);
        $this->assertStringContainsString('detournement de fonds code penal', $queries);
        $this->assertStringNotContainsString('code du travail licenciement', $queries);
    }

    public function test_company_tax_penalty_candidates_are_tax_first(): void
    {
        $plan = (new AiReasoningService())->createSearchPlan(
            'La DGI applique une penalite et une majoration a une SARL apres une declaration fiscale tardive.'
        );

        $this->assertNotNull($plan);
        $this->assertSame('company tax declaration penalty or reassessment', $plan['legalIssue']);
        $this->assertSame('tax', $plan['dominantDomain']);
        $this->assertContains('tax', $plan['allowedCategories']);
        $queries = implode(' ', $plan['searchQueries']);
        $this->assertStringContainsString('declaration fiscale societe sanction fiscale', $queries);
        $this->assertStringContainsString('penalite fiscale majoration societe', $queries);
        $this->assertStringNotContainsString('registre de commerce', $queries);
    }

    public function test_arabic_property_concepts_expand_to_french_property_queries(): void
    {
        $service = new AiReasoningService();

        $possession = $service->createSearchPlan('من له الحيازة القانونية لعقار غير محفظ وكيف تثبت؟');
        $claim = $service->createSearchPlan('ما أساس دعوى الاستحقاق لاسترجاع عقار من حائز بدون حق؟');
        $preemption = $service->createSearchPlan('ما هي شروط الشفعة في العقار؟');

        $this->assertNotNull($possession);
        $this->assertSame('real_estate_rent', $possession['dominantDomain']);
        $this->assertStringContainsString('possession propriete immobiliere', implode(' ', $possession['searchQueries']));

        $this->assertNotNull($claim);
        $this->assertSame('real_estate_rent', $claim['dominantDomain']);
        $this->assertStringContainsString('action en revendication propriete immobiliere', implode(' ', $claim['searchQueries']));

        $this->assertNotNull($preemption);
        $this->assertStringContainsString('droit de preemption', implode(' ', $preemption['searchQueries']));
    }

    public function test_sale_ownership_heirs_plan_adds_succession_scope_without_fake_articles(): void
    {
        $plan = (new AiReasoningService())->createSearchPlan(
            'A seller sold a car, the buyer paid and took possession, then the seller died and his heirs claim ownership.'
        );

        $this->assertNotNull($plan);
        $this->assertContains('Code des Obligations et des Contrats', $plan['allowedDocumentTitles']);
        $this->assertContains('Code de la famille', $plan['allowedDocumentTitles']);
        $this->assertContains('civil', $plan['allowedCategories']);
        $this->assertContains('family', $plan['allowedCategories']);
        $this->assertStringContainsString('succession heritiers', implode(' ', $plan['searchQueries']));
        $this->assertFalse(collect($plan['searchQueries'])->contains(
            fn (string $query): bool => preg_match('/\b(?:article|art)\s*(premier|\d+)/i', $query)
        ));
    }

    public function test_arabic_sarl_status_routes_to_company_specific_source(): void
    {
        $plan = (new AiReasoningService())->createSearchPlan(
            'متى تكتسب الشركة ذات المسؤولية المحدودة الشخصية المعنوية وهل تعتبر تجارية بشكلها؟'
        );

        $this->assertNotNull($plan);
        $this->assertSame('sarl commercial status and legal personality', $plan['legalIssue']);
        $this->assertContains('Societe en nom collectif et SARL', $plan['allowedDocumentTitles']);
        $this->assertStringContainsString('societe en nom collectif et sarl', implode(' ', $plan['searchQueries']));
        $this->assertStringNotContainsString('registre de commerce', implode(' ', $plan['searchQueries']));
    }

    public function test_arabic_banking_approval_routes_to_credit_institution_source(): void
    {
        $plan = (new AiReasoningService())->createSearchPlan(
            'هل يمكن لشركة أن تمارس نشاط مؤسسة ائتمان في المغرب دون الحصول على اعتماد مسبق؟'
        );

        $this->assertNotNull($plan);
        $this->assertSame('banking credit institution approval', $plan['legalIssue']);
        $this->assertContains('Etablissements de credit et organismes assimiles', $plan['allowedDocumentTitles']);
        $this->assertContains('banking_finance', $plan['allowedCategories']);
        $this->assertStringContainsString('agrement', implode(' ', $plan['searchQueries']));
        $this->assertStringNotContainsString('registre de commerce', implode(' ', $plan['searchQueries']));
    }

    public function test_arabic_vat_refund_routes_to_tva_source(): void
    {
        $plan = (new AiReasoningService())->createSearchPlan(
            'ما النص الذي ينظم طلب استرجاع الضريبة على القيمة المضافة في النظام المغربي؟'
        );

        $this->assertNotNull($plan);
        $this->assertSame('vat refund request', $plan['legalIssue']);
        $this->assertContains('Application de la taxe sur la valeur ajoutee', $plan['allowedDocumentTitles']);
        $this->assertContains('tax', $plan['allowedCategories']);
        $this->assertStringContainsString('taxe sur la valeur ajoutee', implode(' ', $plan['searchQueries']));
    }

    public function test_arabic_sale_delivery_routes_to_obligations_code(): void
    {
        $plan = (new AiReasoningService())->createSearchPlan(
            'البائع قبض الثمن لكنه لم يمكن المشتري من حيازة المبيع، ما مفهوم التسليم في عقد البيع؟'
        );

        $this->assertNotNull($plan);
        $this->assertStringContainsString('sale delivery legal definition', $plan['legalIssue']);
        $this->assertContains('Code des Obligations et des Contrats', $plan['allowedDocumentTitles']);
        $this->assertContains('civil', $plan['allowedCategories']);
        $this->assertStringContainsString('delivrance', implode(' ', $plan['searchQueries']));
    }

    public function test_arabic_defendant_domicile_does_not_route_to_appeal_deadline(): void
    {
        $plan = (new AiReasoningService())->createSearchPlan(
            'إذا أردت مقاضاة مدعى عليه له موطن معروف، أي محكمة تكون مختصة ترابيا من حيث الأصل؟'
        );

        $this->assertNotNull($plan);
        $this->assertSame('civil procedure territorial jurisdiction', $plan['legalIssue']);
        $this->assertContains('Code de procedure civile', $plan['allowedDocumentTitles']);
        $this->assertStringContainsString('competence territoriale', implode(' ', $plan['searchQueries']));
        $this->assertStringNotContainsString('appel delai', implode(' ', $plan['searchQueries']));
    }

    public function test_arabic_unregistered_property_double_sale_generates_property_dispute_candidates(): void
    {
        $plan = (new AiReasoningService())->createSearchPlan(
            'باع شخص عقارًا غير محفظ لشخصين مختلفين، من له الأولوية؟'
        );

        $this->assertNotNull($plan);
        $this->assertStringContainsString('unregistered real estate double sale priority', $plan['legalIssue']);
        $this->assertSame('real_estate_rent', $plan['dominantDomain']);
        $this->assertContains('Code des droits reels', $plan['allowedDocumentTitles']);
        $this->assertContains('real-estate', $plan['allowedCategories']);
        $queries = implode(' ', $plan['searchQueries']);
        $this->assertStringContainsString('immeuble non immatricule deux acquereurs priorite', $queries);
        $this->assertStringContainsString('action en revendication propriete immobiliere', $queries);
        $this->assertStringNotContainsString('code des obligations et des contrats vente', $queries);
        $this->assertStringNotContainsString('immatriculation fonciere opposition', $queries);
        $this->assertStringNotContainsString('bornage', $queries);
    }

    public function test_domain_first_fallback_builds_search_plan_for_mixed_tax_question(): void
    {
        $plan = (new AiReasoningService())->createSearchPlan(
            'شركة لم تصرح بالضريبة داخل الأجل. Quels textes chercher pour contester la sanction fiscale ?'
        );

        $this->assertNotNull($plan);
        $this->assertSame('tax', $plan['dominantDomain']);
        $this->assertContains($plan['domainConfidence'], ['moderate', 'strong']);
        $this->assertNotEmpty($plan['searchQueries']);
        $this->assertStringContainsString('fiscal', implode(' ', $plan['searchQueries']));
    }

    public function test_domain_first_fallback_builds_search_plan_for_mixed_administrative_question(): void
    {
        $plan = (new AiReasoningService())->createSearchPlan(
            'جماعة رفضت الترخيص دون تعليل. Quel recours contre la décision administrative ?'
        );

        $this->assertNotNull($plan);
        $this->assertSame('administrative_urbanism', $plan['dominantDomain']);
        $this->assertNotEmpty($plan['searchQueries']);
    }

    public function test_public_debt_collection_plan_is_not_overwritten_by_procedure_terms(): void
    {
        $plan = (new AiReasoningService())->createSearchPlan(
            'كيف يتم ترتيب إجراءات التحصيل الجبري للديون العمومية؟'
        );

        $this->assertNotNull($plan);
        $this->assertSame('tax', $plan['dominantDomain']);
        $this->assertContains('Code de recouvrement des creances publiques', $plan['allowedDocumentTitles']);
        $queries = implode(' ', $plan['searchQueries']);
        $this->assertStringContainsString('code de recouvrement des creances publiques', $queries);
        $this->assertStringNotContainsString('procedure civile code de procedure civile', $queries);
        $this->assertStringNotContainsString('autorisation permis license', $queries);
    }

    public function test_domain_route_guardrails_override_procedural_and_proof_noise(): void
    {
        $service = new AiReasoningService();
        $cases = [
            [
                'Un proprietaire veut expulser un occupant sans contrat clair. Quelle regle marocaine concerne la preuve du droit de propriete ?',
                'real_estate_rent',
            ],
            [
                'Un employe detourne l argent de son employeur. Faut-il chercher abus de confiance ou vol dans le Code penal ?',
                'criminal',
            ],
            [
                'Un gerant de SARL vend un actif important sans accord des associes. Quelle regle concerne les pouvoirs du gerant ?',
                'commercial_company',
            ],
            [
                'Une banque refuse d ouvrir un compte sans expliquer sa decision. Quelles obligations bancaires chercher ?',
                'banking_finance',
            ],
            [
                'عامل تعرض لحادث أثناء العمل. ما النص الأقرب حول حادث الشغل؟',
                'labor',
            ],
            [
                'شركة لم تصرح بالضريبة داخل الأجل. ما النص الأقرب حول الجزاءات الضريبية؟',
                'tax',
            ],
            [
                'في مدونة الأسرة، الحاضنة تريد الانتقال بمدينة أخرى مع الطفل. ما القاعدة حول مصلحة المحضون؟',
                'family_marriage_divorce',
            ],
        ];

        foreach ($cases as [$question, $expectedDomain]) {
            $plan = $service->createSearchPlan($question);

            $this->assertNotNull($plan, $question);
            $this->assertSame($expectedDomain, $plan['dominantDomain'], $question);
            $this->assertNotEmpty($plan['searchQueries'], $question);
        }
    }

    public function test_coownership_syndic_route_rejects_company_governance_noise(): void
    {
        $plan = (new AiReasoningService())->createSearchPlan(
            'Des coproprietaires contestent le syndic, quelle majorite est exigee pour sa nomination ?'
        );

        $this->assertNotNull($plan);
        $this->assertSame('real_estate_rent', $plan['dominantDomain']);
        $queries = implode(' ', $plan['searchQueries']);
        $this->assertStringContainsString('copropriete', $queries);
        $this->assertStringNotContainsString('sarl sa actionnaire', $queries);
        $this->assertStringNotContainsString('registre de commerce', $queries);
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

    public function test_citation_verifier_adds_warning_for_uncited_risky_legal_claims(): void
    {
        $answer = $this->verifyCitationSupport(
            'Article 35 requires a valid reason for dismissal [1]. The employee must file a lawsuit within 15 days.',
            [$this->citation('Article 35', 'Valid reason for dismissal linked to aptitude, conduct, or company needs.')],
            'en'
        );

        $this->assertStringContainsString('Citation verification', $answer);
        $this->assertStringContainsString('deadline', $answer);
        $this->assertStringNotContainsString('file a lawsuit within 15 days.', $answer);
    }

    public function test_citation_verifier_keeps_supported_answer_clean(): void
    {
        $answer = $this->verifyCitationSupport(
            'Article 35 requires a valid reason for dismissal linked to conduct or company needs [1].',
            [$this->citation('Article 35', 'Valid reason for dismissal linked to aptitude, conduct, or company needs.')],
            'en'
        );

        $this->assertStringNotContainsString('Citation verification', $answer);
    }

    public function test_citation_verifier_warns_when_authority_source_is_legacy(): void
    {
        $citation = array_merge($this->citation('Article 35', 'Valid reason for dismissal linked to aptitude, conduct, or company needs.'), [
            'sourceAuthorityLevel' => 'legacy',
            'sourceAuthoritySignals' => ['legacy_source'],
        ]);
        $answer = $this->verifyCitationSupport(
            'Article 35 requires a valid reason for dismissal linked to conduct or company needs [1].',
            [$citation],
            'en'
        );

        $this->assertStringContainsString('weak, old, legacy', $answer);
    }

    public function test_answer_support_audit_exposes_structured_unsupported_claims(): void
    {
        $audit = (new AiReasoningService())->verifyAnswerSupport(
            'Article 35 requires a valid reason for dismissal [1]. The employee must file a lawsuit within 15 days.',
            [$this->citation('Article 35', 'Valid reason for dismissal linked to aptitude, conduct, or company needs.')],
            'en'
        );

        $this->assertSame('insufficient_sources', $audit['status']);
        $this->assertContains('unsupported_claim', $audit['warnings']);
        $this->assertSame(2, $audit['citationCoverage']['riskyClaimCount']);
        $this->assertNotEmpty($audit['unsupportedClaims']);
        $this->assertSame('Article 35', $audit['citationAudits'][0]['articleNumber']);
    }

    public function test_answer_support_audit_marks_supported_claims_as_strong(): void
    {
        $audit = (new AiReasoningService())->verifyAnswerSupport(
            'Article 35 requires a valid reason for dismissal linked to conduct or company needs [1].',
            [$this->citation('Article 35', 'Valid reason for dismissal linked to aptitude, conduct, or company needs.')],
            'en'
        );

        $this->assertSame('strong_sources', $audit['status']);
        $this->assertSame([], $audit['warnings']);
        $this->assertSame(1, $audit['citationCoverage']['supportedRiskyClaimCount']);
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
            'supportLevel' => 'strong',
            'supportSignals' => ['dismissal', 'valid', 'reason'],
            'sourceAuthorityLevel' => 'official_current',
            'sourceAuthoritySignals' => ['versioned_corpus', 'active_version', 'official_source_type'],
        ];
    }

    private function verifyCitationSupport(string $answer, array $citations, string $language): string
    {
        $method = new \ReflectionMethod(AiReasoningService::class, 'verifyCitationSupport');
        $method->setAccessible(true);

        return $method->invoke(new AiReasoningService(), $answer, $citations, $language);
    }
}
