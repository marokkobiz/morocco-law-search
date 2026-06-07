<?php

namespace App\Services;

use Illuminate\Support\Str;

class LegalDomainClassifier
{
    private const DOMAIN_PROFILES = [
        'labor' => [
            'terms' => ['travail', 'emploi', 'contrat de travail', 'salarie', 'employeur', 'licenciement', 'licenciement abusif', 'motif valable', 'preavis', 'salaire', 'indemnite', 'faute grave', 'procedure disciplinaire', 'decision licenciement', 'accident du travail', 'demission', 'employee', 'employer', 'dismissal', 'termination', 'wrongful dismissal', 'notice', 'salary', 'wage'],
            'subdomains' => [
                'dismissal' => ['licenciement', 'licenciement abusif', 'faute grave', 'motif valable', 'preavis', 'procedure disciplinaire', 'decision licenciement', 'dismissal', 'termination', 'fired', 'wrongful dismissal'],
                'wages' => ['salaire', 'paie', 'remuneration', 'wage', 'salary'],
                'discipline' => ['sanction', 'disciplinaire', 'faute', 'procedure disciplinaire'],
            ],
        ],
        'criminal' => [
            'terms' => ['penal', 'crime', 'infraction', 'vol', 'soustraction', 'homicide', 'coups', 'blessures', 'agression', 'arme', 'prison', 'menace', 'escroquerie', 'abus de confiance', 'theft', 'robbery', 'assault', 'criminal'],
            'subdomains' => [
                'theft' => ['vol', 'soustraction frauduleuse', 'theft', 'robbery', 'stolen'],
                'violence' => ['coups', 'blessures', 'agression', 'homicide', 'arme', 'violence', 'assault', 'shot'],
                'procedure' => ['plainte', 'poursuite', 'procureur', 'tribunal penal'],
            ],
        ],
        'criminal_procedure' => [
            'terms' => ['procedure penale', 'code de procedure penale', 'enquete', 'garde a vue', 'police judiciaire', 'procureur du roi', 'instruction', 'juge d instruction', 'chambre criminelle', 'appel penal', 'cassation penale', 'detention preventive', 'mandat de depot', 'criminal procedure', 'investigation', 'custody', 'public prosecutor'],
            'subdomains' => [
                'investigation' => ['enquete', 'police judiciaire', 'officier de police judiciaire', 'garde a vue', 'perquisition', 'saisie', 'investigation', 'custody'],
                'prosecution' => ['procureur', 'ministere public', 'poursuite', 'classement', 'citation directe', 'public prosecutor'],
                'trial_appeal' => ['instruction', 'juge d instruction', 'audience', 'jugement penal', 'appel penal', 'cassation penale'],
            ],
        ],
        'civil_procedure' => [
            'terms' => ['procedure civile', 'code de procedure civile', 'tribunal de premiere instance', 'cour d appel', 'competence', 'requete', 'assignation', 'notification', 'execution forcee', 'saisie', 'saisie arret', 'verification de saisie', 'ordonnance', 'refere', 'appel civil', 'cassation civile', 'civil procedure', 'civil court', 'service of process'],
            'subdomains' => [
                'jurisdiction' => ['competence', 'tribunal de premiere instance', 'cour d appel', 'ressort', 'jurisdiction'],
                'filing_service' => ['requete', 'assignation', 'convocation', 'notification', 'greffe', 'service'],
                'enforcement' => ['execution forcee', 'saisie', 'saisie arret', 'commandement', 'huissier', 'enforcement'],
            ],
        ],
        'civil_obligations_contracts' => [
            'terms' => ['obligations', 'contrats', 'contrat', 'convention', 'vente', 'acheteur', 'acquereur', 'vendeur', 'prix', 'chose vendue', 'delivrance', 'paiement', 'propriete', 'transfert de propriete', 'possession', 'obligations du vendeur', 'obligations de l acheteur', 'ayants cause', 'responsabilite civile', 'dommages interets', 'inexecution', 'resolution', 'preuve', 'preuve ecrite', 'commencement de preuve', 'aveu', 'dette', 'creance', 'pret', 'remboursement', 'virement', 'reconnaissance de dette', 'contract', 'sale', 'buyer', 'seller', 'price', 'delivery', 'ownership', 'possession', 'breach of contract', 'proof', 'evidence', 'loan', 'debt', 'repayment', 'gift', 'bank transfer'],
            'subdomains' => [
                'sale' => ['vente', 'contrat de vente', 'acheteur', 'acquereur', 'vendeur', 'prix', 'chose vendue', 'delivrance', 'paiement', 'propriete', 'transfert de propriete', 'possession', 'tradition reelle', 'obligations du vendeur', 'ayants cause', 'sale', 'buyer', 'seller', 'delivery', 'ownership'],
                'contract_performance' => ['execution', 'bonne foi', 'resolution', 'resiliation', 'obligation', 'inexecution', 'dommages interets', 'breach of contract', 'non performance'],
                'civil_liability' => ['responsabilite', 'prejudice', 'dommage', 'reparation'],
                'proof_debt' => ['preuve', 'preuve ecrite', 'commencement de preuve', 'aveu', 'dette', 'creance', 'pret', 'remboursement', 'virement', 'reconnaissance de dette', 'proof', 'evidence', 'loan', 'debt', 'repayment', 'gift', 'bank transfer', 'whatsapp'],
            ],
        ],
        'consumer_protection' => [
            'terms' => ['consommateur', 'protection du consommateur', 'securite des produits et des services', 'produit defectueux', 'garantie', 'remboursement', 'pratiques commerciales', 'information consommateur', 'consumer', 'product safety', 'warranty', 'refund'],
            'subdomains' => [
                'product_safety' => ['securite des produits', 'produit defectueux', 'product safety', 'defective product'],
                'consumer_contracts' => ['consommateur', 'garantie', 'remboursement', 'information consommateur', 'consumer', 'warranty', 'refund'],
                'commercial_practices' => ['pratiques commerciales', 'publicite', 'clauses abusives'],
            ],
        ],
        'succession_inheritance' => [
            'terms' => ['succession', 'successoral', 'successorale', 'heritage', 'heritier', 'heritiers', 'ayants droit', 'ayants cause', 'testament', 'legs', 'partage', 'liquidation de la succession', 'de cujus', 'faraid', 'inheritance', 'heirs', 'estate'],
            'subdomains' => [
                'heirs' => ['heritier', 'heritiers', 'ayants droit', 'ayants cause', 'heirs'],
                'estate' => ['succession', 'heritage', 'masse successorale', 'partage', 'liquidation', 'estate'],
                'will' => ['testament', 'legs', 'legs obligatoire', 'will'],
            ],
        ],
        'family_marriage_divorce' => [
            'terms' => ['famille', 'mariage', 'divorce', 'garde', 'pension', 'nafaqa', 'filiation', 'epoux', 'epouse', 'marriage', 'divorce', 'custody', 'alimony'],
            'subdomains' => [
                'marriage' => ['mariage', 'epoux', 'epouse', 'dot', 'marriage'],
                'divorce' => ['divorce', 'repudiation', 'separation'],
                'custody_support' => ['garde', 'pension', 'nafaqa', 'custody', 'alimony'],
            ],
        ],
        'real_estate_rent' => [
            'terms' => ['immobilier', 'foncier', 'propriete fonciere', 'droit de propriete', 'preuve du droit de propriete', 'titre foncier', 'bail', 'loyer', 'locataire', 'bailleur', 'occupant', 'expulsion', 'copropriete', 'rent', 'tenant', 'landlord', 'lease', 'real estate'],
            'subdomains' => [
                'rent' => ['bail', 'loyer', 'locataire', 'bailleur', 'rent', 'tenant', 'landlord', 'lease'],
                'land_title' => ['titre foncier', 'propriete fonciere', 'immatriculation fonciere', 'land title'],
                'coownership' => ['copropriete', 'syndic'],
            ],
        ],
        'commercial_company' => [
            'terms' => ['commerce', 'commercial', 'societe', 'sarl', 'sa', 'actionnaire', 'associe', 'registre de commerce', 'gerant', 'company', 'corporate', 'shareholder'],
            'subdomains' => [
                'company' => ['societe', 'sarl', 'sa', 'actionnaire', 'associe', 'gerant', 'company', 'shareholder'],
                'merchant' => ['commercant', 'acte de commerce', 'fonds de commerce'],
                'registration' => ['registre de commerce', 'immatriculation commerciale'],
            ],
        ],
        'banking_finance' => [
            'terms' => ['banque', 'bank', 'bank al maghrib', 'etablissement de credit', 'organisme assimile', 'credit', 'micro credit', 'compte bancaire', 'ouverture de compte', 'secret bancaire', 'secret professionnel bancaire', 'confidentialite bancaire', 'cheque', 'virement bancaire', 'moyen de paiement', 'interdiction bancaire', 'comite des etablissements de credit', 'banking', 'loan', 'payment institution'],
            'subdomains' => [
                'credit' => ['credit', 'pret bancaire', 'etablissement de credit', 'micro credit', 'loan'],
                'payments' => ['cheque', 'virement', 'moyen de paiement', 'carte bancaire', 'payment'],
                'regulator' => ['bank al maghrib', 'comite des etablissements de credit', 'supervision bancaire'],
            ],
        ],
        'insurance' => [
            'terms' => ['assurance', 'assurances', 'code des assurances', 'assureur', 'assure', 'contrat d assurance', 'sinistre', 'prime', 'indemnisation', 'prevoyance sociale', 'autorite de controle des assurances', 'acaps', 'insurance'],
            'subdomains' => [
                'insurance_contract' => ['contrat d assurance', 'prime', 'sinistre', 'garantie', 'assureur', 'assure'],
                'regulator' => ['autorite de controle des assurances', 'acaps', 'prevoyance sociale'],
                'compensation' => ['indemnisation', 'dommages', 'reparation'],
            ],
        ],
        'health_medical' => [
            'terms' => ['sante', 'systeme national de sante', 'medecine', 'medecin', 'ordre national des medecins', 'pharmacie', 'medicament', 'clinique', 'hopital', 'patient', 'biomedical', 'recherches biomedicales', 'medecine legale', 'health', 'medical', 'doctor', 'pharmacy'],
            'subdomains' => [
                'medical_profession' => ['medecin', 'ordre national des medecins', 'exercice de la medecine', 'profession medicale', 'doctor'],
                'pharmacy_medicine' => ['pharmacie', 'medicament', 'produit pharmaceutique', 'pharmacy'],
                'health_system' => ['systeme national de sante', 'hopital', 'clinique', 'patient', 'health system'],
                'biomedical_research' => ['recherches biomedicales', 'biomedical', 'protection des personnes participant aux recherches'],
            ],
        ],
        'professional_regulation' => [
            'terms' => ['profession', 'ordre professionnel', 'adoul', 'notaire', 'avocat', 'huissier', 'expert judiciaire', 'traducteur agree', 'ingenieur geometre topographe', 'comptable agree', 'architecte', 'professional regulation'],
            'subdomains' => [
                'legal_professions' => ['avocat', 'notaire', 'adoul', 'huissier', 'traducteur agree', 'expert judiciaire'],
                'technical_professions' => ['ingenieur geometre topographe', 'architecte', 'comptable agree'],
                'professional_body' => ['ordre professionnel', 'conseil national', 'inscription au tableau'],
            ],
        ],
        'digital_data_ip_media' => [
            'terms' => ['donnees a caractere personnel', 'protection des donnees', 'transactions electroniques', 'signature electronique', 'services de confiance', 'carte nationale d identite electronique', 'droit d auteur', 'droits voisins', 'presse', 'edition', 'communication audiovisuelle', 'personal data', 'electronic transactions', 'copyright', 'press'],
            'subdomains' => [
                'data_protection' => ['donnees a caractere personnel', 'protection des donnees', 'cn dp', 'privacy', 'personal data'],
                'electronic_transactions' => ['transactions electroniques', 'signature electronique', 'services de confiance', 'identite electronique'],
                'ip_media' => ['droit d auteur', 'droits voisins', 'presse', 'edition', 'copyright', 'media'],
            ],
        ],
        'environment_water_energy' => [
            'terms' => ['eau', 'loi relative a l eau', 'environnement', 'energie', 'electricite', 'energie renouvelable', 'nucleaire', 'radiologique', 'surete nucleaire', 'pollution', 'water', 'environment', 'energy'],
            'subdomains' => [
                'water' => ['eau', 'bassin hydraulique', 'ressources en eau', 'water'],
                'energy' => ['energie', 'electricite', 'energie renouvelable', 'energy'],
                'nuclear_safety' => ['nucleaire', 'radiologique', 'surete nucleaire'],
            ],
        ],
        'prison_corrections' => [
            'terms' => ['etablissement penitentiaire', 'etablissements penitentiaires', 'prison', 'detenu', 'detention', 'administration penitentiaire', 'reinsertion', 'corrections'],
            'subdomains' => [
                'prison_administration' => ['etablissement penitentiaire', 'administration penitentiaire', 'prison'],
                'detained_persons' => ['detenu', 'detention', 'reinsertion'],
            ],
        ],
        'tax' => [
            'terms' => ['impot', 'impots', 'taxe', 'fiscal', 'fiscalite', 'tva', 'douane', 'recouvrement des creances publiques', 'creances publiques', 'dette publique', 'recouvrement force fiscal', 'commandement fiscal', 'avis d imposition', 'contestation fiscale', 'declaration fiscale', 'sanction fiscale', 'redressement fiscal', 'tax', 'vat'],
            'subdomains' => [
                'vat' => ['tva', 'vat'],
                'income_tax' => ['ir', 'impot sur le revenu'],
                'corporate_tax' => ['is', 'impot sur les societes'],
                'customs' => ['douane', 'importation', 'exportation'],
            ],
        ],
        'administrative_urbanism' => [
            'terms' => ['administratif', 'administration', 'decision administrative', 'acte administratif', 'recours administratif', 'refus administratif', 'motivation administrative', 'autorisation', 'permis', 'urbanisme', 'commune', 'collectivite', 'expropriation', 'marche public', 'public procurement', 'permit', 'license'],
            'subdomains' => [
                'urbanism' => ['urbanisme', 'permis de construire', 'lotissement', 'construction'],
                'public_procurement' => ['marche public', 'commande publique', 'appel d offres', 'public procurement'],
                'administrative_authorization' => ['autorisation', 'permis', 'license', 'administration'],
            ],
        ],
    ];

    private const CATEGORY_ALIASES = [
        'civil' => 'civil_obligations_contracts',
        'contracts' => 'civil_obligations_contracts',
        'contract' => 'civil_obligations_contracts',
        'labor' => 'labor',
        'travail' => 'labor',
        'criminal' => 'criminal',
        'penal' => 'criminal',
        'criminal-procedure' => 'criminal_procedure',
        'procedure-penale' => 'criminal_procedure',
        'civil-procedure' => 'civil_procedure',
        'procedure-civile' => 'civil_procedure',
        'family' => 'family_marriage_divorce',
        'succession' => 'succession_inheritance',
        'successions' => 'succession_inheritance',
        'heritage' => 'succession_inheritance',
        'inheritance' => 'succession_inheritance',
        'testament' => 'succession_inheritance',
        'legs' => 'succession_inheritance',
        'partage' => 'succession_inheritance',
        'real-estate' => 'real_estate_rent',
        'real_estate' => 'real_estate_rent',
        'commercial' => 'commercial_company',
        'commerce' => 'commercial_company',
        'business' => 'commercial_company',
        'companies' => 'commercial_company',
        'company' => 'commercial_company',
        'consumer' => 'consumer_protection',
        'consommateur' => 'consumer_protection',
        'banking' => 'banking_finance',
        'bank' => 'banking_finance',
        'banque' => 'banking_finance',
        'finance' => 'banking_finance',
        'financial' => 'banking_finance',
        'insurance' => 'insurance',
        'assurance' => 'insurance',
        'health' => 'health_medical',
        'sante' => 'health_medical',
        'medical' => 'health_medical',
        'tax' => 'tax',
        'fiscal' => 'tax',
        'administrative' => 'administrative_urbanism',
        'urbanism' => 'administrative_urbanism',
        'official-bulletin' => 'official-bulletin',
    ];

    public function classifyQuery(string $query): array
    {
        $classification = $this->classify([$query, $this->arabicDomainSignals($query)]);
        $boosts = $this->explicitDomainBoosts($query);

        if (!$boosts) {
            return $classification;
        }

        $scores = $classification['scores'] ?? [];
        foreach ($boosts as $domain => $boost) {
            $scores[$domain] = ($scores[$domain] ?? 0) + $boost;
        }
        arsort($scores);
        $domain = array_key_first($scores);

        return array_merge($classification, [
            'domain' => $domain,
            'scores' => $scores,
            'tags' => collect($classification['tags'] ?? [])->push($domain)->filter()->unique()->values()->all(),
        ]);
    }

    private function explicitDomainBoosts(string $query): array
    {
        $text = $this->normalize($query.' '.$this->arabicDomainSignals($query));
        $rules = [
            'tax' => [
                'sanction fiscale', 'penalite fiscale', 'penalites fiscales', 'contestation fiscale',
                'avis d imposition', 'declaration fiscale', 'defaut de declaration fiscale',
                'societe n a pas declare', 'n a pas declare la taxe', 'pas declare la taxe',
                'redressement fiscal', 'recouvrement des creances publiques', 'creances publiques',
                'dette publique', 'recouvrement force fiscal', 'taxe sur la valeur ajoutee',
            ],
            'administrative_urbanism' => ['decision administrative', 'recours administratif', 'refus administratif', 'motivation administrative', 'autorisation commune'],
            'banking_finance' => [
                'secret bancaire', 'confidentialite bancaire', 'obligations bancaires', 'frais bancaires',
                'compte bancaire', 'ouverture de compte', 'ouvrir un compte', 'banque refuse',
                'etablissement de credit', 'etablissements de credit', 'agrement credit',
                'agrement etablissement de credit', 'institution de credit',
            ],
            'real_estate_rent' => [
                'droit de propriete fonciere', 'preuve du droit de propriete', 'propriete fonciere',
                'bail habitation', 'occupant sans contrat', 'expulser occupant', 'expulsion occupant',
                'priorite entre acheteurs', 'double vente immobiliere', 'immeuble non immatricule',
                'terrain non immatricule', 'bien immobilier non immatricule', 'coproprietaire',
                'coproprietaires', 'syndic copropriete', 'nomination syndic', 'designation syndic',
                'assemblee generale copropriete',
            ],
            'labor' => [
                'accident du travail', 'accident de travail', 'accident pendant le travail',
                'accident sur le lieu du travail', 'licenciement', 'procedure disciplinaire',
                'contrat de travail',
            ],
            'commercial_company' => [
                'gerant de sarl', 'pouvoirs du gerant', 'associes sarl', 'parts sociales',
                'cession de parts', 'cession des parts', 'personnalite morale',
                'immatriculation au registre de commerce', 'sarl vend un actif',
                'actif important sans accord',
            ],
            'criminal' => [
                'abus de confiance', 'code penal', 'qualification penale', 'infraction penale',
                'texte penal', 'cas penal', 'detourne l argent', 'detourne des fonds',
                'detournement de fonds', 'abus de confiance ou vol', 'vol code penal',
            ],
            'family_marriage_divorce' => [
                'code de la famille', 'interet de l enfant', 'interet du mineur', 'garde enfant',
                'mere gardienne', 'pension alimentaire', 'droit de garde', 'deplacement de l enfant',
            ],
            'civil_procedure' => ['procedure civile', 'saisie arret', 'execution forcee', 'competence territoriale'],
        ];
        $boosts = [];

        foreach ($rules as $domain => $phrases) {
            foreach ($phrases as $phrase) {
                if ($this->matches($text, $phrase)) {
                    $boosts[$domain] = ($boosts[$domain] ?? 0) + 12;
                }
            }
        }

        return $boosts;
    }

    private function arabicDomainSignals(string $query): string
    {
        if (preg_match('/\p{Arabic}/u', $query) !== 1) {
            return '';
        }

        $signals = [];
        $rules = [
            'family_marriage_divorce famille garde pension divorce custody interet de l enfant code de la famille' => '/حضان|حاضن|الحاضنة|المحضون|مصلحة المحضون|مدونة الأسرة|مدونة الاسرة|نفقة|زواج|طلاق|الأسرة|الاسرة/u',
            'real_estate_rent immobilier foncier droit de propriete bail loyer copropriete double vente immobiliere non immatricule' => '/عقار|ملكية|تحفيظ|رسم عقاري|كراء|إيجار|ايجار|سانديك|سنديك|غير محفظ|الأولوية بين المشترين|الاولوية بين المشترين/u',
            'labor travail salarie employeur licenciement accident du travail accident de travail demission' => '/شغل|أجير|اجير|مشغل|طرد|فصل من العمل|حادث شغل|حادث الشغل|حادث أثناء العمل|حادث اثناء العمل|استقال/u',
            'commercial_company commerce societe registre de commerce gerant associe sarl parts sociales' => '/شركة|تجاري|القانون التجاري|السجل التجاري|شريك|مسير|سارل|حصص|حصة اجتماعية/u',
            'civil_obligations_contracts obligations contrats vente dette preuve responsabilite' => '/التزامات|عقود|عقد|بيع|دين|قرض|إثبات|اثبات|مسؤولية مدنية/u',
            'civil_procedure procedure civile tribunal appel saisie notification execution' => '/مسطرة مدنية|إجراء مدني|اجراء مدني|محكمة|استئناف|حجز|تبليغ|تنفيذ/u',
            'criminal penal infraction vol menace escroquerie abus de confiance detournement' => '/جنائي|جريمة|سرقة|تهديد|نصب|احتيال|خيانة الأمانة|خيانة الامانة|اختلاس|استولى|دون حق|النص الجنائي|القانون الجنائي/u',
            'banking_finance banque etablissement de credit compte bancaire secret bancaire agrement credit' => '/بنك|بنكي|ائتمان|حساب بنكي|سر بنكي|سرية بنكية|معطيات الزبون|مؤسسة الائتمان|مؤسسة ائتمان|اعتماد مؤسسة الائتمان|قروض للجمهور|قروضا للجمهور|ترخيص بنكي/u',
            'tax fiscal impot taxe tva contestation fiscale sanction fiscale' => '/ضريبة|ضرائب|جبائي|جباية|القيمة المضافة|تصريح ضريبي|تصرح بالضريبة|لم تصرح بالضريبة|الجزاءات الضريبية|جزاءات ضريبية/u',
            'administrative_urbanism administration decision administrative autorisation commune recours administratif' => '/إدار|ادار|قرار إداري|قرار اداري|ترخيص|رخصة|جماعة|طعن إداري|طعن اداري/u',
        ];

        foreach ($rules as $signal => $pattern) {
            if (preg_match($pattern, $query)) {
                $signals[] = $signal;
            }
        }

        return implode(' ', $signals);
    }

    public function classifyDocument(array $context): array
    {
        $documentTitle = (string) ($context['documentTitle'] ?? $context['document_title'] ?? '');
        $lawReference = (string) ($context['lawReference'] ?? $context['law_reference'] ?? '');
        $override = $this->documentTitleOverride($documentTitle);

        if ($override) {
            return $override;
        }

        return $this->classify([
            trim("{$documentTitle} {$documentTitle} {$documentTitle} {$lawReference} {$lawReference}"),
            $context['sourceCategory'] ?? $context['source_category'] ?? $context['category'] ?? '',
            $context['sourceName'] ?? $context['source_name'] ?? '',
            $context['headings'] ?? [],
            $context['text'] ?? '',
        ], $context['tags'] ?? []);
    }

    private function documentTitleOverride(string $title): ?array
    {
        $normalized = $this->normalize($title);
        $rules = [
            'official-bulletin' => [
                'bulletin officiel n',
            ],
            'civil_obligations_contracts' => [
                'code des obligations et des contrats',
                'dahir des obligations et contrats',
            ],
            'commercial_company' => [
                'code de commerce',
                'societes anonymes',
                'societe en nom collectif',
                'sarl',
                'cooperatives',
                'registre de commerce',
                'charte de l investissement',
            ],
            'labor' => [
                'code du travail',
                'contrat de travail',
                'comptables agrees',
            ],
            'criminal' => [
                'code penal',
                'traite des etres humains',
            ],
            'family_marriage_divorce' => [
                'code de la famille',
            ],
            'real_estate_rent' => [
                'code des droits reels',
                'immatriculation fonciere',
                'copropriete',
                'recouvrement des loyers',
                'urbanisme',
                'lotissements',
                'morcellements',
            ],
            'tax' => [
                'code de recouvrement des creances publiques',
                'fiscalite',
                'loi de finances',
            ],
            'consumer_protection' => [
                'protection du consommateur',
                'securite des produits et des services',
            ],
            'administrative_urbanism' => [
                'communes',
                'regions',
                'prefectures et provinces',
                'service militaire',
                'agence nationale des equipements publics',
                'ecole hassania des travaux publics',
            ],
            'civil_procedure' => [
                'code de procedure civile',
                'dahir sur la procedure civile',
                'assistance judiciaire',
                'organisation judiciaire',
                'juridictions de proximite',
                'tribunaux administratifs',
                'cours d appel administratives',
                'arbitrage et mediation conventionnelle',
            ],
            'criminal_procedure' => [
                'code de procedure penale',
                'procedure penale',
            ],
            'banking_finance' => [
                'bank al maghrib',
                'etablissements de credit',
                'comite des etablissements de credit',
                'conseil national du credit',
                'microfinance',
                'micro credit',
                'titres de creances negociables',
                'obligations securisees',
                'operations de pension',
                'places financieres offshore',
                'blanchiment de capitaux',
                'liquidite d urgence',
            ],
            'insurance' => [
                'code des assurances',
                'assurances',
                'prevoyance sociale',
                'risques systemiques',
                'evenements catastrophiques',
            ],
            'health_medical' => [
                'systeme national de sante',
                'exercice de la medecine',
                'ordre national des medecins',
                'code du medicament',
                'pharmacie',
                'dispositifs medicaux',
                'centres hospitalo universitaires',
                'sage femme',
                'professions infirmieres',
                'medecine legale',
                'recherches biomedicales',
                'dopage',
                'securite sanitaire des produits alimentaires',
            ],
            'digital_data_ip_media' => [
                'donnees a caractere personnel',
                'services de confiance',
                'transactions electroniques',
                'identite electronique',
                'droit d auteur',
                'droits voisins',
                'presse et l edition',
                'cybersecurite',
                'archives',
            ],
            'environment_water_energy' => [
                'loi relative a l eau',
                'environnement',
                'developpement durable',
                'energie',
                'electricite',
                'nucleaire',
                'radiologique',
            ],
            'professional_regulation' => [
                'profession d adoul',
                'profession de notaire',
                'profession d avocat',
                'traducteurs agrees',
                'ingenieur geometre topographe',
                'comptables agrees',
            ],
            'prison_corrections' => [
                'etablissements penitentiaires',
            ],
        ];

        foreach ($rules as $domain => $patterns) {
            foreach ($patterns as $pattern) {
                if (str_contains($normalized, $pattern)) {
                    return [
                        'domain' => $domain,
                        'subdomain' => $this->defaultSubdomainForDocument($domain, $normalized),
                        'tags' => array_values(array_filter([
                            $domain,
                            $this->defaultSubdomainForDocument($domain, $normalized),
                            ...($domain === 'labor' ? ['emploi', 'travail'] : []),
                        ])),
                        'scores' => [$domain => 100],
                        'subdomainScores' => [],
                    ];
                }
            }
        }

        return null;
    }

    private function defaultSubdomainForDocument(string $domain, string $normalizedTitle): ?string
    {
        return match ($domain) {
            'civil_obligations_contracts' => str_contains($normalizedTitle, 'vente') ? 'sale' : 'contract_performance',
            'commercial_company' => str_contains($normalizedTitle, 'commerce') ? 'merchant' : 'company',
            'labor' => str_contains($normalizedTitle, 'travail') ? 'dismissal' : null,
            'criminal' => str_contains($normalizedTitle, 'traite') ? 'procedure' : null,
            'family_marriage_divorce' => 'marriage',
            'real_estate_rent' => str_contains($normalizedTitle, 'copropriete') ? 'coownership' : (str_contains($normalizedTitle, 'bail') ? 'rent' : 'land_title'),
            'tax' => str_contains($normalizedTitle, 'recouvrement') ? 'customs' : 'corporate_tax',
            'consumer_protection' => str_contains($normalizedTitle, 'securite') ? 'product_safety' : 'consumer_contracts',
            'administrative_urbanism' => str_contains($normalizedTitle, 'urbanisme') || str_contains($normalizedTitle, 'lotissement') ? 'urbanism' : 'administrative_authorization',
            'civil_procedure' => str_contains($normalizedTitle, 'execution') ? 'enforcement' : 'jurisdiction',
            'banking_finance' => str_contains($normalizedTitle, 'credit') || str_contains($normalizedTitle, 'microfinance') ? 'credit' : 'regulator',
            'insurance' => str_contains($normalizedTitle, 'contrat') || str_contains($normalizedTitle, 'assurance') ? 'insurance_contract' : 'regulator',
            'health_medical' => str_contains($normalizedTitle, 'medicament') || str_contains($normalizedTitle, 'pharmacie') ? 'pharmacy_medicine' : (str_contains($normalizedTitle, 'biomedical') ? 'biomedical_research' : 'medical_profession'),
            'digital_data_ip_media' => str_contains($normalizedTitle, 'donnees') ? 'data_protection' : (str_contains($normalizedTitle, 'droit') || str_contains($normalizedTitle, 'presse') ? 'ip_media' : 'electronic_transactions'),
            'environment_water_energy' => str_contains($normalizedTitle, 'eau') ? 'water' : (str_contains($normalizedTitle, 'energie') || str_contains($normalizedTitle, 'electricite') ? 'energy' : 'nuclear_safety'),
            'professional_regulation' => str_contains($normalizedTitle, 'adoul') || str_contains($normalizedTitle, 'notaire') || str_contains($normalizedTitle, 'avocat') || str_contains($normalizedTitle, 'traducteur') ? 'legal_professions' : 'technical_professions',
            'prison_corrections' => 'prison_administration',
            default => null,
        };
    }

    public function classifyArticle(array $context, ?array $documentTaxonomy = null): array
    {
        return $this->classify([
            $context['documentTitle'] ?? $context['document_title'] ?? '',
            $context['sourceCategory'] ?? $context['source_category'] ?? $context['category'] ?? '',
            $context['chapterHeading'] ?? $context['chapter_heading'] ?? '',
            $context['sectionHeading'] ?? $context['section_heading'] ?? '',
            $context['articleTitle'] ?? $context['article_title'] ?? '',
            $context['articleText'] ?? $context['article_text'] ?? $context['content'] ?? '',
        ], $context['tags'] ?? [], $documentTaxonomy);
    }

    public function classifyChunk(array $context, ?array $articleTaxonomy = null): array
    {
        return $this->classify([
            $context['documentTitle'] ?? $context['document_title'] ?? '',
            $context['articleTitle'] ?? $context['article_title'] ?? '',
            $context['chunkText'] ?? $context['chunk_text'] ?? $context['content'] ?? '',
        ], $context['tags'] ?? [], $articleTaxonomy);
    }

    public function conceptTermsForQuery(string $query, int $limit = 14): array
    {
        return $this->conceptTermsForTaxonomy($this->classifyQuery($query), $limit);
    }

    public function conceptTermsForTaxonomy(?array $taxonomy, int $limit = 14): array
    {
        $domain = $taxonomy['domain'] ?? null;

        if (!$domain || !isset(self::DOMAIN_PROFILES[$domain])) {
            return [];
        }

        $domains = collect($taxonomy['scores'] ?? [])
            ->sortDesc()
            ->keys()
            ->filter(fn (string $candidate): bool => isset(self::DOMAIN_PROFILES[$candidate]))
            ->prepend($domain)
            ->unique()
            ->take(3);
        $terms = collect($this->conceptAliasesForTags($taxonomy['tags'] ?? []));

        foreach ($domains as $candidateDomain) {
            $profile = self::DOMAIN_PROFILES[$candidateDomain];
            $subdomain = $candidateDomain === $domain
                ? ($taxonomy['subdomain'] ?? null)
                : collect($taxonomy['subdomainScores'][$candidateDomain] ?? [])->sortDesc()->keys()->first();

            if ($subdomain && isset($profile['subdomains'][$subdomain])) {
                $terms = $terms->concat($profile['subdomains'][$subdomain]);
            }

            $terms = $terms->concat($profile['terms']);
        }

        return $terms
            ->map(fn (string $term): string => trim($term))
            ->filter(fn (string $term): bool => $term !== '')
            ->unique(fn (string $term): string => $this->normalize($term))
            ->take($limit)
            ->values()
            ->all();
    }

    private function conceptAliasesForTags(array $tags): array
    {
        $aliases = [];
        $normalizedTags = collect($tags)->map(fn (string $tag): string => $this->tag($tag))->all();

        if (in_array('heirs', $normalizedTags, true) || in_array('heritier', $normalizedTags, true) || in_array('heritiers', $normalizedTags, true)) {
            $aliases = array_merge($aliases, ['heritiers', 'heritier', 'succession', 'ayants droit']);
        }

        if (in_array('buyer', $normalizedTags, true)) {
            $aliases[] = 'acheteur';
        }

        if (in_array('seller', $normalizedTags, true)) {
            $aliases[] = 'vendeur';
        }

        if (in_array('ownership', $normalizedTags, true)) {
            $aliases[] = 'transfert de propriete';
        }

        return array_values(array_unique($aliases));
    }

    private function classify(array $texts, array|string|null $seedTags = [], ?array $fallback = null): array
    {
        $text = $this->normalize(implode(' ', array_map(
            fn (mixed $value): string => is_array($value) ? implode(' ', $value) : (string) $value,
            $texts
        )));
        $tags = collect(is_array($seedTags) ? $seedTags : [$seedTags])
            ->map(fn (mixed $tag): string => $this->tag((string) $tag))
            ->filter()
            ->values();
        $domainScores = [];
        $subdomainScores = [];

        foreach (self::CATEGORY_ALIASES as $alias => $domain) {
            if ($this->matches($text, $alias)) {
                $domainScores[$domain] = ($domainScores[$domain] ?? 0) + 6;
                $tags->push($this->tag($alias));
            }
        }

        foreach (self::DOMAIN_PROFILES as $domain => $profile) {
            foreach ($profile['terms'] as $term) {
                if ($this->matches($text, $term)) {
                    $domainScores[$domain] = ($domainScores[$domain] ?? 0) + $this->termWeight($term);
                    $tags->push($this->tag($term));
                }
            }

            foreach ($profile['subdomains'] as $subdomain => $terms) {
                foreach ($terms as $term) {
                    if ($this->matches($text, $term)) {
                        $subdomainScores[$domain][$subdomain] = ($subdomainScores[$domain][$subdomain] ?? 0) + $this->termWeight($term);
                        $tags->push($this->tag($subdomain));
                    }
                }
            }
        }

        arsort($domainScores);
        $domain = array_key_first($domainScores) ?: ($fallback['domain'] ?? null);
        $subdomain = null;

        if ($domain && isset($subdomainScores[$domain])) {
            arsort($subdomainScores[$domain]);
            $subdomain = array_key_first($subdomainScores[$domain]);
        }

        $subdomain ??= $fallback['subdomain'] ?? null;

        return [
            'domain' => $domain,
            'subdomain' => $subdomain,
            'tags' => $tags
                ->merge(array_filter([$domain, $subdomain]))
                ->map(fn (string $tag): string => $this->tag($tag))
                ->filter()
                ->unique()
                ->take(30)
                ->values()
                ->all(),
            'scores' => $domainScores,
            'subdomainScores' => $subdomainScores,
        ];
    }

    private function matches(string $text, string $term): bool
    {
        $term = $this->normalize($term);

        if ($term === '') {
            return false;
        }

        if (str_contains($term, ' ')) {
            return str_contains($text, $term);
        }

        return (bool) preg_match('/\b'.preg_quote($term, '/').'\b/u', $text);
    }

    private function termWeight(string $term): int
    {
        return str_contains(trim($term), ' ') ? 4 : 2;
    }

    private function tag(string $value): string
    {
        return Str::of($value)->lower()->ascii()->replaceMatches('/[^a-z0-9]+/', '_')->trim('_')->toString();
    }

    private function normalize(string $value): string
    {
        return Str::of($value)
            ->lower()
            ->ascii()
            ->replaceMatches('/[-_]+/', ' ')
            ->replaceMatches('/[^\pL\pN\s]+/u', ' ')
            ->replaceMatches('/\s+/', ' ')
            ->trim()
            ->toString();
    }
}
