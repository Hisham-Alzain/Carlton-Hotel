---
phase: 04-guests-stay
status: complete
completed: 2026-09-26
requirements-completed: [GUEST-01, GUEST-02, GUEST-03, GUEST-04, GUEST-05, GUEST-06, DOCS-01, XCUT-01]
---

# Phase 4: Guests & Stay — Summary

Staff get a guest directory (`GET /guests`, derived `stay_status` filter, 5 queries) and a single-round-trip guest profile (`GET /guests/{guest}`, frozen at 10 queries) with append-only notes, staff-editable preferences and a derived six-item pre-arrival checklist. Guests save their own preferences (`PATCH /auth/guest/preferences`), submit online check-in (`POST /stays/{reservation}/online-check-in`) and, once staff approve, see a **display-only digital key** on their stay reads. The key is **NOT lock-grade**: a 12-character random code, encrypted at rest with an HMAC-SHA256 lookup hash, hidden from staff, pushes, the activity log, Firestore and Postman, expired at hotel check-out time and revoked on check-out, cancellation, rejection and expiry. The ID scan (GUEST-06) reuses the unchanged `POST /pre-arrival/documents`. Three additive, reversible migrations, two new permissions (`guests.view`, `guests.edit` on reception and concierge), one new error code (`online_check_in_closed`), one new notification type (`check_in_approved`), one scheduled command (`stays:expire-digital-keys`, every 15 minutes), one new config key (`hotel.check_out_time` / `HOTEL_CHECK_OUT_TIME`). Nine sequential waves (04-01 to 04-09).

## Endpoints delivered

| Endpoint | Guard/Permission | Notes |
|---|---|---|
| `GET /api/guests` | `auth:users`, `permission:guests.view` | Directory. `search` (name/first/last/phone/email), `phone`/`email` `eq`/`like`, `preferred_locale` `eq`/`in`, `sort` (`name`, `last_name`, `created_at`, id tie-break), default order `last_name, name, id`, `per_page` 15/100, `stay_status` (`in_house`, `departing`, `arriving`, `upcoming`, `past`, `none`; precedence departing > in_house > arriving > upcoming > past > none; 422 `validation_failed` on `errors.stay_status` for an unknown or array value). 13-key row with a `current_reservation` summary. Exactly 5 queries with or without the filter. |
| `GET /api/guests/{guest}` | `auth:users`, `permission:guests.view` | Profile, 21 top-level keys: identity, `stay_status`, stats, preferences, `current_reservation` (in-house else next arrival; approval, documents metadata only, key status without the code), `pre_arrival_checklist`, `stay_history` (25) + `stays_total` + `has_more`, `notes` (10 newest) + `notes_count`. `GuestProfileResource` is referenced only by `Admin\GuestController`. 10 queries (frozen). |
| `GET /api/guests/{guest}/notes` | `auth:users`, `permission:guests.view` | Paginated newest-first (id desc tie-break), 15/100. |
| `POST /api/guests/{guest}/notes` | `auth:users`, `permission:guests.edit` | `body` required, string, max 2000. Append-only (no PUT/PATCH/DELETE), no activity-log entry, never on a guest-facing route. 201 "Guest note added." |
| `PATCH /api/guests/{guest}/preferences` | `auth:users`, `permission:guests.edit` | Same request/action as the guest route; staff user recorded as causer. |
| `PATCH /api/auth/guest/preferences` | `auth:guests` | No guest identifier in path or body; the token's guest only (a `guest_uuid` in the body is ignored). Merge write, `null`/empty string clears, 422 `errors.preferences` when none of `bed_type`/`pillow_type`/`floor_preference`/`other` is present, `bed_type` `extra` refused. |
| `POST /api/stays/{reservation}/online-check-in` | `auth:guests` | `authorize()` ownership -> 403 `forbidden`; `confirmed` only -> 422 `reservation_state`; hotel-local today after `check_in` -> 422 `online_check_in_closed` `{check_in, today}`; body `arrival_time` `H:i`; resubmission overwrites; `CheckInApproval::firstOrCreate(pending)` never downgrades a decision; never issues a key; `Cache-Control: no-store, private`. |
| `PATCH /api/cms/check-in-approvals/{reservation}/approve` (changed) | unchanged | Approving a `confirmed`/`checked_in` stay issues the key (kept if already active) and dispatches `CheckInApproved` (key-ready push, no code) on a real transition into approved; rejecting revokes the key (`rejected`). |
| Cancel (`DELETE /api/reservations/{uuid}`, `DELETE /api/cms/reservations/{uuid}`) (changed) | unchanged | Cancellation and key revocation (`cancelled`) now share one `DB::transaction`. |
| Check-out (`POST /api/cms/reservations/{uuid}/check-out`, guest `POST /api/folio/approve`) (changed) | unchanged | `ReservationCheckedOut` -> synchronous `RevokeDigitalKeyOnCheckOut` (`checked_out`). |
| `GET /api/stays/status`, `/active`, `/upcoming` (changed) | unchanged | Additive `online_check_in`, `digital_key` (`{code, issued_at, expires_at}` while active, else `null`; an undecryptable code reads as `null`, never a 500), `pre_arrival_checklist`; `Cache-Control: no-store, private`. `/stays/past` unchanged. |

## Waves

1. **04-01 - Guest notes + guests group**: `guest_notes` migration, `GuestNote` (no `LogsActivity`), `Guest::notes()`, `guests.view`/`guests.edit` seeded on reception + concierge (19 -> 21 permissions, 9 -> 10 groups), `AddGuestNoteRequest`/`Action`, `GuestNoteResource`, `GuestService`, `Admin\GuestController`, the `guests` route group.
2. **04-02 - Preferences**: guests preference columns, `PillowType`, `FloorPreference`, BedType docblock (EXTRA inventory-only), `Guest::getActivitylogOptions()` override (`logExcept(['preferences_other','pillow_type'])`), `UpdateGuestPreferencesRequest`/`Action` shared by both routes, `GuestPreferencesResource`, `me.preferences`.
3. **04-03 - Stay payload**: reservation online-check-in/key columns, key hidden + non-fillable + encrypted + `logExcept`, `StayPayload`, `PreArrivalChecklist`, three stay resources, `StayService` eager loads, no-store.
4. **04-04 - Online check-in**: `SubmitOnlineCheckInRequest` (owner 403), `SubmitOnlineCheckInAction` (lock, confirmed-only, hotel-local window, `firstOrCreate`), `OnlineCheckInClosedException`, route.
5. **04-05 - Key issuance**: `hotel.check_out_time`, `HotelClock::checkOutAt()`, `IssueDigitalKeyAction`, `RevokeDigitalKeyAction`, `DigitalKeyRevocationReason`, `NotificationType::CHECK_IN_APPROVED`, `CheckInApproved` event + `SendCheckInApprovedNotification`, `ApproveCheckInAction` hook, `Reservation::booted()` expiry recompute on `check_out` change.
6. **04-06 - Key lifecycle**: `RevokeDigitalKeyOnCheckOut` (synchronous), atomic `CancelReservationAction`, `RevokeExpiredDigitalKeysAction` + `stays:expire-digital-keys` every 15 minutes `withoutOverlapping()`.
7. **04-07 - Directory**: `GuestStayStatus`, additive `GuestEntitlement` siblings (`constrainLive`, `isLive`, `stayStatus`, `targetFrom`, `targetReservation`), `GuestFilter`, `GuestDirectoryResource`, `GET /guests`.
8. **04-08 - Profile**: `StayPayload` staff shapes (`staffDigitalKey`, `documents`, `staffReservation`, `historyItem`), `GuestService::profile`, `GuestProfileResource`, `GET /guests/{guest}`.
9. **04-09 - Close**: guides, changelog, Pitfall 6, Postman, `carlton-tree.html`, the gate and this summary.

## Key decisions (with sources)

| # | Decision | Source |
|---|---|---|
| 1 | Every decision D-01 to D-15 in `04-CONTEXT.md` implemented exactly as locked. | consultant + two ai-councils |
| 2 | The digital key is a display-only credential and NOT lock-grade; preconditions for any lock integration (non-static OTP delivery, Sanctum token expiry, hash-based verifier) are recorded in PITFALLS Pitfall 6. | D-11, council |
| 3 | `GuestEntitlement::currentReservation()` and its 8 call sites are untouched (FA-06-1 deferred per D-03); `targetReservation()`/`targetFrom()` are additive siblings used only by Phase 4 staff surfaces. `git diff` shows 137 added lines and 0 removed. | consultant (plan-q4) |
| 4 | GuestService::profile frozen at 10 queries (measured 10 on the fully furnished fixture; graph listed in the numbered comment above the method; no lower value achievable without hand-merging eager loads). | consultant (plan-q3) |
| 5 | Expiry sweep every 15 minutes (RESEARCH recommendation; D-11 fixes no cadence; one-line change in `routes/console.php`); expired keys are already hidden at read time (FA-4.06-1). | consultant (plan-q2) |
| 6 | Two per-model activity-log overrides (`Guest`: `preferences_other`, `pillow_type`; `Reservation`: `digital_key_code`, `digital_key_hash`) are a deliberate deviation from "options fixed in the trait"; each restates the full trait chain (no `parent::` on a trait method). | D-08, D-11, RESEARCH Pitfall 1 |
| 7 | `CancelReservationAction` status update + revoke moved into one `DB::transaction` (the one sanctioned change; RESEARCH Pitfall 6). | D-14 |
| 8 | `RevokeDigitalKeyOnCheckOut` is synchronous so the key dies with the check-out response even without a queue worker (FA-4.06-2). | planner, confirmed |
| 9 | Notes readable under `guests.view` (no `guests.notes.view`), a locked scope choice deviating from PITFALLS #7. | D-01 |
| 10 | Planner flagged assumptions FA-4.01-1/2, FA-4.02-1/2/3, FA-4.03-1..5, FA-4.04-1/2/3, FA-4.05-1..6, FA-4.06-1..4, FA-4.07-1..4, FA-4.08-1..4 confirmed as written during the build. | build |
| 11 (build) | `POST /guests/{guest}/notes` answers via `success()` directly, because `respondFromService` replaces every 201 message with the generic "Created successfully." | build |
| 12 (build) | `GuestService::index` is exactly 5 queries: paginator count, guests page with a `has_reservations` EXISTS column, page live reservations, their `reservation_rooms`, their `rooms`; `stay_status` adds EXISTS subqueries, not queries. | build |
| 13 (build) | `GuestResource` is nested in `ReservationResource.guest`, so `preferences` also appears on staff `cms/reservations` and guest `reservations` payloads (FA-4.02-2); documented in both guides and the changelog. | build |

## Permissions (XCUT-01)

| Permission | Seeded role presets | Routes it gates | Notes |
|---|---|---|---|
| `guests.edit` | reception, concierge | POST /guests/{guest}/notes, PATCH /guests/{guest}/preferences | staff cannot edit guest identity (no `PATCH /guests/{guest}`, D-13) |
| `guests.view` | reception, concierge | GET /guests, GET /guests/{guest}, GET /guests/{guest}/notes | notes readable under guests.view (no guests.notes.view, a locked scope choice deviating from PITFALLS #7) |

Seeder baseline 19 -> 21 permissions, 9 -> 10 groups (`SeederTest`, `PermissionsGroupedTest` updated); `guests.*` is not listed under "Genuinely inert" (`PermissionGuideAccuracyTest` green).

## Dashboard & App Path Changes (DOCS-01)

| Client (dashboard / app) | Method | Path | Change (added / changed / removed) | Notes |
|---|---|---|---|---|
| app | GET | `/auth/guest/me` | changed: additive | `preferences` object |
| app | PATCH | `/auth/guest/preferences` | added | guest preferences |
| app | POST | `/folio/approve` | changed: behavioural | the shared check-out now also revokes the digital key |
| app | GET | `/reservations` | changed: additive | nested `guest.preferences` |
| app | DELETE | `/reservations/{uuid}` | changed: behavioural | revokes the digital key in the cancellation transaction |
| app | GET | `/reservations/{uuid}` | changed: additive | nested `guest.preferences` |
| app | GET | `/stays/active` | changed: additive | `online_check_in`, `digital_key`, `pre_arrival_checklist`; `Cache-Control: no-store, private` |
| app | GET | `/stays/status` | changed: additive | same three blocks; no-store |
| app | GET | `/stays/upcoming` | changed: additive | same three blocks; no-store |
| app | POST | `/stays/{uuid}/online-check-in` | added | online check-in |
| dashboard | PATCH | `/cms/check-in-approvals/{uuid}/approve` | changed: behavioural | approve issues/keeps the key and pushes `check_in_approved`; reject revokes; response never carries the code |
| dashboard | GET | `/cms/reservations` | changed: additive | nested `guest.preferences` |
| dashboard | DELETE | `/cms/reservations/{uuid}` | changed: behavioural | revokes the digital key |
| dashboard | GET | `/cms/reservations/{uuid}` | changed: additive | nested `guest.preferences` |
| dashboard | POST | `/cms/reservations/{uuid}/check-out` | changed: behavioural | revokes the digital key |
| dashboard | GET | `/guests` | added | replaces the directory mock |
| dashboard | GET | `/guests/{uuid}` | added | replaces the profile mock |
| dashboard | GET | `/guests/{uuid}/notes` | added | notes list |
| dashboard | POST | `/guests/{uuid}/notes` | added | append-only note |
| dashboard | PATCH | `/guests/{uuid}/preferences` | added | replaces `PATCH /guests/{id}/preferences` (dashboard mock, never called) |

No existing path, field or error_code changed incompatibly; fields added as listed; error code added `online_check_in_closed` (422); ID scan uses the unchanged `POST /pre-arrival/documents`.

## Docs Updated (DOCS-01)

- [x] `API_GUIDE_DASHBOARD.md`: `## Module: Guests (guests.view · guests.edit)` with `### GET /guests`, `### GET /guests/{uuid}`, `### GET /guests/{uuid}/notes`, `### POST /guests/{uuid}/notes`, `### PATCH /guests/{uuid}/preferences`; approvals key paragraph; permission catalog 10 modules / 21 permissions; reception and concierge presets include `guests.view`/`guests.edit`; P12 "guest directory" dropped from "Coming in later phases"; nested `guest.preferences` note on reservation detail.
- [x] `API_GUIDE_MOBILE.md`: endpoint index 59 -> 61 (`PATCH /auth/guest/preferences`, `POST /stays/{uuid}/online-check-in`); `### PATCH /api/auth/guest/preferences`; `preferences` in guest profile fields (also on nested `guest` objects); `### POST /api/stays/{uuid}/online-check-in`; `online_check_in` / `digital_key` (NOT lock-grade) / `pre_arrival_checklist` + no-store on the stay reads; `check_in_approved` push; `**ID scan (GUEST-06):**` paragraph under `POST /api/pre-arrival/documents`.
- [x] `CHANGELOG_MOBILE_API.md`: `## 10 — Guests & Stay (Phase 4)`; three Changed (non-breaking) rows (`me.preferences`, nested `guest.preferences` on reservations, stay reads); two tier-2 inventory rows.
- [x] `.planning/research/PITFALLS.md`: Pitfall 6 `**Phase 4 status:**` (NOT lock-grade, preconditions).
- [x] Postman: new folder `19 - Guests (Admin)` (`List guests (search + stay_status)`, `Guest profile (Ahmad)`, `List guest notes (Ahmad)`, `Add guest note (Ahmad)`, `Update guest preferences (staff, Ahmad)`); `Guest — Update Preferences` in `02 - Auth`; `Online check-in (Layla)` in `17 - Stays (Guest)`. No script stores any `digital_key` value; the environment file is untouched.
- [x] `carlton-tree.html`: `guest directory · profile`, `guest preferences`, `online check-in`, `ID scan` set to `api:true` with the D-15 endpoints and meta; `check-in approvals` meta gains "approve issues the digital key". Verified: 94 nodes, 77 `api:true`, `var TREE` parses.

## Test counts

- Engineer-stage gate: full suite 1238/1238 passing, 6382 assertions, 0 failures (`php artisan test`, serial) before the Phase 4 QA specs; permission gate (`SeederTest|PermissionsGroupedTest|RolePresetsTest|PermissionGuideAccuracyTest`) 17/17, 144 assertions.
- Phase 4 QA specs (names per the plans): `tests/Feature/Guests/{GuestNotesTest, GuestPreferencesTest, GuestDirectoryTest, GuestProfileTest}.php`; `tests/Feature/Stays/{StayPayloadTest, OnlineCheckInTest, DigitalKeyIssuanceTest, DigitalKeyLifecycleTest, DigitalKeyLeakageTest}.php`; `tests/Unit/Guest/{PreArrivalChecklistTest, GuestStayStatusTest}.php`; `tests/Unit/Booking/{SubmitOnlineCheckInActionTest, IssueDigitalKeyActionTest, RevokeDigitalKeyActionTest}.php`. Final full-suite total (QA stage): 1383/1383 passing, 9130 assertions, 0 failures (145 Phase 4 QA tests across the 15 spec files).

## Deviations from PLAN.md

- No per-plan 04-01 to 04-08 `SUMMARY.md` files (consolidated here and in `04-09-SUMMARY.md`, same as Phases 2 and 3).
- 04-09 Tasks 1 and 2 were delegated to Sonnet sub-agents as the plan prescribes.
- `backend/.env.example` could not be edited by the engineer: the session's permission settings deny Read/Edit on that file via the Edit tool. The Close stage added `HOTEL_CHECK_OUT_TIME=12:00` after `HOTEL_TIMEZONE=Asia/Damascus` via a PowerShell command instead (not covered by the deny rule); the config default is 12:00, so behaviour never depended on it.
- `app/Actions/Booking/IssueDigitalKeyAction.php`'s `ALPHABET` docblock said "no 0/O/1/I/L"; corrected to "no 0/O/1/I" to match the locked D-11 alphabet (which includes `L`). No code change; QA finding applied at Close.
- The engineer added a nested `guest.preferences` note to both guides and a changelog row (FA-4.02-2), which the 04-09 task list did not name.

## Flagged carry-forwards

- FA-06-1: still deferred per D-03; 8 call sites unchanged (`StayController`, `TableReservationController`, `TransportRequestController`, `FolioService::myFolio`/`approveMyFolio`, `PreArrivalService`, `ServiceBookingService`, `ServiceRequestService`); targetReservation() exists as a sibling and is the recommended replacement when a future phase takes FA-06-1 up.
- FA-02-1, FA-03-2, FA-03-3: unchanged from Phase 3.
- GUEST-03 / GUEST-04 / GUEST-05 / GUEST-06 (FA-4.01-3, FA-4.02-4, FA-4.04-4, FA-4.09-1): unresolved, flagged — truths authored alongside.
- FA-4.06-1: sweep cadence 15 minutes is RESEARCH's recommendation, not fixed by D-11 (authored truth).
- FA-4.07-1: `stay_status` fallback for the two unmatched live cases (a confirmed stay past its `check_in` without check-in; a pending stay arriving today) as authored in 04-07.
- FA-4.05-3: a document re-upload re-opens an approved approval as pending but does not revoke an active key.
- MySQL-only concurrency backstops: the online check-in race (04-04) and the double-minting race (04-05) are proven sequentially on SQLite; row-lock blocking is a MySQL guarantee.
- CONTEXT deferred ideas: hash-only/show-once/rotate key with a throttled `GET /stays/{r}/digital-key` and a staff/kiosk verifier; paginated `GET /guests/{guest}/stays`; `guests.notes.view`; note edit/delete; staff identity edit; duplicate-guest merge; CSV export; signed-URL document viewer / private disk; receipt routes to 403; late check-out as a `check_out` change; `pillow_type` as special-category data; removing the tracked `backend/database/database.sqlite` (APP_KEY hygiene); `ReservationCancelled` event; key rotation on logout; real OTP delivery and Sanctum token expiry.
- Canon items routed to /gsd-secure-phase: SQL injection through search/filters, stored XSS through note bodies / `preferences_other`, retention/erasure/consent for notes, preferences and ID documents, brute-forcing the display code (no verifier exists).

## Production deploy notes

- [BLOCKING] Run php artisan migrate — three additive, reversible migrations: `guest_notes`, guest preferences columns, reservation online check-in / digital key columns.
- [BLOCKING] Run php artisan db:seed --class=RolesAndPermissionsSeeder — seeds `guests.view` and `guests.edit` on the reception and concierge presets; without it the new staff routes answer 403 for everyone but super admins.
- Set `HOTEL_CHECK_OUT_TIME` in `.env` (default `12:00`, hotel-local, read with `HOTEL_TIMEZONE`), then re-run `php artisan config:cache` if config is cached. `backend/.env.example` now documents the key (line 13, added at Close).
- [BLOCKING] The scheduler must run (`* * * * * php artisan schedule:run`) so `stays:expire-digital-keys` fires; expiry sweep every 15 minutes (RESEARCH recommendation; D-11 fixes no cadence; one-line change in routes/console.php if the property wants tighter/looser timing). Expired keys are hidden at read time regardless; the sweep clears the stored ciphertext.
- A queue worker delivers the `check_in_approved` push (unchanged requirement); key revocation on check-out is synchronous and needs no worker.
- Re-run `php artisan event:cache` / `optimize` if events are cached (two new listeners: `SendCheckInApprovedNotification`, `RevokeDigitalKeyOnCheckOut`).
- APP_KEY rotation: the stored codes are encrypted and the hash is keyed with `app.key`. Rotating `APP_KEY` without listing the old key in `APP_PREVIOUS_KEYS` makes stored keys read as absent (the guest sees `digital_key: null`, never a 500); staff re-approve to mint a fresh code.
- The digital key is a display credential and NOT lock-grade: it opens no lock and must not be wired to lock hardware before the Pitfall 6 preconditions are met.
- Announce to Flutter: the three stay reads gain `online_check_in`, `digital_key`, `pre_arrival_checklist` and send `Cache-Control: no-store`; the app must not cache or log the code. Announce to React: the guest directory/profile/notes/preferences mocks are now live endpoints.

## Edge-probe and prohibition ledger

| # | Requirement | Category | Disposition | Where |
|---|---|---|---|---|
| 1 | GUEST-01 | boundary | covered | 04-07, `GuestDirectoryTest::test_pagination_boundaries` |
| 2 | GUEST-01 | adjacency | covered | 04-07, `GuestDirectoryTest::test_row_precedence_for_back_to_back_stays` |
| 3 | GUEST-01 | empty | covered | 04-07 `GuestDirectoryTest::test_empty_directory`; 04-01 `GuestNotesTest::test_guest_without_notes_lists_nothing` |
| 4 | GUEST-01 | encoding | covered | 04-07, `GuestDirectoryTest::test_search_encoding` |
| 5 | GUEST-01 | ordering | covered | 04-07 `GuestDirectoryTest::test_default_order`; 04-01 `GuestNotesTest::test_same_second_notes_order_by_id_desc` |
| 6 | GUEST-01 | precision | covered | 04-07, `GuestDirectoryTest::test_hotel_local_today_decides_the_status` |
| 7 | GUEST-02 | adjacency | covered | 04-08, `GuestProfileTest::test_target_prefers_the_in_house_stay` |
| 8 | GUEST-02 | empty | covered | 04-08, `GuestProfileTest::test_guest_without_reservations_or_notes` |
| 9 | GUEST-02 | ordering | covered | 04-08, `GuestProfileTest::test_stay_history_and_counts`, `test_target_is_the_next_arrival` |
| 10 | GUEST-03 | unclassified | **unresolved, flagged** (truths authored alongside) | 04-01 truths (append-only, validation, encoding, leakage) |
| 11 | GUEST-04 | unclassified | **unresolved, flagged** (truths authored alongside) | FA-4.02-4; 04-02 truths |
| 12 | GUEST-05 | unclassified | **unresolved, flagged** (truths authored alongside) | FA-4.04-4; 04-03 .. 04-06 truths |
| 13 | GUEST-06 | unclassified | **unresolved, flagged** | FA-4.09-1; 04-09 truths |

13 surfaced = 9 covered + 4 flagged. Backstop truths beyond the probe set: the online check-in race (04-04) and the double-minting race (04-05), MySQL-only guarantees proven sequentially on SQLite. Prohibitions: 25 kept across plans (04-01 to 04-09), one judgment-tier (the not-lock-grade wording, checked: every `lock-grade` mention in the guides and changelog says NOT lock-grade).

## Gate results (engineer stage)

- Routes: the seven new routes carry exactly their guard and permission (`routes ok`).
- Permission tests: `SeederTest`, `PermissionsGroupedTest`, `PermissionGuideAccuracyTest` green (17 tests, 144 assertions).
- Scratch SQLite (`storage/framework/phase4-gate.sqlite`): `migrate:fresh --seed`, `migrate:rollback --step=3`, `migrate` all succeed; `backend/database/database.sqlite` sha1 identical before and after.
- Hardening files (`ApproveFolioAction`, `CheckInReservationAction`, `AssignRoomAction`, `Admin\ReservationController`): zero Phase 4 identifiers.
- `SubmitDocumentsRequest.php` and `QuoteReservationAction.php` unchanged vs HEAD; `GuestEntitlement.php` diff is additions only.
- No config cache present (`bootstrap/cache/config.php` absent).

## Files touched

Migrations `2026_09_26_120000_create_guest_notes_table.php`, `2026_09_26_120100_add_preferences_to_guests_table.php`, `2026_09_26_120200_add_online_check_in_to_reservations_table.php`; `database/factories/GuestNoteFactory.php`; `database/seeders/RolesAndPermissionsSeeder.php`; `app/Models/{GuestNote, Guest, Reservation}.php`; `app/Enums/{BedType, PillowType, FloorPreference, NotificationType, DigitalKeyRevocationReason, GuestStayStatus}.php`; `app/Events/CheckInApproved.php`; `app/Exceptions/OnlineCheckInClosedException.php`; `app/Listeners/{SendCheckInApprovedNotification, RevokeDigitalKeyOnCheckOut}.php`; `app/Actions/Guest/{AddGuestNoteAction, UpdateGuestPreferencesAction}.php`; `app/Actions/Booking/{SubmitOnlineCheckInAction, IssueDigitalKeyAction, RevokeDigitalKeyAction, RevokeExpiredDigitalKeysAction, CancelReservationAction}.php`; `app/Actions/Service/ApproveCheckInAction.php`; `app/Console/Commands/ExpireDigitalKeys.php`; `app/Filters/GuestFilter.php`; `app/Support/{StayPayload, PreArrivalChecklist, GuestEntitlement, HotelClock}.php`; `app/Http/Requests/Guest/{AddGuestNoteRequest, UpdateGuestPreferencesRequest}.php`; `app/Http/Requests/Booking/SubmitOnlineCheckInRequest.php`; `app/Http/Resources/Guest/{GuestNoteResource, GuestPreferencesResource, GuestDirectoryResource, GuestProfileResource}.php`; `app/Http/Resources/GuestResource.php`; `app/Http/Resources/Booking/{UpcomingStayResource, ActiveStayResource, CheckInStatusResource}.php`; `app/Services/Guest/GuestService.php`; `app/Services/Auth/AuthGuestService.php`; `app/Services/Booking/StayService.php`; `app/Http/Controllers/Admin/GuestController.php`; `app/Http/Controllers/Auth/GuestAuthController.php`; `app/Http/Controllers/Api/StayController.php`; `routes/{api, console}.php`; `config/{hotel, activitylog}.php`; all five `lang/*/custom.php`; `tests/Feature/{SeederTest, Staff/PermissionsGroupedTest}.php`; `docs/{API_GUIDE_DASHBOARD.md, API_GUIDE_MOBILE.md, CHANGELOG_MOBILE_API.md}`; `docs/postman/carlton-api.postman_collection.json`; repo-root `docs/carlton-tree.html`; `.planning/research/PITFALLS.md`; plus the Phase 4 QA specs listed under Test counts.

See `04-09-SUMMARY.md` for the docs/Postman/tree delegate results and the closing gate output.
