<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class ClearDashboardCache extends Command
{
    protected $signature = 'dashboard:clear-cache';

    protected $description = 'Clear sidebar and dashboard cached stats';

    public function handle(): int
    {
        Cache::forget('total_articles');
        Cache::forget('total_documents');
        Cache::forget('doc_lang_counts');
        Cache::forget('doc_group_counts');

        $this->info('Dashboard cache cleared.');

        return Command::SUCCESS;
    }
}
