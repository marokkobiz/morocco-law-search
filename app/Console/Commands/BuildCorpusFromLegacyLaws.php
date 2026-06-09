<?php

namespace App\Console\Commands;

use App\Services\LegacyLawCorpusImportService;
use Illuminate\Console\Command;
use Throwable;

class BuildCorpusFromLegacyLaws extends Command
{
    protected $signature = 'corpus:import-legacy-laws
        {--limit= : Import only this many legacy document groups}
        {--source-url=* : Import only legacy groups matching these source URLs}';

    protected $description = 'Build the source-based, versioned legal corpus from the legacy flat laws table.';

    public function handle(LegacyLawCorpusImportService $importer): int
    {
        $limit = $this->option('limit');
        $limit = $limit === null || $limit === '' ? null : max(1, (int) $limit);

        try {
            $sourceUrls = array_values(array_filter((array) $this->option('source-url')));
            $summary = $importer->import($limit, $sourceUrls ?: null);
        } catch (Throwable $error) {
            $this->error($error->getMessage());

            return self::FAILURE;
        }

        $this->info('Legacy laws imported into versioned corpus.');
        $this->line('Import run id: '.$summary['importRunId']);
        $this->line('Documents imported: '.$summary['documentsImported']);
        $this->line('Articles extracted: '.$summary['articlesExtracted']);
        $this->line('Chunks created: '.$summary['chunksCreated']);
        $this->line('Unchanged versions skipped: '.$summary['skippedVersions']);

        foreach ($summary['errors'] as $error) {
            $this->warn('- '.$error['message']);
        }

        return $summary['errors'] ? self::FAILURE : self::SUCCESS;
    }
}
