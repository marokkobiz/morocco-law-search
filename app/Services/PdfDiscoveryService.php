<?php

namespace App\Services;

use App\Services\Crawler\DynamicPageRenderer;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\DomCrawler\UriResolver;
use Throwable;

/**
 * Reusable, site-agnostic PDF discovery.
 *
 * Hybrid strategy:
 *  - Static pages  -> Laravel HTTP client + Symfony DomCrawler (fast, low memory)
 *  - Dynamic pages -> Symfony Panther + headless Chrome (JS-rendered), used only
 *    when CRAWLER_DYNAMIC_MODE=true or when a static pass yields nothing and
 *    CRAWLER_USE_PANTHER_FALLBACK=true.
 *
 * Handles relative/absolute URLs, fragment stripping, duplicate elimination and
 * exponential-backoff retries for slow origins such as Adala.
 */
class PdfDiscoveryService
{
    public function __construct(private readonly DynamicPageRenderer $renderer)
    {
    }

    /**
     * Return the absolute, de-duplicated PDF links found on a single page.
     *
     * @return array<int, string>
     */
    public function discoverPdfLinks(string $url): array
    {
        return $this->crawlSite($url)['pdf_links'];
    }

    /**
     * Fetch and parse a single page, returning its anchors split into PDF links
     * and follow-up page links, plus the raw HTML and which engine rendered it.
     *
     * @return array{
     *     url: string,
     *     rendered_with: string,
     *     html: string,
     *     anchors: array<int, array{url: string, text: string, is_pdf: bool}>,
     *     pdf_links: array<int, string>,
     *     page_links: array<int, string>
     * }
     */
    public function crawlSite(string $url): array
    {
        $fetch = $this->fetchHtml($url);
        $html = $fetch['html'] ?? '';

        $empty = [
            'url' => $url,
            'rendered_with' => $fetch['rendered_with'] ?? 'static',
            'html' => '',
            'anchors' => [],
            'pdf_links' => [],
            'page_links' => [],
        ];

        if (trim($html) === '') {
            return $empty;
        }

        $crawler = new Crawler($html, $url);
        $anchors = $this->extractAnchors($crawler, $url);

        $pdfLinks = [];
        $pageLinks = [];

        foreach ($anchors as $anchor) {
            if ($anchor['is_pdf']) {
                $pdfLinks[$anchor['url']] = $anchor['url'];
            } else {
                $pageLinks[$anchor['url']] = $anchor['url'];
            }
        }

        return [
            'url' => $url,
            'rendered_with' => $fetch['rendered_with'],
            'html' => $html,
            'anchors' => $anchors,
            'pdf_links' => array_values($pdfLinks),
            'page_links' => array_values($pageLinks),
        ];
    }

    /**
     * Extract absolute, de-duplicated PDF links from an already-parsed crawler.
     * Relative links are resolved against the crawler's base URI.
     *
     * @return array<int, string>
     */
    public function extractPdfLinks(Crawler $crawler): array
    {
        $baseUri = $this->crawlerBaseUri($crawler);
        $links = [];

        $crawler->filter('a[href]')->each(function (Crawler $node) use (&$links, $baseUri): void {
            $absolute = $this->resolveHref((string) $node->attr('href'), $baseUri);

            if ($absolute !== null && $this->isLikelyPdf($absolute)) {
                $links[$absolute] = $absolute;
            }
        });

        return array_values($links);
    }

    /**
     * @return array<int, array{url: string, text: string, is_pdf: bool}>
     */
    public function extractAnchors(Crawler $crawler, ?string $baseUri = null): array
    {
        $baseUri ??= $this->crawlerBaseUri($crawler);
        $anchors = [];
        $probe = (bool) config('crawler.discovery.probe_content_type', false);

        $crawler->filter('a[href]')->each(function (Crawler $node) use (&$anchors, $baseUri, $probe): void {
            $href = trim((string) $node->attr('href'));

            if ($href === '' || preg_match('/^(mailto:|tel:|javascript:|#)/i', $href)) {
                return;
            }

            $absolute = $this->resolveHref($href, $baseUri);

            if ($absolute === null) {
                return;
            }

            $isPdf = $this->isLikelyPdf($absolute);

            if (!$isPdf && $probe && $this->looksLikeDownloadEndpoint($absolute)) {
                $isPdf = $this->confirmPdfByContentType($absolute);
            }

            $anchors[$absolute] = [
                'url' => $absolute,
                'text' => trim(preg_replace('/\s+/u', ' ', $node->text('')) ?? ''),
                'is_pdf' => $isPdf,
            ];
        });

        return array_values($anchors);
    }

    public function isLikelyPdf(string $url): bool
    {
        $path = strtolower((string) parse_url($url, PHP_URL_PATH));

        if (str_ends_with($path, '.pdf')) {
            return true;
        }

        foreach ((array) config('crawler.discovery.pdf_patterns', []) as $pattern) {
            if (@preg_match($pattern, $url) === 1) {
                return true;
            }
        }

        return false;
    }

    /**
     * Confirm a download endpoint actually serves a PDF by inspecting the
     * Content-Type header. Used only for ambiguous URLs when probing is enabled.
     */
    public function confirmPdfByContentType(string $url): bool
    {
        try {
            $response = $this->httpClient()->get($url);

            if (!$response->successful()) {
                return false;
            }

            $contentType = strtolower((string) $response->header('Content-Type'));

            return str_contains($contentType, 'application/pdf')
                || str_contains($contentType, 'application/octet-stream');
        } catch (Throwable $error) {
            Log::debug('Content-type probe failed', ['url' => $url, 'message' => $error->getMessage()]);

            return false;
        }
    }

    /**
     * @return array{html: ?string, rendered_with: string}
     */
    public function fetchHtml(string $url): array
    {
        $dynamicMode = (bool) config('crawler.discovery.dynamic_mode', false);
        $fallback = (bool) config('crawler.discovery.use_panther_fallback', false);

        if ($dynamicMode) {
            $rendered = $this->renderer->render($url);

            if ($this->htmlHasLinks($rendered)) {
                return ['html' => $rendered, 'rendered_with' => 'dynamic'];
            }
            // Forced dynamic but Panther unavailable/empty: fall back to static.
        }

        $static = $this->fetchStatic($url);

        if ($this->htmlHasLinks($static)) {
            return ['html' => $static, 'rendered_with' => 'static'];
        }

        if (($fallback || $dynamicMode) && $this->renderer->isAvailable()) {
            $rendered = $this->renderer->render($url);

            if ($rendered !== null && trim($rendered) !== '') {
                return ['html' => $rendered, 'rendered_with' => 'dynamic'];
            }
        }

        return ['html' => $static, 'rendered_with' => 'static'];
    }

    private function fetchStatic(string $url): ?string
    {
        $backoff = array_values((array) config('crawler.discovery.retry_backoff_seconds', [10, 30, 60, 120]));
        $maxRetries = max(1, (int) config('crawler.discovery.max_retries', 5));

        try {
            $response = $this->httpClient()
                ->retry($maxRetries, function (int $attempt) use ($backoff): int {
                    $seconds = (int) ($backoff[$attempt - 1] ?? end($backoff) ?: 10);

                    return $seconds * 1000;
                }, throw: false)
                ->get($url);

            if (!$response->successful()) {
                Log::warning('Static crawl fetch failed', [
                    'url' => $url,
                    'status' => $response->status(),
                ]);

                return null;
            }

            return $response->body();
        } catch (Throwable $error) {
            Log::warning('Static crawl fetch threw', [
                'url' => $url,
                'message' => $error->getMessage(),
            ]);

            return null;
        }
    }

    private function httpClient(): \Illuminate\Http\Client\PendingRequest
    {
        $connectTimeout = max(1, (int) config('crawler.discovery.connect_timeout', 30));
        $readTimeout = max(1, (int) config('crawler.discovery.read_timeout', 300));

        return Http::connectTimeout($connectTimeout)
            ->timeout($readTimeout)
            ->withOptions(['verify' => false])
            ->withHeaders([
                'User-Agent' => (string) config('crawler.discovery.user_agent', 'MarokkoBizLawSearch-Crawler/1.0'),
                'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            ]);
    }

    private function resolveHref(string $href, ?string $baseUri): ?string
    {
        $href = trim(html_entity_decode($href, ENT_QUOTES | ENT_HTML5, 'UTF-8'));

        if ($href === '' || preg_match('/^(mailto:|tel:|javascript:|#)/i', $href)) {
            return null;
        }

        $href = preg_replace('/#.*$/', '', $href) ?? $href;

        if ($href === '') {
            return null;
        }

        try {
            $absolute = $baseUri ? UriResolver::resolve($href, $baseUri) : $href;
        } catch (Throwable) {
            $absolute = $href;
        }

        if (!preg_match('#^https?://#i', $absolute)) {
            return null;
        }

        return $absolute;
    }

    private function crawlerBaseUri(Crawler $crawler): ?string
    {
        try {
            return $crawler->getUri();
        } catch (Throwable) {
            return null;
        }
    }

    private function htmlHasLinks(?string $html): bool
    {
        return is_string($html) && stripos($html, '<a') !== false;
    }

    private function looksLikeDownloadEndpoint(string $url): bool
    {
        return (bool) preg_match('/(download|telecharger|telechargement|file|document|attachment)/i', $url);
    }
}
