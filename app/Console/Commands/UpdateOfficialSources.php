<?php

namespace App\Console\Commands;

use App\Services\OfficialSourceUpdateService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Throwable;

class UpdateOfficialSources extends Command
{
    protected $signature = 'corpus:update-official-sources
        {--source=all : Official source to update: all or official-bulletins}
        {--lookahead=80 : Number of newer bulletin ids to probe}
        {--backfill=24 : Number of previous bulletin ids to re-check}
        {--recent=0 : Only check this many latest known bulletin ids}
        {--curated-codes : Also import curated foundational SGG bulletins such as Code du travail and Code de la famille}
        {--sgg-page-limit=0 : Maximum SGG page PDF links to import; 0 means all discovered new links}
        {--timeout-ms=8000 : HTTP timeout in milliseconds}
        {--reimport-existing : Reimport discovered sources even if their bulletin id already exists}
        {--embed : Refresh semantic embeddings after importing new corpus chunks}';

    protected $description = 'Discover official Moroccan legal sources, import them, and sync them into the versioned legal corpus.';

    public function handle(OfficialSourceUpdateService $service): int
    {
        try {
            $summary = $service->update([
                'source' => (string) $this->option('source'),
                'lookahead' => (int) $this->option('lookahead'),
                'backfill' => (int) $this->option('backfill'),
                'recent' => (int) $this->option('recent'),
                'curatedCodes' => (bool) $this->option('curated-codes'),
                'sggPageLimit' => (int) $this->option('sgg-page-limit'),
                'timeoutMs' => (int) $this->option('timeout-ms'),
                'reimportExisting' => (bool) $this->option('reimport-existing'),
            ]);
        } catch (Throwable $error) {
            $this->error($error->getMessage());

            return self::FAILURE;
        }

        $this->info('Official source update complete.');
        $this->line('Import run id: '.$summary['importRunId']);
        $this->line('Source set: '.$summary['source']);
        $this->line('Supported sources: '.implode(', ', array_keys($summary['supportedSources'])));
        $this->line('Existing bulletins: '.$summary['existingBulletinCount']);
        $this->line('Candidate ids checked: '.$summary['candidateCount']);
        $this->line('Reachable sources found: '.$summary['discoveredSourceCount']);
        $this->line('Sources imported into legacy laws: '.$summary['importedSourceCount']);
        $this->line('Articles imported into legacy laws: '.$summary['importedArticleCount']);
        $this->line('Corpus documents versioned: '.$summary['corpus']['documentsImported']);
        $this->line('Corpus articles extracted: '.$summary['corpus']['articlesExtracted']);
        $this->line('Corpus chunks created: '.$summary['corpus']['chunksCreated']);
        $this->line('Unchanged corpus versions skipped: '.$summary['corpus']['skippedVersions']);

        if ($this->option('embed') && (int) ($summary['corpus']['chunksCreated'] ?? 0) > 0) {
            $this->info('Refreshing semantic embeddings for changed corpus chunks.');
            Artisan::call('corpus:embed-chunks');
            $this->output->write(Artisan::output());
        }

        foreach ($summary['sources'] as $source) {
            $label = isset($source['bulletinId'])
                ? "BO {$source['bulletinId']}"
                : ($source['documentTitle'] ?? $source['sourceUrl'] ?? 'Official source');
            $this->line("- {$label}: {$source['articleCount']} articles");
        }

        foreach ($summary['errors'] as $error) {
            $this->warn('- '.($error['message'] ?? json_encode($error)));
        }

        return $summary['errors'] ? self::FAILURE : self::SUCCESS;
    }
}
