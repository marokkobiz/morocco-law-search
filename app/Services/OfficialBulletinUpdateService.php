<?php

namespace App\Services;

use App\Models\Law;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

class OfficialBulletinUpdateService
{
    public const CATEGORY = 'official-bulletin';

    public const BASE_URL = 'https://www.sgg.gov.ma/BO/FR/2873';
    private const DEFAULT_HIGHEST_BULLETIN_ID = 7500;
    private const SOURCE_NAME = 'Secretariat General du Gouvernement - Bulletin officiel';

    public function __construct(private readonly LawPdfImportService $importer)
    {
    }

    public function update(array $options = []): array
    {
        $existingIds = $this->existingBulletinIds();
        $candidateIds = $this->candidateIds(
            $existingIds,
            (int) ($options['lookahead'] ?? 80),
            (int) ($options['backfill'] ?? 24),
            (int) ($options['recent'] ?? 0),
            (bool) ($options['reimportExisting'] ?? false)
        );
        $sources = $this->discoverSources($candidateIds, (int) ($options['timeoutMs'] ?? 8000));
        $sources = [
            ...$sources,
            ...$this->curatedSources($existingIds, (int) ($options['timeoutMs'] ?? 8000), (bool) ($options['reimportExisting'] ?? false), (bool) ($options['curatedCodes'] ?? false)),
        ];
        $importedSources = [];
        $failures = [];
        $articleCount = 0;

        foreach ($sources as $source) {
            try {
                $count = $this->importer->importSource($source);
                $articleCount += $count;
                $importedSources[] = [
                    'bulletinId' => $source['bulletinId'],
                    'sourceUrl' => $source['sourceUrl'],
                    'articleCount' => $count,
                ];
            } catch (Throwable $error) {
                $failures[] = [
                    'bulletinId' => $source['bulletinId'],
                    'sourceUrl' => $source['sourceUrl'],
                    'message' => $error->getMessage(),
                ];
            }
        }

        return [
            'existingBulletinCount' => count($existingIds),
            'candidateCount' => count($candidateIds),
            'discoveredSourceCount' => count($sources),
            'importedSourceCount' => count($importedSources),
            'importedArticleCount' => $articleCount,
            'sources' => $importedSources,
            'failures' => $failures,
        ];
    }

    public function candidateIds(array $existingIds, int $lookahead = 80, int $backfill = 24, int $recent = 0, bool $reimportExisting = false): array
    {
        $existingIds = array_values(array_unique(array_map('intval', $existingIds)));
        $highestId = max([self::DEFAULT_HIGHEST_BULLETIN_ID, ...$existingIds]);

        if ($recent > 0) {
            $start = max(1, $highestId - $recent + 1);
            $end = $highestId;
        } else {
            $start = max(1, $highestId - max(0, $backfill));
            $end = $highestId + max(0, $lookahead);
        }

        $existingSet = array_flip($existingIds);

        return collect(range($end, $start))
            ->filter(fn (int $id): bool => $reimportExisting || !isset($existingSet[$id]))
            ->values()
            ->all();
    }

    public function discoverSources(array $candidateIds, int $timeoutMs = 8000): array
    {
        $sources = [];

        foreach ($candidateIds as $bulletinId) {
            foreach ($this->candidateUrls((int) $bulletinId) as $url) {
                if ($this->isReachablePdf($url, $timeoutMs)) {
                    $sources[] = $this->sourcePayload((int) $bulletinId, $url, $timeoutMs);
                    break;
                }
            }
        }

        return $sources;
    }

    private function existingBulletinIds(): array
    {
        return Law::query()
            ->where('category', self::CATEGORY)
            ->select('document_title', 'law_reference', 'source_url')
            ->distinct()
            ->get()
            ->map(fn (Law $law): ?int => $this->extractBulletinId(
                implode(' ', array_filter([$law->document_title, $law->law_reference, $law->source_url]))
            ))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function candidateUrls(int $bulletinId): array
    {
        $currentYear = Carbon::now('Africa/Casablanca')->year;
        $years = [$currentYear, $currentYear - 1, $currentYear - 2];
        $fileNames = ["BO_{$bulletinId}_fr.pdf", "BO_{$bulletinId}_Fr.pdf"];
        $urls = [];

        foreach ($years as $year) {
            foreach ($fileNames as $fileName) {
                $urls[] = rtrim(self::BASE_URL, '/')."/{$year}/{$fileName}";
                $urls[] = "https://www.sgg.gov.ma/BO/fr/{$year}/".strtolower($fileName);
                $urls[] = "https://www.sgg.gov.ma/Portals/0/Bo/bulletin/Fr/{$year}/{$fileName}";
            }
        }

        return array_values(array_unique($urls));
    }

    private function isReachablePdf(string $url, int $timeoutMs): bool
    {
        $timeoutSeconds = max(1, (int) ceil($timeoutMs / 1000));

        try {
            $response = Http::timeout($timeoutSeconds)
                ->withHeaders([
                    'Range' => 'bytes=0-0',
                    'User-Agent' => 'MarokkoBizLawSearch/1.0',
                    'Accept' => 'application/pdf,*/*',
                ])
                ->get($url);
        } catch (Throwable) {
            return false;
        }

        if (!in_array($response->status(), [200, 206], true)) {
            return false;
        }

        $contentType = strtolower($response->header('content-type', ''));

        return str_contains($contentType, 'pdf') || str_starts_with($response->body(), '%PDF');
    }

    private function sourcePayload(int $bulletinId, string $url, int $timeoutMs): array
    {
        $year = $this->extractYear($url);

        return [
            'bulletinId' => $bulletinId,
            'documentTitle' => "Bulletin officiel n {$bulletinId} - Textes generaux",
            'lawReference' => "BO n {$bulletinId}",
            'category' => self::CATEGORY,
            'sourceName' => self::SOURCE_NAME,
            'sourceUrl' => $url,
            'language' => 'fr',
            'tags' => array_filter(['official-bulletin', 'public-law', 'administration', $year ? (string) $year : null]),
            'timeoutMs' => $timeoutMs,
        ];
    }

    private function curatedSources(array $existingIds, int $timeoutMs, bool $reimportExisting, bool $enabled): array
    {
        if (!$enabled) {
            return [];
        }

        $existingSet = array_flip(array_map('intval', $existingIds));
        $sources = [];

        foreach (config('legal_sources.official_sources.official-bulletins.curated_bulletins', []) as $bulletin) {
            $bulletinId = (int) ($bulletin['bulletin_id'] ?? 0);

            if ($bulletinId <= 0 || (!$reimportExisting && isset($existingSet[$bulletinId]))) {
                continue;
            }

            foreach ((array) ($bulletin['urls'] ?? []) as $url) {
                if ($this->isReachablePdf((string) $url, $timeoutMs)) {
                    $sources[] = [
                        'bulletinId' => $bulletinId,
                        'documentTitle' => $bulletin['document_title'] ?? "Bulletin officiel n {$bulletinId} - Textes generaux",
                        'lawReference' => $bulletin['law_reference'] ?? "BO n {$bulletinId}",
                        'category' => self::CATEGORY,
                        'sourceName' => self::SOURCE_NAME,
                        'sourceUrl' => (string) $url,
                        'language' => 'fr',
                        'tags' => array_values(array_filter((array) ($bulletin['tags'] ?? ['official-bulletin']))),
                        'timeoutMs' => $timeoutMs,
                    ];

                    break;
                }
            }
        }

        return $sources;
    }

    private function extractBulletinId(string $value): ?int
    {
        preg_match('/(?:BO_|BO\s*n|Bulletin officiel\s*n)\s*(\d{4,5})/i', $value, $matches);

        return isset($matches[1]) ? (int) $matches[1] : null;
    }

    private function extractYear(string $url): ?int
    {
        preg_match('/\/(20\d{2})\//', $url, $matches);

        return isset($matches[1]) ? (int) $matches[1] : null;
    }
}
