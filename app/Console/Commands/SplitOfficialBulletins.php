<?php

namespace App\Console\Commands;

use App\Models\LegalArticle;
use App\Models\LegalChunk;
use App\Models\LegalDocument;
use App\Models\LegalDocumentVersion;
use App\Services\BulletinTextSplitter;
use App\Services\LegalDomainClassifier;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SplitOfficialBulletins extends Command
{
    protected $signature = 'corpus:split-bulletins
        {--dry-run : Parse and report without writing anything}
        {--limit= : Process at most this many bulletin issues}';

    protected $description = 'Split Bulletin Officiel issues into individual legal acts so they become searchable documents.';

    public function handle(BulletinTextSplitter $splitter, LegalDomainClassifier $classifier): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $limit = $this->option('limit') !== null ? max(1, (int) $this->option('limit')) : null;

        $bulletins = LegalDocument::query()
            ->where('document_type', 'BO')
            ->where('status', 'active')
            ->whereNotNull('current_version_id')
            ->orderBy('id')
            ->when($limit, fn ($query) => $query->limit($limit))
            ->get();

        $issues = 0;
        $acts = 0;
        $skippedExisting = 0;
        $articlesCreated = 0;
        $chunksCreated = 0;
        $samples = [];

        foreach ($bulletins as $bulletin) {
            if (data_get($bulletin->metadata, 'bo_split_done') && !$dryRun) {
                continue;
            }

            $version = LegalDocumentVersion::query()->find($bulletin->current_version_id);

            if (!$version || trim((string) $version->raw_text) === '') {
                continue;
            }

            $segments = $splitter->split((string) $version->raw_text);
            $issues++;

            if ($dryRun) {
                $acts += count($segments);

                foreach (array_slice($segments, 0, 2) as $segment) {
                    if (count($samples) < 12) {
                        $samples[] = "[{$segment['type']}] ".Str::limit($segment['title'], 110);
                    }
                }

                continue;
            }

            DB::transaction(function () use ($bulletin, $segments, $classifier, &$acts, &$skippedExisting, &$articlesCreated, &$chunksCreated): void {
                foreach ($segments as $segment) {
                    $checksum = hash('sha256', $segment['text']);

                    if (LegalDocument::query()->where('checksum', $checksum)->exists()) {
                        $skippedExisting++;

                        continue;
                    }

                    $taxonomy = $classifier->classifyDocument([
                        'documentTitle' => $segment['title'],
                        'sourceCategory' => null,
                        'sourceName' => 'Bulletin Officiel',
                        'lawReference' => $segment['reference'],
                        'text' => Str::limit($segment['text'], 4000, ''),
                        'tags' => [],
                    ]);

                    $document = LegalDocument::query()->create([
                        'legal_source_id' => $bulletin->legal_source_id,
                        'document_title' => $segment['title'],
                        'document_type' => $segment['type'],
                        'law_reference' => $segment['reference'],
                        'bo_number' => $bulletin->bo_number,
                        'publication_date' => $bulletin->publication_date,
                        'language' => 'fr',
                        'domain' => $taxonomy['domain'] ?? null,
                        'subdomain' => $taxonomy['subdomain'] ?? null,
                        'tags' => $taxonomy['tags'] ?? [],
                        'source_url' => $bulletin->source_url,
                        'checksum' => $checksum,
                        'status' => 'active',
                        'metadata' => [
                            'split_from_bo_document_id' => $bulletin->id,
                            'bo_number' => $bulletin->bo_number,
                            'taxonomy_scores' => $taxonomy['scores'] ?? [],
                        ],
                    ]);

                    $documentVersion = LegalDocumentVersion::query()->create([
                        'legal_document_id' => $document->id,
                        'version_number' => 1,
                        'source_url' => $bulletin->source_url,
                        'checksum' => $checksum,
                        'status' => 'active',
                        'publication_date' => $bulletin->publication_date,
                        'imported_at' => Carbon::now(),
                        'raw_text' => $segment['text'],
                        'metadata' => ['split_from_bo_document_id' => $bulletin->id],
                    ]);

                    $document->forceFill(['current_version_id' => $documentVersion->id])->save();

                    foreach ($this->articlesForSegment($segment['text']) as $index => $article) {
                        $articleTaxonomy = $classifier->classifyArticle([
                            'documentTitle' => $document->document_title,
                            'sourceCategory' => null,
                            'articleTitle' => $document->document_title.' - '.$article['number'],
                            'articleText' => $article['content'],
                            'tags' => [],
                        ], [
                            'domain' => $document->domain,
                            'subdomain' => $document->subdomain,
                            'tags' => $document->tags ?? [],
                        ]);

                        $legalArticle = LegalArticle::query()->create([
                            'legal_document_id' => $document->id,
                            'legal_document_version_id' => $documentVersion->id,
                            'article_number' => $article['number'],
                            'article_title' => Str::limit($document->document_title, 160, '…').' - '.$article['number'],
                            'content' => $article['content'],
                            'language' => 'fr',
                            'domain' => $articleTaxonomy['domain'] ?? $document->domain,
                            'subdomain' => $articleTaxonomy['subdomain'] ?? $document->subdomain,
                            'tags' => $articleTaxonomy['tags'] ?? ($document->tags ?? []),
                            'checksum' => hash('sha256', $article['number']."\n".$article['content']),
                            'sort_order' => $index + 1,
                            'status' => 'active',
                            'metadata' => ['split_from_bo_document_id' => $bulletin->id],
                        ]);

                        $articlesCreated++;
                        $chunksCreated += $this->createChunks($legalArticle, $documentVersion, $classifier);
                    }

                    $acts++;
                }

                $bulletin->forceFill([
                    'metadata' => array_merge($bulletin->metadata ?? [], ['bo_split_done' => true]),
                ])->save();
            });

            $this->line("Issue {$bulletin->bo_number}: ".count($segments).' acts.');
        }

        if ($dryRun) {
            $this->info("Dry run: {$issues} issues parsed, {$acts} acts detected.");

            foreach ($samples as $sample) {
                $this->line('  '.$sample);
            }

            return self::SUCCESS;
        }

        $this->info("Split {$issues} issues into {$acts} new documents ({$articlesCreated} articles, {$chunksCreated} chunks); {$skippedExisting} duplicates skipped.");
        $this->info('Next: php artisan corpus:embed-chunks --active-only && php artisan legal-search:build-fts');

        return self::SUCCESS;
    }

    /**
     * @return list<array{number: string, content: string}>
     */
    private function articlesForSegment(string $text): array
    {
        $parts = preg_split(
            '/(?=(?:ARTICLE|Article)\s+(?:PREMIER|premier|\d+(?:\s*\(\d+\))?(?:\s*(?:bis|ter|quater))?)\s*(?:\.|–|:|-))/u',
            $text,
            -1,
            PREG_SPLIT_NO_EMPTY
        ) ?: [];
        $parts = array_values(array_map('trim', array_filter($parts, fn (string $part): bool => trim($part) !== '')));

        if (count($parts) <= 1) {
            return [['number' => 'Texte', 'content' => $text]];
        }

        // The heading/recitals before the first "Article" stay with it.
        if (!preg_match('/^(?:ARTICLE|Article)/u', $parts[0]) && count($parts) > 1) {
            $parts[1] = $parts[0].' '.$parts[1];
            array_shift($parts);
        }

        $articles = [];

        foreach ($parts as $part) {
            preg_match('/^(?:ARTICLE|Article)\s+(PREMIER|premier|\d+(?:\s*\(\d+\))?(?:\s*(?:bis|ter|quater))?)/u', $part, $match);
            $number = strtolower($match[1] ?? '') === 'premier' ? '1' : ($match[1] ?? (string) (count($articles) + 1));
            $articles[] = [
                'number' => 'Article '.trim($number),
                'content' => $part,
            ];
        }

        return $articles;
    }

    private function createChunks(LegalArticle $article, LegalDocumentVersion $version, LegalDomainClassifier $classifier): int
    {
        $content = trim(preg_replace('/\s+/u', ' ', $article->content) ?? $article->content);
        $max = 1800;
        $overlap = 200;
        $chunks = [];
        $offset = 0;
        $length = Str::length($content);

        while ($offset < $length) {
            $chunks[] = Str::substr($content, $offset, $max);
            $offset += $max - $overlap;
        }

        foreach ($chunks as $index => $chunkText) {
            $taxonomy = $classifier->classifyChunk([
                'documentTitle' => $article->document->document_title ?? '',
                'articleTitle' => $article->article_title,
                'chunkText' => $chunkText,
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
                'content' => $chunkText,
                'token_count' => count(preg_split('/\s+/', trim($chunkText)) ?: []),
                'domain' => $taxonomy['domain'] ?? $article->domain,
                'subdomain' => $taxonomy['subdomain'] ?? $article->subdomain,
                'tags' => $taxonomy['tags'] ?? ($article->tags ?? []),
                'checksum' => hash('sha256', $chunkText),
                'metadata' => [
                    'chunking' => 'bo_split_1800_chars_200_overlap',
                    'taxonomy_scores' => $taxonomy['scores'] ?? [],
                ],
            ]);
        }

        return count($chunks);
    }
}
