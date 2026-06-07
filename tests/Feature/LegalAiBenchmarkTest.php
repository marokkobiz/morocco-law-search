<?php

namespace Tests\Feature;

use App\Models\LegalArticle;
use App\Models\LegalChunk;
use App\Models\LegalDocument;
use App\Models\LegalDocumentVersion;
use App\Models\LegalSource;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class LegalAiBenchmarkTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        $this->actingAs(User::factory()->create([
            'access_status' => 'active',
        ]));
        $this->seedBenchmarkCorpus();
    }

    public function test_benchmark_cases_are_well_formed(): void
    {
        $cases = config('legal_ai_benchmarks.cases');

        $this->assertIsArray($cases);
        $this->assertNotEmpty($cases);

        foreach ($cases as $case) {
            $this->assertNotEmpty($case['id'] ?? null);
            $this->assertContains($case['language'] ?? null, ['ar', 'en', 'fr']);
            $this->assertNotEmpty($case['message'] ?? null);
            $this->assertNotEmpty($case['expectedIntent'] ?? null);
            $this->assertNotEmpty($case['expectedDocuments'] ?? []);
            $this->assertNotEmpty(($case['expectedArticles'] ?? []) ?: ($case['expectedArticlesAny'] ?? []));
            $this->assertNotEmpty($case['expectedDomains'] ?? []);
        }
    }

    public function test_chat_retrieval_matches_legal_ai_benchmark_expectations(): void
    {
        foreach (config('legal_ai_benchmarks.cases') as $case) {
            $response = $this->postJson('/api/laws/chat?debug=1', [
                'message' => $case['message'],
            ])->assertOk();

            $citations = collect($response->json('citations'));

            $this->assertSame($case['expectedIntent'], $response->json('intent'), "Benchmark {$case['id']} intent mismatch.");
            $this->assertNotEmpty($response->json('diagnostics.queries'), "Benchmark {$case['id']} should expose debug queries.");
            $this->assertGreaterThan(0, $response->json('diagnostics.rawResultCount'), "Benchmark {$case['id']} should retrieve raw results.");

            foreach ($case['expectedDocuments'] as $documentTitle) {
                $this->assertTrue($citations->contains('documentTitle', $documentTitle), "Benchmark {$case['id']} missing document {$documentTitle}.");
            }

            $expectedArticles = ($case['expectedArticles'] ?? []) ?: ($case['expectedArticlesAny'] ?? []);
            $hasExpectedArticle = collect($expectedArticles)
                ->contains(fn (string $articleNumber) => $citations->contains('articleNumber', $articleNumber));

            $this->assertTrue($hasExpectedArticle, "Benchmark {$case['id']} missing one of the expected articles: ".implode(', ', $expectedArticles).'.');

            if (isset($case['expectedArticles'])) {
                foreach ($case['expectedArticles'] as $articleNumber) {
                    $this->assertTrue($citations->contains('articleNumber', $articleNumber), "Benchmark {$case['id']} missing article {$articleNumber}.");
                }
            }

            foreach ($case['expectedDomains'] as $domain) {
                $this->assertTrue($citations->contains('domain', $domain), "Benchmark {$case['id']} missing domain {$domain}.");
            }
        }
    }

    private function seedBenchmarkCorpus(): void
    {
        $labor = $this->createBenchmarkDocument('Code du travail', 'Loi 65-99', 'labor');
        $this->createBenchmarkArticle($labor, 'Article 35', 'Licenciement pour motif valable', 'Le licenciement du salarie doit etre fonde sur un motif valable.', 'licenciement salarie employeur motif valable decision licenciement.', 'dismissal');
        $this->createBenchmarkArticle($labor, 'Article 62', 'Procedure disciplinaire', 'Le salarie doit etre entendu et peut se defendre avant une sanction disciplinaire.', 'procedure disciplinaire salarie audition defense licenciement.', 'dismissal');

        $criminal = $this->createBenchmarkDocument('Code penal', 'Dahir 1-59-413', 'criminal');
        $this->createBenchmarkArticle($criminal, 'Article 505', 'Vol', 'La soustraction frauduleuse de la chose d autrui constitue un vol.', 'vol code penal soustraction frauduleuse chose autrui plainte.', 'theft');
        $this->createBenchmarkArticle($criminal, 'Article 540', 'Escroquerie', 'L escroquerie est constituee par des manoeuvres frauduleuses ou de fausses affirmations pour obtenir des fonds.', 'escroquerie manoeuvres frauduleuses fausses affirmations obtenir argent profit code penal.', 'fraud');

        $civil = $this->createBenchmarkDocument('Code des Obligations et des Contrats', 'Dahir du 12 aout 1913', 'civil_obligations_contracts');
        $this->createBenchmarkArticle($civil, 'Article 443', 'Preuve par ecrit', 'La preuve des obligations depassant le seuil legal doit en principe etre etablie par ecrit.', 'preuve ecrit dette obligation pret somme argent transfert bancaire messages whatsapp.', 'proof');
        $this->createBenchmarkArticle($civil, 'Article 448', 'Commencement de preuve', 'Un commencement de preuve par ecrit peut etre complete par d autres elements probatoires.', 'commencement preuve ecrit dette obligation messages electroniques transfert bancaire.', 'proof');
        $this->createBenchmarkArticle($civil, 'Article 488', 'Perfection de la vente', 'La vente est parfaite entre les parties des qu il y a consentement des contractants, chose et prix.', 'vente parfaite consentement contractants chose prix acheteur vendeur.', 'sale');
        $this->createBenchmarkArticle($civil, 'Article 491', 'Propriete de la chose vendue', 'L acheteur acquiert de plein droit la propriete de la chose vendue des que le contrat est parfait.', 'acheteur acquiert propriete chose vendue contrat parfait.', 'sale');
        $this->createBenchmarkArticle($civil, 'Article 499', 'Delivrance de la chose vendue', 'La delivrance a lieu lorsque le vendeur met l acquereur en mesure de prendre possession de la chose vendue sans obstacle.', 'delivrance vendeur acquereur prendre possession chose vendue sans obstacle.', 'sale');

        $family = $this->createBenchmarkDocument('Code de la famille', 'Loi 70-03', 'family_marriage_divorce');
        $this->createBenchmarkArticle($family, 'Article 84', 'Pension alimentaire des enfants', 'Le juge tient compte des besoins des enfants et de la situation des parents pour fixer la pension alimentaire.', 'divorce pension alimentaire enfants juge besoins situation parents.', 'divorce');
        $this->createBenchmarkArticle($family, 'Article 85', 'Fixation de la pension alimentaire', 'La pension alimentaire des enfants est fixee selon les revenus, besoins et situation familiale.', 'pension alimentaire enfants divorce revenus parents besoins tribunal nafaka.', 'divorce');
        $this->createBenchmarkArticle($family, 'Article 175', 'Remariage de la mere gardienne', 'Le mariage de la mere gardienne ne met pas toujours fin a la garde lorsque l interet de l enfant ou les exceptions legales le justifient.', 'garde enfant hadana mere gardienne remariage apres divorce exceptions interet enfant.', 'custody');
        $this->createBenchmarkArticle($family, 'Article 190', 'Evaluation de la nafaka', 'Le tribunal apprecie la pension alimentaire selon les ressources du debiteur et les besoins du beneficiaire.', 'nafaka pension alimentaire ressources debiteur besoins beneficiaire enfants divorce.', 'divorce');

        $rent = $this->createBenchmarkDocument('Recouvrement des loyers', 'Loi 64-99', 'real_estate_rent');
        $this->createBenchmarkArticle($rent, 'Article 1', 'Loyer impaye', 'Le bailleur peut demander le recouvrement des loyers impayes selon les conditions prevues par la loi.', 'bailleur locataire loyer impaye recouvrement des loyers.', 'rent');

        $registration = $this->createBenchmarkDocument('Immatriculation fonciere', 'Dahir 12 aout 1913', 'real_estate_rent');
        $this->createBenchmarkArticle($registration, 'Article 24', 'Opposition a l immatriculation', 'Les oppositions au bornage et a l immatriculation fonciere sont presentees dans les delais prevus apres publication.', 'immatriculation fonciere opposition bornage delai publication annonce terrain immeuble.', 'registration');

        $coproperty = $this->createBenchmarkDocument('Statut de la copropriété des immeubles bâtis', 'Loi 18-00', 'real_estate_rent');
        $this->createBenchmarkArticle($coproperty, 'Article 19', 'Designation du syndic', 'Le syndic de copropriete est designe par l assemblee generale selon la majorite prevue.', 'copropriete syndic designation assemblee generale majorite coproprietaires immeuble.', 'coproperty');

        $commerce = $this->createBenchmarkDocument('Code de commerce', 'Loi 15-95', 'commercial_company');
        $this->createBenchmarkArticle($commerce, 'Article 37', 'Registre de commerce', 'Les commercants et societes commerciales sont tenus de demander leur immatriculation au registre de commerce.', 'societe commerciale commercant immatriculation registre de commerce.', 'registration');

        $sarl = $this->createBenchmarkDocument('Societe en nom collectif et SARL', 'Loi 5-96', 'commercial_company');
        $this->createBenchmarkArticle($sarl, 'Article 2', 'Commercialite des societes', 'La societe a responsabilite limitee est commerciale par sa forme et acquiert la personnalite morale apres immatriculation.', 'sarl societe responsabilite limitee commerciale forme personnalite morale immatriculation.', 'company');

        $procedure = $this->createBenchmarkDocument('Code de procedure civile', 'Dahir 1-74-447', 'civil_procedure');
        $this->createBenchmarkArticle($procedure, 'Article 27', 'Competence territoriale', 'La competence territoriale appartient en principe au tribunal du domicile reel ou elu du defendeur.', 'competence territoriale tribunal domicile defendeur procedure civile.', 'jurisdiction');
        $this->createBenchmarkArticle($procedure, 'Article 134', 'Delai d appel', 'L appel des jugements des tribunaux de premiere instance est forme dans le delai prevu par la procedure civile.', 'appel jugement tribunal premiere instance delai trente jours procedure civile.', 'appeal');

        $banking = $this->createBenchmarkDocument('Etablissements de credit et organismes assimiles', 'Loi 103-12', 'banking_finance');
        $this->createBenchmarkArticle($banking, 'Article 34', 'Agrement bancaire', 'Toute personne morale doit obtenir un agrement prealable avant d exercer comme etablissement de credit.', 'etablissement de credit agrement prealable bank al maghrib activite bancaire personne morale.', 'approval');
        $this->createBenchmarkArticle($banking, 'Article 180', 'Secret professionnel bancaire', 'Les administrateurs, dirigeants et employes des etablissements de credit sont tenus au secret professionnel.', 'banque etablissement credit secret professionnel client informations employes.', 'secrecy');

        $collection = $this->createBenchmarkDocument('Code de recouvrement des creances publiques', 'Loi 15-97', 'tax');
        $this->createBenchmarkArticle($collection, 'Article 39', 'Recouvrement force', 'Le recouvrement force des creances publiques suit les phases de commandement, saisie puis vente.', 'recouvrement force creances publiques commandement saisie vente impots taxes.', 'collection');

        $vat = $this->createBenchmarkDocument('Application de la taxe sur la valeur ajoutee', 'Decret TVA', 'tax');
        $this->createBenchmarkArticle($vat, 'Article 25', 'Remboursement de TVA', 'La demande de remboursement de la taxe sur la valeur ajoutee est deposee selon les formalites prevues.', 'remboursement taxe valeur ajoutee tva demande formalites depot.', 'vat');

        $admin = $this->createBenchmarkDocument('Simplification des procedures et des formalites administratives', 'Loi 55-19', 'administrative_urbanism');
        $this->createBenchmarkArticle($admin, 'Article 10', 'Recepisse de depot', 'L administration remet immediatement un recepisse lors du depot d une demande d acte administratif.', 'administration demande acte administratif depot recepisse immediat procedure formalite.', 'procedure');
        $this->createBenchmarkArticle($admin, 'Article 16', 'Delai maximal de traitement', 'Le delai maximal de traitement des demandes d actes administratifs est fixe par la loi sauf exception.', 'delai maximal traitement demande acte administratif administration soixante jours.', 'delay');
    }

    private function createBenchmarkDocument(string $title, string $reference, string $domain): array
    {
        $source = LegalSource::create([
            'name' => 'Benchmark source '.$title,
            'source_type' => 'code',
            'source_url' => 'https://example.test/'.md5($title),
            'official_domain' => 'example.test',
            'language' => 'fr',
            'checksum' => hash('sha256', $title.$reference),
            'status' => 'active',
        ]);
        $document = LegalDocument::create([
            'legal_source_id' => $source->id,
            'document_title' => $title,
            'document_type' => 'code',
            'law_reference' => $reference,
            'publication_date' => '2024-01-01',
            'language' => 'fr',
            'domain' => $domain,
            'tags' => [$domain],
            'source_url' => $source->source_url,
            'checksum' => hash('sha256', $title.$reference.'document'),
            'status' => 'active',
        ]);
        $version = LegalDocumentVersion::create([
            'legal_document_id' => $document->id,
            'version_number' => 1,
            'source_url' => $source->source_url,
            'checksum' => hash('sha256', $title.$reference.'version'),
            'status' => 'active',
            'publication_date' => '2024-01-01',
            'imported_at' => now(),
        ]);
        $document->update(['current_version_id' => $version->id]);

        return compact('document', 'version');
    }

    private function createBenchmarkArticle(array $documentPayload, string $number, string $title, string $articleContent, string $chunkContent, string $subdomain): void
    {
        /** @var LegalDocument $document */
        $document = $documentPayload['document'];
        /** @var LegalDocumentVersion $version */
        $version = $documentPayload['version'];
        $article = LegalArticle::create([
            'legal_document_id' => $document->id,
            'legal_document_version_id' => $version->id,
            'article_number' => $number,
            'article_title' => $title,
            'content' => $articleContent,
            'language' => 'fr',
            'domain' => $document->domain,
            'subdomain' => $subdomain,
            'tags' => [$document->domain, $subdomain],
            'checksum' => hash('sha256', $document->document_title.$number.$articleContent),
            'sort_order' => (int) preg_replace('/\D+/', '', $number),
            'status' => 'active',
        ]);
        LegalChunk::create([
            'legal_article_id' => $article->id,
            'legal_document_version_id' => $version->id,
            'chunk_index' => 0,
            'content' => $chunkContent,
            'token_count' => str_word_count($chunkContent),
            'domain' => $document->domain,
            'subdomain' => $subdomain,
            'tags' => [$document->domain, $subdomain],
            'checksum' => hash('sha256', $chunkContent),
        ]);
    }
}
