<?php

/*
|--------------------------------------------------------------------------
| Display currencies (Phase 9.1, D-14)
|--------------------------------------------------------------------------
|
| All money in this system is stored, charged and settled in USD (DECIMAL
| `*_usd` columns). The currencies below are DISPLAY-ONLY: the apps convert a
| USD amount for display as `usd × rate`, rounded to `display_decimals`. The
| server never charges, stores or settles in them.
|
| A rate is "units of the currency per 1 USD" and lives in the append-only
| `exchange_rates` table, maintained by staff holding `pricing.edit`
| (POST /api/cms/exchange-rates) — not here, so a rate change needs no deploy.
|
| Adding a currency: add its ISO 4217 code here (upper case, never USD) and
| deploy; then record its first rate. Read only through App\Support\CurrencyConfig,
| which fails closed on a malformed value.
|
| `stale_after_hours`: a rate older than this is flagged `is_stale: true` so the
| app can show "rates as of …". Nothing is blocked by staleness.
|
*/

return [
    'base' => 'USD',

    'currencies' => [
        'SYP' => ['display_decimals' => 0],
        'TRY' => ['display_decimals' => 2],
    ],

    'stale_after_hours' => (int) env('CURRENCY_STALE_AFTER_HOURS', 168),
];
