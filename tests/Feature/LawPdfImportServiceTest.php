<?php

namespace Tests\Feature;

use App\Models\Law;
use App\Services\LawPdfImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LawPdfImportServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_imports_a_text_source_as_law_rows(): void
    {
        $count = app(LawPdfImportService::class)->importSource([
            'documentTitle' => 'Bulletin officiel n 7999 - Textes generaux',
            'lawReference' => 'BO n 7999',
            'category' => 'official-bulletin',
            'sourceName' => 'Test source',
            'sourceUrl' => 'https://example.test/BO_7999_fr.pdf',
            'tags' => ['official-bulletin', '2026'],
            'text' => <<<TEXT
            Article premier
            Le premier article contient assez de contenu pour etre conserve dans la base de donnees.
            Article 2
            Le deuxieme article contient aussi assez de contenu pour etre importe correctement.
            TEXT,
        ]);

        $this->assertSame(2, $count);
        $this->assertDatabaseHas('laws', [
            'article_number' => 'Article 1',
            'document_title' => 'Bulletin officiel n 7999 - Textes generaux',
            'category' => 'official-bulletin',
        ]);
        $this->assertDatabaseHas('laws', [
            'article_number' => 'Article 2',
            'source_url' => 'https://example.test/BO_7999_fr.pdf',
        ]);

        $law = Law::query()->where('article_number', 'Article 1')->firstOrFail();
        $this->assertContains('official-bulletin', $law->tags);
        $this->assertContains('2026', $law->tags);
        $this->assertContains('official_bulletin', $law->tags);
    }
}
