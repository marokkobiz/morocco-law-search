<?php

namespace Tests\Unit;

use App\Services\PdfDiscoveryService;
use Illuminate\Support\Facades\Http;
use Symfony\Component\DomCrawler\Crawler;
use Tests\TestCase;

class PdfDiscoveryServiceTest extends TestCase
{
    private function html(): string
    {
        return <<<'HTML'
        <html><body>
            <a href="https://example.com/docs/absolute.pdf">Absolute PDF</a>
            <a href="/docs/root-relative.pdf">Root relative PDF</a>
            <a href="report.pdf">Relative PDF</a>
            <a href="https://example.com/docs/absolute.pdf">Duplicate PDF</a>
            <a href="/fr/codes">A normal page</a>
            <a href="mailto:test@example.com">Email</a>
            <a href="#section">Fragment</a>
            <a href="/download?file=decree.pdf">Download endpoint</a>
        </body></html>
        HTML;
    }

    public function test_discover_pdf_links_resolves_and_deduplicates(): void
    {
        config([
            'crawler.discovery.dynamic_mode' => false,
            'crawler.discovery.use_panther_fallback' => false,
        ]);

        Http::fake([
            'https://example.com/library' => Http::response($this->html(), 200),
        ]);

        $links = app(PdfDiscoveryService::class)->discoverPdfLinks('https://example.com/library');

        $this->assertContains('https://example.com/docs/absolute.pdf', $links);
        $this->assertContains('https://example.com/docs/root-relative.pdf', $links);
        $this->assertContains('https://example.com/report.pdf', $links);
        $this->assertContains('https://example.com/download?file=decree.pdf', $links);

        // Absolute PDF appears twice in the HTML but must be deduplicated.
        $this->assertCount(
            1,
            array_filter($links, fn (string $u): bool => $u === 'https://example.com/docs/absolute.pdf'),
        );
    }

    public function test_crawl_site_splits_pdf_and_page_links(): void
    {
        Http::fake([
            'https://example.com/library' => Http::response($this->html(), 200),
        ]);

        $result = app(PdfDiscoveryService::class)->crawlSite('https://example.com/library');

        $this->assertSame('static', $result['rendered_with']);
        $this->assertContains('https://example.com/fr/codes', $result['page_links']);
        $this->assertNotContains('https://example.com/fr/codes', $result['pdf_links']);
        $this->assertContains('https://example.com/docs/absolute.pdf', $result['pdf_links']);
    }

    public function test_extract_pdf_links_uses_crawler_base_uri(): void
    {
        $crawler = new Crawler($this->html(), 'https://example.com/library');

        $links = app(PdfDiscoveryService::class)->extractPdfLinks($crawler);

        $this->assertContains('https://example.com/docs/root-relative.pdf', $links);
        $this->assertContains('https://example.com/report.pdf', $links);
    }

    public function test_is_likely_pdf_matches_extension_and_patterns(): void
    {
        $service = app(PdfDiscoveryService::class);

        $this->assertTrue($service->isLikelyPdf('https://example.com/a/b.pdf'));
        $this->assertTrue($service->isLikelyPdf('https://example.com/get?file=c.pdf'));
        $this->assertFalse($service->isLikelyPdf('https://example.com/page'));
    }
}
