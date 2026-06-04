<?php

namespace App\Console\Commands;

use App\Services\OfficialBulletinUpdateService;
use Illuminate\Console\Command;
use Throwable;

class UpdateOfficialBulletins extends Command
{
    protected $signature = 'laws:update-official-bulletins
        {--lookahead=80 : Number of newer bulletin ids to probe}
        {--backfill=24 : Number of previous bulletin ids to re-check}
        {--recent=0 : Only check this many latest known bulletin ids}
        {--timeout-ms=8000 : HTTP timeout in milliseconds}
        {--reimport-existing : Reimport discovered sources even if their bulletin id already exists}';

    protected $description = 'Discover and import recent Moroccan Official Bulletin PDFs.';

    public function handle(OfficialBulletinUpdateService $service): int
    {
        try {
            $summary = $service->update([
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

        $this->info('Official bulletin update complete.');
        $this->line('Existing bulletins: '.$summary['existingBulletinCount']);
        $this->line('Candidate ids checked: '.$summary['candidateCount']);
        $this->line('Reachable sources found: '.$summary['discoveredSourceCount']);
        $this->line('Sources imported: '.$summary['importedSourceCount']);
        $this->line('Articles imported: '.$summary['importedArticleCount']);

        foreach ($summary['sources'] as $source) {
            $this->line("- BO {$source['bulletinId']}: {$source['articleCount']} articles");
        }

        foreach ($summary['failures'] as $failure) {
            $this->warn("- Failed BO {$failure['bulletinId']}: {$failure['message']}");
        }

        return count($summary['failures']) ? self::FAILURE : self::SUCCESS;
    }
}
