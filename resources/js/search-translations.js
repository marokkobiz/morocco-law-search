(function () {
  var storageKey = 'marokko.workspaceLanguage';
  var supportedLanguages = ['fr', 'en', 'ar'];

  var categoryLabels = {
    'Droit commercial': { en: 'Commercial law', ar: '\u0627\u0644\u0642\u0627\u0646\u0648\u0646 \u0627\u0644\u062a\u062c\u0627\u0631\u064a' },
    'Droit civil': { en: 'Civil law', ar: '\u0627\u0644\u0642\u0627\u0646\u0648\u0646 \u0627\u0644\u0645\u062f\u0646\u064a' },
    'Procedure civile': { en: 'Civil procedure', ar: '\u0627\u0644\u0645\u0633\u0637\u0631\u0629 \u0627\u0644\u0645\u062f\u0646\u064a\u0629' },
    'Droit penal': { en: 'Criminal law', ar: '\u0627\u0644\u0642\u0627\u0646\u0648\u0646 \u0627\u0644\u062c\u0646\u0627\u0626\u064a' },
    Immobilier: { en: 'Real estate', ar: '\u0627\u0644\u0639\u0642\u0627\u0631\u0627\u062a' },
    'Collectivites territoriales': { en: 'Territorial governance', ar: '\u0627\u0644\u062c\u0645\u0627\u0639\u0627\u062a \u0627\u0644\u062a\u0631\u0627\u0628\u064a\u0629' },
    Travail: { en: 'Labor', ar: '\u0627\u0644\u0634\u063a\u0644' },
    'Professions reglementees': { en: 'Regulated professions', ar: '\u0627\u0644\u0645\u0647\u0646 \u0627\u0644\u0645\u0646\u0638\u0645\u0629' },
    Sante: { en: 'Health', ar: '\u0627\u0644\u0635\u062d\u0629' },
    Famille: { en: 'Family', ar: '\u0627\u0644\u0623\u0633\u0631\u0629' },
    Banque: { en: 'Banking', ar: '\u0627\u0644\u0628\u0646\u0648\u0643' },
    'Marches financiers': { en: 'Financial markets', ar: '\u0627\u0644\u0623\u0633\u0648\u0627\u0642 \u0627\u0644\u0645\u0627\u0644\u064a\u0629' },
    Environnement: { en: 'Environment', ar: '\u0627\u0644\u0628\u064a\u0626\u0629' },
    Consommation: { en: 'Consumer law', ar: '\u0627\u0644\u0627\u0633\u062a\u0647\u0644\u0627\u0643' },
    'Professions judiciaires': { en: 'Judicial professions', ar: '\u0627\u0644\u0645\u0647\u0646 \u0627\u0644\u0642\u0636\u0627\u0626\u064a\u0629' },
    'Transactions electroniques': { en: 'Electronic transactions', ar: '\u0627\u0644\u0645\u0639\u0627\u0645\u0644\u0627\u062a \u0627\u0644\u0625\u0644\u0643\u062a\u0631\u0648\u0646\u064a\u0629' },
    'Finances publiques': { en: 'Public finance', ar: '\u0627\u0644\u0645\u0627\u0644\u064a\u0629 \u0627\u0644\u0639\u0645\u0648\u0645\u064a\u0629' },
    Assurances: { en: 'Insurance', ar: '\u0627\u0644\u062a\u0623\u0645\u064a\u0646\u0627\u062a' },
    'Surete nucleaire': { en: 'Nuclear safety', ar: '\u0627\u0644\u0633\u0644\u0627\u0645\u0629 \u0627\u0644\u0646\u0648\u0648\u064a\u0629' },
    'Fiscalite locale': { en: 'Local taxation', ar: '\u0627\u0644\u062c\u0628\u0627\u064a\u0627\u062a \u0627\u0644\u0645\u062d\u0644\u064a\u0629' },
    'Etablissements penitentiaires': { en: 'Prison institutions', ar: '\u0627\u0644\u0645\u0624\u0633\u0633\u0627\u062a \u0627\u0644\u0633\u062c\u0646\u064a\u0629' },
    'Gouvernance administrative': { en: 'Administrative governance', ar: '\u0627\u0644\u062d\u0643\u0627\u0645\u0629 \u0627\u0644\u0625\u062f\u0627\u0631\u064a\u0629' },
    Journalism: { en: 'Journalism', ar: '\u0627\u0644\u0635\u062d\u0627\u0641\u0629' },
    'Organisation judiciaire': { en: 'Judicial organization', ar: '\u0627\u0644\u062a\u0646\u0638\u064a\u0645 \u0627\u0644\u0642\u0636\u0627\u0626\u064a' },
    'Entreprises publiques': { en: 'Public enterprises', ar: '\u0627\u0644\u0645\u0624\u0633\u0633\u0627\u062a \u0627\u0644\u0639\u0645\u0648\u0645\u064a\u0629' },
    Culture: { en: 'Culture', ar: '\u0627\u0644\u062b\u0642\u0627\u0641\u0629' },
    Aviation: { en: 'Aviation', ar: '\u0627\u0644\u0637\u064a\u0631\u0627\u0646' },
    Education: { en: 'Education', ar: '\u0627\u0644\u062a\u0639\u0644\u064a\u0645' },
    'Commande publique': { en: 'Public procurement', ar: '\u0627\u0644\u0635\u0641\u0642\u0627\u062a \u0627\u0644\u0639\u0645\u0648\u0645\u064a\u0629' },
    'Commerce exterieur': { en: 'Foreign trade', ar: '\u0627\u0644\u062a\u062c\u0627\u0631\u0629 \u0627\u0644\u062e\u0627\u0631\u062c\u064a\u0629' },
    'Securite industrielle': { en: 'Industrial safety', ar: '\u0627\u0644\u0633\u0644\u0627\u0645\u0629 \u0627\u0644\u0635\u0646\u0627\u0639\u064a\u0629' },
    Fiscalite: { en: 'Taxation', ar: '\u0627\u0644\u0636\u0631\u0627\u0626\u0628' },
    Tourisme: { en: 'Tourism', ar: '\u0627\u0644\u0633\u064a\u0627\u062d\u0629' },
    Investissement: { en: 'Investment', ar: '\u0627\u0644\u0627\u0633\u062a\u062b\u0645\u0627\u0631' },
    Securite: { en: 'Security', ar: '\u0627\u0644\u0623\u0645\u0646' },
  };

  var categorySlugLabels = {
    commercial: { fr: 'Droit commercial', en: 'Commercial law', ar: '\u0627\u0644\u0642\u0627\u0646\u0648\u0646 \u0627\u0644\u062a\u062c\u0627\u0631\u064a' },
    civil: { fr: 'Droit civil', en: 'Civil law', ar: '\u0627\u0644\u0642\u0627\u0646\u0648\u0646 \u0627\u0644\u0645\u062f\u0646\u064a' },
    'civil-procedure': { fr: 'Procedure civile', en: 'Civil procedure', ar: '\u0627\u0644\u0645\u0633\u0637\u0631\u0629 \u0627\u0644\u0645\u062f\u0646\u064a\u0629' },
    criminal: { fr: 'Droit penal', en: 'Criminal law', ar: '\u0627\u0644\u0642\u0627\u0646\u0648\u0646 \u0627\u0644\u062c\u0646\u0627\u0626\u064a' },
    'real-estate': { fr: 'Immobilier', en: 'Real estate', ar: '\u0627\u0644\u0639\u0642\u0627\u0631\u0627\u062a' },
    'territorial-governance': { fr: 'Collectivites territoriales', en: 'Territorial governance', ar: '\u0627\u0644\u062c\u0645\u0627\u0639\u0627\u062a \u0627\u0644\u062a\u0631\u0627\u0628\u064a\u0629' },
    labor: { fr: 'Travail', en: 'Labor', ar: '\u0627\u0644\u0634\u063a\u0644' },
    'regulated-professions': { fr: 'Professions reglementees', en: 'Regulated professions', ar: '\u0627\u0644\u0645\u0647\u0646 \u0627\u0644\u0645\u0646\u0638\u0645\u0629' },
    health: { fr: 'Sante', en: 'Health', ar: '\u0627\u0644\u0635\u062d\u0629' },
    family: { fr: 'Famille', en: 'Family', ar: '\u0627\u0644\u0623\u0633\u0631\u0629' },
    banking: { fr: 'Banque', en: 'Banking', ar: '\u0627\u0644\u0628\u0646\u0648\u0643' },
    'financial-market': { fr: 'Marches financiers', en: 'Financial markets', ar: '\u0627\u0644\u0623\u0633\u0648\u0627\u0642 \u0627\u0644\u0645\u0627\u0644\u064a\u0629' },
    environment: { fr: 'Environnement', en: 'Environment', ar: '\u0627\u0644\u0628\u064a\u0626\u0629' },
    consumer: { fr: 'Consommation', en: 'Consumer law', ar: '\u0627\u0644\u0627\u0633\u062a\u0647\u0644\u0627\u0643' },
    'judicial-professions': { fr: 'Professions judiciaires', en: 'Judicial professions', ar: '\u0627\u0644\u0645\u0647\u0646 \u0627\u0644\u0642\u0636\u0627\u0626\u064a\u0629' },
    'electronic-transactions': { fr: 'Transactions electroniques', en: 'Electronic transactions', ar: '\u0627\u0644\u0645\u0639\u0627\u0645\u0644\u0627\u062a \u0627\u0644\u0625\u0644\u0643\u062a\u0631\u0648\u0646\u064a\u0629' },
    'public-finance': { fr: 'Finances publiques', en: 'Public finance', ar: '\u0627\u0644\u0645\u0627\u0644\u064a\u0629 \u0627\u0644\u0639\u0645\u0648\u0645\u064a\u0629' },
    insurance: { fr: 'Assurances', en: 'Insurance', ar: '\u0627\u0644\u062a\u0623\u0645\u064a\u0646\u0627\u062a' },
    'nuclear-safety': { fr: 'Surete nucleaire', en: 'Nuclear safety', ar: '\u0627\u0644\u0633\u0644\u0627\u0645\u0629 \u0627\u0644\u0646\u0648\u0648\u064a\u0629' },
    'local-taxation': { fr: 'Fiscalite locale', en: 'Local taxation', ar: '\u0627\u0644\u062c\u0628\u0627\u064a\u0627\u062a \u0627\u0644\u0645\u062d\u0644\u064a\u0629' },
    prisons: { fr: 'Etablissements penitentiaires', en: 'Prison institutions', ar: '\u0627\u0644\u0645\u0624\u0633\u0633\u0627\u062a \u0627\u0644\u0633\u062c\u0646\u064a\u0629' },
    'administrative-governance': { fr: 'Gouvernance administrative', en: 'Administrative governance', ar: '\u0627\u0644\u062d\u0643\u0627\u0645\u0629 \u0627\u0644\u0625\u062f\u0627\u0631\u064a\u0629' },
    journalism: { fr: 'Journalisme', en: 'Journalism', ar: '\u0627\u0644\u0635\u062d\u0627\u0641\u0629' },
    'judicial-organization': { fr: 'Organisation judiciaire', en: 'Judicial organization', ar: '\u0627\u0644\u062a\u0646\u0638\u064a\u0645 \u0627\u0644\u0642\u0636\u0627\u0626\u064a' },
    'public-enterprises': { fr: 'Entreprises publiques', en: 'Public enterprises', ar: '\u0627\u0644\u0645\u0624\u0633\u0633\u0627\u062a \u0627\u0644\u0639\u0645\u0648\u0645\u064a\u0629' },
    culture: { fr: 'Culture', en: 'Culture', ar: '\u0627\u0644\u062b\u0642\u0627\u0641\u0629' },
    aviation: { fr: 'Aviation', en: 'Aviation', ar: '\u0627\u0644\u0637\u064a\u0631\u0627\u0646' },
    education: { fr: 'Education', en: 'Education', ar: '\u0627\u0644\u062a\u0639\u0644\u064a\u0645' },
    'public-procurement': { fr: 'Commande publique', en: 'Public procurement', ar: '\u0627\u0644\u0635\u0641\u0642\u0627\u062a \u0627\u0644\u0639\u0645\u0648\u0645\u064a\u0629' },
    'foreign-trade': { fr: 'Commerce exterieur', en: 'Foreign trade', ar: '\u0627\u0644\u062a\u062c\u0627\u0631\u0629 \u0627\u0644\u062e\u0627\u0631\u062c\u064a\u0629' },
    'industrial-safety': { fr: 'Securite industrielle', en: 'Industrial safety', ar: '\u0627\u0644\u0633\u0644\u0627\u0645\u0629 \u0627\u0644\u0635\u0646\u0627\u0639\u064a\u0629' },
    tax: { fr: 'Fiscalite', en: 'Taxation', ar: '\u0627\u0644\u0636\u0631\u0627\u0626\u0628' },
    tourism: { fr: 'Tourisme', en: 'Tourism', ar: '\u0627\u0644\u0633\u064a\u0627\u062d\u0629' },
    investment: { fr: 'Investissement', en: 'Investment', ar: '\u0627\u0644\u0627\u0633\u062a\u062b\u0645\u0627\u0631' },
    security: { fr: 'Securite', en: 'Security', ar: '\u0627\u0644\u0623\u0645\u0646' },
    'market-regulation': { fr: 'Regulation des marches', en: 'Market regulation', ar: '\u062a\u0646\u0638\u064a\u0645 \u0627\u0644\u0623\u0633\u0648\u0627\u0642' },
    aquaculture: { fr: 'Aquaculture', en: 'Aquaculture', ar: '\u062a\u0631\u0628\u064a\u0629 \u0627\u0644\u0623\u062d\u064a\u0627\u0621 \u0627\u0644\u0645\u0627\u0626\u064a\u0629' },
    energy: { fr: 'Energie', en: 'Energy', ar: '\u0627\u0644\u0637\u0627\u0642\u0629' },
    microfinance: { fr: 'Microfinance', en: 'Microfinance', ar: '\u0627\u0644\u062a\u0645\u0648\u064a\u0644 \u0627\u0644\u0623\u0635\u063a\u0631' },
    'disability-rights': { fr: 'Droits des personnes handicapees', en: 'Disability rights', ar: '\u062d\u0642\u0648\u0642 \u0627\u0644\u0623\u0634\u062e\u0627\u0635 \u0641\u064a \u0648\u0636\u0639\u064a\u0629 \u0625\u0639\u0627\u0642\u0629' },
    agriculture: { fr: 'Agriculture', en: 'Agriculture', ar: '\u0627\u0644\u0641\u0644\u0627\u062d\u0629' },
    veterinary: { fr: 'Veterinaire', en: 'Veterinary', ar: '\u0627\u0644\u0628\u064a\u0637\u0631\u0629' },
    'social-protection': { fr: 'Protection sociale', en: 'Social protection', ar: '\u0627\u0644\u062d\u0645\u0627\u064a\u0629 \u0627\u0644\u0627\u062c\u062a\u0645\u0627\u0639\u064a\u0629' },
    fisheries: { fr: 'Peche maritime', en: 'Fisheries', ar: '\u0627\u0644\u0635\u064a\u062f \u0627\u0644\u0628\u062d\u0631\u064a' },
    'sports-events': { fr: 'Evenements sportifs', en: 'Sports events', ar: '\u0627\u0644\u062a\u0638\u0627\u0647\u0631\u0627\u062a \u0627\u0644\u0631\u064a\u0627\u0636\u064a\u0629' },
    'rights-liberties': { fr: 'Droits et libertes', en: 'Rights and liberties', ar: '\u0627\u0644\u062d\u0642\u0648\u0642 \u0648\u0627\u0644\u062d\u0631\u064a\u0627\u062a' },
    'energy-efficiency': { fr: 'Efficacite energetique', en: 'Energy efficiency', ar: '\u0627\u0644\u0646\u062c\u0627\u0639\u0629 \u0627\u0644\u0637\u0627\u0642\u064a\u0629' },
    archives: { fr: 'Archives', en: 'Archives', ar: '\u0627\u0644\u0623\u0631\u0634\u064a\u0641' },
    hydrocarbons: { fr: 'Hydrocarbures', en: 'Hydrocarbons', ar: '\u0627\u0644\u0645\u062d\u0631\u0648\u0642\u0627\u062a' },
    ports: { fr: 'Ports', en: 'Ports', ar: '\u0627\u0644\u0645\u0648\u0627\u0646\u0626' },
    fintech: { fr: 'Fintech', en: 'Fintech', ar: '\u0627\u0644\u062a\u0643\u0646\u0648\u0644\u0648\u062c\u064a\u0627 \u0627\u0644\u0645\u0627\u0644\u064a\u0629' },
  };

  var domainCategoryLabels = {
    official_bulletin: { fr: 'Bulletins officiels', en: 'Official bulletins', ar: '\u0627\u0644\u0646\u0634\u0631\u0627\u062a \u0627\u0644\u0631\u0633\u0645\u064a\u0629' },
    commercial_company: { fr: 'Droit commercial', en: 'Commercial law', ar: '\u0627\u0644\u0642\u0627\u0646\u0648\u0646 \u0627\u0644\u062a\u062c\u0627\u0631\u064a' },
    civil_obligations_contracts: { fr: 'Droit civil', en: 'Civil law', ar: '\u0627\u0644\u0642\u0627\u0646\u0648\u0646 \u0627\u0644\u0645\u062f\u0646\u064a' },
    civil_procedure: { fr: 'Procedure civile', en: 'Civil procedure', ar: '\u0627\u0644\u0645\u0633\u0637\u0631\u0629 \u0627\u0644\u0645\u062f\u0646\u064a\u0629' },
    administrative_urbanism: { fr: 'Droit administratif et urbanisme', en: 'Administrative law and urbanism', ar: '\u0627\u0644\u0642\u0627\u0646\u0648\u0646 \u0627\u0644\u0625\u062f\u0627\u0631\u064a \u0648\u0627\u0644\u062a\u0639\u0645\u064a\u0631' },
    labor: { fr: 'Travail', en: 'Labor', ar: '\u0627\u0644\u0634\u063a\u0644' },
    health_medical: { fr: 'Sante', en: 'Health', ar: '\u0627\u0644\u0635\u062d\u0629' },
    criminal: { fr: 'Droit penal', en: 'Criminal law', ar: '\u0627\u0644\u0642\u0627\u0646\u0648\u0646 \u0627\u0644\u062c\u0646\u0627\u0626\u064a' },
    real_estate_rent: { fr: 'Immobilier', en: 'Real estate', ar: '\u0627\u0644\u0639\u0642\u0627\u0631' },
    tax: { fr: 'Fiscalite', en: 'Taxation', ar: '\u0627\u0644\u0636\u0631\u0627\u0626\u0628' },
    banking_finance: { fr: 'Banque et finance', en: 'Banking and finance', ar: '\u0627\u0644\u0628\u0646\u0648\u0643 \u0648\u0627\u0644\u0645\u0627\u0644\u064a\u0629' },
    environment_water_energy: { fr: 'Environnement, eau et energie', en: 'Environment, water and energy', ar: '\u0627\u0644\u0628\u064a\u0626\u0629 \u0648\u0627\u0644\u0645\u0627\u0621 \u0648\u0627\u0644\u0637\u0627\u0642\u0629' },
    family_marriage_divorce: { fr: 'Famille', en: 'Family law', ar: '\u0645\u062f\u0648\u0646\u0629 \u0627\u0644\u0623\u0633\u0631\u0629' },
    digital_data_ip_media: { fr: 'Numerique, donnees et medias', en: 'Digital, data and media', ar: '\u0627\u0644\u0631\u0642\u0645\u0646\u0629 \u0648\u0627\u0644\u0645\u0639\u0637\u064a\u0627\u062a \u0648\u0627\u0644\u0625\u0639\u0644\u0627\u0645' },
    insurance: { fr: 'Assurances', en: 'Insurance', ar: '\u0627\u0644\u062a\u0623\u0645\u064a\u0646\u0627\u062a' },
    consumer_protection: { fr: 'Protection du consommateur', en: 'Consumer protection', ar: '\u062d\u0645\u0627\u064a\u0629 \u0627\u0644\u0645\u0633\u062a\u0647\u0644\u0643' },
    professional_regulation: { fr: 'Professions reglementees', en: 'Regulated professions', ar: '\u0627\u0644\u0645\u0647\u0646 \u0627\u0644\u0645\u0646\u0638\u0645\u0629' },
    prison_corrections: { fr: 'Etablissements penitentiaires', en: 'Prisons', ar: '\u0627\u0644\u0633\u062c\u0648\u0646' },
    succession_inheritance: { fr: 'Successions', en: 'Inheritance', ar: '\u0627\u0644\u0625\u0631\u062b' },
  };

  Object.assign(categoryLabels, categorySlugLabels, domainCategoryLabels);

  Object.values(Object.assign({}, categorySlugLabels, domainCategoryLabels)).forEach(function (labels) {
    [labels.fr, labels.en, labels.ar].filter(Boolean).forEach(function (alias) {
      categoryLabels[alias] = labels;
      categoryLabels[alias.toLowerCase()] = labels;
    });
  });

  var categorySearchQueries = {
    official_bulletin: { fr: 'Bulletin officiel', en: 'Official Bulletin Morocco', ar: '\u0627\u0644\u0646\u0634\u0631\u0629 \u0627\u0644\u0631\u0633\u0645\u064a\u0629' },
    commercial_company: { fr: 'Code de commerce', en: 'Code de commerce', ar: '\u0627\u0644\u0642\u0627\u0646\u0648\u0646 \u0627\u0644\u062a\u062c\u0627\u0631\u064a' },
    civil_obligations_contracts: { fr: 'Code des Obligations et des Contrats', en: 'Code des Obligations et des Contrats', ar: '\u0627\u0644\u0642\u0627\u0646\u0648\u0646 \u0627\u0644\u0645\u062f\u0646\u064a \u0627\u0644\u0639\u0642\u0648\u062f \u0627\u0644\u0627\u0644\u062a\u0632\u0627\u0645\u0627\u062a' },
    civil_procedure: { fr: 'Code de procedure civile', en: 'Code de procedure civile', ar: '\u0627\u0644\u0645\u0633\u0637\u0631\u0629 \u0627\u0644\u0645\u062f\u0646\u064a\u0629' },
    administrative_urbanism: { fr: 'Droit administratif urbanisme', en: 'administrative law urban planning Morocco', ar: '\u0627\u0644\u0642\u0627\u0646\u0648\u0646 \u0627\u0644\u0625\u062f\u0627\u0631\u064a \u0627\u0644\u062a\u0639\u0645\u064a\u0631' },
    labor: { fr: 'Code du travail', en: 'Code du travail', ar: '\u0642\u0627\u0646\u0648\u0646 \u0627\u0644\u0634\u063a\u0644' },
    health_medical: { fr: 'Sante medecine pharmacie', en: 'health medical law Morocco', ar: '\u0627\u0644\u0635\u062d\u0629 \u0627\u0644\u0637\u0628 \u0627\u0644\u0635\u064a\u062f\u0644\u0629' },
    criminal: { fr: 'Code penal', en: 'Code penal', ar: '\u0627\u0644\u0642\u0627\u0646\u0648\u0646 \u0627\u0644\u062c\u0646\u0627\u0626\u064a' },
    real_estate_rent: { fr: 'Immobilier bail loyer propriete', en: 'real estate rent property Morocco', ar: '\u0627\u0644\u0639\u0642\u0627\u0631 \u0627\u0644\u0643\u0631\u0627\u0621 \u0627\u0644\u0645\u0644\u0643\u064a\u0629' },
    tax: { fr: 'Fiscalite impot taxe', en: 'tax law Morocco', ar: '\u0627\u0644\u0636\u0631\u0627\u0626\u0628 \u0627\u0644\u062c\u0628\u0627\u064a\u0627\u062a' },
    banking_finance: { fr: 'Banque finance credit', en: 'banking finance Morocco', ar: '\u0627\u0644\u0628\u0646\u0648\u0643 \u0627\u0644\u0645\u0627\u0644\u064a\u0629 \u0627\u0644\u0627\u0626\u062a\u0645\u0627\u0646' },
    environment_water_energy: { fr: 'Environnement eau energie', en: 'environment water energy Morocco', ar: '\u0627\u0644\u0628\u064a\u0626\u0629 \u0627\u0644\u0645\u0627\u0621 \u0627\u0644\u0637\u0627\u0642\u0629' },
    family_marriage_divorce: { fr: 'Code de la famille', en: 'Code de la famille', ar: '\u0645\u062f\u0648\u0646\u0629 \u0627\u0644\u0623\u0633\u0631\u0629 \u0627\u0644\u0632\u0648\u0627\u062c \u0627\u0644\u0637\u0644\u0627\u0642' },
    digital_data_ip_media: { fr: 'Donnees personnelles transactions electroniques droit auteur presse', en: 'digital data media law Morocco', ar: '\u0627\u0644\u0645\u0639\u0637\u064a\u0627\u062a \u0627\u0644\u0634\u062e\u0635\u064a\u0629 \u0627\u0644\u0625\u0639\u0644\u0627\u0645 \u0627\u0644\u0645\u0639\u0627\u0645\u0644\u0627\u062a \u0627\u0644\u0625\u0644\u0643\u062a\u0631\u0648\u0646\u064a\u0629' },
    insurance: { fr: 'Assurances', en: 'insurance law Morocco', ar: '\u0627\u0644\u062a\u0623\u0645\u064a\u0646\u0627\u062a' },
    consumer_protection: { fr: 'Protection du consommateur', en: 'consumer protection Morocco', ar: '\u062d\u0645\u0627\u064a\u0629 \u0627\u0644\u0645\u0633\u062a\u0647\u0644\u0643' },
    professional_regulation: { fr: 'Professions reglementees avocat notaire adoul', en: 'regulated professions Morocco', ar: '\u0627\u0644\u0645\u0647\u0646 \u0627\u0644\u0645\u0646\u0638\u0645\u0629 \u0627\u0644\u0645\u062d\u0627\u0645\u0627\u0629 \u0627\u0644\u062a\u0648\u062b\u064a\u0642' },
    prison_corrections: { fr: 'Etablissements penitentiaires prison', en: 'prison law Morocco', ar: '\u0627\u0644\u0633\u062c\u0648\u0646 \u0627\u0644\u0645\u0624\u0633\u0633\u0627\u062a \u0627\u0644\u0633\u062c\u0646\u064a\u0629' },
    succession_inheritance: { fr: 'Succession heritage', en: 'inheritance succession Morocco', ar: '\u0627\u0644\u0625\u0631\u062b \u0627\u0644\u062a\u0631\u0643\u0629' },
  };

  var quickLabels = {
    immobilier: { fr: 'immobilier', en: 'Real estate', ar: '\u0627\u0644\u0639\u0642\u0627\u0631\u0627\u062a' },
    commerce: { fr: 'commerce', en: 'Commerce', ar: '\u0627\u0644\u062a\u062c\u0627\u0631\u0629' },
    travail: { fr: 'travail', en: 'Labor', ar: '\u0627\u0644\u0634\u063a\u0644' },
    famille: { fr: 'famille', en: 'Family', ar: '\u0627\u0644\u0623\u0633\u0631\u0629' },
    fiscalite: { fr: 'fiscalite', en: 'Tax', ar: '\u0627\u0644\u0636\u0631\u0627\u0626\u0628' },
    banque: { fr: 'banque', en: 'Banking', ar: '\u0627\u0644\u0628\u0646\u0648\u0643' },
    contrats: { fr: 'contrats', en: 'Contracts', ar: '\u0627\u0644\u0639\u0642\u0648\u062f' },
    propriete: { fr: 'propriete', en: 'Property', ar: '\u0627\u0644\u0645\u0644\u0643\u064a\u0629' },
  };

  Object.values(quickLabels).forEach(function (labels) {
    [labels.fr, labels.en, labels.ar].filter(Boolean).forEach(function (alias) {
      quickLabels[alias] = labels;
      quickLabels[alias.toLowerCase()] = labels;
    });
  });

  var ui = {
    fr: {
      language: 'Langue',
      coverage: 'Radar de couverture',
      library: 'Bibliotheque active',
      articles: 'articles',
      sources: 'SOURCES',
      areas: 'DOMAINES',
      footer: 'Connecte au corpus juridique marocain indexe',
      landing: 'Accueil',
      heroKicker: 'Corpus juridique marocain indexe',
      heroTitle: 'Sources disponibles, recherche instantanee.',
      heroSubcopy: 'Trouvez des articles indexes, verifiez les sources et traduisez les resultats depuis un seul espace.',
      searchPlaceholder: 'Rechercher articles, codes, sources, categories...',
      search: 'Rechercher',
      clearSearch: 'Effacer la recherche',
      indexedSources: 'Sources indexees',
      articleRanking: 'Classement des articles',
      tools: 'Outils EN / AR',
      index: 'INDEX',
      coverageSnapshot: 'Apercu de couverture',
      articleResults: 'Resultats des articles',
      ready: 'Pret quand vous l etes',
      noSearchYet: 'Aucune recherche',
      startKeyword: 'Commencez par un mot-cle',
      searchByTopic: 'Recherchez par sujet, code, reference, source ou domaine juridique.',
      noMatching: 'Aucun article correspondant',
      tryBroader: 'Essayez un terme juridique plus large ou une famille de lois.',
      searching: 'Recherche dans les articles indexes...',
      all: 'Tous',
      matches: 'correspondances',
      topMatches: 'meilleures correspondances',
      visible: function (count) { return count + ' visibles'; },
      top: function (count) { return 'Top ' + count; },
      resultCount: function (count) { return count + ' resultat' + (Number(count) === 1 ? '' : 's'); },
      resultsFor: function (query) { return 'Resultats pour "' + query + '"'; },
      suggestionTypes: { AREA: 'DOMAINE', DOCUMENT: 'DOCUMENT', ARTICLE: 'ARTICLE', SOURCE: 'SOURCE' },
      bestMatch: 'Meilleur resultat',
      verifiedSource: 'Source verifiee',
      source: 'Source',
      officialSource: 'Source juridique officielle',
      open: 'Ouvrir',
      english: 'Anglais',
      arabic: 'Arabe',
      hideEnglish: 'Masquer anglais',
      hideArabic: 'Masquer arabe',
    },
    en: {
      language: 'Language',
      coverage: 'Coverage radar',
      library: 'Live library',
      articles: 'articles',
      sources: 'SOURCES',
      areas: 'AREAS',
      footer: 'Connected to the indexed Moroccan legal corpus',
      landing: 'Landing',
      heroKicker: 'Indexed Moroccan legal corpus',
      heroTitle: 'Available sources, instantly searchable.',
      heroSubcopy: 'Find indexed articles, verify sources, and translate results from one calm workspace.',
      searchPlaceholder: 'Search articles, codes, sources, categories...',
      search: 'Search',
      clearSearch: 'Clear search',
      indexedSources: 'Indexed sources',
      articleRanking: 'Article ranking',
      tools: 'EN / AR tools',
      index: 'INDEX',
      coverageSnapshot: 'Coverage snapshot',
      articleResults: 'Article results',
      ready: 'Ready when you are',
      noSearchYet: 'No search yet',
      startKeyword: 'Start with a keyword',
      searchByTopic: 'Search by topic, code, reference, source, or legal area.',
      noMatching: 'No matching articles yet',
      tryBroader: 'Try a broader legal term or search by law family.',
      searching: 'Searching indexed articles...',
      all: 'All',
      matches: 'matches',
      topMatches: 'top matches',
      visible: function (count) { return count + ' visible'; },
      top: function (count) { return 'Top ' + count; },
      resultCount: function (count) { return count + ' result' + (Number(count) === 1 ? '' : 's'); },
      resultsFor: function (query) { return 'Results for "' + query + '"'; },
      suggestionTypes: { AREA: 'AREA', DOCUMENT: 'DOCUMENT', ARTICLE: 'ARTICLE', SOURCE: 'SOURCE' },
      bestMatch: 'Best match',
      verifiedSource: 'Verified source',
      source: 'Source',
      officialSource: 'Official legal source',
      open: 'Open',
      english: 'English',
      arabic: 'Arabic',
      hideEnglish: 'Hide English',
      hideArabic: 'Hide Arabic',
    },
    ar: {
      language: '\u0627\u0644\u0644\u063a\u0629',
      coverage: '\u062e\u0631\u064a\u0637\u0629 \u0627\u0644\u062a\u063a\u0637\u064a\u0629',
      library: '\u0627\u0644\u0645\u0643\u062a\u0628\u0629 \u0627\u0644\u0645\u0628\u0627\u0634\u0631\u0629',
      articles: '\u0645\u0627\u062f\u0629',
      sources: '\u0645\u0635\u0627\u062f\u0631',
      areas: '\u0645\u062c\u0627\u0644\u0627\u062a',
      footer: '\u0645\u062a\u0635\u0644 \u0628\u0627\u0644\u0640 corpus \u0627\u0644\u0642\u0627\u0646\u0648\u0646\u064a \u0627\u0644\u0645\u063a\u0631\u0628\u064a \u0627\u0644\u0645\u0641\u0647\u0631\u0633',
      landing: '\u0627\u0644\u0631\u0626\u064a\u0633\u064a\u0629',
      heroKicker: '\u0627\u0644\u0641\u0647\u0631\u0633 \u0627\u0644\u0642\u0627\u0646\u0648\u0646\u064a \u0627\u0644\u0645\u063a\u0631\u0628\u064a',
      heroTitle: '\u0645\u0635\u0627\u062f\u0631 \u0645\u062a\u0627\u062d\u0629 \u0648\u0642\u0627\u0628\u0644\u0629 \u0644\u0644\u0628\u062d\u062b \u0641\u0648\u0631\u0627.',
      heroSubcopy: '\u0627\u0628\u062d\u062b \u0641\u064a \u0627\u0644\u0645\u0648\u0627\u062f \u0627\u0644\u0645\u0641\u0647\u0631\u0633\u0629\u060c \u062a\u062d\u0642\u0642 \u0645\u0646 \u0627\u0644\u0645\u0635\u0627\u062f\u0631\u060c \u0648\u062a\u0631\u062c\u0645 \u0627\u0644\u0646\u062a\u0627\u0626\u062c \u0645\u0646 \u0645\u0633\u0627\u062d\u0629 \u0648\u0627\u062d\u062f\u0629.',
      searchPlaceholder: '\u0627\u0628\u062d\u062b \u0641\u064a \u0627\u0644\u0645\u0648\u0627\u062f\u060c \u0627\u0644\u0642\u0648\u0627\u0646\u064a\u0646\u060c \u0627\u0644\u0645\u0635\u0627\u062f\u0631\u060c \u0627\u0644\u0645\u062c\u0627\u0644\u0627\u062a...',
      search: '\u0628\u062d\u062b',
      clearSearch: '\u0645\u0633\u062d \u0627\u0644\u0628\u062d\u062b',
      indexedSources: '\u0645\u0635\u0627\u062f\u0631 \u0645\u0641\u0647\u0631\u0633\u0629',
      articleRanking: '\u062a\u0631\u062a\u064a\u0628 \u0627\u0644\u0645\u0648\u0627\u062f',
      tools: '\u0623\u062f\u0648\u0627\u062a EN / AR',
      index: '\u0627\u0644\u0641\u0647\u0631\u0633',
      coverageSnapshot: '\u0645\u0644\u062e\u0635 \u0627\u0644\u062a\u063a\u0637\u064a\u0629',
      articleResults: '\u0646\u062a\u0627\u0626\u062c \u0627\u0644\u0645\u0648\u0627\u062f',
      ready: '\u062c\u0627\u0647\u0632 \u0639\u0646\u062f\u0645\u0627 \u062a\u0643\u0648\u0646 \u062c\u0627\u0647\u0632\u0627',
      noSearchYet: '\u0644\u0627 \u064a\u0648\u062c\u062f \u0628\u062d\u062b \u0628\u0639\u062f',
      startKeyword: '\u0627\u0628\u062f\u0623 \u0628\u0643\u0644\u0645\u0629 \u0645\u0641\u062a\u0627\u062d\u064a\u0629',
      searchByTopic: '\u0627\u0628\u062d\u062b \u062d\u0633\u0628 \u0627\u0644\u0645\u0648\u0636\u0648\u0639 \u0623\u0648 \u0627\u0644\u0642\u0627\u0646\u0648\u0646 \u0623\u0648 \u0627\u0644\u0645\u0631\u062c\u0639 \u0623\u0648 \u0627\u0644\u0645\u0635\u062f\u0631 \u0623\u0648 \u0627\u0644\u0645\u062c\u0627\u0644 \u0627\u0644\u0642\u0627\u0646\u0648\u0646\u064a.',
      noMatching: '\u0644\u0627 \u062a\u0648\u062c\u062f \u0645\u0648\u0627\u062f \u0645\u0637\u0627\u0628\u0642\u0629 \u0628\u0639\u062f',
      tryBroader: '\u062c\u0631\u0651\u0628 \u0645\u0635\u0637\u0644\u062d\u0627 \u0642\u0627\u0646\u0648\u0646\u064a\u0627 \u0623\u0648\u0633\u0639 \u0623\u0648 \u0627\u0628\u062d\u062b \u062d\u0633\u0628 \u0639\u0627\u0626\u0644\u0629 \u0627\u0644\u0642\u0627\u0646\u0648\u0646.',
      searching: '\u062c\u0627\u0631\u064a \u0627\u0644\u0628\u062d\u062b \u0641\u064a \u0627\u0644\u0645\u0648\u0627\u062f \u0627\u0644\u0645\u0641\u0647\u0631\u0633\u0629...',
      all: '\u0627\u0644\u0643\u0644',
      matches: '\u0645\u0637\u0627\u0628\u0642\u0627\u062a',
      topMatches: '\u0623\u0641\u0636\u0644 \u0627\u0644\u0646\u062a\u0627\u0626\u062c',
      visible: function (count) { return count + ' \u0638\u0627\u0647\u0631\u0629'; },
      top: function (count) { return '\u0623\u0641\u0636\u0644 ' + count; },
      resultCount: function (count) { return count + ' \u0646\u062a\u064a\u062c\u0629'; },
      resultsFor: function (query) { return '\u0646\u062a\u0627\u0626\u062c \u0627\u0644\u0628\u062d\u062b \u0639\u0646 "' + query + '"'; },
      suggestionTypes: { AREA: '\u0645\u062c\u0627\u0644', DOCUMENT: '\u0648\u062b\u064a\u0642\u0629', ARTICLE: '\u0645\u0627\u062f\u0629', SOURCE: '\u0645\u0635\u062f\u0631' },
      bestMatch: '\u0623\u0641\u0636\u0644 \u0646\u062a\u064a\u062c\u0629',
      verifiedSource: '\u0645\u0635\u062f\u0631 \u0645\u0648\u062b\u0642',
      source: '\u0627\u0644\u0645\u0635\u062f\u0631',
      officialSource: '\u0645\u0635\u062f\u0631 \u0642\u0627\u0646\u0648\u0646\u064a \u0631\u0633\u0645\u064a',
      open: '\u0641\u062a\u062d',
      english: '\u0627\u0644\u0625\u0646\u062c\u0644\u064a\u0632\u064a\u0629',
      arabic: '\u0627\u0644\u0639\u0631\u0628\u064a\u0629',
      hideEnglish: '\u0625\u062e\u0641\u0627\u0621 \u0627\u0644\u0625\u0646\u062c\u0644\u064a\u0632\u064a\u0629',
      hideArabic: '\u0625\u062e\u0641\u0627\u0621 \u0627\u0644\u0639\u0631\u0628\u064a\u0629',
    },
  };

  var currentLanguage = function () {
    var value = localStorage.getItem(storageKey) || 'fr';
    return supportedLanguages.indexOf(value) !== -1 ? value : 'fr';
  };

  var normalizeLookupKey = function (value) {
    return (value || '').toString().trim().toLowerCase().replace(/[\s-]+/g, '_');
  };

  var lookupEntry = function (labels, original) {
    var value = (original || '').toString().trim();
    var normalized = normalizeLookupKey(value);

    return labels[value]
      || labels[value.toLowerCase()]
      || labels[normalized]
      || labels[normalized.replace(/_/g, '-')]
      || null;
  };

  var translated = function (labels, original, language) {
    if (!original) {
      return original;
    }

    var direct = lookupEntry(labels, original);

    if (direct) {
      return direct[language] || direct.fr || original;
    }

    return original;
  };

  var searchQueryFor = function (value, language) {
    var direct = lookupEntry(categorySearchQueries, value);

    if (!direct) {
      return value;
    }

    return direct[language] || direct.fr || value;
  };

  var setText = function (element, value) {
    if (element && element.textContent.trim() !== value) {
      element.textContent = value;
    }
  };

  var setTrailingText = function (element, value) {
    if (!element) {
      return;
    }

    var textNode = Array.from(element.childNodes).find(function (node) { return node.nodeType === Node.TEXT_NODE; });

    if (textNode) {
      if (textNode.textContent.trim() !== value) {
        textNode.textContent = value;
      }
      return;
    }

    element.append(document.createTextNode(value));
  };

  var setInputDisplayValue = function (input, value) {
    if (!input || input.value === value) {
      return;
    }

    var nativeSetter = Object.getOwnPropertyDescriptor(window.HTMLInputElement.prototype, 'value');

    if (nativeSetter && nativeSetter.set) {
      nativeSetter.set.call(input, value);
    } else {
      input.value = value;
    }
  };

  var displayLabelFor = function (value, language) {
    var original = (value || '').trim();

    if (!original) {
      return original;
    }

    return translated(Object.assign({}, categoryLabels, quickLabels), original, language);
  };

  var setElementOriginal = function (element, value) {
    if (element && value && !element.dataset.originalLabel) {
      element.dataset.originalLabel = value;
    }
  };

  var ensureSelector = function () {
    var target = document.querySelector('.topbar-meta');

    if (!target || target.querySelector('.interface-language-control')) {
      return;
    }

    var wrapper = document.createElement('label');
    wrapper.className = 'interface-language-control';

    var label = document.createElement('span');
    label.dataset.interfaceLanguageLabel = '1';

    var select = document.createElement('select');
    select.setAttribute('aria-label', 'Workspace language');
    [['fr', 'FR'], ['en', 'EN'], ['ar', 'AR']].forEach(function (pair) {
      var option = document.createElement('option');
      option.value = pair[0];
      option.textContent = pair[1];
      select.append(option);
    });

    select.value = currentLanguage();
    select.addEventListener('change', function () {
      localStorage.setItem(storageKey, select.value);
      applyTranslations();
    });

    wrapper.append(label, select);
    target.insertBefore(wrapper, target.firstChild);
  };

  var rememberOriginal = function (element) {
    if (!element.dataset.originalLabel) {
      element.dataset.originalLabel = element.textContent.trim();
    }

    return element.dataset.originalLabel;
  };

  var updateSuggestionRows = function (language, copy) {
    document.querySelectorAll('.suggestion-row').forEach(function (row) {
      var label = row.querySelector('span');
      var type = row.querySelector('strong');

      if (label) {
        var original = label.dataset.originalLabel || label.textContent.trim();
        setElementOriginal(label, original);
        label.textContent = displayLabelFor(original, language);
      }

      if (type) {
        var originalType = type.dataset.originalType || type.textContent.trim();
        type.dataset.originalType = originalType;
        setText(type, copy.suggestionTypes[originalType] || originalType);
      }
    });
  };

  var updateLocalizedSearchDisplay = function (language, copy) {
    var input = document.querySelector('.search-input-row input');

    if (!input) {
      return;
    }

    input.placeholder = copy.searchPlaceholder;

    var rawValue = input.value.trim();
    var localizedValue = displayLabelFor(rawValue, language);

    if (localizedValue && localizedValue !== rawValue) {
      setInputDisplayValue(input, localizedValue);
    }

    var h2 = document.querySelector('.results-head h2');

    if (h2) {
      var heading = h2.textContent.trim();
      var match = heading.match(/"(.*?)"/);
      var query = displayLabelFor(input.value.trim() || (match ? match[1] : '') || '', language);

      if (heading.indexOf('Ready when you are') !== -1 || heading.indexOf('Pret quand') !== -1 || heading.indexOf('\u062c\u0627\u0647\u0632') !== -1) {
        setText(h2, copy.ready);
      } else if (heading.indexOf('Results for') !== -1 || heading.indexOf('Resultats pour') !== -1 || heading.indexOf('\u0646\u062a\u0627\u0626\u062c \u0627\u0644\u0628\u062d\u062b') !== -1) {
        setText(h2, copy.resultsFor(query));
      }
    }
  };

  var updateResultCount = function (copy) {
    var count = document.querySelector('.result-count');

    if (!count) {
      return;
    }

    var text = count.textContent.trim();
    var number = (text.match(/\d+/)) ? text.match(/\d+/)[0] : '';

    if (text.indexOf('No search yet') !== -1 || text.indexOf('Aucune recherche') !== -1 || text.indexOf('\u0644\u0627 \u064a\u0648\u062c\u062f \u0628\u062d\u062b') !== -1) {
      setText(count, copy.noSearchYet);
    } else if (text.indexOf('visible') !== -1 || text.indexOf('visibles') !== -1 || text.indexOf('\u0638\u0627\u0647\u0631\u0629') !== -1) {
      setText(count, copy.visible(number));
    } else if (text.indexOf('Top') !== -1 || text.indexOf('\u0623\u0641\u0636\u0644') !== -1) {
      setText(count, copy.top(number));
    } else if (number && (text.indexOf('result') !== -1 || text.indexOf('\u0646\u062a\u064a\u062c\u0629') !== -1)) {
      setText(count, copy.resultCount(number));
    }
  };

  var updateResultCards = function (language, copy) {
    document.querySelectorAll('.category-chip').forEach(function (chip) {
      var original = rememberOriginal(chip);
      setText(chip, displayLabelFor(original, language));
    });

    document.querySelectorAll('.score-pill').forEach(function (pill) {
      var original = rememberOriginal(pill);
      if (original === 'Best match') setText(pill, copy.bestMatch);
      if (original === 'Verified source') setText(pill, copy.verifiedSource);
    });

    document.querySelectorAll('.source-box span').forEach(function (span) {
      var original = rememberOriginal(span);
      if (original === 'Source') setText(span, copy.source);
    });

    document.querySelectorAll('.source-box strong').forEach(function (strong) {
      var original = rememberOriginal(strong);
      if (original === 'Official legal source') setText(strong, copy.officialSource);
    });

    document.querySelectorAll('.source-box a').forEach(function (link) {
      link.textContent = copy.open;
    });

    document.querySelectorAll('.action-row button').forEach(function (button) {
      var original = rememberOriginal(button);
      if (original.indexOf('Hide English') !== -1) button.textContent = copy.hideEnglish;
      else if (original.indexOf('Hide Arabic') !== -1) button.textContent = copy.hideArabic;
      else if (original.indexOf('English') !== -1) button.textContent = copy.english;
      else if (original.indexOf('Arabic') !== -1) button.textContent = copy.arabic;
    });
  };

  var updateStaticInterface = function (language, copy) {
    setText(document.querySelector('.topbar-link'), copy.landing);
    document.querySelector('.hero-kicker').textContent = copy.heroKicker;
    setText(document.querySelector('.search-hero h1'), copy.heroTitle);
    setText(document.querySelector('.hero-subcopy'), copy.heroSubcopy);
    document.querySelector('.search-button').textContent = copy.search;

    var clearButton = document.querySelector('.search-input-row .icon-button');
    if (clearButton) {
      clearButton.setAttribute('aria-label', copy.clearSearch);
    }

    var signals = document.querySelectorAll('.signal-row span');
    if (signals[0]) signals[0].textContent = copy.indexedSources;
    if (signals[1]) signals[1].textContent = copy.articleRanking;
    if (signals[2]) signals[2].textContent = copy.tools;

    setText(document.querySelector('.library-heading span'), copy.index);
    setText(document.querySelector('.library-heading strong'), copy.coverageSnapshot);

    var metricLabels = document.querySelectorAll('.stat-grid .metric-card span:last-child');
    if (metricLabels[0]) setText(metricLabels[0], copy.articles);
    if (metricLabels[1]) setText(metricLabels[1], copy.sources);
    if (metricLabels[2]) setText(metricLabels[2], copy.areas);

    setText(document.querySelector('.results-head .section-label'), copy.articleResults);
    updateResultCount(copy);

    var resultToolsSummary = document.querySelector('.result-tools > div:first-child span');
    if (resultToolsSummary) {
      var original = rememberOriginal(resultToolsSummary);
      if (original === 'top matches') setText(resultToolsSummary, copy.topMatches);
      if (original === 'matches') setText(resultToolsSummary, copy.matches);
    }

    var allFilter = document.querySelector('.result-filter-strip button:first-child');
    if (allFilter) {
      setText(allFilter, copy.all);
    }

    var emptyTitle = document.querySelector('.empty-panel h3');
    var emptyText = document.querySelector('.empty-panel p');
    var hasSearchValue = !!document.querySelector('.search-input-row input');

    if (emptyTitle) {
      setText(emptyTitle, hasSearchValue ? copy.noMatching : copy.startKeyword);
    }

    if (emptyText) {
      setText(emptyText, hasSearchValue ? copy.tryBroader : copy.searchByTopic);
    }

    document.querySelectorAll('.state-panel:not(.is-error)').forEach(function (panel) {
      if (panel.textContent.indexOf('Searching indexed articles') !== -1 || panel.textContent.indexOf('Recherche') !== -1 || panel.textContent.indexOf('\u062c\u0627\u0631\u064a \u0627\u0644\u0628\u062d\u062b') !== -1) {
        panel.textContent = copy.searching;
      }
    });

    updateSuggestionRows(language, copy);
    updateLocalizedSearchDisplay(language, copy);
    updateResultCards(language, copy);
  };

  var submitWorkspaceSearch = function (query) {
    var input = document.querySelector('.search-input-row input');
    var form = document.querySelector('.search-console');

    if (!input || !form || !query) {
      return false;
    }

    setInputDisplayValue(input, query);
    input.dispatchEvent(new Event('input', { bubbles: true }));

    window.setTimeout(function () {
      if (typeof form.requestSubmit === 'function') {
        form.requestSubmit();
        return;
      }

      form.dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }));
    }, 0);

    return true;
  };

  document.addEventListener('click', function (event) {
    var coverageItem = event.target.closest('.coverage-item');

    if (coverageItem) {
      var label = coverageItem.querySelector('span');
      var original = (label && label.dataset.originalLabel) || (label && label.textContent.trim()) || '';
      var query = searchQueryFor(original, currentLanguage());

      if (query && query !== original && submitWorkspaceSearch(query)) {
        event.preventDefault();
        event.stopPropagation();
        if (event.stopImmediatePropagation) event.stopImmediatePropagation();
        window.setTimeout(scheduleApply, 120);
        window.setTimeout(scheduleApply, 700);
        return;
      }
    }

    var clickable = event.target.closest('.coverage-item, .quick-strip button, .suggestion-row');

    if (!clickable) {
      return;
    }

    window.setTimeout(scheduleApply, 0);
    window.setTimeout(scheduleApply, 180);
    window.setTimeout(scheduleApply, 700);
  }, true);

  var applying = false;

  var applyTranslations = function () {
    if (applying) {
      return;
    }

    applying = true;

    try {
      var language = currentLanguage();
      var copy = ui[language];

      document.documentElement.lang = language;
      document.documentElement.dir = language === 'ar' ? 'rtl' : 'ltr';

      ensureSelector();

      var selector = document.querySelector('.interface-language-control select');
      if (selector && selector.value !== language) {
        selector.value = language;
      }

      var liveLibrary = Array.from(document.querySelectorAll('.topbar-meta > span'))
        .find(function (element) { return element.querySelector('.live-dot'); });
      var articleCount = document.querySelector('.topbar-meta strong');
      var articleNumber = (articleCount && articleCount.textContent.match(/[\d,]+/)) ? articleCount.textContent.match(/[\d,]+/)[0] : '';

      setText(document.querySelector('[data-interface-language-label]'), copy.language);
      setText(document.querySelector('.category-radar .panel-title'), copy.coverage);
      if (liveLibrary) liveLibrary.textContent = copy.library;
      setText(articleCount, (articleNumber + ' ' + copy.articles).trim());
      setText(document.querySelector('.library-footer span'), copy.footer);

      document.querySelectorAll('.metric-card span:last-child').forEach(function (element) {
        var original = rememberOriginal(element);
        if (original === 'SOURCES') setText(element, copy.sources);
        if (original === 'AREAS') setText(element, copy.areas);
      });

      document.querySelectorAll('.coverage-item span').forEach(function (element) {
        var original = rememberOriginal(element);
        setText(element, translated(categoryLabels, original, language));
      });

      document.querySelectorAll('.quick-strip button').forEach(function (button) {
        var original = rememberOriginal(button).toLowerCase();
        setText(button, translated(quickLabels, original, language));
      });

      document.querySelectorAll('.result-filter-strip button').forEach(function (button) {
        var label = (button.childNodes[0] && button.childNodes[0].textContent) ? button.childNodes[0].textContent.trim() : '';
        var original = button.dataset.originalLabel || label;
        button.dataset.originalLabel = original;

        var translatedLabel = translated(categoryLabels, original, language);
        if (button.childNodes[0] && button.childNodes[0].nodeType === Node.TEXT_NODE && button.childNodes[0].textContent.trim() !== translatedLabel) {
          button.childNodes[0].textContent = translatedLabel;
        }
      });

      updateStaticInterface(language, copy);
    } finally {
      applying = false;
    }
  };

  var scheduled = false;
  var scheduleApply = function () {
    if (scheduled || applying) {
      return;
    }

    scheduled = true;
    window.requestAnimationFrame(function () {
      scheduled = false;
      applyTranslations();
    });
  };

  applyTranslations();
  window.addEventListener('load', scheduleApply);
  new MutationObserver(scheduleApply).observe(document.getElementById('root'), {
    childList: true,
    subtree: true,
  });
})();
