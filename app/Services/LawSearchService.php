<?php

namespace App\Services;

use App\Models\Law;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

class LawSearchService
{
    public const SEARCH_RESULT_LIMIT = 1000;

    private const SUGGESTION_LIMIT = 8;
    private const CHAT_ONLY_CATEGORIES = ['official-bulletin', 'official_bulletin', 'Official Bulletin', 'Bulletins officiels'];
    private const CHAT_ONLY_SOURCE_TYPES = ['bo', 'official-bulletin', 'official_bulletin', 'official bulletin'];
    private const CHAT_ONLY_SEARCH_ALIASES = [
        'official bulletin',
        'bulletin officiel',
        'official-bulletin',
        'latest laws',
        'recent laws',
        'new laws',
        'legal updates',
        'nouvelles lois',
        'nouveaux textes',
        'dernieres lois',
    ];
    private const SEARCH_STOP_WORDS = [
        'a', 'an', 'and', 'are', 'as', 'after', 'before', 'by', 'can', 'does', 'for', 'from', 'has', 'have',
        'he', 'her', 'him', 'his', 'i', 'if', 'in', 'is', 'it', 'its', 'my', 'of', 'on', 'or', 'she', 'that',
        'the', 'this', 'to', 'was', 'what', 'when', 'where', 'with',
        'au', 'aux', 'ce', 'ces', 'de', 'des', 'du', 'elle', 'en', 'est', 'et', 'il', 'la', 'le', 'les', 'leur',
        'leurs', 'lui', 'ou', 'par', 'pour', 'que', 'qui', 'sur', 'un', 'une',
        'في', 'من', 'على', 'عن', 'إلى', 'الى', 'هل', 'إذا', 'اذا', 'ما', 'هي', 'هو', 'و', 'أو', 'او', 'بعد', 'قبل',
    ];

    private const DOCUMENT_TITLE_HINTS = [
        ['title' => 'Code penal', 'aliases' => ['code penal', 'penal code']],
        ['title' => 'Code de commerce', 'aliases' => ['code de commerce', 'commercial code']],
        ['title' => 'Code du travail', 'aliases' => ['code du travail', 'labor code', 'labour code']],
        ['title' => 'Code de la famille', 'aliases' => ['code de la famille', 'family code']],
        ['title' => 'Code de procedure civile', 'aliases' => ['code de procedure civile', 'civil procedure code']],
        ['title' => 'Recouvrement des loyers', 'aliases' => ['recouvrement des loyers', 'recouvrement loyers']],
        ['title' => 'Immatriculation fonciere', 'aliases' => ['immatriculation fonciere', 'land registration', 'titre foncier']],
        ['title' => 'Statut de la copropriété des immeubles bâtis', 'aliases' => ['statut de la copropriete des immeubles batis', 'statut de la copropriété des immeubles bâtis', 'copropriete des immeubles batis', 'copropriété des immeubles bâtis']],
        ['title' => 'Societe en nom collectif et SARL', 'aliases' => ['societe en nom collectif et sarl', 'sarl', 'societe a responsabilite limitee']],
        ['title' => 'Etablissements de credit et organismes assimiles', 'aliases' => ['etablissements de credit et organismes assimiles', 'etablissements de credit', 'credit institution']],
        ['title' => 'Code de recouvrement des creances publiques', 'aliases' => ['code de recouvrement des creances publiques', 'recouvrement des creances publiques']],
        ['title' => 'Application de la taxe sur la valeur ajoutee', 'aliases' => ['application de la taxe sur la valeur ajoutee', 'taxe sur la valeur ajoutee', 'tva']],
        ['title' => 'Simplification des procedures et des formalites administratives', 'aliases' => ['simplification des procedures et des formalites administratives', 'simplification des procedures', 'formalites administratives', 'acte administratif']],
        [
            'title' => 'Code des Obligations et des Contrats',
            'aliases' => ['code des obligations et des contrats', 'obligations et contrats', 'obligations and contracts'],
        ],
    ];
    private const PRIMARY_DOCUMENT_TITLES_BY_DOMAIN = [
        'banking_finance' => ['Bank Al-Maghrib', 'Etablissements de credit'],
        'civil_obligations_contracts' => ['Code des Obligations et des Contrats'],
        'civil_procedure' => ['Code de procedure civile'],
        'commercial_company' => ['Code de commerce'],
        'criminal' => ['Code penal'],
        'criminal_procedure' => ['Code de procedure penale'],
        'family_marriage_divorce' => ['Code de la famille'],
        'insurance' => ['Code des assurances'],
        'labor' => ['Code du travail'],
        'real_estate_rent' => ['Code des droits reels', 'Immatriculation fonciere', 'Recouvrement des loyers'],
        'tax' => ['Code de recouvrement des creances publiques'],
    ];

    public function __construct(
        private readonly LegalDomainClassifier $classifier,
        private readonly LegalEmbeddingService $embeddings,
    )
    {
    }

    public function search(string $keyword, int $limit = self::SEARCH_RESULT_LIMIT, array $options = []): array
    {
        $keyword = trim($keyword);
        $limit = max(1, min($limit, self::SEARCH_RESULT_LIMIT));

        if ($keyword === '') {
            return ['results' => [], 'hasMore' => false, 'limit' => $limit];
        }

        if (!($options['includeChatOnlySources'] ?? false) && $this->isChatOnlySearchKeyword($keyword)) {
            return ['results' => [], 'hasMore' => false, 'limit' => $limit];
        }

        if ($options['disableCache'] ?? false) {
            return $this->runSearch($keyword, $limit, $options);
        }

        return $this->remember(
            'laws.search.'.md5($keyword.'|'.$limit.'|'.json_encode($options)),
            60,
            fn () => $this->runSearch($keyword, $limit, $options)
        );
    }

    public function latestOfficialBulletinArticles(int $limit = self::SEARCH_RESULT_LIMIT): array
    {
        $limit = max(1, min($limit, self::SEARCH_RESULT_LIMIT));

        $corpusPayload = $this->runLatestOfficialBulletinCorpus($limit);

        if ($corpusPayload['results']) {
            return $corpusPayload;
        }

        $rows = Law::query()
            ->select($this->baseFields())
            ->selectRaw($this->bulletinSortSql().' AS bulletin_sort_number')
            ->selectRaw($this->articleSortSql().' AS article_sort_number')
            ->selectRaw('1000 AS relevance_score')
            ->whereIn('category', self::CHAT_ONLY_CATEGORIES)
            ->orderByDesc('bulletin_sort_number')
            ->orderByDesc('document_title')
            ->orderBy('article_sort_number')
            ->orderBy('article_number')
            ->orderBy('id')
            ->limit($limit + 1)
            ->get();

        return [
            'results' => $this->formatLegacyRows($rows->take($limit)),
            'hasMore' => $rows->count() > $limit,
            'limit' => $limit,
        ];
    }

    public function suggestions(string $keyword, int $limit = self::SUGGESTION_LIMIT): array
    {
        $keyword = trim($keyword);

        if (Str::length($keyword) < 2) {
            return [];
        }

        $limit = max(1, min($limit, 12));

        return $this->remember('laws.suggestions.'.md5($keyword.'|'.$limit), 60, function () use ($keyword, $limit): array {
            $values = collect();

            foreach (['document_title' => 'Document', 'title' => 'Article', 'category' => 'Area', 'law_reference' => 'Reference'] as $column => $type) {
                Law::query()
                    ->whereNotNull($column)
                    ->where($column, '<>', '')
                    ->where(fn (Builder $query) => $query
                        ->where($column, 'like', $keyword.'%')
                        ->orWhere($column, 'like', '%'.$keyword.'%'))
                    ->tap(fn (Builder $query) => $this->excludeChatOnlySources($query))
                    ->select($column)
                    ->distinct()
                    ->limit($limit)
                    ->pluck($column)
                    ->each(fn (string $text) => $values->push(['text' => $text, 'type' => $type]));
            }

            return $values
                ->unique(fn (array $item) => $item['type'].'|'.$item['text'])
                ->sortBy(fn (array $item) => Str::length($item['text']).'|'.$item['text'])
                ->take($limit)
                ->values()
                ->all();
        });
    }

    public function overview(): array
    {
        return $this->remember('laws.overview', 300, function (): array {
            if ($this->hasActiveCorpus()) {
                return $this->corpusOverview();
            }

            $base = Law::query();
            $this->excludeChatOnlySources($base);

            $totals = (clone $base)
                ->selectRaw('COUNT(*) AS total_articles')
                ->selectRaw('COUNT(DISTINCT document_title) AS total_documents')
                ->selectRaw('COUNT(DISTINCT category) AS total_categories')
                ->first();

            $categories = (clone $base)
                ->whereNotNull('category')
                ->where('category', '<>', '')
                ->select('category')
                ->selectRaw('COUNT(*) AS article_count')
                ->selectRaw('COUNT(DISTINCT document_title) AS document_count')
                ->groupBy('category')
                ->orderByDesc('article_count')
                ->orderBy('category')
                ->get()
                ->map(fn (Law $law) => [
                    'category' => $law->category,
                    'articleCount' => (int) $law->article_count,
                    'documentCount' => (int) $law->document_count,
                ])
                ->all();

            return [
                'totalArticles' => (int) ($totals->total_articles ?? 0),
                'totalDocuments' => (int) ($totals->total_documents ?? 0),
                'totalCategories' => (int) ($totals->total_categories ?? 0),
                'categories' => $categories,
            ];
        });
    }

    private function remember(string $key, int $seconds, callable $callback): mixed
    {
        try {
            return Cache::remember($key, $seconds, $callback);
        } catch (Throwable $exception) {
            report($exception);

            return $callback();
        }
    }

    private function keywordTerms(string $keyword): Collection
    {
        return collect(preg_split('/\s+/u', $keyword) ?: [])
            ->map(fn (string $term): string => trim($term, " \t\n\r\0\x0B.,;:!?()[]{}\"'`"))
            ->map(fn (string $term): string => mb_strtolower($term))
            ->filter(fn (string $term): bool => mb_strlen($term) >= 3)
            ->reject(fn (string $term): bool => in_array($term, self::SEARCH_STOP_WORDS, true))
            ->values();
    }

    private function corpusOverview(): array
    {
        $base = $this->activeCorpusBaseQuery();
        $this->excludeChatOnlyCorpusSources($base);

        $totals = (clone $base)
            ->selectRaw('COUNT(DISTINCT legal_articles.id) AS total_articles')
            ->selectRaw('COUNT(DISTINCT legal_sources.id) AS total_sources')
            ->selectRaw("COUNT(DISTINCT COALESCE(legal_documents.domain, 'general')) AS total_categories")
            ->first();

        $categories = (clone $base)
            ->selectRaw("COALESCE(legal_documents.domain, 'general') AS category")
            ->selectRaw('COUNT(DISTINCT legal_articles.id) AS article_count')
            ->selectRaw('COUNT(DISTINCT legal_documents.id) AS document_count')
            ->groupBy(DB::raw("COALESCE(legal_documents.domain, 'general')"))
            ->orderByDesc('article_count')
            ->orderBy('category')
            ->get()
            ->map(fn (object $row): array => [
                'category' => $row->category,
                'articleCount' => (int) $row->article_count,
                'documentCount' => (int) $row->document_count,
            ])
            ->all();

        return [
            'totalArticles' => (int) ($totals->total_articles ?? 0),
            'totalDocuments' => (int) ($totals->total_sources ?? 0),
            'totalCategories' => (int) ($totals->total_categories ?? 0),
            'categories' => $categories,
        ];
    }

    private function runSearch(string $keyword, int $limit, array $options): array
    {
        if (!($options['useCorpus'] ?? false)) {
            return $this->runLegacySearch($keyword, $limit, $options);
        }

        $corpusPayload = $this->runCorpusSearch($keyword, $limit, $options);

        if ($corpusPayload['results'] || ($options['disableLegacyFallback'] ?? false)) {
            return $corpusPayload;
        }

        return $this->runLegacySearch($keyword, $limit, $options);
    }

    private function runCorpusSearch(string $keyword, int $limit, array $options): array
    {
        $articleNumber = $this->extractArticleNumber($keyword);
        $documentHints = $this->extractDocumentTitleHints($keyword);
        $referencePatterns = $this->extractReferencePatterns($keyword);
        $terms = $this->keywordTerms($keyword);
        $queryTaxonomy = $this->classifier->classifyQuery($keyword);

        if ($this->shouldUseDocumentTitleFastPath($keyword, $documentHints, $articleNumber, $referencePatterns, $options)) {
            return $this->runDocumentTitleCorpusSearch($documentHints, $limit, $queryTaxonomy, $options);
        }

        if ($this->shouldUseDocumentConstrainedFastPath($documentHints, $articleNumber, $referencePatterns, $options)) {
            return $this->runDocumentConstrainedCorpusSearch($keyword, $documentHints, $terms, $limit, $queryTaxonomy, $options);
        }

        if ($this->shouldUseDomainFastPath($keyword, $queryTaxonomy, $terms, $articleNumber, $documentHints, $referencePatterns, $options)) {
            return $this->runDomainCorpusSearch($keyword, $limit, $queryTaxonomy, $options);
        }

        $semanticScores = $this->semanticChunkScores($keyword, $options);
        $semanticChunkIds = array_keys($semanticScores);

        $query = $this->activeCorpusBaseQuery()->select($this->corpusFields());

        if (!($options['includeChatOnlySources'] ?? false)) {
            $this->excludeChatOnlyCorpusSources($query);
        }

        $query->where(function ($where) use ($keyword, $articleNumber, $documentHints, $referencePatterns, $terms, $semanticChunkIds): void {
            $like = '%'.$keyword.'%';

            foreach ([
                'legal_articles.article_title',
                'legal_articles.article_number',
                'legal_articles.content',
                'legal_chunks.content',
                'legal_documents.document_title',
                'legal_documents.law_reference',
                'legal_documents.domain',
                'legal_documents.subdomain',
                'legal_documents.tags',
                'legal_articles.domain',
                'legal_articles.subdomain',
                'legal_articles.tags',
                'legal_chunks.domain',
                'legal_chunks.subdomain',
                'legal_chunks.tags',
                'legacy_laws.category',
                'legacy_laws.tags',
                'legal_sources.name',
            ] as $column) {
                $where->orWhere($column, 'like', $like);
            }

            if ($articleNumber) {
                $where->orWhere('legal_articles.article_number', $articleNumber);
            }

            foreach ($documentHints as $title) {
                $where->orWhere('legal_documents.document_title', $title);
            }

            foreach ($referencePatterns as $pattern) {
                $where->orWhere('legal_documents.law_reference', 'like', $pattern);
            }

            if ($semanticChunkIds) {
                $where->orWhereIn('legal_chunks.id', $semanticChunkIds);
            }

            $terms->each(function (string $term) use ($where): void {
                $termLike = '%'.$term.'%';

                $where->orWhere('legal_articles.article_title', 'like', $termLike)
                    ->orWhere('legal_articles.content', 'like', $termLike)
                    ->orWhere('legal_chunks.content', 'like', $termLike)
                    ->orWhere('legal_documents.document_title', 'like', $termLike)
                    ->orWhere('legal_documents.law_reference', 'like', $termLike)
                    ->orWhere('legal_documents.domain', 'like', $termLike)
                    ->orWhere('legal_documents.subdomain', 'like', $termLike)
                    ->orWhere('legal_documents.tags', 'like', $termLike)
                    ->orWhere('legal_articles.domain', 'like', $termLike)
                    ->orWhere('legal_articles.subdomain', 'like', $termLike)
                    ->orWhere('legal_articles.tags', 'like', $termLike)
                    ->orWhere('legal_chunks.domain', 'like', $termLike)
                    ->orWhere('legal_chunks.subdomain', 'like', $termLike)
                    ->orWhere('legal_chunks.tags', 'like', $termLike)
                    ->orWhere('legacy_laws.category', 'like', $termLike)
                    ->orWhere('legacy_laws.tags', 'like', $termLike)
                    ->orWhere('legal_sources.name', 'like', $termLike);
            });
        });

        $documentScoreSql = $this->corpusDocumentScoreSql($keyword, $documentHints, $referencePatterns, $queryTaxonomy);
        $articleScoreSql = $this->corpusArticleScoreSql($articleNumber);
        $fullTextScoreSql = $this->corpusFullTextScoreSql($keyword, $terms->all());
        $metadataScoreSql = $this->corpusMetadataScoreSql($keyword, $documentHints, $referencePatterns, $queryTaxonomy);
        $articleTitleScoreSql = $this->corpusArticleTitleScoreSql($keyword, $terms->all());
        $semanticScoreSql = $this->corpusSemanticScoreSql($semanticScores);
        $authorityScoreSql = $this->corpusSourceAuthorityScoreSql();
        $scoreSql = $this->corpusScoreSql($keyword, $articleNumber, $documentHints, $referencePatterns, $terms->all(), $queryTaxonomy);
        $rows = $query
            ->selectRaw($documentScoreSql['sql'].' AS document_match_score', $documentScoreSql['bindings'])
            ->selectRaw($articleScoreSql['sql'].' AS article_match_score', $articleScoreSql['bindings'])
            ->selectRaw($fullTextScoreSql['sql'].' AS full_text_score', $fullTextScoreSql['bindings'])
            ->selectRaw($metadataScoreSql['sql'].' AS metadata_score', $metadataScoreSql['bindings'])
            ->selectRaw($articleTitleScoreSql['sql'].' AS article_title_score', $articleTitleScoreSql['bindings'])
            ->selectRaw($semanticScoreSql['sql'].' AS semantic_score', $semanticScoreSql['bindings'])
            ->selectRaw($authorityScoreSql['sql'].' AS source_authority_score', $authorityScoreSql['bindings'])
            ->selectRaw($this->articleSortSql('legal_articles.article_number').' AS article_sort_number')
            ->selectRaw($scoreSql['sql'].' AS relevance_score', $scoreSql['bindings'])
            ->orderByDesc('article_match_score')
            ->orderByDesc('document_match_score')
            ->orderByDesc('semantic_score')
            ->orderByDesc('source_authority_score')
            ->orderByDesc('relevance_score')
            ->orderBy('legal_documents.document_title')
            ->orderBy('article_sort_number')
            ->orderBy('legal_articles.article_number')
            ->orderBy('legal_chunks.chunk_index')
            ->limit(max(21, $limit + 1))
            ->get();
        $rows = $this->exactCorpusArticleRows($articleNumber, $documentHints, $limit)
            ->concat($rows)
            ->unique(fn (object $row): string => (string) ($row->legal_chunk_id ?? '').':'.(string) ($row->legal_article_id ?? ''))
            ->values();
        $rankedRows = $this->rerankCorpusRows($rows->take(20), $keyword, $queryTaxonomy);
        $selectedRows = $rankedRows->take(min($limit, 5));

        return [
            'results' => $this->formatCorpusRows($selectedRows),
            'hasMore' => $rows->count() > $selectedRows->count(),
            'limit' => $limit,
        ];
    }

    private function shouldUseDomainFastPath(
        string $keyword,
        array $queryTaxonomy,
        Collection $terms,
        ?string $articleNumber,
        array $documentHints,
        array $referencePatterns,
        array $options
    ): bool {
        if (($options['disableDomainFastPath'] ?? false) || empty($queryTaxonomy['domain'])) {
            return false;
        }

        if ($articleNumber || $documentHints || $referencePatterns) {
            return false;
        }

        $normalized = $this->normalizeSearchText($keyword);
        $domain = (string) $queryTaxonomy['domain'];

        if ($normalized === $this->normalizeSearchText($domain)) {
            return true;
        }

        $domainScore = (int) ($queryTaxonomy['scores'][$domain] ?? 0);

        return $terms->count() <= 5 && $domainScore >= 4;
    }

    private function shouldUseDocumentTitleFastPath(
        string $keyword,
        array $documentHints,
        ?string $articleNumber,
        array $referencePatterns,
        array $options
    ): bool {
        if (($options['disableDocumentTitleFastPath'] ?? false) || !$documentHints || $articleNumber || $referencePatterns) {
            return false;
        }

        $normalized = $this->normalizeSearchText($keyword);

        return collect($documentHints)
            ->contains(fn (string $title): bool => $normalized === $this->normalizeSearchText($title));
    }

    private function shouldUseDocumentConstrainedFastPath(
        array $documentHints,
        ?string $articleNumber,
        array $referencePatterns,
        array $options
    ): bool {
        return !($options['disableDocumentConstrainedFastPath'] ?? false)
            && $documentHints
            && !$articleNumber
            && !$referencePatterns;
    }

    private function runDocumentTitleCorpusSearch(array $documentHints, int $limit, array $queryTaxonomy, array $options): array
    {
        $authorityScoreSql = $this->corpusSourceAuthorityScoreSql();
        $query = $this->activeCorpusBaseQuery()
            ->select($this->corpusFields())
            ->selectRaw('1400 AS document_match_score')
            ->selectRaw('0 AS article_match_score')
            ->selectRaw('0 AS full_text_score')
            ->selectRaw('1200 AS metadata_score')
            ->selectRaw('0 AS article_title_score')
            ->selectRaw('0 AS semantic_score')
            ->selectRaw($authorityScoreSql['sql'].' AS source_authority_score', $authorityScoreSql['bindings'])
            ->selectRaw($this->articleSortSql('legal_articles.article_number').' AS article_sort_number')
            ->selectRaw('2200 AS relevance_score')
            ->whereIn('legal_documents.document_title', $documentHints);

        if (!($options['includeChatOnlySources'] ?? false)) {
            $this->excludeChatOnlyCorpusSources($query);
        }

        if ($domain = ($queryTaxonomy['domain'] ?? null)) {
            $query->orderByRaw(
                '(CASE WHEN legal_documents.domain = ? THEN 3 WHEN legal_articles.domain = ? THEN 2 WHEN legal_chunks.domain = ? THEN 1 ELSE 0 END) DESC',
                [$domain, $domain, $domain]
            );
        }

        $rows = $query
            ->orderByDesc('source_authority_score')
            ->orderBy('legal_documents.document_title')
            ->orderBy('article_sort_number')
            ->orderBy('legal_articles.article_number')
            ->orderBy('legal_chunks.chunk_index')
            ->limit(max($limit + 1, $limit * 4))
            ->get()
            ->unique(fn (object $row): string => (string) ($row->legal_article_id ?? $row->legal_chunk_id))
            ->values();

        return [
            'results' => $this->formatCorpusRows($rows->take($limit)),
            'hasMore' => $rows->count() > $limit,
            'limit' => $limit,
        ];
    }

    private function runDocumentConstrainedCorpusSearch(
        string $keyword,
        array $documentHints,
        Collection $terms,
        int $limit,
        array $queryTaxonomy,
        array $options
    ): array {
        $authorityScoreSql = $this->corpusSourceAuthorityScoreSql();
        $fullTextScoreSql = $this->corpusFullTextScoreSql($keyword, $terms->all());
        $articleTitleScoreSql = $this->corpusArticleTitleScoreSql($keyword, $terms->all());
        $metadataScoreSql = $this->corpusMetadataScoreSql($keyword, $documentHints, [], $queryTaxonomy);
        $query = $this->activeCorpusBaseQuery()
            ->select($this->corpusFields())
            ->selectRaw('1200 AS document_match_score')
            ->selectRaw('0 AS article_match_score')
            ->selectRaw($fullTextScoreSql['sql'].' AS full_text_score', $fullTextScoreSql['bindings'])
            ->selectRaw($metadataScoreSql['sql'].' AS metadata_score', $metadataScoreSql['bindings'])
            ->selectRaw($articleTitleScoreSql['sql'].' AS article_title_score', $articleTitleScoreSql['bindings'])
            ->selectRaw('0 AS semantic_score')
            ->selectRaw($authorityScoreSql['sql'].' AS source_authority_score', $authorityScoreSql['bindings'])
            ->selectRaw($this->articleSortSql('legal_articles.article_number').' AS article_sort_number')
            ->selectRaw('1800 AS relevance_score')
            ->whereIn('legal_documents.document_title', $documentHints);

        if (!($options['includeChatOnlySources'] ?? false)) {
            $this->excludeChatOnlyCorpusSources($query);
        }

        if ($domain = ($queryTaxonomy['domain'] ?? null)) {
            $query->orderByRaw(
                '(CASE WHEN legal_documents.domain = ? THEN 3 WHEN legal_articles.domain = ? THEN 2 WHEN legal_chunks.domain = ? THEN 1 ELSE 0 END) DESC',
                [$domain, $domain, $domain]
            );
        }

        $rows = $query
            ->orderByDesc('article_title_score')
            ->orderByDesc('full_text_score')
            ->orderByDesc('metadata_score')
            ->orderByDesc('source_authority_score')
            ->orderBy('legal_documents.document_title')
            ->orderBy('article_sort_number')
            ->orderBy('legal_articles.article_number')
            ->orderBy('legal_chunks.chunk_index')
            ->limit(max($limit + 1, $limit * 4))
            ->get()
            ->unique(fn (object $row): string => (string) ($row->legal_article_id ?? $row->legal_chunk_id))
            ->values();

        return [
            'results' => $this->formatCorpusRows($rows->take($limit)),
            'hasMore' => $rows->count() > $limit,
            'limit' => $limit,
        ];
    }

    private function runDomainCorpusSearch(string $keyword, int $limit, array $queryTaxonomy, array $options): array
    {
        $domain = (string) $queryTaxonomy['domain'];
        $subdomain = $queryTaxonomy['subdomain'] ?? null;
        $authorityScoreSql = $this->corpusSourceAuthorityScoreSql();
        $primaryTitleScoreSql = $this->domainPrimaryTitleScoreSql($domain);
        $query = $this->activeCorpusBaseQuery()
            ->select($this->corpusFields())
            ->selectRaw('900 AS document_match_score')
            ->selectRaw('0 AS article_match_score')
            ->selectRaw('0 AS full_text_score')
            ->selectRaw('600 AS metadata_score')
            ->selectRaw('0 AS article_title_score')
            ->selectRaw('0 AS semantic_score')
            ->selectRaw($authorityScoreSql['sql'].' AS source_authority_score', $authorityScoreSql['bindings'])
            ->selectRaw($primaryTitleScoreSql['sql'].' AS primary_title_score', $primaryTitleScoreSql['bindings'])
            ->selectRaw($this->articleSortSql('legal_articles.article_number').' AS article_sort_number')
            ->selectRaw('1200 AS relevance_score');

        if (!($options['includeChatOnlySources'] ?? false)) {
            $this->excludeChatOnlyCorpusSources($query);
        }

        $query->where(function ($where) use ($domain): void {
            $where->where('legal_documents.domain', $domain)
                ->orWhere('legal_articles.domain', $domain)
                ->orWhere('legal_chunks.domain', $domain);

            if ($domain === 'official-bulletin') {
                $where->orWhere('legal_sources.source_type', 'BO')
                    ->orWhere('legal_documents.document_type', 'BO');
            }
        });

        if ($subdomain) {
            $query->orderByRaw(
                '(CASE WHEN legal_documents.subdomain = ? THEN 3 WHEN legal_articles.subdomain = ? THEN 2 WHEN legal_chunks.subdomain = ? THEN 1 ELSE 0 END) DESC',
                [$subdomain, $subdomain, $subdomain]
            );
        }

        $rows = $query
            ->orderByDesc('primary_title_score')
            ->orderByDesc('source_authority_score')
            ->orderBy('legal_documents.document_title')
            ->orderBy('article_sort_number')
            ->orderBy('legal_articles.article_number')
            ->orderBy('legal_chunks.chunk_index')
            ->limit(max($limit + 1, $limit * 4))
            ->get()
            ->unique(fn (object $row): string => (string) ($row->legal_article_id ?? $row->legal_chunk_id))
            ->values();

        return [
            'results' => $this->formatCorpusRows($rows->take($limit)),
            'hasMore' => $rows->count() > $limit,
            'limit' => $limit,
        ];
    }

    private function domainPrimaryTitleScoreSql(string $domain): array
    {
        $titles = self::PRIMARY_DOCUMENT_TITLES_BY_DOMAIN[$domain] ?? [];

        if (!$titles) {
            return ['sql' => '0', 'bindings' => []];
        }

        $clauses = [];
        $bindings = [];

        foreach ($titles as $index => $title) {
            $clauses[] = 'WHEN ? THEN '.(1000 - ($index * 20));
            $bindings[] = $title;
        }

        return [
            'sql' => '(CASE legal_documents.document_title '.implode(' ', $clauses).' ELSE 0 END)',
            'bindings' => $bindings,
        ];
    }

    private function runLatestOfficialBulletinCorpus(int $limit): array
    {
        $rows = $this->activeCorpusBaseQuery()
            ->select($this->corpusFields())
            ->selectRaw($this->corpusBulletinSortSql().' AS bulletin_sort_number')
            ->selectRaw($this->articleSortSql('legal_articles.article_number').' AS article_sort_number')
            ->selectRaw('1000 AS document_match_score')
            ->selectRaw('1000 AS article_match_score')
            ->selectRaw('1000 AS relevance_score')
            ->where(fn ($query) => $query
                ->whereIn('legal_documents.domain', self::CHAT_ONLY_CATEGORIES)
                ->orWhere('legal_sources.source_type', 'BO')
                ->orWhere('legal_documents.document_type', 'BO'))
            ->orderByDesc('bulletin_sort_number')
            ->orderByDesc('legal_documents.publication_date')
            ->orderByDesc('legal_documents.document_title')
            ->orderBy('article_sort_number')
            ->orderBy('legal_articles.article_number')
            ->orderBy('legal_chunks.chunk_index')
            ->limit($limit + 1)
            ->get();

        return [
            'results' => $this->formatCorpusRows($rows->take($limit)),
            'hasMore' => $rows->count() > $limit,
            'limit' => $limit,
        ];
    }

    private function exactCorpusArticleRows(?string $articleNumber, array $documentHints, int $limit): Collection
    {
        if (!$articleNumber) {
            return collect();
        }

        $authorityScoreSql = $this->corpusSourceAuthorityScoreSql();
        $query = $this->activeCorpusBaseQuery()
            ->select($this->corpusFields())
            ->selectRaw('2000 AS document_match_score')
            ->selectRaw('5000 AS article_match_score')
            ->selectRaw('0 AS full_text_score')
            ->selectRaw('0 AS metadata_score')
            ->selectRaw('0 AS article_title_score')
            ->selectRaw('0 AS semantic_score')
            ->selectRaw($authorityScoreSql['sql'].' AS source_authority_score', $authorityScoreSql['bindings'])
            ->selectRaw($this->articleSortSql('legal_articles.article_number').' AS article_sort_number')
            ->selectRaw('7000 AS relevance_score')
            ->where('legal_articles.article_number', $articleNumber);

        if ($documentHints) {
            $query->whereIn('legal_documents.document_title', $documentHints);
        }

        return $query
            ->orderBy('legal_documents.document_title')
            ->orderBy('legal_chunks.chunk_index')
            ->limit($limit)
            ->get();
    }

    private function runLegacySearch(string $keyword, int $limit, array $options): array
    {
        $articleNumber = $this->extractArticleNumber($keyword);
        $documentHints = $this->extractDocumentTitleHints($keyword);
        $referencePatterns = $this->extractReferencePatterns($keyword);
        $terms = $this->keywordTerms($keyword);

        $query = Law::query()->select($this->baseFields());

        if (!($options['includeChatOnlySources'] ?? false)) {
            $this->excludeChatOnlySources($query);
        }

        $query->where(function (Builder $where) use ($keyword, $articleNumber, $documentHints, $referencePatterns, $terms): void {
            $like = '%'.$keyword.'%';

            foreach (['title', 'document_title', 'source_name', 'category', 'law_reference', 'article_number', 'content'] as $column) {
                $where->orWhere($column, 'like', $like);
            }

            if ($articleNumber) {
                $where->orWhere('article_number', $articleNumber);
            }

            foreach ($documentHints as $title) {
                $where->orWhere('document_title', $title);
            }

            foreach ($referencePatterns as $pattern) {
                $where->orWhere('law_reference', 'like', $pattern);
            }

            $terms->each(function (string $term) use ($where): void {
                $where->orWhere('title', 'like', '%'.$term.'%')
                    ->orWhere('document_title', 'like', '%'.$term.'%')
                    ->orWhere('law_reference', 'like', '%'.$term.'%');
            });
        });

        $documentScoreSql = $this->documentScoreSql($keyword, $documentHints, $referencePatterns);
        $articleScoreSql = $this->articleScoreSql($articleNumber);
        $scoreSql = $this->scoreSql($keyword, $articleNumber, $documentHints, $referencePatterns, $terms->all());
        $rows = $query
            ->selectRaw($documentScoreSql['sql'].' AS document_match_score', $documentScoreSql['bindings'])
            ->selectRaw($articleScoreSql['sql'].' AS article_match_score', $articleScoreSql['bindings'])
            ->selectRaw($this->articleSortSql().' AS article_sort_number')
            ->selectRaw($scoreSql['sql'].' AS relevance_score', $scoreSql['bindings'])
            ->orderByDesc('article_match_score')
            ->orderByDesc('document_match_score')
            ->orderByDesc('relevance_score')
            ->orderBy('document_title')
            ->orderBy('article_sort_number')
            ->orderBy('article_number')
            ->orderBy('title')
            ->limit($limit + 1)
            ->get();

        return [
            'results' => $this->formatLegacyRows($rows->take($limit)),
            'hasMore' => $rows->count() > $limit,
            'limit' => $limit,
        ];
    }

    private function corpusDocumentScoreSql(string $keyword, array $documentHints, array $referencePatterns, array $queryTaxonomy): array
    {
        $like = '%'.$keyword.'%';
        $clauses = [
            'CASE WHEN legal_documents.document_title = ? THEN 120 ELSE 0 END',
            'CASE WHEN legal_documents.law_reference = ? THEN 110 ELSE 0 END',
            'CASE WHEN legal_documents.document_title LIKE ? THEN 80 ELSE 0 END',
            'CASE WHEN legal_sources.name LIKE ? THEN 75 ELSE 0 END',
            'CASE WHEN legal_documents.domain = ? THEN 72 ELSE 0 END',
            'CASE WHEN legal_documents.domain LIKE ? THEN 70 ELSE 0 END',
            'CASE WHEN legal_documents.law_reference LIKE ? THEN 70 ELSE 0 END',
            'CASE WHEN legal_documents.domain LIKE ? THEN 25 ELSE 0 END',
        ];
        $bindings = [$keyword, $keyword, $like, $like, $keyword, $like, $like, $like];

        foreach ($documentHints as $title) {
            $clauses[] = 'CASE WHEN legal_documents.document_title = ? THEN 220 ELSE 0 END';
            $bindings[] = $title;
        }

        foreach ($referencePatterns as $pattern) {
            $clauses[] = 'CASE WHEN legal_documents.law_reference LIKE ? THEN 160 ELSE 0 END';
            $bindings[] = $pattern;
            $clauses[] = 'CASE WHEN legal_documents.document_title LIKE ? THEN 120 ELSE 0 END';
            $bindings[] = $pattern;
        }

        if ($domain = ($queryTaxonomy['domain'] ?? null)) {
            $clauses[] = 'CASE WHEN legal_documents.domain = ? THEN 260 ELSE 0 END';
            $bindings[] = $domain;
            $clauses[] = 'CASE WHEN legal_articles.domain = ? THEN 180 ELSE 0 END';
            $bindings[] = $domain;
            $clauses[] = 'CASE WHEN legal_chunks.domain = ? THEN 120 ELSE 0 END';
            $bindings[] = $domain;
            $clauses[] = 'CASE WHEN legal_documents.domain IS NOT NULL AND legal_documents.domain <> ? THEN -110 ELSE 0 END';
            $bindings[] = $domain;
        }

        if ($subdomain = ($queryTaxonomy['subdomain'] ?? null)) {
            $clauses[] = 'CASE WHEN legal_documents.subdomain = ? THEN 120 ELSE 0 END';
            $bindings[] = $subdomain;
            $clauses[] = 'CASE WHEN legal_articles.subdomain = ? THEN 90 ELSE 0 END';
            $bindings[] = $subdomain;
            $clauses[] = 'CASE WHEN legal_chunks.subdomain = ? THEN 70 ELSE 0 END';
            $bindings[] = $subdomain;
        }

        foreach (array_slice($queryTaxonomy['tags'] ?? [], 0, 10) as $tag) {
            $tagLike = '%'.$tag.'%';
            $clauses[] = 'CASE WHEN legal_documents.tags LIKE ? THEN 26 ELSE 0 END';
            $bindings[] = $tagLike;
            $clauses[] = 'CASE WHEN legal_articles.tags LIKE ? THEN 22 ELSE 0 END';
            $bindings[] = $tagLike;
            $clauses[] = 'CASE WHEN legal_chunks.tags LIKE ? THEN 18 ELSE 0 END';
            $bindings[] = $tagLike;
        }

        return [
            'sql' => '('.implode(' + ', $clauses).')',
            'bindings' => $bindings,
        ];
    }

    private function corpusFullTextScoreSql(string $keyword, array $terms): array
    {
        $like = '%'.$keyword.'%';
        $clauses = [
            'CASE WHEN legal_chunks.content LIKE ? THEN 120 ELSE 0 END',
            'CASE WHEN legal_articles.content LIKE ? THEN 105 ELSE 0 END',
        ];
        $bindings = [$like, $like];

        foreach ($terms as $term) {
            $termLike = '%'.$term.'%';
            $clauses[] = 'CASE WHEN legal_chunks.content LIKE ? THEN 16 ELSE 0 END';
            $bindings[] = $termLike;
            $clauses[] = 'CASE WHEN legal_articles.content LIKE ? THEN 12 ELSE 0 END';
            $bindings[] = $termLike;
        }

        return [
            'sql' => '('.implode(' + ', $clauses).')',
            'bindings' => $bindings,
        ];
    }

    private function corpusArticleTitleScoreSql(string $keyword, array $terms): array
    {
        $clauses = [
            'CASE WHEN legal_articles.article_title = ? THEN 180 ELSE 0 END',
            'CASE WHEN legal_articles.article_title LIKE ? THEN 130 ELSE 0 END',
        ];
        $bindings = [$keyword, $keyword.'%'];

        foreach ($terms as $term) {
            $clauses[] = 'CASE WHEN legal_articles.article_title LIKE ? THEN 24 ELSE 0 END';
            $bindings[] = '%'.$term.'%';
        }

        return [
            'sql' => '('.implode(' + ', $clauses).')',
            'bindings' => $bindings,
        ];
    }

    private function corpusMetadataScoreSql(string $keyword, array $documentHints, array $referencePatterns, array $queryTaxonomy): array
    {
        $like = '%'.$keyword.'%';
        $clauses = [
            'CASE WHEN legal_documents.document_title LIKE ? THEN 85 ELSE 0 END',
            'CASE WHEN legal_sources.name LIKE ? THEN 70 ELSE 0 END',
            'CASE WHEN legal_documents.law_reference LIKE ? THEN 65 ELSE 0 END',
            'CASE WHEN legal_documents.domain LIKE ? THEN 50 ELSE 0 END',
            'CASE WHEN legal_documents.subdomain LIKE ? THEN 45 ELSE 0 END',
            'CASE WHEN legal_articles.domain LIKE ? THEN 40 ELSE 0 END',
            'CASE WHEN legal_articles.subdomain LIKE ? THEN 35 ELSE 0 END',
            'CASE WHEN legal_chunks.domain LIKE ? THEN 30 ELSE 0 END',
            'CASE WHEN legal_chunks.subdomain LIKE ? THEN 25 ELSE 0 END',
        ];
        $bindings = [$like, $like, $like, $like, $like, $like, $like, $like, $like];

        foreach ($documentHints as $title) {
            $clauses[] = 'CASE WHEN legal_documents.document_title = ? THEN 220 ELSE 0 END';
            $bindings[] = $title;
        }

        foreach ($referencePatterns as $pattern) {
            $clauses[] = 'CASE WHEN legal_documents.law_reference LIKE ? THEN 160 ELSE 0 END';
            $bindings[] = $pattern;
        }

        if ($domain = ($queryTaxonomy['domain'] ?? null)) {
            $clauses[] = 'CASE WHEN legal_documents.domain = ? THEN 260 ELSE 0 END';
            $bindings[] = $domain;
            $clauses[] = 'CASE WHEN legal_articles.domain = ? THEN 180 ELSE 0 END';
            $bindings[] = $domain;
            $clauses[] = 'CASE WHEN legal_chunks.domain = ? THEN 120 ELSE 0 END';
            $bindings[] = $domain;
            $clauses[] = 'CASE WHEN legal_documents.domain IS NOT NULL AND legal_documents.domain <> ? THEN -120 ELSE 0 END';
            $bindings[] = $domain;
        }

        if ($subdomain = ($queryTaxonomy['subdomain'] ?? null)) {
            $clauses[] = 'CASE WHEN legal_documents.subdomain = ? THEN 130 ELSE 0 END';
            $bindings[] = $subdomain;
            $clauses[] = 'CASE WHEN legal_articles.subdomain = ? THEN 95 ELSE 0 END';
            $bindings[] = $subdomain;
            $clauses[] = 'CASE WHEN legal_chunks.subdomain = ? THEN 70 ELSE 0 END';
            $bindings[] = $subdomain;
        }

        foreach (array_slice($queryTaxonomy['tags'] ?? [], 0, 10) as $tag) {
            $tagLike = '%'.$tag.'%';
            $clauses[] = 'CASE WHEN legal_documents.tags LIKE ? THEN 24 ELSE 0 END';
            $bindings[] = $tagLike;
            $clauses[] = 'CASE WHEN legal_articles.tags LIKE ? THEN 20 ELSE 0 END';
            $bindings[] = $tagLike;
            $clauses[] = 'CASE WHEN legal_chunks.tags LIKE ? THEN 16 ELSE 0 END';
            $bindings[] = $tagLike;
        }

        return [
            'sql' => '('.implode(' + ', $clauses).')',
            'bindings' => $bindings,
        ];
    }

    private function corpusArticleScoreSql(?string $articleNumber): array
    {
        $clauses = ['0'];
        $bindings = [];

        if ($articleNumber) {
            $clauses[] = 'CASE WHEN legal_articles.article_number = ? THEN 180 ELSE 0 END';
            $bindings[] = $articleNumber;
            $clauses[] = 'CASE WHEN legal_articles.article_number LIKE ? THEN 90 ELSE 0 END';
            $bindings[] = $articleNumber.'%';
        }

        return [
            'sql' => '('.implode(' + ', $clauses).')',
            'bindings' => $bindings,
        ];
    }

    private function corpusScoreSql(string $keyword, ?string $articleNumber, array $documentHints, array $referencePatterns, array $terms, array $queryTaxonomy): array
    {
        $fullTextScore = $this->corpusFullTextScoreSql($keyword, $terms);
        $metadataScore = $this->corpusMetadataScoreSql($keyword, $documentHints, $referencePatterns, $queryTaxonomy);
        $articleTitleScore = $this->corpusArticleTitleScoreSql($keyword, $terms);
        $clauses = [
            $fullTextScore['sql'],
            $metadataScore['sql'],
            $articleTitleScore['sql'],
            'CASE WHEN legal_articles.article_number = ? THEN 60 ELSE 0 END',
        ];
        $bindings = [
            ...$fullTextScore['bindings'],
            ...$metadataScore['bindings'],
            ...$articleTitleScore['bindings'],
            $keyword,
        ];

        if ($articleNumber) {
            $clauses[] = 'CASE WHEN legal_articles.article_number = ? THEN 180 ELSE 0 END';
            $bindings[] = $articleNumber;
        }

        return [
            'sql' => '('.implode(' + ', $clauses).')',
            'bindings' => $bindings,
        ];
    }

    private function corpusSourceAuthorityScoreSql(): array
    {
        return [
            'sql' => "(
                CASE WHEN legal_documents.status = 'active' THEN 120 ELSE 0 END
                + CASE WHEN legal_document_versions.status = 'active' THEN 120 ELSE 0 END
                + CASE WHEN legal_articles.status = 'active' THEN 90 ELSE 0 END
                + CASE WHEN legal_documents.current_version_id = legal_document_versions.id THEN 160 ELSE 0 END
                + CASE
                    WHEN lower(COALESCE(legal_sources.source_type, '')) IN ('code', 'dahir', 'loi', 'decret', 'arrete', 'bo') THEN 90
                    WHEN lower(COALESCE(legal_sources.source_type, '')) IN ('official', 'official-bulletin') THEN 80
                    WHEN lower(COALESCE(legal_sources.source_type, '')) IN ('legacy', 'imported', 'scraped') THEN -80
                    ELSE 15
                  END
                + CASE WHEN legal_documents.document_type IN ('code', 'dahir', 'loi', 'decret', 'arrete', 'BO') THEN 45 ELSE 0 END
                + CASE WHEN legal_documents.publication_date IS NOT NULL THEN 10 ELSE 0 END
            )",
            'bindings' => [],
        ];
    }

    private function documentScoreSql(string $keyword, array $documentHints, array $referencePatterns): array
    {
        $like = '%'.$keyword.'%';
        $clauses = [
            'CASE WHEN document_title = ? THEN 120 ELSE 0 END',
            'CASE WHEN law_reference = ? THEN 110 ELSE 0 END',
            'CASE WHEN document_title LIKE ? THEN 80 ELSE 0 END',
            'CASE WHEN source_name LIKE ? THEN 75 ELSE 0 END',
            'CASE WHEN category = ? THEN 72 ELSE 0 END',
            'CASE WHEN category LIKE ? THEN 70 ELSE 0 END',
            'CASE WHEN law_reference LIKE ? THEN 70 ELSE 0 END',
            'CASE WHEN category LIKE ? THEN 25 ELSE 0 END',
        ];
        $bindings = [$keyword, $keyword, $like, $like, $keyword, $like, $like, $like];

        foreach ($documentHints as $title) {
            $clauses[] = 'CASE WHEN document_title = ? THEN 220 ELSE 0 END';
            $bindings[] = $title;
        }

        foreach ($referencePatterns as $pattern) {
            $clauses[] = 'CASE WHEN law_reference LIKE ? THEN 160 ELSE 0 END';
            $bindings[] = $pattern;
            $clauses[] = 'CASE WHEN document_title LIKE ? THEN 120 ELSE 0 END';
            $bindings[] = $pattern;
        }

        return [
            'sql' => '('.implode(' + ', $clauses).')',
            'bindings' => $bindings,
        ];
    }

    private function articleScoreSql(?string $articleNumber): array
    {
        $clauses = ['0'];
        $bindings = [];

        if ($articleNumber) {
            $clauses[] = 'CASE WHEN article_number = ? THEN 180 ELSE 0 END';
            $bindings[] = $articleNumber;
            $clauses[] = 'CASE WHEN article_number LIKE ? THEN 90 ELSE 0 END';
            $bindings[] = $articleNumber.'%';
        }

        return [
            'sql' => '('.implode(' + ', $clauses).')',
            'bindings' => $bindings,
        ];
    }

    private function scoreSql(string $keyword, ?string $articleNumber, array $documentHints, array $referencePatterns, array $terms): array
    {
        $like = '%'.$keyword.'%';
        $clauses = [
            'CASE WHEN title = ? THEN 160 ELSE 0 END',
            'CASE WHEN title LIKE ? THEN 120 ELSE 0 END',
            'CASE WHEN document_title LIKE ? THEN 85 ELSE 0 END',
            'CASE WHEN source_name LIKE ? THEN 80 ELSE 0 END',
            'CASE WHEN category LIKE ? THEN 75 ELSE 0 END',
            'CASE WHEN law_reference LIKE ? THEN 70 ELSE 0 END',
            'CASE WHEN article_number = ? THEN 60 ELSE 0 END',
        ];
        $bindings = [$keyword, $keyword.'%', $like, $like, $like, $like, $keyword];

        $booleanSearchTerm = $this->buildBooleanSearchTerm($keyword);
        if (DB::getDriverName() === 'mysql' && $booleanSearchTerm !== '') {
            $clauses[] = 'COALESCE(MATCH(title, document_title, law_reference, content) AGAINST (? IN BOOLEAN MODE), 0) * 25';
            $bindings[] = $booleanSearchTerm;
        }

        if ($articleNumber) {
            $clauses[] = 'CASE WHEN article_number = ? THEN 180 ELSE 0 END';
            $bindings[] = $articleNumber;
        }

        foreach ($documentHints as $title) {
            $clauses[] = 'CASE WHEN document_title = ? THEN 220 ELSE 0 END';
            $bindings[] = $title;
        }

        foreach ($referencePatterns as $pattern) {
            $clauses[] = 'CASE WHEN law_reference LIKE ? THEN 160 ELSE 0 END';
            $bindings[] = $pattern;
        }

        foreach ($terms as $term) {
            $termLike = '%'.$term.'%';
            $clauses[] = 'CASE WHEN title LIKE ? THEN 24 ELSE 0 END';
            $bindings[] = $termLike;
            $clauses[] = 'CASE WHEN document_title LIKE ? THEN 16 ELSE 0 END';
            $bindings[] = $termLike;
            $clauses[] = 'CASE WHEN source_name LIKE ? THEN 16 ELSE 0 END';
            $bindings[] = $termLike;
            $clauses[] = 'CASE WHEN category LIKE ? THEN 14 ELSE 0 END';
            $bindings[] = $termLike;
            $clauses[] = 'CASE WHEN law_reference LIKE ? THEN 14 ELSE 0 END';
            $bindings[] = $termLike;
            $clauses[] = 'CASE WHEN article_number LIKE ? THEN 10 ELSE 0 END';
            $bindings[] = $termLike;
        }

        return [
            'sql' => '('.implode(' + ', $clauses).')',
            'bindings' => $bindings,
        ];
    }

    private function articleSortSql(string $column = 'article_number'): string
    {
        if (DB::getDriverName() === 'mysql') {
            return "CASE WHEN {$column} LIKE 'Article premier%' THEN 1 ELSE CAST(SUBSTRING_INDEX(SUBSTRING_INDEX({$column}, ' ', 2), ' ', -1) AS UNSIGNED) END";
        }

        return "CASE WHEN lower({$column}) LIKE 'article premier%' THEN 1 ELSE CAST(replace(replace(replace(replace(lower({$column}), 'article ', ''), 'bis', ''), 'ter', ''), 'quater', '') AS INTEGER) END";
    }

    private function bulletinSortSql(): string
    {
        if (DB::getDriverName() === 'mysql') {
            return "CAST(REPLACE(REPLACE(SUBSTRING_INDEX(SUBSTRING_INDEX(law_reference, '/', 1), 'n ', -1), '-bis', ''), ' ', '') AS UNSIGNED)";
        }

        return "CASE WHEN instr(lower(law_reference), 'n ') > 0 THEN CAST(replace(replace(substr(lower(law_reference), instr(lower(law_reference), 'n ') + 2), '-bis', ''), ' ', '') AS INTEGER) ELSE CAST(replace(replace(lower(law_reference), '-bis', ''), ' ', '') AS INTEGER) END";
    }

    private function corpusBulletinSortSql(): string
    {
        if (DB::getDriverName() === 'mysql') {
            return "CAST(REPLACE(REPLACE(COALESCE(legal_documents.bo_number, '0'), '-bis', ''), ' ', '') AS UNSIGNED)";
        }

        return "CAST(replace(replace(COALESCE(legal_documents.bo_number, '0'), '-bis', ''), ' ', '') AS INTEGER)";
    }

    private function buildBooleanSearchTerm(string $keyword): string
    {
        $tokens = collect(preg_split('/\s+/', $keyword) ?: [])
            ->map(fn (string $token): string => trim(preg_replace('/[^\p{L}\p{N}-]/u', '', $token) ?? ''))
            ->filter(fn (string $token): bool => Str::length($token) >= 2)
            ->values();

        return $tokens->isEmpty()
            ? ''
            : $tokens->map(fn (string $token): string => '+'.$token.'*')->implode(' ');
    }

    private function formatCorpusRows(iterable $rows): array
    {
        return collect($rows)->map(function (object $row): array {
            $content = $row->chunk_content ?: $row->article_content;
            $title = $row->article_title ?: trim(($row->document_title ?? '').' '.($row->article_number ?? ''));
            $domain = $row->document_domain ?: ($row->article_domain ?: $row->chunk_domain);
            $subdomain = $row->document_subdomain ?: ($row->article_subdomain ?: $row->chunk_subdomain);
            $category = $row->legacy_category ?: $domain;
            $tags = $this->mergeTags($row->legacy_tags ?? null, $row->chunk_tags ?? null, $row->article_tags ?? null, $row->document_tags ?? null);

            return [
                'id' => $row->legacy_law_id ?: $row->legal_article_id,
                'legal_chunk_id' => (int) $row->legal_chunk_id,
                'legal_article_id' => (int) $row->legal_article_id,
                'legal_document_id' => (int) $row->legal_document_id,
                'legal_document_version_id' => (int) $row->legal_document_version_id,
                'legacy_law_id' => $row->legacy_law_id ? (int) $row->legacy_law_id : null,
                'title' => $title,
                'article_number' => $row->article_number,
                'content' => Str::limit($content ?? '', 900, ''),
                'document_title' => $row->document_title,
                'document_type' => $row->document_type,
                'law_reference' => $row->law_reference,
                'category' => $category,
                'domain' => $domain,
                'subdomain' => $subdomain,
                'source_name' => $row->source_name,
                'source_type' => $row->source_type,
                'source_url' => $this->safeSourceUrl($row->source_url),
                'language' => $row->article_language ?: $row->language,
                'tags' => $tags,
                'bo_number' => $row->bo_number,
                'publication_date' => $this->formatCorpusDate($row->publication_date),
                'effective_date' => $this->formatCorpusDate($row->effective_date),
                'version_number' => isset($row->version_number) ? (int) $row->version_number : null,
                'version_status' => $row->version_status,
                'document_status' => $row->document_status,
                'article_status' => $row->article_status,
                'source_table' => 'corpus',
                'is_legacy' => false,
                'document_match_score' => isset($row->document_match_score) ? (float) $row->document_match_score : null,
                'article_match_score' => isset($row->article_match_score) ? (float) $row->article_match_score : null,
                'full_text_score' => isset($row->full_text_score) ? (float) $row->full_text_score : null,
                'metadata_score' => isset($row->metadata_score) ? (float) $row->metadata_score : null,
                'article_title_score' => isset($row->article_title_score) ? (float) $row->article_title_score : null,
                'semantic_score' => isset($row->semantic_score) ? (float) $row->semantic_score : null,
                'source_authority_score' => isset($row->source_authority_score) ? (float) $row->source_authority_score : null,
                'rerank_score' => isset($row->rerank_score) ? (float) $row->rerank_score : null,
                'article_sort_number' => isset($row->article_sort_number) ? (int) $row->article_sort_number : null,
                'relevance_score' => isset($row->relevance_score) ? (float) $row->relevance_score : null,
            ];
        })->values()->all();
    }

    private function formatLegacyRows(iterable $rows): array
    {
        return collect($rows)->map(fn (Law $law) => [
            'id' => $law->id,
            'legal_chunk_id' => null,
            'legal_article_id' => null,
            'legal_document_id' => null,
            'legal_document_version_id' => null,
            'legacy_law_id' => $law->id,
            'title' => $law->title,
            'article_number' => $law->article_number,
            'content' => Str::limit($law->content, 900, ''),
            'tags' => $law->tags,
            'document_title' => $law->document_title,
            'document_type' => null,
            'law_reference' => $law->law_reference,
            'category' => $law->category,
            'source_name' => $law->source_name,
            'source_type' => 'legacy',
            'source_url' => $this->safeSourceUrl($law->source_url),
            'language' => $law->language,
            'bo_number' => null,
            'publication_date' => null,
            'effective_date' => null,
            'version_number' => null,
            'version_status' => 'legacy',
            'document_status' => 'legacy',
            'article_status' => 'legacy',
            'source_table' => 'legacy_laws',
            'is_legacy' => true,
            'document_match_score' => isset($law->document_match_score) ? (float) $law->document_match_score : null,
            'article_match_score' => isset($law->article_match_score) ? (float) $law->article_match_score : null,
            'article_sort_number' => isset($law->article_sort_number) ? (int) $law->article_sort_number : null,
            'source_authority_score' => -120,
            'relevance_score' => isset($law->relevance_score) ? (float) $law->relevance_score : null,
        ])->values()->all();
    }

    private function rerankCorpusRows(Collection $rows, string $keyword, array $queryTaxonomy): Collection
    {
        $tokens = $this->rerankTokens($keyword);

        return $rows
            ->map(function (object $row) use ($tokens, $queryTaxonomy): object {
                $row->rerank_score = $this->corpusRerankScore($row, $tokens, $queryTaxonomy);

                return $row;
            })
            ->sortByDesc(fn (object $row): float => (float) $row->rerank_score + ((float) ($row->relevance_score ?? 0) / 10000))
            ->values();
    }

    private function safeSourceUrl(?string $sourceUrl): ?string
    {
        $sourceUrl = trim((string) $sourceUrl);

        if ($sourceUrl === '') {
            return null;
        }

        if (str_starts_with($sourceUrl, 'http://') || str_starts_with($sourceUrl, 'https://')) {
            return $sourceUrl;
        }

        if (str_starts_with($sourceUrl, '/uploads/')) {
            return 'https://adala.justice.gov.ma/api'.$sourceUrl;
        }

        if (str_starts_with($sourceUrl, 'uploads/')) {
            return 'https://adala.justice.gov.ma/api/'.$sourceUrl;
        }

        if (str_starts_with($sourceUrl, '/api/uploads/')) {
            return 'https://adala.justice.gov.ma'.$sourceUrl;
        }

        if (str_starts_with($sourceUrl, 'api/uploads/')) {
            return 'https://adala.justice.gov.ma/'.$sourceUrl;
        }

        return null;
    }

    private function corpusRerankScore(object $row, array $tokens, array $queryTaxonomy): float
    {
        $domain = $row->chunk_domain ?: ($row->article_domain ?: $row->document_domain);
        $subdomain = $row->chunk_subdomain ?: ($row->article_subdomain ?: $row->document_subdomain);
        $tags = $this->mergeTags($row->chunk_tags ?? null, $row->article_tags ?? null, $row->document_tags ?? null);
        $titleText = $this->normalizeSearchText(implode(' ', array_filter([
            $row->article_title ?? '',
            $row->article_number ?? '',
            $row->document_title ?? '',
        ])));
        $bodyText = $this->normalizeSearchText(($row->chunk_content ?: $row->article_content) ?? '');
        $score = 0.0;
        $score += ((float) ($row->article_match_score ?? 0)) / 8;
        $score += ((float) ($row->full_text_score ?? 0)) / 30;
        $score += ((float) ($row->metadata_score ?? 0)) / 35;
        $score += ((float) ($row->article_title_score ?? 0)) / 20;
        $score += ((float) ($row->semantic_score ?? 0)) / 8;
        $score += ((float) ($row->source_authority_score ?? 0)) / 80;

        if (($queryTaxonomy['domain'] ?? null) && $domain === $queryTaxonomy['domain']) {
            $score += 10;
        } elseif (($queryTaxonomy['domain'] ?? null) && $domain) {
            $score -= 7;
        }

        if (($queryTaxonomy['subdomain'] ?? null) && $subdomain === $queryTaxonomy['subdomain']) {
            $score += 5;
        }

        $queryTags = $queryTaxonomy['tags'] ?? [];
        $score += count(array_intersect($tags, $queryTags)) * 1.4;

        foreach ($tokens as $token) {
            if (str_contains($titleText, $token)) {
                $score += 1.5;
            }

            if (str_contains($bodyText, $token)) {
                $score += 0.8;
            }
        }

        return round($score, 4);
    }

    private function semanticChunkScores(string $keyword, array $options): array
    {
        if (($options['disableSemanticSearch'] ?? false) || !$this->embeddings->isEnabled()) {
            return [];
        }

        $queryVector = $this->embeddings->embed($keyword);

        if (!$queryVector) {
            return [];
        }

        $candidateLimit = max(50, (int) config('legal_ai.semantic_search.candidate_limit', 2000));
        $resultLimit = max(1, (int) config('legal_ai.semantic_search.result_limit', 12));
        $minimumScore = (float) config('legal_ai.semantic_search.min_score', 0.55);
        $query = $this->activeCorpusBaseQuery()
            ->select([
                'legal_chunks.id AS legal_chunk_id',
                'legal_chunks.embedding',
                'legal_chunks.embedding_model',
            ])
            ->whereNotNull('legal_chunks.embedding')
            ->where('legal_chunks.embedding_model', $this->embeddings->model())
            ->limit($candidateLimit);

        if (!($options['includeChatOnlySources'] ?? false)) {
            $this->excludeChatOnlyCorpusSources($query);
        }

        return $query
            ->get()
            ->mapWithKeys(function (object $row) use ($queryVector, $minimumScore): array {
                $storedVector = $this->embeddings->decodeStoredVector($row->embedding);
                $score = $storedVector ? $this->embeddings->cosineSimilarity($queryVector, $storedVector) : 0.0;

                return $score >= $minimumScore
                    ? [(int) $row->legal_chunk_id => $score]
                    : [];
            })
            ->sortDesc()
            ->take($resultLimit)
            ->all();
    }

    private function corpusSemanticScoreSql(array $semanticScores): array
    {
        if (!$semanticScores) {
            return ['sql' => '0', 'bindings' => []];
        }

        $clauses = [];
        $bindings = [];

        foreach ($semanticScores as $chunkId => $score) {
            $clauses[] = 'WHEN ? THEN ?';
            $bindings[] = (int) $chunkId;
            $bindings[] = round((float) $score * 1000, 4);
        }

        return [
            'sql' => '(CASE legal_chunks.id '.implode(' ', $clauses).' ELSE 0 END)',
            'bindings' => $bindings,
        ];
    }

    private function rerankTokens(string $keyword): array
    {
        return collect(preg_split('/\s+/', $this->normalizeSearchText($keyword)) ?: [])
            ->filter(fn (string $token): bool => Str::length($token) >= 3 && !is_numeric($token))
            ->unique()
            ->take(24)
            ->values()
            ->all();
    }

    private function mergeTags(mixed ...$values): array
    {
        return collect($values)
            ->flatMap(fn (mixed $value): array => $this->decodeTags($value))
            ->map(fn (string $tag): string => Str::of($tag)->lower()->ascii()->replaceMatches('/[^a-z0-9]+/', '_')->trim('_')->toString())
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function decodeTags(mixed $value): array
    {
        if (is_array($value)) {
            return array_values(array_filter(array_map('strval', $value)));
        }

        if (!is_string($value) || trim($value) === '') {
            return [];
        }

        $decoded = json_decode($value, true);

        if (is_array($decoded)) {
            return array_values(array_filter(array_map('strval', $decoded)));
        }

        return collect(preg_split('/[,;|]/', $value) ?: [])
            ->map(fn (string $tag): string => trim($tag))
            ->filter()
            ->values()
            ->all();
    }

    private function corpusFields(): array
    {
        return [
            'legal_chunks.id AS legal_chunk_id',
            'legal_chunks.content AS chunk_content',
            'legal_articles.id AS legal_article_id',
            'legal_articles.legacy_law_id',
            'legal_articles.article_number',
            'legal_articles.article_title',
            'legal_articles.content AS article_content',
            'legal_articles.language AS article_language',
            'legal_articles.status AS article_status',
            'legal_documents.id AS legal_document_id',
            'legal_documents.document_title',
            'legal_documents.document_type',
            'legal_documents.law_reference',
            'legal_documents.bo_number',
            'legal_documents.publication_date',
            'legal_documents.effective_date',
            'legal_documents.language',
            'legal_documents.domain AS document_domain',
            'legal_documents.subdomain AS document_subdomain',
            'legal_documents.tags AS document_tags',
            'legal_documents.status AS document_status',
            'legal_document_versions.id AS legal_document_version_id',
            'legal_document_versions.version_number',
            'legal_document_versions.status AS version_status',
            'legal_articles.domain AS article_domain',
            'legal_articles.subdomain AS article_subdomain',
            'legal_articles.tags AS article_tags',
            'legal_chunks.domain AS chunk_domain',
            'legal_chunks.subdomain AS chunk_subdomain',
            'legal_chunks.tags AS chunk_tags',
            'legal_sources.name AS source_name',
            'legal_sources.source_type',
            'legacy_laws.category AS legacy_category',
            'legacy_laws.tags AS legacy_tags',
            DB::raw('COALESCE(legal_document_versions.source_url, legal_documents.source_url, legal_sources.source_url) AS source_url'),
        ];
    }

    private function formatCorpusDate(mixed $value): ?string
    {
        if (!$value) {
            return null;
        }

        return substr((string) $value, 0, 10);
    }

    private function activeCorpusBaseQuery()
    {
        return DB::table('legal_chunks')
            ->join('legal_articles', 'legal_articles.id', '=', 'legal_chunks.legal_article_id')
            ->join('legal_documents', 'legal_documents.id', '=', 'legal_articles.legal_document_id')
            ->join('legal_document_versions', function ($join): void {
                $join->on('legal_document_versions.id', '=', 'legal_chunks.legal_document_version_id')
                    ->on('legal_document_versions.id', '=', 'legal_articles.legal_document_version_id')
                    ->on('legal_document_versions.legal_document_id', '=', 'legal_documents.id');
            })
            ->leftJoin('legal_sources', 'legal_sources.id', '=', 'legal_documents.legal_source_id')
            ->leftJoin('laws AS legacy_laws', 'legacy_laws.id', '=', 'legal_articles.legacy_law_id')
            ->where('legal_documents.status', 'active')
            ->where('legal_document_versions.status', 'active')
            ->where('legal_articles.status', 'active')
            ->whereColumn('legal_documents.current_version_id', 'legal_document_versions.id');
    }

    private function hasActiveCorpus(): bool
    {
        return $this->activeCorpusBaseQuery()->exists();
    }

    private function baseFields(): array
    {
        return ['id', 'title', 'article_number', 'content', 'tags', 'document_title', 'law_reference', 'category', 'source_name', 'source_url', 'language'];
    }

    private function excludeChatOnlySources(Builder $query): Builder
    {
        return $query->where(fn (Builder $where) => $where
            ->whereNull('category')
            ->orWhereNotIn('category', self::CHAT_ONLY_CATEGORIES));
    }

    private function excludeChatOnlyCorpusSources($query)
    {
        return $query
            ->where(fn ($where) => $where
                ->whereNull('legacy_laws.category')
                ->orWhereNotIn('legacy_laws.category', self::CHAT_ONLY_CATEGORIES))
            ->where(fn ($where) => $where
                ->whereNull('legal_documents.domain')
                ->orWhereNotIn('legal_documents.domain', self::CHAT_ONLY_CATEGORIES))
            ->where(fn ($where) => $where
                ->whereNull('legal_articles.domain')
                ->orWhereNotIn('legal_articles.domain', self::CHAT_ONLY_CATEGORIES))
            ->where(fn ($where) => $where
                ->whereNull('legal_chunks.domain')
                ->orWhereNotIn('legal_chunks.domain', self::CHAT_ONLY_CATEGORIES))
            ->where(fn ($where) => $where
                ->whereNull('legal_sources.source_type')
                ->orWhereNotIn(DB::raw('lower(legal_sources.source_type)'), self::CHAT_ONLY_SOURCE_TYPES))
            ->where(fn ($where) => $where
                ->whereNull('legal_documents.document_type')
                ->orWhereNotIn(DB::raw('lower(legal_documents.document_type)'), self::CHAT_ONLY_SOURCE_TYPES));
    }

    private function isChatOnlySearchKeyword(string $keyword): bool
    {
        $normalized = $this->normalizeSearchText($keyword);

        return collect(self::CHAT_ONLY_SEARCH_ALIASES)
            ->contains(fn (string $alias) => str_contains($normalized, $this->normalizeSearchText($alias)));
    }

    private function extractArticleNumber(string $keyword): ?string
    {
        preg_match('/\b(?:article|art)\s*(premier|\d+(?:\s*(?:bis|ter|quater))?)\b/u', $this->normalizeSearchText($keyword), $matches);

        if (!$matches) {
            return null;
        }

        return $matches[1] === 'premier' ? 'Article 1' : 'Article '.preg_replace('/\s+/', ' ', $matches[1]);
    }

    private function extractReferencePatterns(string $keyword): array
    {
        $normalized = $this->normalizeReferenceText($keyword);
        preg_match('/\b(loi|dahir|decret|arrete)\s*(?:n|no|num|numero)?\s*(\d{1,3}\s*[-\/]\s*\d{2,4})\b/u', $normalized, $typedMatch);

        if ($typedMatch) {
            $reference = preg_replace('/\s*[-\/]\s*/', '-', $typedMatch[2]);

            return ['%'.$typedMatch[1].'%'.$reference.'%', '%'.$reference.'%'];
        }

        preg_match('/\b\d{1,3}\s*[-\/]\s*\d{2,4}\b/u', $normalized, $standaloneMatch);

        return $standaloneMatch ? ['%'.preg_replace('/\s*[-\/]\s*/', '-', $standaloneMatch[0]).'%'] : [];
    }

    private function extractDocumentTitleHints(string $keyword): array
    {
        $normalized = $this->normalizeSearchText($keyword);

        return collect(self::DOCUMENT_TITLE_HINTS)
            ->filter(fn (array $hint) => collect($hint['aliases'])
                ->contains(fn (string $alias) => str_contains($normalized, $this->normalizeSearchText($alias))))
            ->pluck('title')
            ->all();
    }

    private function normalizeSearchText(?string $value): string
    {
        return Str::of($value ?? '')
            ->lower()
            ->ascii()
            ->replaceMatches('/[-_]+/', ' ')
            ->replaceMatches('/\s+/', ' ')
            ->trim()
            ->toString();
    }

    private function normalizeReferenceText(?string $value): string
    {
        return Str::of($value ?? '')
            ->lower()
            ->ascii()
            ->replaceMatches('/[°º]/u', '')
            ->replaceMatches('/\s+/', ' ')
            ->trim()
            ->toString();
    }
}
