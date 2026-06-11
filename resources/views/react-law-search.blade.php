<!doctype html>
<html lang="en">
  <head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Marokko Biz | Moroccan Law Search</title>
    <link rel="icon" href="/marokko-biz-icon.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
      href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&family=Fraunces:opsz,wght@9..144,600;9..144,700&display=swap"
      rel="stylesheet"
    >
    <script>
      (() => {
        const originalFetch = window.fetch.bind(window);

        window.fetch = (input, init = {}) => {
          const url = typeof input === 'string' ? input : input?.url || '';
          const method = String(init.method || input?.method || 'GET').toUpperCase();

          if (url.startsWith('/api/') && !['GET', 'HEAD', 'OPTIONS'].includes(method)) {
            const headers = new Headers(init.headers || input?.headers || {});
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;

            if (csrfToken) {
              headers.set('X-CSRF-TOKEN', csrfToken);
            }

            headers.set('X-Requested-With', 'XMLHttpRequest');
            init = { ...init, headers };
          }

          return originalFetch(input, init);
        };
      })();
    </script>
    @vite(['resources/css/search-workspace.css', 'resources/css/search-remodel.css', 'resources/js/search-workspace.js'])
    <style>
      .interface-language-control {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        min-height: 38px;
        padding: 5px 7px 5px 10px;
        border: 1px solid rgba(105, 145, 205, 0.34);
        border-radius: 999px;
        color: #0757d8;
        background: rgba(231, 241, 255, 0.82);
        font-size: 0.78rem;
        font-weight: 900;
      }

      .interface-language-control select {
        min-height: 28px;
        border: 0;
        border-radius: 999px;
        color: #0757d8;
        background: #fff;
        font: inherit;
        font-size: 0.76rem;
        font-weight: 900;
        outline: 0;
      }

      html[dir="rtl"] .app-shell {
        direction: rtl;
      }

      html[dir="rtl"] .search-input-row,
      html[dir="rtl"] .support-chat-input,
      html[dir="rtl"] .chat-input-row {
        direction: rtl;
      }

      html[dir="rtl"] .search-input-row input,
      html[dir="rtl"] .support-chat-input textarea {
        text-align: right;
      }

      html[dir="rtl"] .suggestion-row,
      html[dir="rtl"] .results-head,
      html[dir="rtl"] .result-tools,
      html[dir="rtl"] .result-card,
      html[dir="rtl"] .empty-panel,
      html[dir="rtl"] .state-panel {
        direction: rtl;
      }

      .result-card.is-jump-target {
        border-color: #0757d8;
        box-shadow: 0 0 0 4px rgba(7, 87, 216, 0.16), 0 24px 64px rgba(7, 87, 216, 0.16);
      }

      .suggestions-panel {
        max-height: min(360px, 46vh);
        overflow-y: auto;
        overscroll-behavior: contain;
      }

      .suggestions-panel::-webkit-scrollbar {
        width: 8px;
      }

      .suggestions-panel::-webkit-scrollbar-thumb {
        background: rgba(7, 87, 216, 0.32);
        border-radius: 999px;
      }

    </style>
  </head>
  <body>
    <div id="root"></div>

    <script>
      (() => {
        document.addEventListener('click', (event) => {
          const landingButton = event.target.closest('.topbar-link');

          if (landingButton?.textContent.trim() === 'Landing') {
            event.preventDefault();
            event.stopImmediatePropagation();
            window.location.assign('/');
          }
        }, true);
      })();
    </script>

    <script>
      (() => {
        const pendingKey = 'marokko.pendingArticleJump';

        const normalize = (value) => (value || '')
          .toString()
          .toLowerCase()
          .normalize('NFD')
          .replace(/[\u0300-\u036f]/g, '')
          .replace(/[^\p{L}\p{N}]+/gu, ' ')
          .trim();

        const parseArticleSuggestion = (text) => {
          const value = (text || '').toString().trim();
          const match = value.match(/^(.*?)\s+-\s+Article\s+(.+)$/i);

          if (!match) {
            return null;
          }

          return {
            document: normalize(match[1]),
            article: normalize(`Article ${match[2]}`),
            raw: value,
          };
        };

        const rememberArticleJump = (row) => {
          const label = row.querySelector('span');
          const type = row.querySelector('strong')?.textContent.trim().toUpperCase();
          const text = label?.dataset.originalLabel || label?.textContent.trim() || '';

          if (type !== 'ARTICLE') {
            return;
          }

          const parsed = parseArticleSuggestion(text);

          if (!parsed) {
            return;
          }

          sessionStorage.setItem(pendingKey, JSON.stringify({
            ...parsed,
            createdAt: Date.now(),
          }));
        };

        const findJumpTarget = (pending) => {
          const cards = [...document.querySelectorAll('.result-card')];

          return cards.find((card) => {
            const title = normalize(card.querySelector('h3')?.textContent || '');
            const article = normalize(card.querySelector('.article-number')?.textContent || '');
            const source = normalize(card.querySelector('.source-box strong')?.textContent || '');

            return article === pending.article
              && (title.includes(pending.document) || source.includes(pending.document) || pending.document.includes(title));
          }) || cards.find((card) => {
            const title = normalize(card.querySelector('h3')?.textContent || '');
            return normalize(pending.raw) === title || title.includes(normalize(pending.raw));
          });
        };

        const tryJumpToPendingArticle = () => {
          const raw = sessionStorage.getItem(pendingKey);

          if (!raw) {
            return;
          }

          let pending;

          try {
            pending = JSON.parse(raw);
          } catch {
            sessionStorage.removeItem(pendingKey);
            return;
          }

          if (!pending?.createdAt || Date.now() - pending.createdAt > 15000) {
            sessionStorage.removeItem(pendingKey);
            return;
          }

          const target = findJumpTarget(pending);

          if (!target) {
            return;
          }

          sessionStorage.removeItem(pendingKey);
          target.scrollIntoView({ behavior: 'smooth', block: 'center' });
          target.classList.add('is-jump-target');
          window.setTimeout(() => target.classList.remove('is-jump-target'), 2400);
        };

        document.addEventListener('click', (event) => {
          const suggestion = event.target.closest('.suggestion-row');

          if (suggestion) {
            rememberArticleJump(suggestion);
          }
        }, true);

        window.addEventListener('load', tryJumpToPendingArticle);
        new MutationObserver(tryJumpToPendingArticle).observe(document.getElementById('root'), {
          childList: true,
          subtree: true,
        });
      })();
    </script>

    <script>
      (() => {
        const shouldOpenWorkspace = window.location.pathname.replace(/\/+$/, '') === '/app';

        if (!shouldOpenWorkspace) {
          return;
        }

        const openWorkspace = () => {
          const button = document.querySelector(
            '.landing-nav-actions .primary-action, .landing-actions .primary-action, .preview-search button'
          );

          if (button) {
            button.click();
            return true;
          }

          return false;
        };

        let attempts = 0;
        const interval = window.setInterval(() => {
          attempts += 1;

          if (openWorkspace() || attempts > 40) {
            window.clearInterval(interval);
          }
        }, 125);

        window.addEventListener('load', openWorkspace);
        new MutationObserver(openWorkspace).observe(document.getElementById('root'), {
          childList: true,
          subtree: true,
        });
      })();
    </script>

    <script>
      (() => {
        const storageKey = 'marokko.workspaceLanguage';
        const supportedLanguages = ['fr', 'en', 'ar'];

        const categoryLabels = {
          'Droit commercial': { en: 'Commercial law', ar: 'القانون التجاري' },
          'Droit civil': { en: 'Civil law', ar: 'القانون المدني' },
          'Procedure civile': { en: 'Civil procedure', ar: 'المسطرة المدنية' },
          'Droit penal': { en: 'Criminal law', ar: 'القانون الجنائي' },
          Immobilier: { en: 'Real estate', ar: 'العقارات' },
          'Collectivites territoriales': { en: 'Territorial governance', ar: 'الجماعات الترابية' },
          Travail: { en: 'Labor', ar: 'الشغل' },
          'Professions reglementees': { en: 'Regulated professions', ar: 'المهن المنظمة' },
          Sante: { en: 'Health', ar: 'الصحة' },
          Famille: { en: 'Family', ar: 'الأسرة' },
          Banque: { en: 'Banking', ar: 'البنوك' },
          'Marches financiers': { en: 'Financial markets', ar: 'الأسواق المالية' },
          Environnement: { en: 'Environment', ar: 'البيئة' },
          Consommation: { en: 'Consumer law', ar: 'الاستهلاك' },
          'Professions judiciaires': { en: 'Judicial professions', ar: 'المهن القضائية' },
          'Transactions electroniques': { en: 'Electronic transactions', ar: 'المعاملات الإلكترونية' },
          'Finances publiques': { en: 'Public finance', ar: 'المالية العمومية' },
          Assurances: { en: 'Insurance', ar: 'التأمينات' },
          'Surete nucleaire': { en: 'Nuclear safety', ar: 'السلامة النووية' },
          'Fiscalite locale': { en: 'Local taxation', ar: 'الجبايات المحلية' },
          'Etablissements penitentiaires': { en: 'Prison institutions', ar: 'المؤسسات السجنية' },
          'Gouvernance administrative': { en: 'Administrative governance', ar: 'الحكامة الإدارية' },
          Journalism: { en: 'Journalism', ar: 'الصحافة' },
          'Organisation judiciaire': { en: 'Judicial organization', ar: 'التنظيم القضائي' },
          'Entreprises publiques': { en: 'Public enterprises', ar: 'المؤسسات العمومية' },
          Culture: { en: 'Culture', ar: 'الثقافة' },
          Aviation: { en: 'Aviation', ar: 'الطيران' },
          Education: { en: 'Education', ar: 'التعليم' },
          'Commande publique': { en: 'Public procurement', ar: 'الصفقات العمومية' },
          'Commerce exterieur': { en: 'Foreign trade', ar: 'التجارة الخارجية' },
          'Securite industrielle': { en: 'Industrial safety', ar: 'السلامة الصناعية' },
          Fiscalite: { en: 'Taxation', ar: 'الضرائب' },
          Tourisme: { en: 'Tourism', ar: 'السياحة' },
          Investissement: { en: 'Investment', ar: 'الاستثمار' },
          Securite: { en: 'Security', ar: 'الأمن' },
        };

        const categorySlugLabels = {
          commercial: { fr: 'Droit commercial', en: 'Commercial law', ar: 'القانون التجاري' },
          civil: { fr: 'Droit civil', en: 'Civil law', ar: 'القانون المدني' },
          'civil-procedure': { fr: 'Procedure civile', en: 'Civil procedure', ar: 'المسطرة المدنية' },
          criminal: { fr: 'Droit penal', en: 'Criminal law', ar: 'القانون الجنائي' },
          'real-estate': { fr: 'Immobilier', en: 'Real estate', ar: 'العقارات' },
          'territorial-governance': { fr: 'Collectivites territoriales', en: 'Territorial governance', ar: 'الجماعات الترابية' },
          labor: { fr: 'Travail', en: 'Labor', ar: 'الشغل' },
          'regulated-professions': { fr: 'Professions reglementees', en: 'Regulated professions', ar: 'المهن المنظمة' },
          health: { fr: 'Sante', en: 'Health', ar: 'الصحة' },
          family: { fr: 'Famille', en: 'Family', ar: 'الأسرة' },
          banking: { fr: 'Banque', en: 'Banking', ar: 'البنوك' },
          'financial-market': { fr: 'Marches financiers', en: 'Financial markets', ar: 'الأسواق المالية' },
          environment: { fr: 'Environnement', en: 'Environment', ar: 'البيئة' },
          consumer: { fr: 'Consommation', en: 'Consumer law', ar: 'الاستهلاك' },
          'judicial-professions': { fr: 'Professions judiciaires', en: 'Judicial professions', ar: 'المهن القضائية' },
          'electronic-transactions': { fr: 'Transactions electroniques', en: 'Electronic transactions', ar: 'المعاملات الإلكترونية' },
          'public-finance': { fr: 'Finances publiques', en: 'Public finance', ar: 'المالية العمومية' },
          insurance: { fr: 'Assurances', en: 'Insurance', ar: 'التأمينات' },
          'nuclear-safety': { fr: 'Surete nucleaire', en: 'Nuclear safety', ar: 'السلامة النووية' },
          'local-taxation': { fr: 'Fiscalite locale', en: 'Local taxation', ar: 'الجبايات المحلية' },
          prisons: { fr: 'Etablissements penitentiaires', en: 'Prison institutions', ar: 'المؤسسات السجنية' },
          'administrative-governance': { fr: 'Gouvernance administrative', en: 'Administrative governance', ar: 'الحكامة الإدارية' },
          journalism: { fr: 'Journalisme', en: 'Journalism', ar: 'الصحافة' },
          'judicial-organization': { fr: 'Organisation judiciaire', en: 'Judicial organization', ar: 'التنظيم القضائي' },
          'public-enterprises': { fr: 'Entreprises publiques', en: 'Public enterprises', ar: 'المؤسسات العمومية' },
          culture: { fr: 'Culture', en: 'Culture', ar: 'الثقافة' },
          aviation: { fr: 'Aviation', en: 'Aviation', ar: 'الطيران' },
          education: { fr: 'Education', en: 'Education', ar: 'التعليم' },
          'public-procurement': { fr: 'Commande publique', en: 'Public procurement', ar: 'الصفقات العمومية' },
          'foreign-trade': { fr: 'Commerce exterieur', en: 'Foreign trade', ar: 'التجارة الخارجية' },
          'industrial-safety': { fr: 'Securite industrielle', en: 'Industrial safety', ar: 'السلامة الصناعية' },
          tax: { fr: 'Fiscalite', en: 'Taxation', ar: 'الضرائب' },
          tourism: { fr: 'Tourisme', en: 'Tourism', ar: 'السياحة' },
          investment: { fr: 'Investissement', en: 'Investment', ar: 'الاستثمار' },
          security: { fr: 'Securite', en: 'Security', ar: 'الأمن' },
          'market-regulation': { fr: 'Regulation des marches', en: 'Market regulation', ar: 'تنظيم الأسواق' },
          aquaculture: { fr: 'Aquaculture', en: 'Aquaculture', ar: 'تربية الأحياء المائية' },
          energy: { fr: 'Energie', en: 'Energy', ar: 'الطاقة' },
          microfinance: { fr: 'Microfinance', en: 'Microfinance', ar: 'التمويل الأصغر' },
          'disability-rights': { fr: 'Droits des personnes handicapees', en: 'Disability rights', ar: 'حقوق الأشخاص في وضعية إعاقة' },
          agriculture: { fr: 'Agriculture', en: 'Agriculture', ar: 'الفلاحة' },
          veterinary: { fr: 'Veterinaire', en: 'Veterinary', ar: 'البيطرة' },
          'social-protection': { fr: 'Protection sociale', en: 'Social protection', ar: 'الحماية الاجتماعية' },
          fisheries: { fr: 'Peche maritime', en: 'Fisheries', ar: 'الصيد البحري' },
          'sports-events': { fr: 'Evenements sportifs', en: 'Sports events', ar: 'التظاهرات الرياضية' },
          'rights-liberties': { fr: 'Droits et libertes', en: 'Rights and liberties', ar: 'الحقوق والحريات' },
          'energy-efficiency': { fr: 'Efficacite energetique', en: 'Energy efficiency', ar: 'النجاعة الطاقية' },
          archives: { fr: 'Archives', en: 'Archives', ar: 'الأرشيف' },
          hydrocarbons: { fr: 'Hydrocarbures', en: 'Hydrocarbons', ar: 'المحروقات' },
          ports: { fr: 'Ports', en: 'Ports', ar: 'الموانئ' },
          fintech: { fr: 'Fintech', en: 'Fintech', ar: 'التكنولوجيا المالية' },
        };

        const domainCategoryLabels = {
          official_bulletin: { fr: 'Bulletins officiels', en: 'Official bulletins', ar: 'النشرات الرسمية' },
          commercial_company: { fr: 'Droit commercial', en: 'Commercial law', ar: 'القانون التجاري' },
          civil_obligations_contracts: { fr: 'Droit civil', en: 'Civil law', ar: 'القانون المدني' },
          civil_procedure: { fr: 'Procedure civile', en: 'Civil procedure', ar: 'المسطرة المدنية' },
          administrative_urbanism: { fr: 'Droit administratif et urbanisme', en: 'Administrative law and urbanism', ar: 'القانون الإداري والتعمير' },
          labor: { fr: 'Travail', en: 'Labor', ar: 'الشغل' },
          health_medical: { fr: 'Sante', en: 'Health', ar: 'الصحة' },
          criminal: { fr: 'Droit penal', en: 'Criminal law', ar: 'القانون الجنائي' },
          real_estate_rent: { fr: 'Immobilier', en: 'Real estate', ar: 'العقار' },
          tax: { fr: 'Fiscalite', en: 'Taxation', ar: 'الضرائب' },
          banking_finance: { fr: 'Banque et finance', en: 'Banking and finance', ar: 'البنوك والمالية' },
          environment_water_energy: { fr: 'Environnement, eau et energie', en: 'Environment, water and energy', ar: 'البيئة والماء والطاقة' },
          family_marriage_divorce: { fr: 'Famille', en: 'Family law', ar: 'مدونة الأسرة' },
          digital_data_ip_media: { fr: 'Numerique, donnees et medias', en: 'Digital, data and media', ar: 'الرقمنة والمعطيات والإعلام' },
          insurance: { fr: 'Assurances', en: 'Insurance', ar: 'التامينات' },
          consumer_protection: { fr: 'Protection du consommateur', en: 'Consumer protection', ar: 'حماية المستهلك' },
          professional_regulation: { fr: 'Professions reglementees', en: 'Regulated professions', ar: 'المهن المنظمة' },
          prison_corrections: { fr: 'Etablissements penitentiaires', en: 'Prisons', ar: 'السجون' },
          succession_inheritance: { fr: 'Successions', en: 'Inheritance', ar: 'الإرث' },
        };

        Object.assign(categoryLabels, categorySlugLabels, domainCategoryLabels);

        Object.values({ ...categorySlugLabels, ...domainCategoryLabels }).forEach((labels) => {
          [labels.fr, labels.en, labels.ar].filter(Boolean).forEach((alias) => {
            categoryLabels[alias] = labels;
            categoryLabels[alias.toLowerCase()] = labels;
          });
        });

        const categorySearchQueries = {
          official_bulletin: { fr: 'Bulletin officiel', en: 'Official Bulletin Morocco', ar: 'النشرة الرسمية' },
          commercial_company: { fr: 'Code de commerce', en: 'Code de commerce', ar: 'القانون التجاري' },
          civil_obligations_contracts: { fr: 'Code des Obligations et des Contrats', en: 'Code des Obligations et des Contrats', ar: 'القانون المدني العقود الالتزامات' },
          civil_procedure: { fr: 'Code de procedure civile', en: 'Code de procedure civile', ar: 'المسطرة المدنية' },
          administrative_urbanism: { fr: 'Droit administratif urbanisme', en: 'administrative law urban planning Morocco', ar: 'القانون الإداري التعمير' },
          labor: { fr: 'Code du travail', en: 'Code du travail', ar: 'قانون الشغل' },
          health_medical: { fr: 'Sante medecine pharmacie', en: 'health medical law Morocco', ar: 'الصحة الطب الصيدلة' },
          criminal: { fr: 'Code penal', en: 'Code penal', ar: 'القانون الجنائي' },
          real_estate_rent: { fr: 'Immobilier bail loyer propriete', en: 'real estate rent property Morocco', ar: 'العقار الكراء الملكية' },
          tax: { fr: 'Fiscalite impot taxe', en: 'tax law Morocco', ar: 'الضرائب الجبايات' },
          banking_finance: { fr: 'Banque finance credit', en: 'banking finance Morocco', ar: 'البنوك المالية الائتمان' },
          environment_water_energy: { fr: 'Environnement eau energie', en: 'environment water energy Morocco', ar: 'البيئة الماء الطاقة' },
          family_marriage_divorce: { fr: 'Code de la famille', en: 'Code de la famille', ar: 'مدونة الأسرة الزواج الطلاق' },
          digital_data_ip_media: { fr: 'Donnees personnelles transactions electroniques droit auteur presse', en: 'digital data media law Morocco', ar: 'المعطيات الشخصية الإعلام المعاملات الإلكترونية' },
          insurance: { fr: 'Assurances', en: 'insurance law Morocco', ar: 'التامينات' },
          consumer_protection: { fr: 'Protection du consommateur', en: 'consumer protection Morocco', ar: 'حماية المستهلك' },
          professional_regulation: { fr: 'Professions reglementees avocat notaire adoul', en: 'regulated professions Morocco', ar: 'المهن المنظمة المحاماة التوثيق' },
          prison_corrections: { fr: 'Etablissements penitentiaires prison', en: 'prison law Morocco', ar: 'السجون المؤسسات السجنية' },
          succession_inheritance: { fr: 'Succession heritage', en: 'inheritance succession Morocco', ar: 'الإرث التركة' },
        };

        const quickLabels = {
          immobilier: { fr: 'immobilier', en: 'Real estate', ar: 'العقارات' },
          commerce: { fr: 'commerce', en: 'Commerce', ar: 'التجارة' },
          travail: { fr: 'travail', en: 'Labor', ar: 'الشغل' },
          famille: { fr: 'famille', en: 'Family', ar: 'الأسرة' },
          fiscalite: { fr: 'fiscalite', en: 'Tax', ar: 'الضرائب' },
          banque: { fr: 'banque', en: 'Banking', ar: 'البنوك' },
          contrats: { fr: 'contrats', en: 'Contracts', ar: 'العقود' },
          propriete: { fr: 'propriete', en: 'Property', ar: 'الملكية' },
        };

        Object.values(quickLabels).forEach((labels) => {
          [labels.fr, labels.en, labels.ar].filter(Boolean).forEach((alias) => {
            quickLabels[alias] = labels;
            quickLabels[alias.toLowerCase()] = labels;
          });
        });

        const ui = {
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
            visible: (count) => `${count} visibles`,
            top: (count) => `Top ${count}`,
            resultCount: (count) => `${count} resultat${Number(count) === 1 ? '' : 's'}`,
            resultsFor: (query) => `Resultats pour "${query}"`,
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
            visible: (count) => `${count} visible`,
            top: (count) => `Top ${count}`,
            resultCount: (count) => `${count} result${Number(count) === 1 ? '' : 's'}`,
            resultsFor: (query) => `Results for "${query}"`,
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
            language: 'اللغة',
            coverage: 'خريطة التغطية',
            library: 'المكتبة المباشرة',
            articles: 'مادة',
            sources: 'مصادر',
            areas: 'مجالات',
            footer: 'متصل بالـ corpus القانوني المغربي المفهرس',
            landing: 'الرئيسية',
            heroKicker: 'الفهرس القانوني المغربي',
            heroTitle: 'مصادر متاحة وقابلة للبحث فورا.',
            heroSubcopy: 'ابحث في المواد المفهرسة، تحقق من المصادر، وترجم النتائج من مساحة واحدة.',
            searchPlaceholder: 'ابحث في المواد، القوانين، المصادر، المجالات...',
            search: 'بحث',
            clearSearch: 'مسح البحث',
            indexedSources: 'مصادر مفهرسة',
            articleRanking: 'ترتيب المواد',
            tools: 'أدوات EN / AR',
            index: 'الفهرس',
            coverageSnapshot: 'ملخص التغطية',
            articleResults: 'نتائج المواد',
            ready: 'جاهز عندما تكون جاهزا',
            noSearchYet: 'لا يوجد بحث بعد',
            startKeyword: 'ابدأ بكلمة مفتاحية',
            searchByTopic: 'ابحث حسب الموضوع أو القانون أو المرجع أو المصدر أو المجال القانوني.',
            noMatching: 'لا توجد مواد مطابقة بعد',
            tryBroader: 'جرّب مصطلحا قانونيا أوسع أو ابحث حسب عائلة القانون.',
            searching: 'جاري البحث في المواد المفهرسة...',
            all: 'الكل',
            matches: 'مطابقات',
            topMatches: 'أفضل النتائج',
            visible: (count) => `${count} ظاهرة`,
            top: (count) => `أفضل ${count}`,
            resultCount: (count) => `${count} نتيجة`,
            resultsFor: (query) => `نتائج البحث عن "${query}"`,
            suggestionTypes: { AREA: 'مجال', DOCUMENT: 'وثيقة', ARTICLE: 'مادة', SOURCE: 'مصدر' },
            bestMatch: 'أفضل نتيجة',
            verifiedSource: 'مصدر موثق',
            source: 'المصدر',
            officialSource: 'مصدر قانوني رسمي',
            open: 'فتح',
            english: 'الإنجليزية',
            arabic: 'العربية',
            hideEnglish: 'إخفاء الإنجليزية',
            hideArabic: 'إخفاء العربية',
          },
        };

        const currentLanguage = () => {
          const value = localStorage.getItem(storageKey) || 'fr';
          return supportedLanguages.includes(value) ? value : 'fr';
        };

        const normalizeLookupKey = (value) => (value || '')
          .toString()
          .trim()
          .toLowerCase()
          .replace(/[\s-]+/g, '_');

        const lookupEntry = (labels, original) => {
          const value = (original || '').toString().trim();
          const normalized = normalizeLookupKey(value);

          return labels[value]
            || labels[value.toLowerCase?.()]
            || labels[normalized]
            || labels[normalized.replaceAll('_', '-')]
            || null;
        };

        const translated = (labels, original, language) => {
          if (!original) {
            return original;
          }

          const direct = lookupEntry(labels, original);

          if (direct) {
            return direct[language] || direct.fr || original;
          }

          return original;
        };

        const searchQueryFor = (value, language) => {
          const direct = lookupEntry(categorySearchQueries, value);

          if (!direct) {
            return value;
          }

          return direct[language] || direct.fr || value;
        };

        const setText = (element, value) => {
          if (element && element.textContent.trim() !== value) {
            element.textContent = value;
          }
        };

        const setTrailingText = (element, value) => {
          if (!element) {
            return;
          }

          const textNode = [...element.childNodes].find((node) => node.nodeType === Node.TEXT_NODE);

          if (textNode) {
            if (textNode.textContent.trim() !== value) {
              textNode.textContent = value;
            }
            return;
          }

          element.append(document.createTextNode(value));
        };

        const trailingText = (element) => {
          if (!element) {
            return '';
          }

          const textNode = [...element.childNodes].find((node) => node.nodeType === Node.TEXT_NODE && node.textContent.trim());

          return textNode?.textContent.trim() || element.textContent.trim();
        };

        const setInputDisplayValue = (input, value) => {
          if (!input || input.value === value) {
            return;
          }

          const nativeSetter = Object.getOwnPropertyDescriptor(window.HTMLInputElement.prototype, 'value')?.set;

          if (nativeSetter) {
            nativeSetter.call(input, value);
          } else {
            input.value = value;
          }
        };

        const displayLabelFor = (value, language) => {
          const original = (value || '').trim();

          if (!original) {
            return original;
          }

          return translated({ ...categoryLabels, ...quickLabels }, original, language);
        };

        const setElementOriginal = (element, value) => {
          if (element && value && !element.dataset.originalLabel) {
            element.dataset.originalLabel = value;
          }
        };

        const ensureSelector = () => {
          const target = document.querySelector('.topbar-meta');

          if (!target || target.querySelector('.interface-language-control')) {
            return;
          }

          const wrapper = document.createElement('label');
          wrapper.className = 'interface-language-control';

          const label = document.createElement('span');
          label.dataset.interfaceLanguageLabel = '1';

          const select = document.createElement('select');
          select.setAttribute('aria-label', 'Workspace language');
          [
            ['fr', 'FR'],
            ['en', 'EN'],
            ['ar', 'AR'],
          ].forEach(([value, label]) => {
            const option = document.createElement('option');
            option.value = value;
            option.textContent = label;
            select.append(option);
          });

          select.value = currentLanguage();
          select.addEventListener('change', () => {
            localStorage.setItem(storageKey, select.value);
            applyTranslations();
          });

          wrapper.append(label, select);
          target.insertBefore(wrapper, target.firstChild);
        };

        const rememberOriginal = (element) => {
          if (!element.dataset.originalLabel) {
            element.dataset.originalLabel = element.textContent.trim();
          }

          return element.dataset.originalLabel;
        };

        const updateSuggestionRows = (language, copy) => {
          document.querySelectorAll('.suggestion-row').forEach((row) => {
            const label = row.querySelector('span');
            const type = row.querySelector('strong');

            if (label) {
              const original = label.dataset.originalLabel || trailingText(label);
              setElementOriginal(label, original);
              setTrailingText(label, displayLabelFor(original, language));
            }

            if (type) {
              const originalType = type.dataset.originalType || type.textContent.trim();
              type.dataset.originalType = originalType;
              setText(type, copy.suggestionTypes?.[originalType] || originalType);
            }
          });
        };

        const updateLocalizedSearchDisplay = (language, copy) => {
          const input = document.querySelector('.search-input-row input');

          if (!input) {
            return;
          }

          input.placeholder = copy.searchPlaceholder;

          const rawValue = input.value.trim();
          const localizedValue = displayLabelFor(rawValue, language);

          if (localizedValue && localizedValue !== rawValue) {
            setInputDisplayValue(input, localizedValue);
          }

          const h2 = document.querySelector('.results-head h2');

          if (h2) {
            const heading = h2.textContent.trim();
            const match = heading.match(/"(.*?)"/);
            const query = displayLabelFor(input.value.trim() || match?.[1] || '', language);

            if (heading.includes('Ready when you are') || heading.includes('Pret quand') || heading.includes('جاهز')) {
              setText(h2, copy.ready);
            } else if (heading.includes('Results for') || heading.includes('Resultats pour') || heading.includes('نتائج البحث')) {
              setText(h2, copy.resultsFor(query));
            }
          }
        };

        const updateResultCount = (copy) => {
          const count = document.querySelector('.result-count');

          if (!count) {
            return;
          }

          const text = count.textContent.trim();
          const number = text.match(/\d+/)?.[0] || '';

          if (text.includes('No search yet') || text.includes('Aucune recherche') || text.includes('لا يوجد بحث')) {
            setText(count, copy.noSearchYet);
          } else if (text.includes('visible') || text.includes('visibles') || text.includes('ظاهرة')) {
            setText(count, copy.visible(number));
          } else if (text.startsWith('Top') || text.startsWith('أفضل')) {
            setText(count, copy.top(number));
          } else if (number && (text.includes('result') || text.includes('نتيجة'))) {
            setText(count, copy.resultCount(number));
          }
        };

        const updateResultCards = (language, copy) => {
          document.querySelectorAll('.category-chip').forEach((chip) => {
            const original = rememberOriginal(chip);
            setText(chip, displayLabelFor(original, language));
          });

          document.querySelectorAll('.score-pill').forEach((pill) => {
            const original = rememberOriginal(pill);
            if (original === 'Best match') setText(pill, copy.bestMatch);
            if (original === 'Verified source') setText(pill, copy.verifiedSource);
          });

          document.querySelectorAll('.source-box span').forEach((span) => {
            const original = rememberOriginal(span);
            if (original === 'Source') setText(span, copy.source);
          });

          document.querySelectorAll('.source-box strong').forEach((strong) => {
            const original = rememberOriginal(strong);
            if (original === 'Official legal source') setText(strong, copy.officialSource);
          });

          document.querySelectorAll('.source-box a').forEach((link) => {
            setTrailingText(link, copy.open);
          });

          document.querySelectorAll('.action-row button').forEach((button) => {
            const original = rememberOriginal(button);
            if (original.includes('Hide English')) setTrailingText(button, copy.hideEnglish);
            else if (original.includes('Hide Arabic')) setTrailingText(button, copy.hideArabic);
            else if (original.includes('English')) setTrailingText(button, copy.english);
            else if (original.includes('Arabic')) setTrailingText(button, copy.arabic);
          });
        };

        const updateStaticInterface = (language, copy) => {
          setText(document.querySelector('.topbar-link'), copy.landing);
          setTrailingText(document.querySelector('.hero-kicker'), copy.heroKicker);
          setText(document.querySelector('.search-hero h1'), copy.heroTitle);
          setText(document.querySelector('.hero-subcopy'), copy.heroSubcopy);
          setTrailingText(document.querySelector('.search-button'), copy.search);

          const clearButton = document.querySelector('.search-input-row .icon-button');
          if (clearButton) {
            clearButton.setAttribute('aria-label', copy.clearSearch);
          }

          const signals = document.querySelectorAll('.signal-row span');
          setTrailingText(signals[0], copy.indexedSources);
          setTrailingText(signals[1], copy.articleRanking);
          setTrailingText(signals[2], copy.tools);

          setText(document.querySelector('.library-heading span'), copy.index);
          setText(document.querySelector('.library-heading strong'), copy.coverageSnapshot);

          const metricLabels = document.querySelectorAll('.stat-grid .metric-card span:last-child');
          setText(metricLabels[0], copy.articles);
          setText(metricLabels[1], copy.sources);
          setText(metricLabels[2], copy.areas);

          setText(document.querySelector('.results-head .section-label'), copy.articleResults);
          updateResultCount(copy);

          const resultToolsSummary = document.querySelector('.result-tools > div:first-child span');
          if (resultToolsSummary) {
            const original = rememberOriginal(resultToolsSummary);
            if (original === 'top matches') setText(resultToolsSummary, copy.topMatches);
            if (original === 'matches') setText(resultToolsSummary, copy.matches);
          }

          const allFilter = document.querySelector('.result-filter-strip button:first-child');
          if (allFilter) {
            setText(allFilter, copy.all);
          }

          const emptyTitle = document.querySelector('.empty-panel h3');
          const emptyText = document.querySelector('.empty-panel p');
          const hasSearchValue = !!document.querySelector('.search-input-row input')?.value.trim();

          if (emptyTitle) {
            setText(emptyTitle, hasSearchValue ? copy.noMatching : copy.startKeyword);
          }

          if (emptyText) {
            setText(emptyText, hasSearchValue ? copy.tryBroader : copy.searchByTopic);
          }

          document.querySelectorAll('.state-panel:not(.is-error)').forEach((panel) => {
            if (panel.textContent.includes('Searching indexed articles') || panel.textContent.includes('Recherche') || panel.textContent.includes('جاري البحث')) {
              setTrailingText(panel, copy.searching);
            }
          });

          updateSuggestionRows(language, copy);
          updateLocalizedSearchDisplay(language, copy);
          updateResultCards(language, copy);
        };

        const submitWorkspaceSearch = (query) => {
          const input = document.querySelector('.search-input-row input');
          const form = document.querySelector('.search-console');

          if (!input || !form || !query) {
            return false;
          }

          setInputDisplayValue(input, query);
          input.dispatchEvent(new Event('input', { bubbles: true }));

          window.setTimeout(() => {
            if (typeof form.requestSubmit === 'function') {
              form.requestSubmit();
              return;
            }

            form.dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }));
          }, 0);

          return true;
        };

        document.addEventListener('click', (event) => {
          const coverageItem = event.target.closest('.coverage-item');

          if (coverageItem) {
            const label = coverageItem.querySelector('span');
            const original = label?.dataset.originalLabel || label?.textContent.trim() || '';
            const query = searchQueryFor(original, currentLanguage());

            if (query && query !== original && submitWorkspaceSearch(query)) {
              event.preventDefault();
              event.stopPropagation();
              event.stopImmediatePropagation?.();
              window.setTimeout(scheduleApply, 120);
              window.setTimeout(scheduleApply, 700);
              return;
            }
          }

          const clickable = event.target.closest('.coverage-item, .quick-strip button, .suggestion-row');

          if (!clickable) {
            return;
          }

          window.setTimeout(scheduleApply, 0);
          window.setTimeout(scheduleApply, 180);
          window.setTimeout(scheduleApply, 700);
        }, true);

        let applying = false;

        const applyTranslations = () => {
          if (applying) {
            return;
          }

          applying = true;

          try {
            const language = currentLanguage();
            const copy = ui[language];

            document.documentElement.lang = language;
            document.documentElement.dir = language === 'ar' ? 'rtl' : 'ltr';

            ensureSelector();

            const selector = document.querySelector('.interface-language-control select');
            if (selector && selector.value !== language) {
              selector.value = language;
            }

            const liveLibrary = [...document.querySelectorAll('.topbar-meta > span')]
              .find((element) => element.querySelector('.live-dot'));
            const articleCount = document.querySelector('.topbar-meta strong');
            const articleNumber = articleCount?.textContent.match(/[\d,]+/)?.[0] || '';

            setText(document.querySelector('[data-interface-language-label]'), copy.language);
            setTrailingText(document.querySelector('.category-radar .panel-title'), copy.coverage);
            setTrailingText(liveLibrary, copy.library);
            setText(articleCount, `${articleNumber} ${copy.articles}`.trim());
            setText(document.querySelector('.library-footer span'), copy.footer);

            document.querySelectorAll('.metric-card span:last-child').forEach((element) => {
              const original = rememberOriginal(element);
              if (original === 'SOURCES') setText(element, copy.sources);
              if (original === 'AREAS') setText(element, copy.areas);
            });

            document.querySelectorAll('.coverage-item span').forEach((element) => {
              const original = rememberOriginal(element);
              setText(element, translated(categoryLabels, original, language));
            });

            document.querySelectorAll('.quick-strip button').forEach((button) => {
              const original = rememberOriginal(button).toLowerCase();
              setText(button, translated(quickLabels, original, language));
            });

            document.querySelectorAll('.result-filter-strip button').forEach((button) => {
              const label = button.childNodes[0]?.textContent?.trim() || '';
              const original = button.dataset.originalLabel || label;
              button.dataset.originalLabel = original;

              const translatedLabel = translated(categoryLabels, original, language);
              if (button.childNodes[0] && button.childNodes[0].nodeType === Node.TEXT_NODE && button.childNodes[0].textContent.trim() !== translatedLabel) {
                button.childNodes[0].textContent = translatedLabel;
              }
            });

            updateStaticInterface(language, copy);
          } finally {
            applying = false;
          }
        };

        let scheduled = false;
        const scheduleApply = () => {
          if (scheduled || applying) {
            return;
          }

          scheduled = true;
          window.requestAnimationFrame(() => {
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
    </script>

  </body>
</html>
