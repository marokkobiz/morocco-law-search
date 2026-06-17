<?php

namespace App\Jobs;

use App\Models\CrawlPage;
use App\Models\CrawlSession;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;
use Symfony\Component\DomCrawler\Crawler;

class CrawlDiscoverPages implements ShouldQueue
{
    use Dispatchable, Queueable;

    public function __construct(
        public CrawlSession $session,
        public string $pageUrl,
        public int $depth = 0
    ) {
        $this->onQueue('crawler');
    }

    public function handle(): void
    {
        $maxDepth = config('crawler.max_depth', 3);
        $domainRestrict = config('crawler.domain_restrict', true);
        $rootHost = parse_url($this->session->root_url, PHP_URL_HOST);
        $rootPath = parse_url($this->session->root_url, PHP_URL_PATH) ?? '/';

        try {
            $response = Http::timeout(config('crawler.http_timeout', 30))
                ->withOptions(['verify' => false])
                ->withUserAgent('Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36')
                ->get($this->pageUrl);

            if (!$response->successful()) {
                return;
            }

            $html = $response->body();
            $crawler = new Crawler($html);
            $links = [];

            $crawler->filter('a')->each(function (Crawler $node) use (&$links, $rootHost, $rootPath, $domainRestrict) {
                $href = $node->attr('href');
                if (!$href) {
                    return;
                }

                $href = trim($href);

                if (preg_match('/^(mailto:|tel:|javascript:|#)/i', $href)) {
                    return;
                }

                $skipExtensions = '/\.(css|js|jpg|jpeg|png|gif|svg|ico|webp|woff|woff2|ttf|eot)(\?|#|$)/i';
                if (preg_match($skipExtensions, $href)) {
                    return;
                }

                $absoluteUrl = $this->resolveUrl($href, $this->pageUrl);

                if ($domainRestrict) {
                    $linkHost = parse_url($absoluteUrl, PHP_URL_HOST);
                    if ($linkHost !== $rootHost) {
                        return;
                    }

                    $linkPath = parse_url($absoluteUrl, PHP_URL_PATH) ?? '';

                    $basePath = dirname($rootPath);
                    if (str_starts_with($rootPath, '/ar/') && (str_contains($linkPath, '/en/') || str_contains($linkPath, '/fr/'))) {
                        return;
                    }

                    $keepPatterns = ['/\.pdf$/i', '/\/bo\//', '/\/bulletin\//', '/\/loi\//', '/\/code\//', '/\/decret\//', '/\/arabe\//'];
                    $isKeep = false;
                    foreach ($keepPatterns as $pattern) {
                        if (preg_match($pattern, $absoluteUrl)) {
                            $isKeep = true;
                            break;
                        }
                    }
                    if (!$isKeep) {
                        return;
                    }
                }

                $links[] = $absoluteUrl;
            });

            $links = array_unique($links);

            foreach ($links as $link) {
                $urlHash = hash('sha256', $link);

                $existing = CrawlPage::where('url_hash', $urlHash)->exists();
                if ($existing) {
                    continue;
                }

                $contentType = str_ends_with(parse_url($link, PHP_URL_PATH) ?? '', '.pdf') ? 'pdf' : 'html';

                $page = CrawlPage::create([
                    'session_id' => $this->session->id,
                    'url' => $link,
                    'url_hash' => $urlHash,
                    'depth' => $this->depth + 1,
                    'content_type' => $contentType,
                    'status' => 'discovered',
                ]);

                $this->session->increment('pages_discovered');

                CrawlExtractLaw::dispatch($page);

                if ($contentType === 'html' && ($this->depth + 1) < $maxDepth) {
                    CrawlDiscoverPages::dispatch($this->session, $link, $this->depth + 1);
                }
            }

            $hasMoreDiscovered = CrawlPage::where('session_id', $this->session->id)
                ->where('status', 'discovered')
                ->exists();

            if (!$hasMoreDiscovered && $this->session->status === 'pending') {
                $this->session->update([
                    'status' => 'running',
                    'started_at' => now(),
                ]);
            }
        } catch (\Throwable $e) {
            logger()->error('CrawlDiscoverPages failed for URL: ' . $this->pageUrl, [
                'error' => $e->getMessage(),
                'session_id' => $this->session->id,
            ]);

            $this->session->update([
                'status' => 'failed',
                'finished_at' => now(),
            ]);
        }
    }

    private function resolveUrl(string $href, string $baseUrl): string
    {
        if (preg_match('/^https?:\/\//i', $href)) {
            return $href;
        }

        $parts = parse_url($baseUrl);
        $scheme = $parts['scheme'] ?? 'https';
        $host = $parts['host'] ?? '';
        $path = $parts['path'] ?? '/';

        if (str_starts_with($href, '//')) {
            return $scheme . ':' . $href;
        }

        if (str_starts_with($href, '/')) {
            return $scheme . '://' . $host . $href;
        }

        $baseDir = str_ends_with($path, '/') ? $path : dirname($path);
        $baseDir = rtrim($baseDir, '/') . '/';
        if ($baseDir === '//') {
            $baseDir = '/';
        }

        return $scheme . '://' . $host . $baseDir . $href;
    }
}
