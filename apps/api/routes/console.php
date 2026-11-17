<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Schedule::command('auth:clear-resets')->daily();
Schedule::command('temporary:clear')->hourly();
Schedule::command('geoportal:sync-catalog --trigger=scheduler')->dailyAt('02:00')->runInBackground();
Schedule::command('martin:sync-catalog --trigger=scheduler')->dailyAt('02:15')->runInBackground();
Schedule::command('backup:run --scheduled')->everyMinute()->withoutOverlapping();
Schedule::command('backup:cleanup')->dailyAt('04:00')->timezone('Asia/Jakarta')->withoutOverlapping();
Schedule::command('backup:check-health')->hourly()->withoutOverlapping();
Schedule::command('bkkd:reanalyze-spatial')->everyFiveMinutes()->withoutOverlapping();

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');
