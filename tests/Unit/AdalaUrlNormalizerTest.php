<?php

namespace Tests\Unit;

use App\Services\Adala\AdalaUrlNormalizer;
use Tests\TestCase;

class AdalaUrlNormalizerTest extends TestCase
{
    public function test_it_normalizes_urls_and_strips_fragments(): void
    {
        $normalizer = app(AdalaUrlNormalizer::class);

        $url = $normalizer->normalize(
            "https://adala.justice.gov.ma/api/uploads/2026/05/22/sample.pdf#toolbar=0"
        );

        $this->assertSame(
            'https://adala.justice.gov.ma/api/uploads/2026/05/22/sample.pdf',
            $url
        );
    }

    public function test_it_detects_pdf_urls_and_extracts_publication_date(): void
    {
        $normalizer = app(AdalaUrlNormalizer::class);
        $url = 'https://adala.justice.gov.ma/api/uploads/2026/05/22/sample.pdf';

        $this->assertTrue($normalizer->isPdfUrl($url));
        $this->assertSame('2026-05-22', $normalizer->publicationDateFromUrl($url));
    }

    public function test_it_canonicalizes_locale_prefixed_upload_urls(): void
    {
        $normalizer = app(AdalaUrlNormalizer::class);

        $canonical = 'https://adala.justice.gov.ma/api/uploads/2026/06/19/decision-1781870445944.pdf';
        $prefixed = 'https://adala.justice.gov.ma/ar/api/uploads/2026/06/19/decision-1781870445944.pdf';

        $this->assertSame($canonical, $normalizer->normalize($prefixed));
        $this->assertSame($normalizer->hash($canonical), $normalizer->hash($prefixed));
        $this->assertSame('1781870445944', $normalizer->uploadFileId($prefixed));
        $this->assertSame('ar', $normalizer->languageFromUrl($prefixed));
    }

    public function test_it_filters_out_arabic_pages_and_pdfs_when_only_french_and_english_are_allowed(): void
    {
        config(['adala.allowed_languages' => ['fr', 'en']]);

        $normalizer = app(AdalaUrlNormalizer::class);

        $this->assertFalse($normalizer->isAllowedCrawlPage('https://adala.justice.gov.ma/ar/codes'));
        $this->assertTrue($normalizer->isAllowedCrawlPage('https://adala.justice.gov.ma/fr/codes'));
        $this->assertTrue($normalizer->isAllowedCrawlPage('https://adala.justice.gov.ma/en/codes'));
        $this->assertFalse($normalizer->isAllowedPdfUrl(
            'https://adala.justice.gov.ma/api/uploads/sample.pdf',
            'https://adala.justice.gov.ma/ar/decisions'
        ));
        $this->assertTrue($normalizer->isAllowedPdfUrl(
            'https://adala.justice.gov.ma/api/uploads/sample.pdf',
            'https://adala.justice.gov.ma/fr/decisions'
        ));
    }
}
