<?php

namespace App\Services;

use App\Models\ImportRun;
use App\Models\Law;
use App\Models\LegalArticle;
use App\Models\LegalChunk;
use App\Models\LegalDocument;
use App\Models\LegalDocumentVersion;
use App\Models\LegalSource;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

class LegacyLawCorpusImportService
{
    public function __construct(private readonly LegalDomainClassifier $classifier)
    {
    }

    public function import(?int $limit = null, ?array $sourceUrls = null): array
    {
        $sourceUrls = $sourceUrls === null ? null : array_values(array_unique(array_filter($sourceUrls)));
        $run = ImportRun::query()->create([
            'import_type' => 'legacy_laws_to_versioned_corpus',
            'source_url' => $sourceUrls ? 'legacy:laws:selected' : 'legacy:laws',
            'started_at' => Carbon::now(),
            'status' => 'running',
            'metadata' => [
                'legacy_table' => 'laws',
                'source_urls' => $sourceUrls,
                'note' => 'Initial migration from the legacy flat laws table into the versioned legal corpus.',
            ],
        ]);

        $summary = [
            'documentsImported' => 0,
            'articlesExtracted' => 0,
            'chunksCreated' => 0,
            'skippedVersions' => 0,
            'errors' => [],
        ];

        try {
            $groups = $this->legacyDocumentGroups($limit, $sourceUrls);

            foreach ($groups as $group) {
                try {
                    $result = $this->importGroup($group);
                    $summary['documentsImported'] += $result['documentImported'] ? 1 : 0;
                    $summary['articlesExtracted'] += $result['articlesExtracted'];
                    $summary['chunksCreated'] += $result['chunksCreated'];
                    $summary['skippedVersions'] += $result['skippedVersion'] ? 1 : 0;
                } catch (Throwable $error) {
                    $summary['errors'][] = [
                        'documentTitle' => $group->document_title,
                        'sourceUrl' => $group->source_url,
                        'message' => $error->getMessage(),
                    ];
                }
            }

            $run->update([
                'finished_at' => Carbon::now(),
                'status' => $summary['errors'] ? 'completed_with_errors' : 'completed',
                'documents_imported' => $summary['documentsImported'],
                'articles_extracted' => $summary['articlesExtracted'],
                'chunks_created' => $summary['chunksCreated'],
                'errors' => $summary['errors'],
                'metadata' => array_merge($run->metadata ?? [], [
                    'skipped_versions' => $summary['skippedVersions'],
                    'legacy_document_groups' => count($groups),
                ]),
            ]);
        } catch (Throwable $error) {
            $summary['errors'][] = ['message' => $error->getMessage()];
            $run->update([
                'finished_at' => Carbon::now(),
                'status' => 'failed',
                'errors' => $summary['errors'],
            ]);

            throw $error;
        }

        return array_merge($summary, ['importRunId' => $run->id]);
    }

    private function legacyDocumentGroups(?int $limit, ?array $sourceUrls): Collection
    {
        $query = Law::query()
            ->select('source_url', 'source_name', 'document_title', 'law_reference', 'category', 'language')
            ->selectRaw('COUNT(*) AS article_count')
            ->groupBy('source_url', 'source_name', 'document_title', 'law_reference', 'category', 'language')
            ->orderBy('source_name')
            ->orderBy('document_title');

        if ($sourceUrls) {
            $query->whereIn('source_url', $sourceUrls);
        }

        if ($limit) {
            $query->limit($limit);
        }

        return $query->get();
    }

    private function importGroup(object $group): array
    {
        $laws = $this->groupLaws($group);

        if ($laws->isEmpty()) {
            return [
                'documentImported' => false,
                'articlesExtracted' => 0,
                'chunksCreated' => 0,
                'skippedVersion' => true,
            ];
        }

        return DB::transaction(function () use ($group, $laws): array {
            $source = $this->upsertSource($group, $laws);
            $document = $this->upsertDocument($source, $group, $laws);
            $checksum = $this->documentChecksum($laws);
            $existingVersion = $document->versions()->where('checksum', $checksum)->first();

            if ($existingVersion) {
                $document->update([
                    'current_version_id' => $existingVersion->id,
                    'checksum' => $checksum,
                    'status' => 'active',
                ]);

                return [
                    'documentImported' => false,
                    'articlesExtracted' => 0,
                    'chunksCreated' => 0,
                    'skippedVersion' => true,
                ];
            }

            $document->versions()->where('status', 'active')->update(['status' => 'replaced']);
            $document->articles()->where('status', 'active')->update(['status' => 'replaced']);
            $versionNumber = ((int) $document->versions()->max('version_number')) + 1;
            $version = LegalDocumentVersion::query()->create([
                'legal_document_id' => $document->id,
                'version_number' => $versionNumber,
                'source_url' => $group->source_url,
                'checksum' => $checksum,
                'status' => 'active',
                'publication_date' => $document->publication_date,
                'effective_date' => $document->effective_date,
                'imported_at' => Carbon::now(),
                'raw_text' => $laws->map(fn (Law $law): string => trim($law->article_number."\n".$law->content))->implode("\n\n"),
                'metadata' => [
                    'legacy_law_ids' => $laws->pluck('id')->values()->all(),
                    'legacy_article_count' => $laws->count(),
                ],
            ]);

            $articleCount = 0;
            $chunkCount = 0;

            foreach ($laws->values() as $index => $law) {
                $article = $this->createArticle($document, $version, $law, $index);
                $articleCount++;
                $chunkCount += $this->createChunks($article, $version);
            }

            $document->update([
                'current_version_id' => $version->id,
                'checksum' => $checksum,
                'status' => 'active',
            ]);

            return [
                'documentImported' => true,
                'articlesExtracted' => $articleCount,
                'chunksCreated' => $chunkCount,
                'skippedVersion' => false,
            ];
        });
    }

    private function groupLaws(object $group): Collection
    {
        $query = Law::query()
            ->orderBy('article_number')
            ->orderBy('id');

        foreach (['source_url', 'source_name', 'document_title', 'law_reference', 'category', 'language'] as $column) {
            $value = $group->{$column};
            $value === null
                ? $query->whereNull($column)
                : $query->where($column, $value);
        }

        return $query->get()
            ->sort(fn (Law $left, Law $right): int => [
                $this->articleSortNumber($left->article_number),
                $left->article_number,
                $left->id,
            ] <=> [
                $this->articleSortNumber($right->article_number),
                $right->article_number,
                $right->id,
            ])
            ->values();
    }

    private function upsertSource(object $group, Collection $laws): LegalSource
    {
        $sourceUrl = $group->source_url ?: null;
        $sourceType = $this->sourceType($group);
        $name = $group->source_name ?: $this->fallbackSourceName($sourceType);
        $attributes = $sourceUrl ? ['source_url' => $sourceUrl] : ['name' => $name, 'source_type' => $sourceType];

        return LegalSource::query()->updateOrCreate($attributes, [
            'name' => $name,
            'source_type' => $sourceType,
            'source_url' => $sourceUrl,
            'official_domain' => $this->officialDomain($sourceUrl),
            'language' => $group->language ?: 'fr',
            'checksum' => hash('sha256', implode('|', array_filter([$name, $sourceUrl, (string) $group->document_title]))),
            'status' => 'active',
            'metadata' => [
                'legacy_categories' => $laws->pluck('category')->filter()->unique()->values()->all(),
                'legacy_source_name' => $group->source_name,
            ],
        ]);
    }

    private function upsertDocument(LegalSource $source, object $group, Collection $laws): LegalDocument
    {
        $sourceUrl = $group->source_url ?: null;
        $title = $group->document_title ?: $laws->first()->title;
        $taxonomy = $this->classifier->classifyDocument([
            'documentTitle' => $title,
            'sourceCategory' => $group->category,
            'sourceName' => $group->source_name,
            'lawReference' => $group->law_reference,
            'text' => $laws->take(8)->map(fn (Law $law): string => trim($law->title.' '.$law->content))->implode(' '),
            'tags' => $laws->flatMap(fn (Law $law): array => $this->parseTags($law->tags))->values()->all(),
        ]);
        $identity = $sourceUrl
            ? ['source_url' => $sourceUrl]
            : [
                'document_title' => $title,
                'law_reference' => $group->law_reference,
                'language' => $group->language ?: 'fr',
            ];

        return LegalDocument::query()->updateOrCreate($identity, [
            'legal_source_id' => $source->id,
            'document_title' => $title,
            'document_type' => $this->documentType($group, $title),
            'law_reference' => $group->law_reference,
            'bo_number' => $this->boNumber($title, $group->law_reference),
            'publication_date' => null,
            'effective_date' => null,
            'language' => $group->language ?: 'fr',
            'domain' => $taxonomy['domain'] ?? $group->category,
            'subdomain' => $taxonomy['subdomain'] ?? null,
            'tags' => $taxonomy['tags'] ?? [],
            'source_url' => $sourceUrl,
            'status' => 'active',
            'metadata' => [
                'legacy_source_name' => $group->source_name,
                'legacy_article_count' => $laws->count(),
                'taxonomy_scores' => $taxonomy['scores'] ?? [],
                'taxonomy_subdomain_scores' => $taxonomy['subdomainScores'] ?? [],
                'coverage_note' => 'Imported from the legacy flat laws table; official coverage depends on indexed sources.',
            ],
        ]);
    }

    private function createArticle(LegalDocument $document, LegalDocumentVersion $version, Law $law, int $index): LegalArticle
    {
        $taxonomy = $this->classifier->classifyArticle([
            'documentTitle' => $document->document_title,
            'sourceCategory' => $law->category,
            'articleTitle' => $law->title,
            'articleText' => $law->content,
            'tags' => $this->parseTags($law->tags),
        ], [
            'domain' => $document->domain,
            'subdomain' => $document->subdomain,
            'tags' => $document->tags ?? [],
        ]);

        return LegalArticle::query()->create([
            'legal_document_id' => $document->id,
            'legal_document_version_id' => $version->id,
            'legacy_law_id' => $law->id,
            'article_number' => $law->article_number,
            'article_title' => $law->title,
            'content' => $law->content,
            'language' => $law->language ?: $document->language,
            'domain' => $taxonomy['domain'] ?? $document->domain,
            'subdomain' => $taxonomy['subdomain'] ?? $document->subdomain,
            'tags' => $taxonomy['tags'] ?? ($document->tags ?? []),
            'checksum' => hash('sha256', implode("\n", [$law->article_number, $law->title, $law->content])),
            'sort_order' => $index + 1,
            'status' => 'active',
            'metadata' => [
                'legacy_tags' => $law->tags,
                'legacy_relevance_source' => 'laws',
                'taxonomy_scores' => $taxonomy['scores'] ?? [],
                'taxonomy_subdomain_scores' => $taxonomy['subdomainScores'] ?? [],
            ],
        ]);
    }

    private function createChunks(LegalArticle $article, LegalDocumentVersion $version): int
    {
        $chunks = $this->chunksForText($article->content);

        foreach ($chunks as $index => $content) {
            $taxonomy = $this->classifier->classifyChunk([
                'documentTitle' => $article->document->document_title ?? '',
                'articleTitle' => $article->article_title,
                'chunkText' => $content,
                'tags' => $article->tags ?? [],
            ], [
                'domain' => $article->domain,
                'subdomain' => $article->subdomain,
                'tags' => $article->tags ?? [],
            ]);

            LegalChunk::query()->create([
                'legal_article_id' => $article->id,
                'legal_document_version_id' => $version->id,
                'chunk_index' => $index,
                'content' => $content,
                'token_count' => count(preg_split('/\s+/', trim($content)) ?: []),
                'domain' => $taxonomy['domain'] ?? $article->domain,
                'subdomain' => $taxonomy['subdomain'] ?? $article->subdomain,
                'tags' => $taxonomy['tags'] ?? ($article->tags ?? []),
                'checksum' => hash('sha256', $content),
                'metadata' => [
                    'chunking' => 'legacy_article_1800_chars_200_overlap',
                    'taxonomy_scores' => $taxonomy['scores'] ?? [],
                    'taxonomy_subdomain_scores' => $taxonomy['subdomainScores'] ?? [],
                ],
            ]);
        }

        return count($chunks);
    }

    private function chunksForText(string $text): array
    {
        $text = trim(preg_replace('/\s+/', ' ', $text) ?? $text);

        if ($text === '') {
            return [''];
        }

        $max = 1800;
        $overlap = 200;
        $chunks = [];
        $offset = 0;
        $length = Str::length($text);

        while ($offset < $length) {
            $chunks[] = Str::substr($text, $offset, $max);
            $offset += $max - $overlap;
        }

        return $chunks;
    }

    private function documentChecksum(Collection $laws): string
    {
        return hash('sha256', $laws
            ->map(fn (Law $law): string => implode("\n", [
                $law->article_number,
                $law->title,
                $law->content,
                $law->document_title,
                $law->law_reference,
                $law->source_url,
            ]))
            ->implode("\n---\n"));
    }

    private function articleSortNumber(?string $articleNumber): int
    {
        $normalized = Str::of($articleNumber ?? '')
            ->lower()
            ->ascii()
            ->toString();

        if (str_contains($normalized, 'premier')) {
            return 1;
        }

        preg_match('/\d+/', $normalized, $match);

        return isset($match[0]) ? (int) $match[0] : PHP_INT_MAX;
    }

    private function sourceType(object $group): string
    {
        $text = Str::of(implode(' ', array_filter([
            $group->source_name,
            $group->source_url,
            $group->document_title,
            $group->law_reference,
            $group->category,
        ])))->lower()->ascii()->toString();

        return match (true) {
            str_contains($text, 'bulletin officiel') || str_contains($text, 'sgg.gov.ma') || str_contains($text, 'official-bulletin') => 'BO',
            str_contains($text, 'adala') || str_contains($text, 'justice.gov.ma') => 'Adala',
            str_contains($text, 'traite') || str_contains($text, 'treaty') || str_contains($text, 'convention internationale') => 'treaty',
            str_contains($text, 'arrete') => 'order',
            str_contains($text, 'decret') => 'decree',
            str_contains($text, 'dahir') => 'dahir',
            default => 'code',
        };
    }

    private function documentType(object $group, string $title): string
    {
        $text = Str::of(implode(' ', array_filter([$title, $group->law_reference])))->lower()->ascii()->toString();

        return match (true) {
            str_contains($text, 'bulletin officiel') => 'BO',
            str_contains($text, 'traite') || str_contains($text, 'treaty') || str_contains($text, 'convention internationale') => 'treaty',
            str_contains($text, 'arrete') => 'order',
            str_contains($text, 'decret') => 'decree',
            str_contains($text, 'dahir') => 'dahir',
            str_contains($text, 'code') => 'code',
            default => $this->sourceType($group),
        };
    }

    private function boNumber(?string $title, ?string $reference): ?string
    {
        preg_match('/\b(?:BO|Bulletin officiel)\s*n?\s*([0-9]{3,5}(?:-bis)?)\b/i', (string) $title.' '.(string) $reference, $match);

        return $match[1] ?? null;
    }

    private function officialDomain(?string $url): ?string
    {
        if (!$url) {
            return null;
        }

        $host = parse_url($url, PHP_URL_HOST);

        return $host ?: null;
    }

    private function parseTags(mixed $value): array
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

    private function fallbackSourceName(string $sourceType): string
    {
        return match ($sourceType) {
            'BO' => 'Secretariat General du Gouvernement - Bulletin officiel',
            'Adala' => 'Adala - Ministere de la Justice',
            default => 'Legacy indexed source',
        };
    }
}
