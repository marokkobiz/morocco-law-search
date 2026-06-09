<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command(config('legal_sources.schedule.command', 'corpus:update-official-sources --source=all'))
    ->dailyAt(config('legal_sources.schedule.daily_at', '03:00'))
    ->timezone(config('legal_sources.schedule.timezone', 'Africa/Casablanca'))
    ->withoutOverlapping();
