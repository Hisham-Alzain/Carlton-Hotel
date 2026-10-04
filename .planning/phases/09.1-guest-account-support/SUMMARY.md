---
phase: 09.1-guest-account-support
status: complete
completed: 2026-10-05
---

# Phase 9.1 — Guest Account & App Support: Summary

**Base:** 82169a5 (suite 2376). Opus 5.5 stood in for Fable (no council). Plans 09.1-01..09.1-09; detail in 09.1-NN-SUMMARY.md.

## Endpoints (5 new, nothing renamed or removed)
- DELETE `/api/auth/guest/me`: guest token, 5/min, body `{"confirm":true}`, 200 `data: null`.
- GET `/api/public/exchange-rates`: no login, 60/min, `Cache-Control: public, max-age=300`.
- GET `/api/cms/exchange-rates`, GET `/api/cms/exchange-rates/history`, POST `/api/cms/exchange-rates`: staff, `pricing.edit`.

## Additive changes to existing routes
- Guest `preferred_locale` accepts en, ar, fr, tr, es.
- `verify-otp` sets a new guest's `preferred_locale` from `Accept-Language` (existing guests never overwritten).
- Guest directory hides deleted accounts unless `account_status` is sent; directory and profile gain `account_status`, `account_deleted_at`.
- Staff notes/preferences on a deleted guest return 422.

## Error codes (all 422, 5 locales)
`guest_account_deletion_blocked {reasons, booking_codes}`, `guest_account_deleted`, `exchange_rate_large_change {currency, current_rate, proposed_rate, change_percent}`.

## Semantics
- Deletion anonymizes, never hard-deletes. Kept: reservations (last_name), folios, payments, tickets, service records, reviews, ID scans on checked-out stays. Wiped: identity, preferences, all sign-in/device tokens, notifications, staff notes, OTP rows, guest chat text/attachments (incl. Firestore copies), personal data in activity-log entries (both `properties` and `attribute_changes`).
- Blocked while the guest has a live stay, an open folio or an upcoming service booking. Repeat call is harmless (row lock). Same phone can re-register as a new account.
- Rates: append-only `exchange_rates` (DECIMAL(20,6), units per 1 USD), SYP/TRY in `config/currency.php`; display-only, never charged. Change >50% needs `confirm_large_change`. Stale after `CURRENCY_STALE_AFTER_HOURS` (168).
- Permission: rate routes reuse `pricing.edit` (no preset holds it); catalogue stays 30/13; `$notYetBuilt` now empty.

## Flutter changes
Send `Accept-Language` on verify-otp; picker may send fr/tr/es; add delete-account flow (handle 422 block, expect 401 after success); fetch rates from `/public/exchange-rates`, keep built-in rates as fallback when `rate` is null, show "rates as of" when stale.

## Dashboard changes
Exchange-rate screen behind `pricing.edit` with confirm step for >50% changes; deleted-guest badge and filter; new error codes. See DASHBOARD_FRONTEND_HANDOFF.md §6.1.

## Deploy
- **[BLOCKING]** `php artisan migrate`: `2026_10_04_200000_add_account_status_to_guests_table` (existing rows become active), `2026_10_04_200100_create_exchange_rates_table`.
- No seeder change, no new permission. Run `config:cache` (new `config/currency.php`). Rates board is empty until staff enter the first SYP/TRY rates.

## Owner actions
1. Check which staff accounts already hold `pricing.edit` (they gain rate editing).
2. Decide who updates rates and how often; set `CURRENCY_STALE_AFTER_HOURS` if needed.
3. Update privacy policy, App Store privacy and Play Data-safety forms (bookings, payments, checked-out ID registrations are retained).
4. Website team: delete-my-account page (Play web-deletion requirement).
5. Brief Flutter and dashboard teams.
6. ID scans live on the public disk (Phase 4): separate security follow-up.
7. Phase 10 must forfeit loyalty balance on account deletion and index `loyalty_rewards.sort_order`.

## Deviations
1) Activity log v5 keeps old values in `attribute_changes`: both redacted, including phone in the guest's reservation audit rows. 2) Post-commit file deletion catches and logs failures. 3) `accepted` rule message added to BaseRequest + 5 locales. 4) Phase 4 directory/profile exact-key tests extended. 5) Unknown `?account_status=` returns 200 empty (shared filter behaviour). 6) `change_percent` is signed. 7) Unit tests in `tests/Unit/Guest/`. 8) Pint applied to new files only.

## Tests
Baseline 2376 → 2581 (+205) excluding Phase 10 work-in-progress tests, all green. A Phase 10 migration in the shared tree briefly failed `CmsListIndexTest` (`loyalty_rewards.sort_order` unindexed): not a 9.1 defect.

## Decision coverage
D-01..D-23 each map to code plus a test or doc; table in 09.1-09-SUMMARY.md.
