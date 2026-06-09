<?php

namespace App\Console\Commands;

use App\Models\Law;
use App\Models\LegalDocument;
use App\Services\LawPdfImportService;
use App\Services\LegacyLawCorpusImportService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;
use Throwable;

class ImportDownloadedSggPdfs extends Command
{
    protected $signature = 'corpus:import-downloaded-sgg
        {manifest : JSON manifest generated from official SGG PDF downloads}
        {--limit=0 : Maximum manifest rows to import; 0 means all}
        {--embed : Refresh semantic embeddings after importing new chunks}
        {--force : Re-import source URLs even if already present}
        {--sync-every=10 : Sync imported legacy laws into the versioned corpus after this many new or pending sources}
        {--max-mb=20 : Skip local PDFs larger than this many megabytes; 0 disables the guard}';

    protected $description = 'Import locally downloaded official SGG PDFs into laws and the versioned legal corpus.';

    public function handle(LawPdfImportService $pdfImporter, LegacyLawCorpusImportService $corpusImporter): int
    {
        $manifestPath = $this->absolutePath((string) $this->argument('manifest'));

        if (!is_file($manifestPath)) {
            $this->error("Manifest not found: {$manifestPath}");

            return self::FAILURE;
        }

        $manifestJson = preg_replace('/^\xEF\xBB\xBF/', '', (string) file_get_contents($manifestPath)) ?? '';
        $rows = json_decode($manifestJson, true);

        if (!is_array($rows)) {
            $this->error('Manifest must be a JSON array.');

            return self::FAILURE;
        }

        $limit = max(0, (int) $this->option('limit'));
        $force = (bool) $this->option('force');
        $syncEvery = max(1, (int) $this->option('sync-every'));
        $maxBytes = max(0, (int) $this->option('max-mb')) * 1024 * 1024;
        $pendingSyncUrls = [];
        $syncedUrls = [];
        $processed = 0;
        $importedArticles = 0;
        $skipped = 0;
        $queuedExisting = 0;
        $oversized = 0;
        $failed = 0;
        $corpusSummary = $this->emptyCorpusSummary();

        foreach ($rows as $row) {
            if ($limit > 0 && $processed >= $limit) {
                break;
            }

            $processed++;
            $sourceUrl = trim((string) ($row['sourceUrl'] ?? ''));
            $localPath = $this->absolutePath((string) ($row['localPath'] ?? ''));

            if ($sourceUrl === '' || !is_file($localPath)) {
                $failed++;
                $this->warn("Skipping invalid manifest row {$processed}.");
                continue;
            }

            if (!$force && $this->versionedSourceExists($sourceUrl)) {
                $skipped++;
                continue;
            }

            if (!$force && $this->legacySourceExists($sourceUrl)) {
                $queuedExisting++;
                $pendingSyncUrls[] = $sourceUrl;
                $this->syncPending($pendingSyncUrls, $syncedUrls, $corpusSummary, $corpusImporter, $syncEvery);
                continue;
            }

            $fileSize = filesize($localPath) ?: 0;

            if ($maxBytes > 0 && $fileSize > $maxBytes) {
                $oversized++;
                $this->warn('- oversized PDF skipped: '.$sourceUrl.' ('.round($fileSize / 1024 / 1024, 2).' MB)');
                continue;
            }

            try {
                $pdfContent = file_get_contents($localPath);
                $count = $pdfImporter->importSource([
                    'documentTitle' => trim((string) ($row['documentTitle'] ?? '')) ?: $this->titleFromUrl($sourceUrl),
                    'lawReference' => $row['lawReference'] ?? null,
                    'category' => $row['category'] ?? 'official-sgg',
                    'sourceName' => $row['sourceName'] ?? 'Secretariat General du Gouvernement - Textes officiels',
                    'sourceUrl' => $sourceUrl,
                    'language' => $row['language'] ?? $this->languageFromUrl($sourceUrl),
                    'tags' => array_values(array_filter((array) ($row['tags'] ?? ['official-sgg']))),
                    'pdfContent' => $pdfContent,
                ]);
                unset($pdfContent);
                gc_collect_cycles();
            } catch (Throwable $error) {
                $failed++;
                $this->warn("- {$sourceUrl}: ".$error->getMessage());
                gc_collect_cycles();
                continue;
            }

            $importedArticles += $count;
            $pendingSyncUrls[] = $sourceUrl;
            $this->line("- imported {$count} articles: {$sourceUrl}");
            $this->syncPending($pendingSyncUrls, $syncedUrls, $corpusSummary, $corpusImporter, $syncEvery);
        }

        $this->syncPending($pendingSyncUrls, $syncedUrls, $corpusSummary, $corpusImporter, 1);

        $this->info('Downloaded SGG import complete.');
        $this->line("Rows processed: {$processed}");
        $this->line("Sources imported or queued for sync: ".count($syncedUrls));
        $this->line("Fully versioned sources skipped because already present: {$skipped}");
        $this->line("Existing law rows queued into versioned corpus: {$queuedExisting}");
        $this->line("Oversized PDFs skipped: {$oversized}");
        $this->line("Import failures: {$failed}");
        $this->line("Legacy law articles imported: {$importedArticles}");
        $this->line("Corpus documents versioned: {$corpusSummary['documentsImported']}");
        $this->line("Corpus articles extracted: {$corpusSummary['articlesExtracted']}");
        $this->line("Corpus chunks created: {$corpusSummary['chunksCreated']}");
        $this->line("Unchanged corpus versions skipped: {$corpusSummary['skippedVersions']}");

        foreach ($corpusSummary['errors'] ?? [] as $error) {
            $this->warn('- '.($error['message'] ?? json_encode($error)));
        }

        if ($this->option('embed') && (int) ($corpusSummary['chunksCreated'] ?? 0) > 0) {
            $this->info('Refreshing semantic embeddings for changed corpus chunks.');
            Artisan::call('corpus:embed-chunks');
            $this->output->write(Artisan::output());
        }

        return $failed > 0 || !empty($corpusSummary['errors']) ? self::FAILURE : self::SUCCESS;
    }

    private function syncPending(array &$pendingSyncUrls, array &$syncedUrls, array &$corpusSummary, LegacyLawCorpusImportService $corpusImporter, int $threshold): void
    {
        $pendingSyncUrls = array_values(array_unique(array_filter($pendingSyncUrls)));

        if (count($pendingSyncUrls) < $threshold) {
            return;
        }

        $summary = $corpusImporter->import(null, $pendingSyncUrls);
        $syncedUrls = array_values(array_unique([...$syncedUrls, ...$pendingSyncUrls]));
        $pendingSyncUrls = [];

        foreach (['documentsImported', 'articlesExtracted', 'chunksCreated', 'skippedVersions'] as $key) {
            $corpusSummary[$key] += (int) ($summary[$key] ?? 0);
        }

        $corpusSummary['errors'] = [
            ...($corpusSummary['errors'] ?? []),
            ...($summary['errors'] ?? []),
        ];

        $this->line("  synced corpus batch: {$summary['documentsImported']} documents, {$summary['articlesExtracted']} articles, {$summary['chunksCreated']} chunks.");
        gc_collect_cycles();
    }

    private function emptyCorpusSummary(): array
    {
        return [
            'documentsImported' => 0,
            'articlesExtracted' => 0,
            'chunksCreated' => 0,
            'skippedVersions' => 0,
            'errors' => [],
        ];
    }

    private function versionedSourceExists(string $sourceUrl): bool
    {
        return LegalDocument::query()->where('source_url', $sourceUrl)->exists();
    }

    private function legacySourceExists(string $sourceUrl): bool
    {
        return Law::query()->where('source_url', $sourceUrl)->exists();
    }

    private function absolutePath(string $path): string
    {
        $path = trim($path);

        if ($path === '') {
            return '';
        }

        if (preg_match('/^[A-Za-z]:[\\\\\\/]/', $path) || str_starts_with($path, '\\\\')) {
            return $path;
        }

        return base_path($path);
    }

    private function titleFromUrl(string $url): string
    {
        return Str::of(pathinfo((string) parse_url($url, PHP_URL_PATH), PATHINFO_FILENAME))
            ->replace(['_', '-'], ' ')
            ->squish()
            ->title()
            ->toString();
    }

    private function languageFromUrl(string $url): string
    {
        $url = strtolower($url);

        return str_contains($url, '/arabe/') || str_contains($url, '_ar.') ? 'ar' : 'fr';
    }
}
