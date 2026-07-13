<?php

namespace App\Console\Commands;

use App\Models\LegalArticle;
use App\Models\LegalChunk;
use App\Models\LegalDocument;
use App\Services\LegalDomainClassifier;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class ReclassifyLegalCorpus extends Command
{
    protected $signature = 'corpus:reclassify-taxonomy
        {--only-null : Only update rows with a missing domain}
        {--dry-run : Show what would change without writing}
        {--limit= : Maximum number of documents to process}';

    protected $description = 'Recompute domain, subdomain, and tags for legal documents, articles, and chunks.';

    public function handle(LegalDomainClassifier $classifier): int
    {
        $onlyNull = (bool) $this->option('only-null');
        $dryRun = (bool) $this->option('dry-run');
        $limit = $this->option('limit') !== null ? max(1, (int) $this->option('limit')) : null;

        $summary = [
            'documents_seen' => 0,
            'documents_changed' => 0,
            'articles_seen' => 0,
            'articles_changed' => 0,
            'chunks_seen' => 0,
            'chunks_changed' => 0,
        ];

        $query = LegalDocument::query()->orderBy('id');

        if ($onlyNull) {
            $query->where(function (Builder $query): void {
                $query->whereNull('domain')
                    ->orWhereHas('articles', fn (Builder $articles): Builder => $articles->whereNull('domain'))
                    ->orWhereHas('articles.chunks', fn (Builder $chunks): Builder => $chunks->whereNull('domain'));
            });
        }

        $processedDocuments = 0;
        $query->chunkById(20, function ($documents) use ($classifier, $onlyNull, $dryRun, $limit, &$summary, &$processedDocuments): bool {
            foreach ($documents as $document) {
                if ($limit !== null && $processedDocuments >= $limit) {
                    return false;
                }

                $processedDocuments++;
                $summary['documents_seen']++;
                $articlePreview = $document->articles()
                    ->orderBy('sort_order')
                    ->limit(8)
                    ->get(['article_title', 'content']);
                $documentTaxonomy = $classifier->classifyDocument([
                    'documentTitle' => $document->document_title,
                    'sourceCategory' => null,
                    'lawReference' => $document->law_reference,
                    'text' => $articlePreview->map(fn (LegalArticle $article): string => trim(($article->article_title ?? '').' '.$article->content))->implode(' '),
                    'tags' => $document->tags ?? [],
                ]);
                $documentTaxonomy = $this->withFallback($documentTaxonomy, [
                    'domain' => $document->domain,
                    'subdomain' => $document->subdomain,
                    'tags' => $document->tags ?? [],
                ]);

                if ((!$onlyNull || $document->domain === null) && $this->taxonomyChanged($document, $documentTaxonomy)) {
                    $summary['documents_changed']++;
                    $this->writeIfNeeded($document, $documentTaxonomy, $dryRun);
                }

                $document->articles()->orderBy('id')->chunkById(200, function ($articles) use ($document, $classifier, $onlyNull, $dryRun, $documentTaxonomy, &$summary): void {
                    foreach ($articles as $article) {
                    $summary['articles_seen']++;
                    $articleTaxonomy = $classifier->classifyArticle([
                        'documentTitle' => $document->document_title,
                        'sourceCategory' => $documentTaxonomy['domain'] ?? $document->domain,
                        'articleTitle' => $article->article_title,
                        'articleText' => $article->content,
                        'tags' => $article->tags ?? [],
                    ], $documentTaxonomy);
                    $articleTaxonomy = $this->withFallback($articleTaxonomy, [
                        'domain' => $documentTaxonomy['domain'] ?? $document->domain,
                        'subdomain' => $documentTaxonomy['subdomain'] ?? $document->subdomain,
                        'tags' => $documentTaxonomy['tags'] ?? ($document->tags ?? []),
                    ]);

                    if ((!$onlyNull || $article->domain === null) && $this->taxonomyChanged($article, $articleTaxonomy)) {
                        $summary['articles_changed']++;
                        $this->writeIfNeeded($article, $articleTaxonomy, $dryRun);
                    }

                    $article->chunks()->orderBy('id')->chunkById(100, function ($chunks) use ($document, $article, $classifier, $onlyNull, $dryRun, $articleTaxonomy, &$summary): void {
                        foreach ($chunks as $chunk) {
                        $summary['chunks_seen']++;
                        $chunkTaxonomy = $classifier->classifyChunk([
                            'documentTitle' => $document->document_title,
                            'articleTitle' => $article->article_title,
                            'chunkText' => $chunk->content,
                            'tags' => $chunk->tags ?? [],
                        ], $articleTaxonomy);
                        $chunkTaxonomy = $this->withFallback($chunkTaxonomy, [
                            'domain' => $articleTaxonomy['domain'] ?? $article->domain,
                            'subdomain' => $articleTaxonomy['subdomain'] ?? $article->subdomain,
                            'tags' => $articleTaxonomy['tags'] ?? ($article->tags ?? []),
                        ]);

                        if ((!$onlyNull || $chunk->domain === null) && $this->taxonomyChanged($chunk, $chunkTaxonomy)) {
                            $summary['chunks_changed']++;
                            $this->writeIfNeeded($chunk, $chunkTaxonomy, $dryRun);
                        }
                    }
                    });
                }
                });
            }

            return true;
        });

        foreach ($summary as $label => $value) {
            $this->line(str_replace('_', ' ', $label).': '.$value);
        }

        if ($dryRun) {
            $this->warn('Dry run only. No rows were changed.');
        }

        return self::SUCCESS;
    }

    private function withFallback(array $taxonomy, array $fallback): array
    {
        $domain = $taxonomy['domain'] ?? $fallback['domain'] ?? null;
        $subdomain = $taxonomy['subdomain'] ?? $fallback['subdomain'] ?? null;
        $tags = collect($taxonomy['tags'] ?? [])
            ->merge($fallback['tags'] ?? [])
            ->merge(array_filter([$domain, $subdomain]))
            ->map(fn (mixed $tag): string => trim(strtolower(str_replace([' ', '-'], '_', (string) $tag))))
            ->filter()
            ->unique()
            ->take(40)
            ->values()
            ->all();

        return array_merge($taxonomy, [
            'domain' => $domain,
            'subdomain' => $subdomain,
            'tags' => $tags,
        ]);
    }

    private function taxonomyChanged(object $model, array $taxonomy): bool
    {
        return ($model->domain ?? null) !== ($taxonomy['domain'] ?? null)
            || ($model->subdomain ?? null) !== ($taxonomy['subdomain'] ?? null)
            || array_values((array) ($model->tags ?? [])) !== array_values((array) ($taxonomy['tags'] ?? []));
    }

    private function applyTaxonomy(object $model, array $taxonomy): void
    {
        $metadata = (array) ($model->metadata ?? []);
        $model->forceFill([
            'domain' => $taxonomy['domain'] ?? null,
            'subdomain' => $taxonomy['subdomain'] ?? null,
            'tags' => $taxonomy['tags'] ?? [],
            'metadata' => array_merge($metadata, [
                'taxonomy_scores' => $taxonomy['scores'] ?? [],
                'taxonomy_subdomain_scores' => $taxonomy['subdomainScores'] ?? [],
                'taxonomy_reclassified_at' => now()->toIso8601String(),
            ]),
        ])->save();
    }

    private function writeIfNeeded(object $model, array $taxonomy, bool $dryRun): void
    {
        if ($dryRun) {
            return;
        }

        DB::transaction(fn (): mixed => $this->applyTaxonomy($model, $taxonomy));
    }
}
