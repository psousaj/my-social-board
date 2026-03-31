<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('providers:refresh-tokens --provider=instagram')->dailyAt('02:00');
Schedule::command('ingestion:poll-instagram-fallback --stale-minutes=15')->everyFifteenMinutes();
