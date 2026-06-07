<?php

namespace Tests\Unit;

use App\Services\LegalDomainClassifier;
use PHPUnit\Framework\TestCase;

class LegalDomainClassifierTest extends TestCase
{
    public function test_mixed_arabic_french_questions_route_to_their_dominant_legal_domain(): void
    {
        $classifier = new LegalDomainClassifier();
        $cases = [
            ['شركة لم تصرح بالضريبة داخل الأجل. Comment contester la sanction fiscale ?', 'tax'],
            ['جماعة رفضت الترخيص دون تعليل. Quel recours contre la décision administrative ?', 'administrative_urbanism'],
            ['بنك كشف معطيات الزبون. Quels textes régissent le secret bancaire ?', 'banking_finance'],
            ['أجير تعرض لحادث أثناء العمل. Comment vérifier les droits liés à l accident du travail ?', 'labor'],
            ['مالك يريد إفراغ محتل. Quelle règle concerne le droit de propriété foncière ?', 'real_estate_rent'],
            ['دائن يريد إجراء حجز لدى الغير. Comment vérifier la saisie-arrêt en procédure civile ?', 'civil_procedure'],
        ];

        foreach ($cases as [$question, $expectedDomain]) {
            $this->assertSame($expectedDomain, $classifier->classifyQuery($question)['domain'], $question);
        }
    }

    public function test_specific_domain_signals_beat_neighboring_fact_domains(): void
    {
        $classifier = new LegalDomainClassifier();

        $this->assertSame('tax', $classifier->classifyQuery('Une société conteste une sanction fiscale après sa déclaration.')['domain']);
        $this->assertSame('criminal', $classifier->classifyQuery('Un employé détourne des fonds: vol ou abus de confiance ?')['domain']);
        $this->assertSame('banking_finance', $classifier->classifyQuery('Une banque refuse un compte: quelles obligations bancaires ?')['domain']);
        $this->assertSame('administrative_urbanism', $classifier->classifyQuery('Une commune refuse une autorisation sans motivation administrative.')['domain']);
    }

    public function test_routing_guardrails_hold_top_wrong_domain_confusions(): void
    {
        $classifier = new LegalDomainClassifier();
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
                'عامل تعرض لحادث أثناء العمل. ما النص الأقرب حول حادث الشغل؟',
                'labor',
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
                'شركة لم تصرح بالضريبة داخل الأجل. ما النص الأقرب حول الجزاءات الضريبية؟',
                'tax',
            ],
            [
                'في مدونة الأسرة، الحاضنة تريد الانتقال بمدينة أخرى مع الطفل. ما القاعدة حول مصلحة المحضون؟',
                'family_marriage_divorce',
            ],
        ];

        foreach ($cases as [$question, $expectedDomain]) {
            $this->assertSame($expectedDomain, $classifier->classifyQuery($question)['domain'], $question);
        }
    }
}
