<?php

/*
|--------------------------------------------------------------------------
| Hotel business date
|--------------------------------------------------------------------------
|
| This file is the SINGLE SOURCE OF TRUTH for the hotel's own calendar date.
| Storage and the application timezone stay UTC (`config/app.php`); only
| business-date decisions — "which day is it at the hotel?" — use this zone.
|
| `timezone` — an IANA identifier, read from `HOTEL_TIMEZONE` (default
|              `Asia/Damascus`).
|
| `check_out_time` — the hotel-local wall time (`H:i`) at which a stay ends on
|              its check-out date, read from `HOTEL_CHECK_OUT_TIME` (default
|              `12:00`). The digital key expires then (Phase 4, D-11).
|
| Consumers read it through `App\Support\HotelClock`, never directly: the
| check-in stay window (Phase 3, D-02), the front-desk room board and the
| availability / rates grids (Phase 2) all ask `HotelClock::today()`, so they
| can never disagree about "today" around midnight UTC.
|
*/

return [
    'timezone'       => env('HOTEL_TIMEZONE', 'Asia/Damascus'),
    'check_out_time' => env('HOTEL_CHECK_OUT_TIME', '12:00'),
];
