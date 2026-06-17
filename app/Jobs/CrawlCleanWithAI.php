<?php

namespace App\Jobs;

use App\Models\CrawlPage;
use App\Models\LegalArticle;
use App\Models\LegalChunk;
use App\Models\LegalDocument;
use App\Models\LegalDocumentVersion;
use App\Models\LegalSource;
use App\Services\AI\AIProvider;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;

class CrawlCleanWithAI implements ShouldQueue
{
    use Dispatchable, Queueable;

    private const SYSTEM_PROMPT = <<<'PROMPT'
You are an expert AI specializing in Moroccan legal documents and Arabic linguistics.
You will receive raw, messy text extracted from a PDF or HTML webpage from an official Moroccan government source.

Task:
1. Clean the Arabic text. Fix broken UTF-8 characters, reversed letters, and misordered table data. Reconstruct proper Arabic grammar.
2. Extract ONLY the legal articles, paragraphs, or legal decisions from this text. Ignore page numbers, footers, administrative headers, navigation menus, and advertising.
3. Classify this legal text into exactly ONE of these domains: administrative_urbanism, labor, criminal, criminal_procedure, civil_procedure, civil_obligations_contracts, consumer_protection, succession_inheritance, family_marriage_divorce, real_estate_rent, commercial_company, banking_finance, insurance, health_medical, professional_regulation, digital_data_ip_media, environment_water_energy, prison_corrections, tax, or Other.
4. Count the number of distinct legal articles found.

Return ONLY a valid JSON object with these exact keys:
{
  "domain": "string",
  "cleaned_arabic_text": "string or null",
  "article_count": integer,
  "title": "string or null"
}

If you find NO legal content, return:
{
  "domain": "Other",
  "cleaned_arabic_text": null,
  "article_count": 0,
  "title": null
}
PROMPT;

    public function __construct(
        public CrawlPage $page
    ) {
        $this->onQueue('ai');
    }

    public function handle(AIProvider $ai): void
    {
        try {
            $text = mb_substr($this->page->raw_text ?? '', 0, 8000);

            $rawResponse = $ai->generate(self::SYSTEM_PROMPT, $text);

            $aiData = json_decode(trim($rawResponse), true);

            if (JSON_ERROR_NONE !== json_last_error()) {
                $extracted = $this->extractJsonFromText($rawResponse);
                $aiData = $extracted ? json_decode($extracted, true) : null;
            }

            if (!$aiData || !isset($aiData['domain'])) {
                throw new \RuntimeException('Invalid AI response: ' . substr($rawResponse, 0, 500));
            }

            $this->page->update(['ai_json' => $aiData]);

            $articleCount = (int) ($aiData['article_count'] ?? 0);
            $cleanedText = $aiData['cleaned_arabic_text'] ?? null;
            $domain = $this->normalizeDomain($aiData['domain'] ?? 'Other');
            $title = $aiData['title'] ?? null;

            if ($articleCount > 0 && $cleanedText) {
                $rootUrl = $this->page->session->root_url;
                $host = parse_url($rootUrl, PHP_URL_HOST) ?? 'unknown';

                $source = LegalSource::firstOrCreate(
                    ['name' => 'Crawled: ' . $host],
                    [
                        'source_type' => 'ai_crawler',
                        'source_url' => $rootUrl,
                        'language' => 'ar',
                        'status' => 'active',
                    ]
                );

                $document = LegalDocument::create([
                    'legal_source_id' => $source->id,
                    'document_title' => $title ?? ('Law from ' . $this->page->url),
                    'document_type' => 'code',
                    'language' => 'ar',
                    'domain' => $domain,
                    'source_url' => $this->page->url,
                    'status' => 'active',
                ]);

                $version = LegalDocumentVersion::create([
                    'legal_document_id' => $document->id,
                    'version_number' => 1,
                    'source_url' => $this->page->url,
                    'checksum' => hash('sha256', $cleanedText),
                    'status' => 'active',
                    'raw_text' => $cleanedText,
                    'imported_at' => now(),
                ]);

                $document->update(['current_version_id' => $version->id]);

                $article = LegalArticle::create([
                    'legal_document_id' => $document->id,
                    'legal_document_version_id' => $version->id,
                    'article_number' => '1',
                    'content' => $cleanedText,
                    'language' => 'ar',
                    'domain' => $domain,
                    'checksum' => hash('sha256', $cleanedText),
                    'status' => 'active',
                ]);

                LegalChunk::create([
                    'legal_article_id' => $article->id,
                    'legal_document_version_id' => $version->id,
                    'chunk_index' => 0,
                    'content' => $cleanedText,
                    'token_count' => str_word_count($cleanedText),
                    'domain' => $domain,
                    'checksum' => hash('sha256', $cleanedText),
                ]);

                $this->page->session()->increment('laws_stored');
                $this->page->update([
                    'status' => 'completed',
                    'domain' => $domain,
                    'processed_at' => now(),
                ]);
            } else {
                $this->page->update([
                    'status' => 'failed',
                    'domain' => $domain,
                    'error_message' => 'No legal content found by AI',
                    'processed_at' => now(),
                ]);
            }
        } catch (\Throwable $e) {
            if (str_contains($e->getMessage(), '"code":429') || str_contains($e->getMessage(), 'rate-limited')) {
                if ($this->attempts() >= 5) {
                    $this->page->update([
                        'status' => 'failed',
                        'error_message' => 'Rate-limited after 5 attempts: ' . $e->getMessage(),
                        'processed_at' => now(),
                    ]);
                    return;
                }
                $seconds = 30;
                if (preg_match('/"retry_after_seconds":(\d+)/', $e->getMessage(), $m)) {
                    $seconds = (int) $m[1] + 2;
                }
                $this->release($seconds);
                return;
            }
            $this->page->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
                'processed_at' => now(),
            ]);
        }
    }

    private function extractJsonFromText(string $text): ?string
    {
        if (preg_match('/\{.*\}/s', $text, $matches)) {
            return $matches[0];
        }
        return null;
    }

    private function normalizeDomain(string $domain): string
    {
        $domain = trim($domain);

        $map = [
            'Administrative' => 'administrative_urbanism',
            'administrative' => 'administrative_urbanism',
            'Real-Estate' => 'real_estate_rent',
            'Real Estate' => 'real_estate_rent',
            'real_estate' => 'real_estate_rent',
            'Economy' => 'economy',
            'economy' => 'economy',
            'Labor' => 'labor',
            'labour' => 'labor',
            'Family' => 'family_marriage_divorce',
            'family' => 'family_marriage_divorce',
            'Tax' => 'tax',
            'taxation' => 'tax',
            'Criminal' => 'criminal',
            'criminal_law' => 'criminal',
            'Commercial' => 'commercial_company',
            'commercial' => 'commercial_company',
            'Consumer' => 'consumer_protection',
            'consumer' => 'consumer_protection',
            'Health' => 'health_medical',
            'health' => 'health_medical',
            'Insurance' => 'insurance',
            'insurance' => 'insurance',
            'Banking' => 'banking_finance',
            'banking' => 'banking_finance',
            'Environment' => 'environment_water_energy',
            'environment' => 'environment_water_energy',
            'Digital' => 'digital_data_ip_media',
            'digital' => 'digital_data_ip_media',
            'Succession' => 'succession_inheritance',
            'succession' => 'succession_inheritance',
            'Civil' => 'civil_obligations_contracts',
            'civil' => 'civil_obligations_contracts',
            'Prison' => 'prison_corrections',
            'prison' => 'prison_corrections',
            'Professional' => 'professional_regulation',
            'professional' => 'professional_regulation',
        ];

        return $map[$domain] ?? $domain;
    }
}
