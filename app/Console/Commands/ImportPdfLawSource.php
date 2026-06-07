<?php

namespace App\Console\Commands;

use App\Services\LawPdfImportService;
use Illuminate\Console\Command;
use Throwable;

class ImportPdfLawSource extends Command
{
    protected $signature = 'laws:import-pdf
        {sourceUrl : PDF URL to download and import}
        {--document-title= : Document title to store with imported articles}
        {--law-reference= : Law reference, for example Loi 15-95}
        {--category=legal-text : Category to store with imported articles}
        {--source-name=Imported PDF source : Human-readable source name}
        {--language=fr : Source language}
        {--file= : Local PDF file to parse while preserving sourceUrl as the official source}
        {--timeout-ms=8000 : HTTP timeout in milliseconds}
        {--tag=* : Tag value; can be repeated}';

    protected $description = 'Import one Moroccan legal PDF into the laws table.';

    public function handle(LawPdfImportService $importer): int
    {
        $sourceUrl = trim((string) $this->argument('sourceUrl'));
        $documentTitle = trim((string) ($this->option('document-title') ?: 'Imported Moroccan legal text'));

        try {
            $localFile = $this->option('file') ? base_path((string) $this->option('file')) : null;
            $pdfContent = $localFile && is_file($localFile) ? file_get_contents($localFile) : null;
            $count = $importer->importSource([
                'documentTitle' => $documentTitle,
                'lawReference' => $this->option('law-reference') ?: null,
                'category' => $this->option('category') ?: 'legal-text',
                'sourceName' => $this->option('source-name') ?: 'Imported PDF source',
                'sourceUrl' => $sourceUrl,
                'language' => $this->option('language') ?: 'fr',
                'pdfContent' => $pdfContent,
                'timeoutMs' => (int) $this->option('timeout-ms'),
                'tags' => $this->option('tag') ?: [],
            ]);
        } catch (Throwable $error) {
            $this->error($error->getMessage());

            return self::FAILURE;
        }

        $this->info("Imported {$count} article rows from {$documentTitle}.");

        return self::SUCCESS;
    }
}
