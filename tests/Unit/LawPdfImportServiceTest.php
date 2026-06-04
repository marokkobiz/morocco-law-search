<?php

namespace Tests\Unit;

use App\Services\LawPdfImportService;
use PHPUnit\Framework\TestCase;

class LawPdfImportServiceTest extends TestCase
{
    public function test_it_parses_article_markers_from_pdf_text(): void
    {
        $service = new LawPdfImportService();
        $text = <<<TEXT
        Bulletin officiel
        Article premier
        Le present texte fixe les conditions generales applicables aux procedures administratives et aux obligations principales.
        Article 2 bis:
        Les dispositions complementaires precisent les controles, les delais et les effets juridiques applicables.
        TEXT;

        $articles = $service->parseArticlesFromText($text);

        $this->assertCount(2, $articles);
        $this->assertSame('Article 1', $articles[0]['articleNumber']);
        $this->assertStringContainsString('conditions generales', $articles[0]['content']);
        $this->assertSame('Article 2 bis', $articles[1]['articleNumber']);
    }

    public function test_it_disambiguates_repeated_article_numbers(): void
    {
        $service = new LawPdfImportService();

        $articles = $service->disambiguateRepeatedArticleNumbers([
            ['articleNumber' => 'Article 1', 'content' => 'First article'],
            ['articleNumber' => 'Article 1', 'content' => 'Second article'],
            ['articleNumber' => 'Article 2', 'content' => 'Third article'],
        ]);

        $this->assertSame('Article 1', $articles[0]['articleNumber']);
        $this->assertSame('Article 1 (2)', $articles[1]['articleNumber']);
        $this->assertSame('Article 2', $articles[2]['articleNumber']);
    }
}
