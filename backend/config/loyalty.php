<?php

/*
|--------------------------------------------------------------------------
| Loyalty program constants
|--------------------------------------------------------------------------
|
| Only fixed program guard-rails live here. Every rate and cap (earn rate,
| redeem value, expiry months, expiry-warning days, minimum points, maximum
| payable percent) is a dashboard setting stored in `loyalty_settings` and
| read fresh on each use - never put a rate in this file (Phase 10, Q4, M-8).
|
| `restored_voucher_grace_days` - days a voucher restored by a reservation
|              cancel stays usable beyond its original expiry (Q13).
|
| `max_adjust_points` - largest magnitude, in points, of a single manual staff
|              adjustment (Q20).
|
*/

return [
    // Q13: grace added to a voucher restored on cancel.
    'restored_voucher_grace_days' => 30,

    // Q20: cap on the magnitude of one manual adjustment.
    'max_adjust_points' => 1000000,
];
