<?php

namespace App\Services\Adala;

use Illuminate\Support\Str;

class AdalaUrlNormalizer
{
    public function normalize(string $url, ?string $baseUrl = null): string
    {
        $url = html_entity_decode(trim($url), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $url = preg_replace('/#.*$/', '', $url) ?? $url;
        $url = trim($url);

        if ($url === '') {
            return '';
        }

        if (str_starts_with($url, '//')) {
            $url = 'https:'.$url;
        } elseif (!preg_match('/^https?:\/\//i', $url)) {
            $base = rtrim($baseUrl ?? (string) config('adala.base_url'), '/');
            $url = str_starts_with($url, '/') ? $base.$url : $base.'/'.$url;
        }

        $parts = parse_url($url);

        if (!$parts || !isset($parts['host'])) {
            return $url;
        }

        $scheme = strtolower($parts['scheme'] ?? 'https');
        $host = strtolower($parts['host']);
        $path = $parts['path'] ?? '/';
        $path = preg_replace('#/+#', '/', $path) ?? $path;
        $path = $this->canonicalAdalaPath($path);
        $query = isset($parts['query']) ? '?'.$parts['query'] : '';

        return $scheme.'://'.$host.$path.$query;
    }

    public function hash(string $url): string
    {
        return hash('sha256', $this->canonicalPdfUrl($url));
    }

    public function canonicalPdfUrl(string $url, ?string $baseUrl = null): string
    {
        return $this->normalize($url, $baseUrl);
    }

    /**
     * Adala serves PDFs from /api/uploads/... but HTML links may prefix /ar/, /fr/, or /en/.
     * Those locale-prefixed upload URLs often 404 and create duplicate crawl records.
     */
    private function canonicalAdalaPath(string $path): string
    {
        $path = preg_replace('#^/(ar|fr|en)(?=/(api/)?uploads/)#i', '', $path) ?? $path;

        return $path === '' ? '/' : $path;
    }

    public function uploadFileId(string $url): ?string
    {
        $path = (string) parse_url($this->normalize($url), PHP_URL_PATH);

        if (preg_match('/-(\d+)\.pdf$/i', $path, $matches)) {
            return $matches[1];
        }

        return null;
    }

    public function isAllowedHost(string $url): bool
    {
        $host = strtolower((string) parse_url($this->normalize($url), PHP_URL_HOST));
        $allowed = config('adala.allowed_hosts', []);

        return in_array($host, $allowed, true);
    }

    public function isPdfUrl(string $url): bool
    {
        $normalized = $this->normalize($url);
        $path = strtolower((string) parse_url($normalized, PHP_URL_PATH));

        if (str_ends_with($path, '.pdf')) {
            return true;
        }

        foreach ((array) config('adala.pdf_path_patterns', []) as $pattern) {
            if (@preg_match($pattern, $normalized) === 1) {
                return true;
            }
        }

        return false;
    }

    public function isRelevantPageUrl(string $url): bool
    {
        if (!$this->isAllowedHost($url) || $this->isPdfUrl($url)) {
            return false;
        }

        if (!$this->isAllowedCrawlPage($url)) {
            return false;
        }

        $normalized = $this->normalize($url);
        $path = (string) parse_url($normalized, PHP_URL_PATH);

        if ($path === '' || $path === '/') {
            return $this->isAllowedLanguage($this->languageFromUrl($url));
        }

        foreach ((array) config('adala.page_path_patterns', []) as $pattern) {
            if (@preg_match($pattern, $normalized) === 1) {
                return true;
            }
        }

        return !str_contains($path, '.');
    }

    public function isAllowedCrawlPage(string $url): bool
    {
        if (preg_match('#/(ar|arabic)(/|$)#i', strtolower(html_entity_decode($url, ENT_QUOTES | ENT_HTML5, 'UTF-8')))) {
            return false;
        }

        return $this->isAllowedLanguage($this->languageFromUrl($url));
    }

    public function isAllowedLanguage(string $language): bool
    {
        $allowed = array_values(array_filter((array) config('adala.allowed_languages', ['fr', 'en'])));

        if ($allowed === []) {
            return true;
        }

        return in_array(strtolower(trim($language)), array_map('strtolower', $allowed), true);
    }

    public function allowedLanguages(): array
    {
        return array_values(array_filter((array) config('adala.allowed_languages', ['fr', 'en'])));
    }

    public function titleFromUrl(string $url): string
    {
        $path = (string) parse_url($this->normalize($url), PHP_URL_PATH);
        $filename = pathinfo($path, PATHINFO_FILENAME);

        return Str::of(urldecode($filename))
            ->replace(['_', '-'], ' ')
            ->squish()
            ->title()
            ->toString();
    }

    public function languageFromUrl(string $url, ?string $contextUrl = null): string
    {
        foreach ([$url, $contextUrl] as $candidate) {
            if (!is_string($candidate) || trim($candidate) === '') {
                continue;
            }

            $lower = strtolower(html_entity_decode(trim($candidate), ENT_QUOTES | ENT_HTML5, 'UTF-8'));

            if (preg_match('#/(ar|arabic)(/|$)#', $lower)) {
                return 'ar';
            }

            if (preg_match('#/(en|english)(/|$)#', $lower)) {
                return 'en';
            }

            if (preg_match('#/(fr|french)(/|$)#', $lower)) {
                return 'fr';
            }
        }

        return (string) config('adala.import.default_language', 'fr');
    }

    public function isAllowedPdfUrl(string $pdfUrl, ?string $contextUrl = null): bool
    {
        return $this->isAllowedLanguage($this->languageFromUrl($pdfUrl, $contextUrl));
    }

    public function publicationDateFromUrl(string $url): ?string
    {
        $path = (string) parse_url($this->normalize($url), PHP_URL_PATH);

        if (preg_match('#/uploads/(\d{4})/(\d{2})/(\d{2})/#', $path, $matches)) {
            return sprintf('%s-%s-%s', $matches[1], $matches[2], $matches[3]);
        }

        return null;
    }
}
