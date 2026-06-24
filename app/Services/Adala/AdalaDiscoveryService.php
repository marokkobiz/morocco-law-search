<?php

namespace App\Services\Adala;

use App\Models\AdalaCrawlPage;
use App\Models\AdalaCrawlRun;
use App\Models\AdalaDocument;
use App\Services\PdfDiscoveryService;
use Throwable;

class AdalaDiscoveryService
{
    public function __construct(
        private readonly AdalaUrlNormalizer $urls,
        private readonly PdfDiscoveryService $discovery,
    ) {
    }

    public function seedRunPages(AdalaCrawlRun $run): void
    {
        foreach ((array) ($run->seed_urls ?: config('adala.seed_urls', [])) as $seedUrl) {
            $normalized = $this->urls->normalize((string) $seedUrl);

            if ($normalized === '' || !$this->urls->isAllowedHost($normalized) || !$this->urls->isAllowedCrawlPage($normalized)) {
                continue;
            }

            $this->queuePage($run, $normalized, 0);
        }
    }

    /**
     * Crawl the next pending page and return the first newly discovered PDF document, if any.
     */
    public function discoverNextDocument(AdalaCrawlRun $run): ?AdalaDocument
    {
        if ($this->hasReachedPageLimit($run)) {
            return null;
        }

        $page = AdalaCrawlPage::query()
            ->where('adala_crawl_run_id', $run->id)
            ->where('status', AdalaCrawlPage::STATUS_PENDING)
            ->orderBy('depth')
            ->orderBy('id')
            ->first();

        if (!$page) {
            return null;
        }

        $delayMs = max(0, (int) config('adala.crawl.request_delay_ms', 500));

        if ($delayMs > 0) {
            usleep($delayMs * 1000);
        }

        try {
            $result = $this->crawlPage($run, $page);
            $run->incrementStat('pages_crawled');

            foreach ($result['pdf_urls'] as $pdfUrl) {
                $document = $this->registerPdf($run, $pdfUrl, $result['metadata'][$pdfUrl] ?? []);

                if ($document) {
                    return $document;
                }
            }

            foreach ($result['page_urls'] as $pageUrl) {
                $this->queuePage($run, $pageUrl, $page->depth + 1);
            }

            return null;
        } catch (Throwable $error) {
            $page->forceFill([
                'status' => AdalaCrawlPage::STATUS_FAILED,
                'error_message' => $error->getMessage(),
                'crawled_at' => now(),
            ])->save();

            throw $error;
        }
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    public function registerPdf(AdalaCrawlRun $run, string $pdfUrl, array $metadata = []): ?AdalaDocument
    {
        $normalized = $this->urls->normalize($pdfUrl);
        $hash = $this->urls->hash($normalized);
        $uploadFileId = $this->urls->uploadFileId($normalized);
        $metadata['upload_file_id'] = $uploadFileId;

        if ($normalized === '' || !$this->urls->isPdfUrl($normalized)) {
            return null;
        }

        $language = $metadata['language']
            ?? $this->urls->languageFromUrl($pdfUrl, $metadata['discovered_from_page'] ?? null);

        if (!$this->urls->isAllowedLanguage($language)) {
            return null;
        }

        $existing = AdalaDocument::query()->where('url_hash', $hash)->first();

        if (!$existing && $uploadFileId) {
            $existing = AdalaDocument::query()
                ->where('source_url', 'like', '%-'.$uploadFileId.'.pdf')
                ->orWhere('metadata->upload_file_id', $uploadFileId)
                ->first();
        }

        if ($existing) {
            if ($existing->normalized_url !== $normalized || $existing->url_hash !== $hash) {
                $existing->forceFill([
                    'source_url' => $normalized,
                    'normalized_url' => $normalized,
                    'url_hash' => $hash,
                ])->save();
            }

            if ($existing->hasReachedStatus(AdalaDocument::STATUS_COMPLETED)) {
                return null;
            }

            if ($existing->status === AdalaDocument::STATUS_FAILED) {
                return null;
            }

            return $existing->status === AdalaDocument::STATUS_DISCOVERED ? $existing : null;
        }

        $title = $metadata['title'] ?? null;
        $title = ($title && !$this->isGenericLinkTitle($title))
            ? $title
            : $this->urls->titleFromUrl($normalized);

        $document = AdalaDocument::query()->create([
            'adala_crawl_run_id' => $run->id,
            'source_url' => $normalized,
            'normalized_url' => $normalized,
            'url_hash' => $hash,
            'title' => $title,
            'language' => $language,
            'category' => $metadata['category'] ?? (string) config('adala.import.category', 'adala'),
            'document_type' => $metadata['document_type'] ?? 'legal-text',
            'publication_date' => $metadata['publication_date'] ?? $this->urls->publicationDateFromUrl($normalized),
            'status' => AdalaDocument::STATUS_DISCOVERED,
            'discovered_at' => now(),
            'metadata' => $metadata,
        ]);

        $run->incrementStat('documents_discovered');

        return $document;
    }

    public function hasPendingPages(AdalaCrawlRun $run): bool
    {
        if ($this->hasReachedPageLimit($run)) {
            return false;
        }

        return AdalaCrawlPage::query()
            ->where('adala_crawl_run_id', $run->id)
            ->where('status', AdalaCrawlPage::STATUS_PENDING)
            ->exists();
    }

    private function crawlPage(AdalaCrawlRun $run, AdalaCrawlPage $page): array
    {
        // Reuse the shared hybrid (static DomCrawler + optional Panther) crawler
        // for fetching and link extraction; apply Adala-specific filtering here.
        $result = $this->discovery->crawlSite($page->page_url);

        if (trim($result['html']) === '') {
            throw new RuntimeException("Empty or unreachable page while crawling {$page->page_url}");
        }

        $pdfUrls = [];
        $pageUrls = [];
        $metadata = [];

        foreach ($result['anchors'] as $anchor) {
            $absolute = $this->urls->normalize($anchor['url'], $page->page_url);

            if (!$this->urls->isAllowedHost($absolute)) {
                continue;
            }

            if ($anchor['is_pdf'] || $this->urls->isPdfUrl($absolute)) {
                if (!$this->urls->isAllowedPdfUrl($absolute, $page->page_url)) {
                    continue;
                }

                $pdfUrls[$absolute] = $absolute;
                $metadata[$absolute] = [
                    'title' => $this->resolvePdfTitle($anchor['text'], $absolute),
                    'language' => $this->urls->languageFromUrl($absolute, $page->page_url),
                    'publication_date' => $this->urls->publicationDateFromUrl($absolute),
                    'discovered_from_page' => $page->page_url,
                ];

                continue;
            }

            if ($this->urls->isRelevantPageUrl($absolute)) {
                $pageUrls[$absolute] = $absolute;
            }
        }

        $page->forceFill([
            'status' => AdalaCrawlPage::STATUS_CRAWLED,
            'pdfs_found' => count($pdfUrls),
            'crawled_at' => now(),
            'render_mode' => $result['rendered_with'],
        ])->save();

        return [
            'pdf_urls' => array_values($pdfUrls),
            'page_urls' => array_values($pageUrls),
            'metadata' => $metadata,
        ];
    }

    private function queuePage(AdalaCrawlRun $run, string $pageUrl, int $depth): void
    {
        $maxDepth = max(0, (int) config('adala.crawl.max_depth', 8));

        if ($depth > $maxDepth) {
            return;
        }

        $normalized = $this->urls->normalize($pageUrl);

        if ($normalized === '' || !$this->urls->isRelevantPageUrl($normalized)) {
            return;
        }

        AdalaCrawlPage::query()->firstOrCreate(
            [
                'adala_crawl_run_id' => $run->id,
                'url_hash' => $this->urls->hash($normalized),
            ],
            [
                'page_url' => $normalized,
                'depth' => $depth,
                'status' => AdalaCrawlPage::STATUS_PENDING,
            ],
        );
    }

    private function hasReachedPageLimit(AdalaCrawlRun $run): bool
    {
        $maxPages = (int) config('adala.crawl.max_pages', 0);

        return $maxPages > 0 && (int) $run->pages_crawled >= $maxPages;
    }

    private function resolvePdfTitle(string $linkText, string $pdfUrl): ?string
    {
        $linkText = trim(preg_replace('/\s+/u', ' ', $linkText) ?? '');

        if ($linkText !== '' && !$this->isGenericLinkTitle($linkText)) {
            return $linkText;
        }

        return $this->urls->titleFromUrl($pdfUrl);
    }

    private function isGenericLinkTitle(string $title): bool
    {
        $normalized = mb_strtolower(trim($title));

        foreach (['إقرأ الآن', 'read now', 'lire maintenant', 'télécharger', 'download', 'pdf'] as $generic) {
            if ($normalized === mb_strtolower($generic)) {
                return true;
            }
        }

        return false;
    }
}
