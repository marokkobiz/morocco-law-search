<?php

namespace App\Console\Commands;

use App\Models\Article;
use Illuminate\Console\Command;

class SyncLawsToMeilisearch extends Command
{
    protected $signature = 'laws:sync {--force : Re-sync all articles regardless of synced_at}';

    protected $description = 'Sync unsynced articles from SQL to Meilisearch via Scout';

    public function handle(): int
    {
        $query = Article::query();

        if (!$this->option('force')) {
            $query->whereNull('synced_at');
        }

        $total = $query->count();

        if ($total === 0) {
            $this->info('No articles to sync.');

            return Command::SUCCESS;
        }

        $this->info("Syncing {$total} article(s) to Meilisearch...");

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $query->chunk(100, function ($articles) use ($bar) {
            $articles->each->searchable();
            Article::whereIn('id', $articles->pluck('id'))
                ->update(['synced_at' => now()]);
            $bar->advance($articles->count());
        });

        $bar->finish();
        $this->newLine();
        $this->info('Sync complete.');

        return Command::SUCCESS;
    }
}
