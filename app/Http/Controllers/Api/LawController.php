<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Law;
use App\Models\LawTranslation;
use App\Services\LawSearchService;
use App\Services\TranslationService;
use App\Services\TranslationUnavailableException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LawController extends Controller
{
    private const DEFAULT_SEARCH_MODE = 'fast';

    private const SEARCH_QUERY_ALIASES = [
        [
            'aliases' => ['custody', 'child custody', 'mother custody', 'remarried mother', 'mother remarried', 'hadana', 'الحضانة', 'حضانة', 'زواج الأم', 'تزوجت الأم', 'زواج الام', 'تزوجت الام', '7adana'],
            'queries' => ['Code de la famille garde remariage mere', 'Code de la famille garde enfant divorce', 'Code de la famille decheance garde', 'Code de la famille'],
        ],
        [
            'aliases' => ['alimony', 'child support', 'maintenance', 'nafaqa', 'pension alimentaire', 'النفقة', 'نفقة', 'nafa9a'],
            'queries' => ['Code de la famille pension alimentaire nafaqa', 'Code de la famille pension', 'Code de la famille'],
        ],
        [
            'aliases' => ['divorce', 'repudiation', 'الطلاق', 'طلاق', 'talaq', 'tala9'],
            'queries' => ['Code de la famille divorce epoux', 'Code de la famille divorce', 'Code de la famille'],
        ],
        [
            'aliases' => ['inheritance', 'heirs', 'estate', 'succession', 'heritage', 'الإرث', 'الارث', 'الميراث', 'التركة', 'ورثة', 'warata', 'mirath'],
            'queries' => ['Code de la famille succession heritage heritiers', 'Code de la famille succession', 'Code de la famille'],
        ],
        [
            'aliases' => ['debt', 'loan', 'repayment', 'bank transfer', 'whatsapp', 'proof of debt', 'evidence of loan', 'gift', 'دين', 'سلف', 'قرض', 'حوالة', 'تحويل بنكي', 'واتساب', 'إثبات', 'اثبات', 'هدية', 'dayn', 'salaf', '9ard'],
            'queries' => ['Code des Obligations et des Contrats preuve dette pret', 'Code des Obligations et des Contrats preuve', 'Code des Obligations et des Contrats'],
        ],
        [
            'aliases' => ['dismissal', 'termination', 'fire', 'fired', 'employer', 'employee', 'boss', 'wrongful dismissal', 'boss fired', 'طرد', 'فصل', 'الأجير', 'الاجير', 'المشغل', 'العمل', 'choghl', 'khedma'],
            'queries' => ['Code du travail licenciement motif valable', 'Code du travail procedure licenciement', 'Code du travail'],
        ],
        [
            'aliases' => ['rent', 'lease', 'tenant', 'landlord', 'eviction', 'unpaid rent', 'كراء', 'الكراء', 'إفراغ', 'افراغ', 'إخلاء', 'اخلاء', 'مكتري', 'مكري', 'السومة الكرائية', 'kra', 'kira'],
            'queries' => ['Recouvrement des loyers expulsion loyer', 'Code des droits reels bail loyer', 'Recouvrement des loyers', 'Code des droits reels'],
        ],
        [
            'aliases' => ['company registration', 'commercial register', 'shareholder', 'manager', 'company', 'corporate', 'شركة', 'الشركة', 'السجل التجاري', 'تاجر', 'مسير', 'شريك', 'sijil tijari', 'charika'],
            'queries' => ['Code de commerce societe registre commerce', 'Code de commerce societe', 'Code de commerce'],
        ],
        [
            'aliases' => ['theft', 'fraud', 'assault', 'violence', 'stolen', 'سرقة', 'نصب', 'احتيال', 'عنف', 'ضرب', 'جرح', 'sari9a', 'nasb'],
            'queries' => ['Code penal vol escroquerie coups blessures', 'Code penal vol', 'Code penal'],
        ],
        [
            'aliases' => ['القانون التجاري', 'قانون تجاري', 'droit commercial', 'commercial law', 'code de commerce', 'commerce', 'commercial', '9anon tijari', 'qanoun tijari', 'kanoun tijari'],
            'queries' => ['Code de commerce', 'Droit commercial', 'commercial_company', 'commercial', 'commerce'],
        ],
        [
            'aliases' => ['القانون المدني', 'قانون مدني', 'droit civil', 'civil law', 'droit des obligations', 'obligations et contrats', 'civil', '9anon madani', 'qanoun madani', 'kanoun madani'],
            'queries' => ['Code des Obligations et des Contrats', 'Droit civil', 'civil_obligations_contracts', 'obligations et contrats', 'civil'],
        ],
        [
            'aliases' => ['المسطرة المدنية', 'قانون المسطرة المدنية', 'procedure civile', 'civil procedure', 'code de procedure civile', 'mostara madania', 'moustara madania'],
            'queries' => ['Code de procedure civile', 'Procedure civile', 'civil_procedure', 'civil-procedure'],
        ],
        [
            'aliases' => ['القانون الجنائي', 'القانون الجنائي المغربي', 'قانون جنائي', 'droit penal', 'criminal law', 'code penal', '9anon jinai', 'qanoun jinai', 'kanoun jinai'],
            'queries' => ['Code penal', 'Droit penal', 'criminal'],
        ],
        [
            'aliases' => ['مدونة الشغل', 'قانون الشغل', 'الشغل', 'droit du travail', 'code du travail', 'labor law', 'employment law', 'modawanat choghl', '9anon choghl'],
            'queries' => ['Code du travail', 'Travail', 'labor', 'licenciement'],
        ],
        [
            'aliases' => ['مدونة الأسرة', 'قانون الأسرة', 'الاسرة', 'الأسرة', 'droit de la famille', 'code de la famille', 'family law', 'modawanat osra', 'modawanat al osra', '9anon osra'],
            'queries' => ['Code de la famille', 'Famille', 'family_marriage_divorce', 'family'],
        ],
        [
            'aliases' => ['العقارات', 'العقار', 'قانون عقاري', 'immobilier', 'real estate', 'property law'],
            'queries' => ['Code des droits reels', 'Immobilier', 'real_estate_rent', 'real-estate', 'propriete immobiliere'],
        ],
        [
            'aliases' => ['commercial_company', 'القانون التجاري', 'قانون تجاري'],
            'queries' => ['Code de commerce', 'Droit commercial', 'commercial_company'],
        ],
        [
            'aliases' => ['civil_obligations_contracts', 'القانون المدني', 'قانون مدني', 'الالتزامات والعقود', 'العقود'],
            'queries' => ['Code des Obligations et des Contrats', 'Droit civil', 'civil_obligations_contracts'],
        ],
        [
            'aliases' => ['civil_procedure', 'civil-procedure', 'المسطرة المدنية', 'قانون المسطرة المدنية'],
            'queries' => ['Code de procedure civile', 'Procedure civile', 'civil_procedure'],
        ],
        [
            'aliases' => ['criminal', 'القانون الجنائي', 'قانون جنائي'],
            'queries' => ['Code penal', 'Droit penal', 'criminal'],
        ],
        [
            'aliases' => ['labor', 'مدونة الشغل', 'قانون الشغل', 'الشغل'],
            'queries' => ['Code du travail', 'Travail', 'labor'],
        ],
        [
            'aliases' => ['family_marriage_divorce', 'مدونة الأسرة', 'قانون الأسرة', 'الأسرة'],
            'queries' => ['Code de la famille', 'Famille', 'family_marriage_divorce'],
        ],
        [
            'aliases' => ['real_estate_rent', 'العقار', 'العقارات', 'الكراء', 'قانون عقاري'],
            'queries' => ['Code des droits reels', 'Immobilier', 'real_estate_rent', 'real-estate'],
        ],
        [
            'aliases' => ['official-bulletin', 'official_bulletin', 'official bulletin', 'bulletin officiel', 'الجريدة الرسمية', 'النشرة الرسمية'],
            'queries' => ['official-bulletin', 'Bulletin officiel', 'official_bulletin'],
        ],
        [
            'aliases' => ['administrative_urbanism', 'droit administratif', 'urbanisme', 'القانون الإداري', 'التعمير'],
            'queries' => ['administrative_urbanism', 'Droit administratif', 'urbanisme'],
        ],
        [
            'aliases' => ['health_medical', 'sante', 'health', 'medical', 'الصحة', 'الطب', 'الصيدلة'],
            'queries' => ['health_medical', 'Sante', 'medecine', 'pharmacie'],
        ],
        [
            'aliases' => ['tax', 'fiscalite', 'taxation', 'impot', 'taxe', 'الضرائب', 'الجبايات'],
            'queries' => ['tax', 'Fiscalite', 'impot'],
        ],
        [
            'aliases' => ['banking_finance', 'banque', 'finance', 'banking', 'البنوك', 'المالية', 'الائتمان'],
            'queries' => ['banking_finance', 'Banque', 'finance'],
        ],
        [
            'aliases' => ['environment_water_energy', 'environnement', 'eau', 'energie', 'environment', 'البيئة', 'الماء', 'الطاقة'],
            'queries' => ['environment_water_energy', 'Environnement', 'eau', 'energie'],
        ],
        [
            'aliases' => ['digital_data_ip_media', 'donnees personnelles', 'transactions electroniques', 'media', 'digital', 'المعطيات الشخصية', 'الإعلام', 'المعاملات الإلكترونية'],
            'queries' => ['digital_data_ip_media', 'Donnees personnelles', 'transactions electroniques', 'presse'],
        ],
        [
            'aliases' => ['insurance', 'assurances', 'insurance law', 'التامينات', 'التأمين'],
            'queries' => ['insurance', 'Assurances'],
        ],
        [
            'aliases' => ['consumer_protection', 'protection du consommateur', 'consumer protection', 'حماية المستهلك'],
            'queries' => ['consumer_protection', 'Protection du consommateur'],
        ],
        [
            'aliases' => ['professional_regulation', 'professions reglementees', 'regulated professions', 'avocat', 'notaire', 'المهن المنظمة', 'المحاماة', 'التوثيق'],
            'queries' => ['professional_regulation', 'Professions reglementees', 'avocat', 'notaire'],
        ],
        [
            'aliases' => ['prison_corrections', 'etablissements penitentiaires', 'prison', 'السجون', 'المؤسسات السجنية'],
            'queries' => ['prison_corrections', 'Etablissements penitentiaires'],
        ],
        [
            'aliases' => ['succession_inheritance', 'succession', 'heritage', 'inheritance', 'الإرث', 'التركة'],
            'queries' => ['succession_inheritance', 'Succession', 'heritage'],
        ],
    ];

    public function __construct(
        private readonly LawSearchService $laws,
        private readonly TranslationService $translations,
    )
    {
    }

    public function overview(): JsonResponse
    {
        return response()->json($this->laws->overview());
    }

    public function suggestions(Request $request): JsonResponse
    {
        $query = (string) $request->query('q', '');

        return response()->json([
            'query' => $query,
            'suggestions' => $this->laws->suggestions($query),
        ]);
    }

    public function search(Request $request): JsonResponse
    {
        $query = trim((string) $request->query('q', ''));
        $mode = strtolower(trim((string) $request->query('translation_mode', self::DEFAULT_SEARCH_MODE))) ?: self::DEFAULT_SEARCH_MODE;

        if ($this->isOfficialBulletinSearch($query)) {
            $payload = $this->laws->latestOfficialBulletinArticles(LawSearchService::SEARCH_RESULT_LIMIT);

            return response()->json([
                'query' => $query,
                'searchMode' => $mode,
                'searchedQueries' => [$query],
                'translatedQueries' => [],
                'translationWarning' => null,
                'count' => count($payload['results']),
                'results' => $payload['results'],
                'hasMore' => $payload['hasMore'],
                'limit' => $payload['limit'],
            ]);
        }

        $searchQueries = $this->translatedSearchQueries($query, $mode);
        $payload = $this->searchAcrossQueries(
            $searchQueries['queries'],
            LawSearchService::SEARCH_RESULT_LIMIT
        );

        return response()->json([
            'query' => $query,
            'searchMode' => $mode,
            'searchedQueries' => $searchQueries['queries'],
            'translatedQueries' => $searchQueries['translations'],
            'translationWarning' => $searchQueries['warning'],
            'count' => count($payload['results']),
            'results' => $payload['results'],
            'hasMore' => $payload['hasMore'],
            'limit' => $payload['limit'],
        ]);
    }

    public function translate(Law $law, Request $request): JsonResponse
    {
        $targetLanguage = strtolower(trim((string) $request->query('target', 'en'))) ?: 'en';
        $stored = LawTranslation::query()
            ->where('law_id', $law->id)
            ->where('target_language', $targetLanguage)
            ->first();

        if ($stored) {
            return response()->json($this->translationPayload($law, $stored, true));
        }

        try {
            $translation = $this->translations->translate($law, $targetLanguage);
        } catch (TranslationUnavailableException) {
            return response()->json([
                'message' => 'Inline translation is temporarily unavailable.',
                'fallbackUrl' => $this->translations->buildExternalTranslationUrl($law, $targetLanguage),
            ], 503);
        }

        $stored = LawTranslation::query()->updateOrCreate(
            [
                'law_id' => $law->id,
                'target_language' => $translation['targetLanguage'],
            ],
            [
                'source_language' => $translation['sourceLanguage'],
                'translated_title' => $translation['translatedTitle'],
                'translated_content' => $translation['translatedContent'],
                'provider' => $translation['provider'],
            ]
        );

        return response()->json($this->translationPayload($law, $stored, false));
    }

    private function translationPayload(Law $law, LawTranslation $translation, bool $cached): array
    {
        return [
            'id' => $law->id,
            'articleNumber' => $law->article_number,
            'documentTitle' => $law->document_title,
            'sourceUrl' => $law->source_url,
            'sourceLanguage' => $translation->source_language,
            'targetLanguage' => $translation->target_language,
            'translatedTitle' => $translation->translated_title,
            'translatedContent' => $translation->translated_content,
            'provider' => $translation->provider,
            'cached' => $cached,
        ];
    }

    private function translatedSearchQueries(string $query, string $mode): array
    {
        $queries = [];
        $translations = [];
        $warning = null;
        $aliasQueries = [];

        if ($mode !== 'original') {
            $aliasQueries = $this->searchAliasQueries($query);

            foreach ($aliasQueries as $aliasQuery) {
                if (!$this->containsEquivalentQuery($queries, $aliasQuery)) {
                    $queries[] = $aliasQuery;
                }
            }
        }

        if ($query !== '' && $this->shouldSearchOriginalQuery($query, $aliasQueries) && !$this->containsEquivalentQuery($queries, $query)) {
            $queries[] = $query;
        }

        foreach ($this->searchTranslationTargets($mode, $query) as $targetLanguage) {
            try {
                $translation = $this->translations->translatePlainText($query, 'auto', $targetLanguage);
            } catch (TranslationUnavailableException) {
                $warning = 'Query translation is temporarily unavailable. Showing original-language search results.';
                continue;
            }

            $translatedQuery = trim((string) ($translation['translatedText'] ?? ''));

            if ($translatedQuery === '') {
                continue;
            }

            $translations[] = [
                'targetLanguage' => $targetLanguage,
                'query' => $translatedQuery,
                'provider' => $translation['provider'] ?? 'public-translation',
            ];

            if (!$this->containsEquivalentQuery($queries, $translatedQuery)) {
                $queries[] = $translatedQuery;
            }
        }

        return [
            'queries' => $queries,
            'translations' => $translations,
            'warning' => $warning,
        ];
    }

    private function searchTranslationTargets(string $mode, string $query): array
    {
        $targets = match ($mode) {
            'fr', 'french' => ['fr'],
            'en', 'english' => ['en'],
            'ar', 'arabic' => ['ar'],
            'fr_en', 'en_fr', 'bilingual', 'translated', 'auto' => ['fr', 'en'],
            'fr_en_ar', 'multilingual', 'smart' => ['fr', 'en', 'ar'],
            default => [],
        };

        if ($targets && $this->containsArabic($query) && !in_array('fr', $targets, true)) {
            array_unshift($targets, 'fr');
        }

        if ($targets && $this->containsArabic($query) && !in_array('en', $targets, true)) {
            $targets[] = 'en';
        }

        return array_values(array_unique($targets));
    }

    private function searchAliasQueries(string $query): array
    {
        if (trim($query) === '') {
            return [];
        }

        $normalized = $this->normalizeSearchQueryForComparison($query);
        $matches = [];

        foreach (self::SEARCH_QUERY_ALIASES as $group) {
            foreach ($group['aliases'] as $alias) {
                if ($normalized === $this->normalizeSearchQueryForComparison($alias)) {
                    return array_values(array_unique($group['queries']));
                }
            }
        }

        foreach (self::SEARCH_QUERY_ALIASES as $group) {
            foreach ($group['aliases'] as $alias) {
                if (str_contains($normalized, $this->normalizeSearchQueryForComparison($alias))) {
                    $matches = array_merge($matches, $group['queries']);
                    break;
                }
            }
        }

        return array_values(array_unique($matches));
    }

    private function shouldSearchOriginalQuery(string $query, array $aliasQueries): bool
    {
        if (!$aliasQueries) {
            return true;
        }

        $terms = array_values(array_filter(preg_split('/\s+/u', trim($query)) ?: []));

        return count($terms) <= 6;
    }

    private function searchAcrossQueries(array $queries, int $limit, bool $includeOfficialBulletins = false): array
    {
        $results = [];
        $seen = [];
        $hasMore = false;
        $options = [
            'useCorpus' => true,
            'includeChatOnlySources' => $includeOfficialBulletins,
            'disableSemanticSearch' => true,
        ];

        foreach ($queries as $query) {
            $payload = $this->laws->search($query, $limit, $options);
            $hasMore = $hasMore || (bool) ($payload['hasMore'] ?? false);

            foreach ($payload['results'] ?? [] as $result) {
                $key = $this->searchResultKey($result);

                if (isset($seen[$key])) {
                    continue;
                }

                $result['matched_query'] = $query;
                $results[] = $result;
                $seen[$key] = true;

                if (count($results) >= $limit) {
                    $hasMore = $hasMore || count($queries) > 1;
                    break 2;
                }
            }
        }

        return [
            'results' => $results,
            'hasMore' => $hasMore,
            'limit' => $limit,
        ];
    }

    private function searchResultKey(array $result): string
    {
        if (!empty($result['legal_chunk_id'])) {
            return 'chunk:'.$result['legal_chunk_id'];
        }

        if (!empty($result['legal_article_id'])) {
            return 'article:'.$result['legal_article_id'];
        }

        return ($result['source_table'] ?? 'law').':'.($result['id'] ?? md5(json_encode($result)));
    }

    private function containsEquivalentQuery(array $queries, string $candidate): bool
    {
        $candidate = $this->normalizeSearchQueryForComparison($candidate);

        foreach ($queries as $query) {
            if ($this->normalizeSearchQueryForComparison($query) === $candidate) {
                return true;
            }
        }

        return false;
    }

    private function isOfficialBulletinSearch(string $query): bool
    {
        $normalized = $this->normalizeSearchQueryForComparison($query);

        if ($normalized === '') {
            return false;
        }

        return collect([
            'official bulletin',
            'official bulletins',
            'official_bulletin',
            'official-bulletin',
            'bulletin officiel',
            'bulletins officiels',
            'النشرة الرسمية',
            'الجريدة الرسمية',
        ])->contains(fn (string $alias): bool => str_contains($normalized, $this->normalizeSearchQueryForComparison($alias)));
    }

    private function normalizeSearchQueryForComparison(string $query): string
    {
        $query = preg_replace('/[-_]+/u', ' ', $query) ?: $query;

        return mb_strtolower(trim(preg_replace('/\s+/u', ' ', $query) ?: $query));
    }

    private function containsArabic(string $query): bool
    {
        return preg_match('/\p{Arabic}/u', $query) === 1;
    }
}
