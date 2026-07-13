<?php

namespace App\Services;

use App\Models\Law;
use App\Models\LegalDocument;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

class OfficialSggPageSourceService
{
    private const SOURCE_NAME = 'Secretariat General du Gouvernement - Textes officiels';

    public function __construct(private readonly LawPdfImportService $importer)
    {
    }

    public function update(array $options = []): array
    {
        $timeoutMs = (int) ($options['timeoutMs'] ?? 8000);
        $limit = (int) ($options['sggPageLimit'] ?? 0);
        $existingUrls = $this->existingSourceUrls();
        $sources = $this->discoverSources($timeoutMs, $existingUrls, $limit);
        $importedSources = [];
        $failures = [];
        $articleCount = 0;

        foreach ($sources as $source) {
            try {
                $count = $this->importer->importSource($source);
                $articleCount += $count;
                $importedSources[] = [
                    'sourceUrl' => $source['sourceUrl'],
                    'documentTitle' => $source['documentTitle'],
                    'language' => $source['language'],
                    'articleCount' => $count,
                ];
            } catch (Throwable $error) {
                $failures[] = [
                    'sourceUrl' => $source['sourceUrl'],
                    'documentTitle' => $source['documentTitle'] ?? null,
                    'message' => $error->getMessage(),
                ];
            }
        }

        return [
            'existingSggSourceCount' => count($existingUrls),
            'candidateCount' => count($sources),
            'discoveredSourceCount' => count($sources),
            'importedSourceCount' => count($importedSources),
            'importedArticleCount' => $articleCount,
            'sources' => $importedSources,
            'failures' => $failures,
        ];
    }

    public function discoverSources(int $timeoutMs = 8000, array $existingUrls = [], int $limit = 0): array
    {
        $timeoutSeconds = max(1, (int) ceil($timeoutMs / 1000));
        $existingSet = array_fill_keys(array_map(fn (string $url): string => $this->normalizeUrl($url), $existingUrls), true);
        $seen = [];
        $sources = [];

        foreach ($this->configuredPages() as $page) {
            try {
                $response = Http::timeout($timeoutSeconds)
                    ->withHeaders([
                        'User-Agent' => 'MarokkoBizLawSearch/1.0',
                        'Accept' => 'text/html,*/*',
                    ])
                    ->get($page['url']);
            } catch (Throwable $error) {
                $sources[] = [
                    'sourceUrl' => '',
                    'documentTitle' => $page['url'],
                    'language' => $page['language'],
                    'failureOnly' => true,
                    'message' => $error->getMessage(),
                ];
                continue;
            }

            if (!$response->successful()) {
                continue;
            }

            foreach ($this->pdfLinks($response->body(), $page['url']) as $link) {
                $normalized = $this->normalizeUrl($link['url']);

                if (isset($existingSet[$normalized]) || isset($seen[$normalized])) {
                    continue;
                }

                $seen[$normalized] = true;
                $sources[] = $this->sourcePayload($link, $page, $timeoutMs);

                if ($limit > 0 && count($sources) >= $limit) {
                    return $sources;
                }
            }
        }

        return array_values(array_filter($sources, fn (array $source): bool => empty($source['failureOnly'])));
    }

    private function configuredPages(): array
    {
        return collect(config('legal_sources.official_sources.sgg-pages.pages', []))
            ->filter(fn (array $page): bool => filled($page['url'] ?? null))
            ->map(fn (array $page): array => [
                'url' => (string) $page['url'],
                'language' => (string) ($page['language'] ?? $this->languageFromUrl((string) $page['url'])),
                'category' => (string) ($page['category'] ?? 'official-sgg'),
                'tags' => array_values(array_filter((array) ($page['tags'] ?? ['official-sgg']))),
            ])
            ->values()
            ->all();
    }

    private function pdfLinks(string $html, string $pageUrl): array
    {
        preg_match_all('/<a\b[^>]*href\s*=\s*["\']([^"\']+\.pdf(?:\?[^"\']*)?)["\'][^>]*>(.*?)<\/a>/isu', $html, $matches, PREG_SET_ORDER);

        return collect($matches)
            ->map(function (array $match) use ($pageUrl): ?array {
                $url = $this->absoluteUrl(html_entity_decode((string) $match[1], ENT_QUOTES | ENT_HTML5), $pageUrl);

                if (!$url || !$this->isOfficialSggPdf($url)) {
                    return null;
                }

                return [
                    'url' => $url,
                    'title' => $this->cleanTitle(strip_tags(html_entity_decode((string) $match[2], ENT_QUOTES | ENT_HTML5))),
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    private function sourcePayload(array $link, array $page, int $timeoutMs): array
    {
        $language = $this->languageFromUrl($link['url'], $page['language']);
        $title = $link['title'] !== '' ? $link['title'] : $this->titleFromUrl($link['url']);

        return [
            'documentTitle' => $title,
            'lawReference' => $this->lawReference($title, $link['url']),
            'category' => $page['category'],
            'sourceName' => self::SOURCE_NAME,
            'sourceUrl' => $link['url'],
            'language' => $language,
            'tags' => array_values(array_unique([
                ...$page['tags'],
                'official-sgg',
                $language,
                ...$this->tagsFromTitle($title),
            ])),
            'timeoutMs' => $timeoutMs,
        ];
    }

    private function existingSourceUrls(): array
    {
        return collect()
            ->merge(Law::query()->whereNotNull('source_url')->pluck('source_url'))
            ->merge(LegalDocument::query()->whereNotNull('source_url')->pluck('source_url'))
            ->filter()
            ->map(fn (string $url): string => $this->normalizeUrl($url))
            ->unique()
            ->values()
            ->all();
    }

    private function absoluteUrl(string $href, string $pageUrl): ?string
    {
        $href = trim($href);

        if ($href === '') {
            return null;
        }

        if (str_starts_with($href, 'http://') || str_starts_with($href, 'https://')) {
            return $href;
        }

        $parts = parse_url($pageUrl);
        $scheme = $parts['scheme'] ?? 'https';
        $host = $parts['host'] ?? 'www.sgg.gov.ma';

        if (str_starts_with($href, '/')) {
            return "{$scheme}://{$host}{$href}";
        }

        $path = isset($parts['path']) ? dirname($parts['path']) : '';
        $path = $path === '\\' || $path === '/' ? '' : trim($path, '/');

        return "{$scheme}://{$host}/".($path ? "{$path}/" : '').$href;
    }

    private function isOfficialSggPdf(string $url): bool
    {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        $path = strtolower((string) parse_url($url, PHP_URL_PATH));

        return in_array($host, ['sgg.gov.ma', 'www.sgg.gov.ma'], true)
            && str_ends_with($path, '.pdf')
            && !str_contains($path, '/avantprojets/');
    }

    private function normalizeUrl(string $url): string
    {
        $url = trim($url);
        $parts = parse_url($url);
        $scheme = strtolower($parts['scheme'] ?? 'https');
        $host = strtolower($parts['host'] ?? '');
        $path = preg_replace('#/+#', '/', (string) ($parts['path'] ?? '')) ?? '';

        return $host ? "{$scheme}://{$host}{$path}" : strtolower($url);
    }

    private function cleanTitle(string $title): string
    {
        $title = trim(preg_replace('/\s+/u', ' ', $title) ?? $title);

        return Str::limit($title, 240, '');
    }

    private function titleFromUrl(string $url): string
    {
        $name = pathinfo((string) parse_url($url, PHP_URL_PATH), PATHINFO_FILENAME);

        return Str::of($name)->replace(['_', '-'], ' ')->squish()->title()->toString();
    }

    private function languageFromUrl(string $url, string $fallback = 'fr'): string
    {
        $text = strtolower($url);

        if (str_contains($text, '/arabe/') || str_contains($text, '_ar.')) {
            return 'ar';
        }

        return $fallback ?: 'fr';
    }

    private function lawReference(string $title, string $url): ?string
    {
        foreach ([
            '/(?:loi|law)\s*(?:organique)?\s*n[°o]?\s*([0-9]{1,4}[-.][0-9]{1,4})/iu',
            '/(?:decret|décret)\s*n[°o]?\s*([0-9]{1,4}[-.][0-9]{1,4}[-.][0-9]{1,4})/iu',
            '/(?:dahir)\s*n[°o]?\s*([0-9]{1,4}[-.][0-9]{1,4}[-.][0-9]{1,4})/iu',
            '/(?:قانون|مرسوم|ظهير)[^\d٠-٩]*(\d{1,4}[-.]\d{1,4}(?:[-.]\d{1,4})?)/u',
        ] as $pattern) {
            if (preg_match($pattern, $title.' '.$url, $match)) {
                return $match[0];
            }
        }

        return null;
    }

    private function tagsFromTitle(string $title): array
    {
        $normalized = Str::of($title)->lower()->ascii()->toString();
        $tags = [];

        foreach ([
            'consumer' => ['consommateur', 'consumer'],
            'banking' => ['bank', 'credit', 'banque'],
            'real-estate' => ['urbanisme', 'copropriete', 'droits reels', 'immobilier'],
            'health' => ['sante', 'medecine', 'pharmacie'],
            'tax' => ['finances', 'fiscal', 'taxe'],
            'public-law' => ['organique', 'constitution', 'communes', 'regions'],
            'public-procurement' => ['marches publics', 'commande publique'],
        ] as $tag => $needles) {
            foreach ($needles as $needle) {
                if (str_contains($normalized, $needle)) {
                    $tags[] = $tag;
                    break;
                }
            }
        }

        return $tags;
    }
}
