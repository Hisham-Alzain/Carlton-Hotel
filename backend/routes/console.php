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

// Phase 10 (LOY-09): the daily expiry sweep is bookkeeping only (expired points
// are already unspendable via `expires_at > now()`), so a quiet 01:00 hotel time
// suffices. Hotel timezone so "daily" matches the hotel's calendar day.
Schedule::command('loyalty:expire-points')->dailyAt('01:00')->timezone(config('hotel.timezone'))->withoutOverlapping();

// Phase 10 (LOY-10): warn each guest once per batch, N days before expiry. 09:00
// hotel time so the push lands in waking hours; the per-batch marker keeps it once.
Schedule::command('loyalty:notify-expiring')->dailyAt('09:00')->timezone(config('hotel.timezone'))->withoutOverlapping();
