<?php

namespace Tests\Unit;

use App\Services\ChatLawService;
use Tests\TestCase;

class ChatRelevanceScoringTest extends TestCase
{
    public function test_arabic_retrieval_signals_do_not_force_article_lookup_intent(): void
    {
        $service = (new \ReflectionClass(ChatLawService::class))->newInstanceWithoutConstructor();

        $this->assertSame(
            ChatLawService::INTENT_CASE_ANALYSIS,
            $service->classifyIntent('هل يمكن لشركة أن تمارس نشاط مؤسسة ائتمان في المغرب دون الحصول على اعتماد مسبق؟')
        );
    }

    public function test_arabic_unregistered_property_double_sale_is_legal_case_analysis(): void
    {
        $service = (new \ReflectionClass(ChatLawService::class))->newInstanceWithoutConstructor();

        $this->assertSame(
            ChatLawService::INTENT_CASE_ANALYSIS,
            $service->classifyIntent('باع شخص عقارًا غير محفظ لشخصين مختلفين، من له الأولوية؟')
        );
    }

    public function test_legal_research_frames_are_searchable_across_french_and_arabic(): void
    {
        $service = (new \ReflectionClass(ChatLawService::class))->newInstanceWithoutConstructor();

        foreach ([
            'Quels textes chercher pour contester un avis d imposition ?',
            'Comment vérifier une saisie-arrêt en procédure civile ?',
            'ما النص الأقرب للطعن في قرار إداري؟',
            'ما القاعدة حول سرية الحساب البنكي؟',
        ] as $question) {
            $this->assertSame(ChatLawService::INTENT_CASE_ANALYSIS, $service->classifyIntent($question), $question);
        }
    }

    public function test_strong_domain_route_penalizes_unrelated_domain_despite_overlap(): void
    {
        $plan = [
            'query' => 'contestation fiscale societe declaration sanction',
            'aiPlan' => [
                'dominantDomain' => 'tax',
                'domainConfidence' => 'strong',
                'relevanceTerms' => ['contestation fiscale', 'declaration fiscale', 'sanction fiscale'],
            ],
            'topic' => null,
        ];

        $tax = $this->score($plan['query'], $plan, $this->law([
            'document_title' => 'Code general des impots',
            'content' => 'Contestation fiscale et sanctions relatives a la declaration fiscale.',
            'domain' => 'tax',
            'category' => 'tax',
        ]));
        $commercial = $this->score($plan['query'], $plan, $this->law([
            'document_title' => 'Code de commerce',
            'content' => 'La societe commerciale effectue une declaration.',
            'domain' => 'commercial_company',
            'category' => 'commercial_company',
        ]));

        $this->assertGreaterThan($commercial['score'], $tax['score']);
        $this->assertGreaterThan(10, $tax['score'] - $commercial['score']);
    }

    public function test_document_title_override_scores_recouvrement_des_loyers_as_real_estate(): void
    {
        $plan = [
            'query' => 'recouvrement des loyers bail habitation locataire',
            'aiPlan' => [
                'dominantDomain' => 'real_estate_rent',
                'domainConfidence' => 'strong',
                'relevanceTerms' => ['loyer', 'bail', 'locataire'],
            ],
            'topic' => null,
        ];

        $score = $this->score($plan['query'], $plan, $this->law([
            'document_title' => 'Recouvrement des loyers',
            'article_number' => 'Article 1',
            'content' => 'Recouvrement des loyers dus par le locataire.',
            'domain' => 'commercial_company',
            'category' => 'commercial_company',
        ]));

        $this->assertGreaterThan(8, $score['score']);
    }

    public function test_specific_tax_contestation_penalizes_generic_tva_source(): void
    {
        $plan = [
            'query' => 'tva',
            'aiPlan' => [
                'dominantDomain' => 'tax',
                'domainConfidence' => 'strong',
                'relevanceTerms' => ['avis d imposition', 'contestation fiscale'],
            ],
            'topic' => null,
        ];

        $generic = $this->score('un contribuable reçoit un avis d imposition contesté', $plan, $this->law([
            'document_title' => 'Application de la taxe sur la valeur ajoutee',
            'article_number' => 'Article 16 ter',
            'content' => 'La taxe sur la valeur ajoutee est appliquee aux operations imposables.',
            'domain' => 'tax',
            'category' => 'tax',
        ]));
        $specific = $this->score('un contribuable reçoit un avis d imposition contesté', $plan, $this->law([
            'document_title' => 'Application de la taxe sur la valeur ajoutee',
            'article_number' => 'Article 220',
            'content' => 'Le contribuable peut contester un avis d imposition dans le cadre du recours fiscal.',
            'domain' => 'tax',
            'category' => 'tax',
        ]));

        $this->assertGreaterThan($generic['score'], $specific['score']);
    }

    public function test_specific_criminal_issue_penalizes_wrong_offense_article(): void
    {
        $plan = [
            'query' => 'vol code penal',
            'aiPlan' => [
                'dominantDomain' => 'criminal',
                'domainConfidence' => 'strong',
                'relevanceTerms' => ['abus de confiance', 'detournement'],
            ],
            'topic' => null,
        ];

        $wrong = $this->score('un employe detourne l argent de son employeur abus de confiance', $plan, $this->law([
            'document_title' => 'Code penal',
            'article_number' => 'Article 505',
            'content' => 'Quiconque soustrait frauduleusement une chose appartenant a autrui est coupable de vol.',
            'domain' => 'criminal',
            'category' => 'criminal',
        ]));
        $specific = $this->score('un employe detourne l argent de son employeur abus de confiance', $plan, $this->law([
            'document_title' => 'Code penal',
            'article_number' => 'Article 547',
            'content' => 'L abus de confiance et la dissipation de fonds remis a titre determine sont punis.',
            'domain' => 'criminal',
            'category' => 'criminal',
        ]));

        $this->assertGreaterThan($wrong['score'], $specific['score']);
    }

    public function test_article_anchor_and_source_gate_make_specific_article_win(): void
    {
        $plan = [
            'query' => 'article 34 etablissements de credit organismes assimiles agrement avant exercer activite',
            'aiPlan' => [
                'trustedArticleAnchors' => [
                    'article 34 etablissements de credit organismes assimiles agrement avant exercer activite',
                ],
                'allowedDocumentTitles' => ['Etablissements de credit et organismes assimiles'],
                'allowedCategories' => ['banking_finance'],
                'relevanceTerms' => ['agrement', 'etablissement de credit', 'exercer activite'],
            ],
            'topic' => null,
        ];

        $target = $this->score($plan['query'], $plan, $this->law([
            'document_title' => 'Etablissements de credit et organismes assimiles',
            'article_number' => 'Article 34',
            'content' => 'Agrement requis avant exercer une activite d etablissement de credit.',
            'domain' => 'banking_finance',
            'source_authority_score' => 550,
        ]));
        $neighbor = $this->score($plan['query'], $plan, $this->law([
            'document_title' => 'Etablissements de credit et organismes assimiles',
            'article_number' => 'Article 180',
            'content' => 'Secret professionnel des dirigeants et employes des etablissements de credit.',
            'domain' => 'banking_finance',
            'source_authority_score' => 550,
        ]));

        $this->assertFalse($target['rejectedByScope']);
        $this->assertFalse($target['rejectedBySource']);
        $this->assertGreaterThan($neighbor['score'], $target['score']);
        $this->assertGreaterThan(25, $target['score']);
    }

    public function test_source_gate_rejects_wrong_document_even_when_terms_overlap(): void
    {
        $plan = [
            'query' => 'article 499 code des obligations et des contrats delivrance possession',
            'aiPlan' => [
                'allowedDocumentTitles' => ['Code des Obligations et des Contrats'],
                'allowedCategories' => ['civil'],
                'relevanceTerms' => ['delivrance', 'possession', 'vendeur', 'acheteur'],
            ],
            'topic' => null,
        ];

        $score = $this->score($plan['query'], $plan, $this->law([
            'document_title' => 'Immatriculation et vente forcee des aeronefs',
            'article_number' => 'Article 499',
            'content' => 'Vente et immatriculation avec possession.',
            'domain' => 'civil_obligations_contracts',
            'source_authority_score' => 550,
        ]));

        $this->assertTrue($score['rejectedBySource']);
        $this->assertSame(0, $score['score']);
    }

    public function test_domain_priority_demotes_cpc_article_134_for_banking_candidates(): void
    {
        $plan = [
            'query' => 'etablissements de credit agrement bancaire',
            'aiPlan' => [
                'dominantDomain' => 'banking_finance',
                'domainConfidence' => 'strong',
            ],
        ];
        $ranked = $this->applyPriority('societe etablissement de credit sans agrement', $plan, [
            $this->scoredLaw(98.65, [
                'document_title' => 'Code de procedure civile',
                'article_number' => 'Article 134',
                'domain' => 'civil_procedure',
                'category' => 'civil_procedure',
            ]),
            $this->scoredLaw(96.8, [
                'document_title' => 'Etablissements de credit et organismes assimiles',
                'article_number' => 'Article 34',
                'domain' => 'banking_finance',
                'category' => 'banking_finance',
            ]),
        ]);

        $this->assertSame('banking_finance', $ranked[0]['domain']);
        $this->assertGreaterThan($ranked[1]['chatRelevanceScore'], $ranked[0]['chatRelevanceScore']);
    }

    public function test_domain_priority_demotes_cpc_article_134_for_commercial_candidates(): void
    {
        $plan = [
            'query' => 'sarl personnalite morale immatriculation',
            'aiPlan' => [
                'dominantDomain' => 'commercial_company',
                'domainConfidence' => 'strong',
            ],
        ];
        $ranked = $this->applyPriority('SARL personnalite morale', $plan, [
            $this->scoredLaw(102.2, [
                'document_title' => 'Code de procedure civile',
                'article_number' => 'Article 134',
                'domain' => 'civil_procedure',
                'category' => 'civil_procedure',
            ]),
            $this->scoredLaw(99.6, [
                'document_title' => 'Societe en nom collectif et SARL',
                'article_number' => 'Article 2',
                'domain' => 'commercial_company',
                'category' => 'commercial_company',
            ]),
        ]);

        $this->assertSame('commercial_company', $ranked[0]['domain']);
        $this->assertGreaterThan($ranked[1]['chatRelevanceScore'], $ranked[0]['chatRelevanceScore']);
    }

    public function test_domain_priority_demotes_doc_proof_articles_for_property_candidates(): void
    {
        $plan = [
            'query' => 'preuve du droit de propriete titre foncier occupant sans contrat',
            'aiPlan' => [
                'dominantDomain' => 'real_estate_rent',
                'domainConfidence' => 'strong',
            ],
        ];
        $ranked = $this->applyPriority('proprietaire expulser occupant preuve droit propriete', $plan, [
            $this->scoredLaw(70.95, [
                'document_title' => 'Code des Obligations et des Contrats',
                'article_number' => 'Article 443',
                'domain' => 'civil_obligations_contracts',
                'category' => 'civil_obligations_contracts',
            ]),
            $this->scoredLaw(64.5, [
                'document_title' => 'Immatriculation fonciere',
                'article_number' => 'Article 52',
                'domain' => 'real_estate_rent',
                'category' => 'real_estate_rent',
            ]),
        ]);

        $this->assertSame('real_estate_rent', $ranked[0]['domain']);
        $this->assertGreaterThan($ranked[1]['chatRelevanceScore'], $ranked[0]['chatRelevanceScore']);
    }

    public function test_domain_priority_does_not_apply_without_preferred_domain_candidate(): void
    {
        $plan = [
            'query' => 'sarl personnalite morale',
            'aiPlan' => [
                'dominantDomain' => 'commercial_company',
                'domainConfidence' => 'strong',
            ],
        ];
        $ranked = $this->applyPriority('SARL personnalite morale', $plan, [
            $this->scoredLaw(102.2, [
                'document_title' => 'Code de procedure civile',
                'article_number' => 'Article 134',
                'domain' => 'civil_procedure',
                'category' => 'civil_procedure',
            ]),
        ]);

        $this->assertSame(102.2, $ranked[0]['chatRelevanceScore']);
    }

    public function test_lease_eviction_priority_demotes_doc_sale_articles_for_french_query(): void
    {
        $plan = [
            'query' => 'expulsion locataire bail habitation',
            'aiPlan' => [
                'dominantDomain' => 'real_estate_rent',
                'domainConfidence' => 'strong',
            ],
        ];
        $ranked = $this->applyPriority('Le bailleur veut expulser un locataire apres des loyers impayes.', $plan, [
            $this->scoredLaw(112.0, [
                'document_title' => 'Code des Obligations et des Contrats',
                'article_number' => 'Article 488',
                'domain' => 'civil_obligations_contracts',
                'category' => 'civil_obligations_contracts',
                'content' => 'La vente est parfaite entre les parties.',
            ]),
            $this->scoredLaw(96.0, [
                'document_title' => 'Recouvrement des loyers',
                'article_number' => 'Article 1',
                'domain' => 'real_estate_rent',
                'category' => 'real_estate_rent',
                'content' => 'Le bailleur demande le recouvrement des loyers dus par le locataire.',
            ]),
        ]);

        $this->assertSame('Recouvrement des loyers', $ranked[0]['document_title']);
        $this->assertGreaterThan($ranked[1]['chatRelevanceScore'], $ranked[0]['chatRelevanceScore']);
    }

    public function test_lease_eviction_priority_demotes_doc_sale_articles_for_arabic_query(): void
    {
        $plan = [
            'query' => 'عقد الكراء إخلاء المستأجر',
            'aiPlan' => [
                'dominantDomain' => 'real_estate_rent',
                'domainConfidence' => 'strong',
            ],
        ];
        $ranked = $this->applyPriority('يريد المكري إخلاء المستأجر وتسليم العقار بسبب عدم أداء الكراء.', $plan, [
            $this->scoredLaw(115.0, [
                'document_title' => 'Code des Obligations et des Contrats',
                'article_number' => 'Article 500',
                'domain' => 'civil_obligations_contracts',
                'category' => 'civil_obligations_contracts',
                'content' => 'La delivrance des choses vendues.',
            ]),
            $this->scoredLaw(97.0, [
                'document_title' => 'Recouvrement des loyers',
                'article_number' => 'Article 1',
                'domain' => 'real_estate_rent',
                'category' => 'real_estate_rent',
                'content' => 'Le bailleur demande le recouvrement des loyers dus par le locataire.',
            ]),
        ]);

        $this->assertSame('Recouvrement des loyers', $ranked[0]['document_title']);
        $this->assertGreaterThan($ranked[1]['chatRelevanceScore'], $ranked[0]['chatRelevanceScore']);
    }

    public function test_lease_priority_does_not_penalize_sale_without_lease_candidate(): void
    {
        $plan = [
            'query' => 'delivrance chose vendue',
            'aiPlan' => [
                'dominantDomain' => 'civil_obligations_contracts',
                'domainConfidence' => 'strong',
            ],
        ];
        $ranked = $this->applyPriority('Le vendeur doit-il livrer le bien vendu a acheteur ?', $plan, [
            $this->scoredLaw(115.0, [
                'document_title' => 'Code des Obligations et des Contrats',
                'article_number' => 'Article 500',
                'domain' => 'civil_obligations_contracts',
                'category' => 'civil_obligations_contracts',
                'content' => 'La delivrance des choses vendues.',
            ]),
        ]);

        $this->assertSame(120.0, $ranked[0]['chatRelevanceScore']);
    }

    public function test_property_title_priority_demotes_doc_proof_when_title_candidate_exists(): void
    {
        $plan = [
            'query' => 'preuve droit de propriete occupant sans contrat',
            'aiPlan' => [
                'dominantDomain' => 'real_estate_rent',
                'domainConfidence' => 'strong',
            ],
        ];
        $ranked = $this->applyPriority('Un proprietaire veut expulser un occupant sans contrat et prouver son droit de propriete.', $plan, [
            $this->scoredLaw(110.0, [
                'document_title' => 'Code des Obligations et des Contrats',
                'article_number' => 'Article 443',
                'domain' => 'civil_obligations_contracts',
                'category' => 'civil_obligations_contracts',
            ]),
            $this->scoredLaw(92.0, [
                'document_title' => 'Immatriculation fonciere',
                'article_number' => 'Article 52',
                'domain' => 'real_estate_rent',
                'category' => 'real_estate_rent',
            ]),
        ]);

        $this->assertSame('Immatriculation fonciere', $ranked[0]['document_title']);
        $this->assertGreaterThan($ranked[1]['chatRelevanceScore'], $ranked[0]['chatRelevanceScore']);
    }

    public function test_property_title_priority_does_not_penalize_doc_without_title_candidate(): void
    {
        $plan = ['query' => 'preuve droit de propriete', 'aiPlan' => []];
        $ranked = $this->applyPriority('Comment prouver le droit de propriete ?', $plan, [
            $this->scoredLaw(110.0, [
                'document_title' => 'Code des Obligations et des Contrats',
                'article_number' => 'Article 443',
                'domain' => 'civil_obligations_contracts',
                'category' => 'civil_obligations_contracts',
            ]),
        ]);

        $this->assertSame(110.0, $ranked[0]['chatRelevanceScore']);
    }

    private function score(string $question, array $plan, array $law): array
    {
        $service = (new \ReflectionClass(ChatLawService::class))->newInstanceWithoutConstructor();
        $method = new \ReflectionMethod(ChatLawService::class, 'scoreRelevance');
        $method->setAccessible(true);

        return $method->invoke($service, $question, $plan, $law);
    }

    private function applyPriority(string $question, array $plan, array $laws): array
    {
        $service = (new \ReflectionClass(ChatLawService::class))->newInstanceWithoutConstructor();
        $method = new \ReflectionMethod(ChatLawService::class, 'applyDomainAwareRankingPriority');
        $method->setAccessible(true);

        return $method->invoke($service, collect($laws), $question, $plan)
            ->sortByDesc('chatRelevanceScore')
            ->values()
            ->all();
    }

    private function scoredLaw(float $score, array $overrides): array
    {
        return array_merge($this->law($overrides), [
            'chatRelevanceScore' => $score,
            'rejectedByScope' => false,
            'rejectedBySource' => false,
        ]);
    }

    private function law(array $overrides): array
    {
        return array_merge([
            'title' => 'Article',
            'article_number' => 'Article 1',
            'content' => '',
            'document_title' => 'Test document',
            'law_reference' => 'Test law',
            'category' => 'test',
            'domain' => 'test',
            'subdomain' => null,
            'tags' => [],
            'source_name' => 'Official source',
            'source_type' => 'code',
            'source_table' => 'corpus',
            'is_legacy' => false,
            'source_authority_score' => 550,
            'version_status' => 'active',
            'document_status' => 'active',
            'matchedQuery' => null,
            'matchedQueryIndex' => 0,
        ], $overrides);
    }
}
