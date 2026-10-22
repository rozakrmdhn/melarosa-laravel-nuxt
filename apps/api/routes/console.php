<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Schedule::command('auth:clear-resets')->daily();
Schedule::command('temporary:clear')->hourly();
Schedule::command('geoportal:sync-catalog --trigger=scheduler')->dailyAt('02:00')->runInBackground();

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');
