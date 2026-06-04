<?php

namespace App\Services;

use App\Models\Law;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;
use Smalot\PdfParser\Parser;
use Throwable;

class LawPdfImportService
{
    private const MIN_ARTICLE_LENGTH = 30;

    private readonly LegalDomainClassifier $classifier;

    public function __construct(?LegalDomainClassifier $classifier = null)
    {
        $this->classifier = $classifier ?? new LegalDomainClassifier();
    }

    public function parsePdfContent(string $pdfContent): string
    {
        try {
            return (new Parser())->parseContent($pdfContent)->getText();
        } catch (Throwable $error) {
            throw new RuntimeException('Could not parse PDF content: '.$error->getMessage(), 0, $error);
        }
    }

    public function parseArticlesFromText(string $text): array
    {
        $text = $this->normalizePdfText($text);
        preg_match_all($this->articleMarkerPattern(), $text, $matches, PREG_OFFSET_CAPTURE);

        if (!$matches[0]) {
            return [];
        }

        $articles = [];
        $matchCount = count($matches[0]);

        for ($index = 0; $index < $matchCount; $index += 1) {
            $marker = $matches[0][$index];
            $rawArticleNumber = $matches[1][$index][0] ?? '';
            $contentStart = $marker[1] + strlen($marker[0]);
            $contentEnd = $matches[0][$index + 1][1] ?? strlen($text);
            $content = $this->cleanArticleContent(substr($text, $contentStart, $contentEnd - $contentStart));

            if (mb_strlen($content) <= self::MIN_ARTICLE_LENGTH) {
                continue;
            }

            $articles[] = [
                'articleNumber' => $this->normalizeArticleNumber($rawArticleNumber),
                'content' => $content,
            ];
        }

        return $this->disambiguateRepeatedArticleNumbers($articles);
    }

    public function disambiguateRepeatedArticleNumbers(array $articles): array
    {
        $seen = [];

        return collect($articles)
            ->map(function (array $article) use (&$seen): array {
                $articleNumber = $article['articleNumber'];
                $seen[$articleNumber] = ($seen[$articleNumber] ?? 0) + 1;

                if ($seen[$articleNumber] > 1) {
                    $article['articleNumber'] = $articleNumber.' ('.$seen[$articleNumber].')';
                }

                return $article;
            })
            ->all();
    }

    public function importSource(array $source): int
    {
        $sourceUrl = $this->sourceValue($source, 'sourceUrl', 'source_url');

        if (!$sourceUrl) {
            throw new RuntimeException('A sourceUrl value is required before importing a PDF source.');
        }

        $text = $source['text'] ?? $this->parsePdfContent($source['pdfContent'] ?? $this->downloadPdf($source));
        $articles = $this->parseArticlesFromText($text);

        if (!$articles) {
            throw new RuntimeException('No article markers were found in '.$sourceUrl.'.');
        }

        $documentTitle = $this->sourceValue($source, 'documentTitle', 'document_title') ?? 'Moroccan legal source';
        $lawReference = $this->sourceValue($source, 'lawReference', 'law_reference');
        $sourceName = $this->sourceValue($source, 'sourceName', 'source_name') ?? 'Imported PDF source';
        $sourceCategory = $source['category'] ?? 'legal-text';
        $language = $source['language'] ?? 'fr';
        $sourceTags = array_values(array_filter($source['tags'] ?? []));
        $documentTaxonomy = $this->classifier->classifyDocument([
            'documentTitle' => $documentTitle,
            'sourceCategory' => $sourceCategory,
            'sourceName' => $sourceName,
            'lawReference' => $lawReference,
            'text' => $text,
            'tags' => $sourceTags,
        ]);
        $category = $sourceCategory;
        $tags = collect($sourceTags)
            ->merge($documentTaxonomy['tags'] ?? [])
            ->filter()
            ->unique()
            ->values()
            ->all();
        $now = Carbon::now();

        $rows = collect($articles)
            ->map(function (array $article) use ($documentTitle, $lawReference, $sourceName, $sourceUrl, $language, $now, $documentTaxonomy, $category, $tags): array {
                $articleTaxonomy = $this->classifier->classifyArticle([
                    'documentTitle' => $documentTitle,
                    'sourceCategory' => $category,
                    'articleTitle' => $documentTitle.' - '.$article['articleNumber'],
                    'articleText' => $article['content'],
                    'tags' => $tags,
                ], $documentTaxonomy);
                $articleTags = collect($tags)
                    ->merge($articleTaxonomy['tags'] ?? [])
                    ->filter()
                    ->unique()
                    ->values()
                    ->all();

                return [
                    'title' => $documentTitle.' - '.$article['articleNumber'],
                    'article_number' => $article['articleNumber'],
                    'content' => $article['content'],
                    'tags' => $articleTags ? json_encode($articleTags, JSON_UNESCAPED_UNICODE) : null,
                    'document_title' => $documentTitle,
                    'law_reference' => $lawReference,
                    'category' => $category,
                    'source_name' => $sourceName,
                    'source_url' => $sourceUrl,
                    'language' => $language,
                    'imported_at' => $now,
                ];
            })
            ->all();

        DB::transaction(function () use ($sourceUrl, $rows): void {
            Law::query()->where('source_url', $sourceUrl)->delete();

            foreach (array_chunk($rows, 500) as $chunk) {
                DB::table('laws')->insert($chunk);
            }
        });

        Cache::flush();

        return count($rows);
    }

    public function importSources(array $sources): int
    {
        $count = 0;

        foreach ($sources as $source) {
            $count += $this->importSource($source);
        }

        return $count;
    }

    private function downloadPdf(array $source): string
    {
        $sourceUrl = $this->sourceValue($source, 'sourceUrl', 'source_url');
        $timeoutSeconds = max(1, (int) ceil(($source['timeoutMs'] ?? 8000) / 1000));

        $response = Http::timeout($timeoutSeconds)
            ->withHeaders([
                'User-Agent' => 'MarokkoBizLawSearch/1.0',
                'Accept' => 'application/pdf,*/*',
            ])
            ->get($sourceUrl);

        if (!$response->successful()) {
            throw new RuntimeException("Could not download {$sourceUrl}: HTTP {$response->status()}.");
        }

        return $response->body();
    }

    private function normalizePdfText(string $text): string
    {
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $text = preg_replace('/[^\S\n]+/u', ' ', $text) ?? $text;
        $text = preg_replace('/\s+((?:Article|Art\.?)\s+(?:premier|\d+(?:er)?[A-Za-z]*(?:\s*(?:bis|ter|quater))?)\s*[:.\-\x{2013}\x{2014}]?)/iu', "\n$1", $text) ?? $text;
        $text = preg_replace('/\n{3,}/', "\n\n", $text) ?? $text;

        return trim($text);
    }

    private function cleanArticleContent(string $content): string
    {
        $lines = collect(preg_split('/\n/u', $content) ?: [])
            ->map(fn (string $line): string => trim(preg_replace('/\s+/u', ' ', $line) ?? $line))
            ->filter(fn (string $line): bool => $line !== '')
            ->reject(fn (string $line): bool => (bool) preg_match('/^(?:\d+|ISSN\s+\d+|Bulletin officiel.*)$/iu', $line));

        return trim(preg_replace('/\s+/u', ' ', $lines->implode(' ')) ?? '');
    }

    private function normalizeArticleNumber(string $rawArticleNumber): string
    {
        $normalized = Str::of($rawArticleNumber)
            ->lower()
            ->ascii()
            ->replaceMatches('/\s+/', ' ')
            ->trim()
            ->toString();

        preg_match('/(?:article|art\.?)\s*(premier|\d+(?:er)?[a-z]*(?:\s*(?:bis|ter|quater))?)/i', $normalized, $matches);
        $value = $matches[1] ?? $normalized;

        if ($value === 'premier') {
            return 'Article 1';
        }

        $value = preg_replace('/^(\d+)er\b/i', '$1', $value) ?? $value;
        $value = preg_replace('/\s+/', ' ', trim($value)) ?? trim($value);

        return 'Article '.$value;
    }

    private function articleMarkerPattern(): string
    {
        return '/(?:^|\n)\s*((?:Article|Art\.?)\s+(?:premier|\d+(?:er)?[A-Za-z]*(?:\s*(?:bis|ter|quater))?))\s*(?:[:.\-\x{2013}\x{2014}]\s*)?/iu';
    }

    private function sourceValue(array $source, string $camelKey, string $snakeKey): ?string
    {
        $value = $source[$camelKey] ?? $source[$snakeKey] ?? null;

        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }
}
