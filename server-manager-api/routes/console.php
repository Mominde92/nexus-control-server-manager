<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Monitoring schedule
|--------------------------------------------------------------------------
|
| Three jobs keep the monitoring data set bounded and its health honest.
| All are `withoutOverlapping()`: a run that takes longer than its interval
| (a large backfill, a slow prune) must never stack a second copy on top.
|
*/

// Fold the trailing raw samples into hourly buckets, then the hourly buckets
// into daily ones. Hourly first, since the daily fold reads its output.
Schedule::command('monitoring:rollup --period=hour')
    ->hourly()
    ->withoutOverlapping()
    ->runInBackground();

Schedule::command('monitoring:rollup --period=day')
    ->dailyAt('00:20')
    ->withoutOverlapping()
    ->runInBackground();

// Enforce the retention windows. Runs after the daily rollup so data is never
// pruned before it has been aggregated.
Schedule::command('monitoring:prune')
    ->dailyAt('01:00')
    ->withoutOverlapping()
    ->runInBackground();

// Liveness cannot be detected by ingest — a downed host sends nothing — so it is
// swept for. Five minutes is frequent enough to be useful without making the
// sweep itself a load source.
Schedule::command('monitoring:check-offline')
    ->everyFiveMinutes()
    ->withoutOverlapping();
