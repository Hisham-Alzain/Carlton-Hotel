<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('booking:release-holds')->everyFiveMinutes();

// Phase 4 (D-11): expired digital keys are already hidden at read time; the
// sweep nulls the stored code and hash. Every 15 minutes per RESEARCH — D-11
// fixes no cadence, so this line is the one place to tighten or loosen it.
Schedule::command('stays:expire-digital-keys')->everyFifteenMinutes()->withoutOverlapping();

// Nightly, off-peak, and never twice at once: emptying the bin force-deletes
// through Eloquent so every row fires its media purge and its cascade, which is
// hundreds of file unlinks on a big clear-out. `withoutOverlapping()` stops a
// slow run being joined by the next night's.
Schedule::command('cms:purge-bin')->dailyAt('03:15')->withoutOverlapping();
