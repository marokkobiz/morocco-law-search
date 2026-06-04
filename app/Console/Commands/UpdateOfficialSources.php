<?php

namespace App\Console\Commands;

use App\Services\OfficialSourceUpdateService;
use Illuminate\Console\Command;
use Throwable;

class UpdateOfficialSources extends Command
{
    protected $signature = 'corpus:update-official-sources
        {--source=all : Official source to update: all or official-bulletins}
        {--lookahead=80 : Number of newer bulletin ids to probe}
        {--backfill=24 : Number of previous bulletin ids to re-check}
        {--recent=0 : Only check this many latest known bulletin ids}
        {--timeout-ms=8000 : HTTP timeout in milliseconds}
        {--reimport-existing : Reimport discovered sources even if their bulletin id already exists}';

    protected $description = 'Discover official Moroccan legal sources, import them, and sync them into the versioned legal corpus.';

    public function handle(OfficialSourceUpdateService $service): int
    {
        try {
            $summary = $service->update([
                'source' => (string) $this->option('source'),
                'lookahead' => (int) $this->option('lookahead'),
                'backfill' => (int) $this->option('backfill'),
                'recent' => (int) $this->option('recent'),
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

        foreach ($summary['sources'] as $source) {
            $this->line("- BO {$source['bulletinId']}: {$source['articleCount']} articles");
        }

        foreach ($summary['errors'] as $error) {
            $this->warn('- '.($error['message'] ?? json_encode($error)));
        }

        return $summary['errors'] ? self::FAILURE : self::SUCCESS;
    }
}
