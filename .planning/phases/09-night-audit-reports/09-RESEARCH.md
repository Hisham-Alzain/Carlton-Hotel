# Phase 9 research (re-verified 2026-10-04)

Status: planning research, re-verified against the tree at `3886416` (Phase 8 closed). Supersedes the Codex draft research of 2026-10-03 where it conflicts with `09-CONTEXT.md` (D-01..D-25). No tests were run by the planner.

## Premise verification

| Premise | Where checked | Result |
|---|---|---|
| Routes at `/api`, no `/v1` | `bootstrap/app.php:22` (`api:` routes file, no `apiPrefix`) | holds |
| `HotelClock::today()`, `dayWindow()` strict + DST-safe half-open UTC, `timezone()` | `app/Support/HotelClock.php:20,26,42` | holds; `dayWindow` throws `InvalidArgumentException` on a non round-trip date |
| `FolioLedger` bcmath helpers | `app/Support/FolioLedger.php` (`normalize`, `fromNumeric`, `sum`, `paid`, `balance`) | holds; no SQL-aggregate helper exists → `MoneyAggregate` is new (D-21) |
| `RecordsRowLocks` | `tests/Concerns/RecordsRowLocks.php` (`lockedSelects`, `assertLocksRow`) | holds; records MySQL `for update` inside a SQLite comment |
| `whereDate` house idiom on reservation dates | `CheckAvailabilityAction:177-178`, `StayService:60-61`, `GuestFilter:65-79`, `CreateTurnoverTaskOnCheckOut:48` | holds |
| `payable_type` is FQCN | `RecordCashPaymentAction:36,45` (`get_class`), morph map `AppServiceProvider:70` (4 bookables only), `Folio::ledgerPayments` OR-join (`Folio.php:85-86`) | holds |
| Catalogue 29 / 12 | seeder list (`reports.view` line 34, Phase 8 `events.*` last), `SeederTest:14,43,231`, `PermissionsGroupedTest:29` | holds → 30 / 13 |
| `$notYetBuilt = ['pricing.edit','reports.view']` | `CmsAccessControlTest:124` | holds; the test scans route `permission:` middleware (pipe lists split) and `app/` string literals |
| Group labels translated? | `PermissionAssignmentService::groupedPermissions` (prefix split), `lang/en/custom.php` sections | no group-label keys exist → none to add |
| Locales | `lang/{en,ar,fr,tr,es}/custom.php`, 219 lines each; `tests/Feature/Cms/LocaleFoundationTest.php` | holds |
| Indexes: payments | `create_payments_table` (`morphs('payable')`, `index('recorded_by')`) | no status/created_at index → D-08 |
| Indexes: folio_items | `create_folio_items_table` (`folio_id`), `add_ledger_columns` (`posted_by`, `reverses_item_id`) | no created_at index → D-08 |
| Indexes: tickets | `create_tickets_table` (`status`, department, assignee, guest, chatbot), `add_support_columns` (reservation, room, created_by, source) | **only `status`; no `(status, priority)`** → R-1, no new index |
| Indexes: disputes | `create_folio_item_disputes_table` (`status`, `(folio_item_id,status)`) | holds |
| Rooms | `rooms.status` string(20) since `2026_09_26_100000`; `is_active` indexed; `Room` uses `SoftDeletes`; live-row unique on `number` | holds |
| Reservations | `booking_code` unique; status enum values; `reservation_rooms.room_id` nullable FK; `folios.reservation_id` unique | holds |
| Refund writer | `grep Refund:: app/` → only `Payment::refunds()` relation | none → `refunds_included:false` |
| Exceptions | flat `app/Exceptions/*`, `DomainException(message, ctx)`; handler uses `custom.errors.<code>` fallback (`bootstrap/app.php:60`) | holds |
| Query-budget idiom | `expectsDatabaseQueryCount(n)` in `EventInquiryDetailTest:233,246` | holds; R-4 uses a `DB::listen` counter to exclude `activity_log` |
| Tree | `docs/carlton-tree.html:304` (night audit), `:347` (reports), 88 `"api":true` | holds |

## Query and index design (final)

Audit first open (≤ 24 statements, D-22): state `insertOrIgnore` (init only) + state `select … for update`; audit lookup; audit insert; per category 1 COUNT + 1 LIMIT-20 sample (10, or fewer with the LIMIT-21 trick); 1 bulk check insert; 0–1 bulk blocker insert; eager loads (checks+actor, blockers+actor+check, opener, closer ≈ 5). Re-read ≤ 7: state lock/select, audit lookup, eager loads. Mutation ≤ 12: state lock, audit lock, child lock, update, reload eager loads.

Evaluator SQL shapes:
- Unsettled departures: `reservations` `whereDate(check_out, D)` + status IN + `(NOT EXISTS folio OR EXISTS folio status=open)` via `whereDoesntHave/orWhereHas('folio', …)` — one row per reservation by construction.
- Unassigned arrivals: `whereDate(check_in, D)` + status IN + `(doesntHave('rooms') OR whereHas rooms whereNull room_id)` — once per reservation.
- Dirty rooms: `Room::query()` (SoftDeletes scope) `where is_active` + `status=dirty`, order by `number`.
- High-priority tickets: `whereIn(status, TicketStatus::active())` + `priority >= 3`, order by id.
- Disputes: `folio_item_disputes` join `folio_items` join `folios` for `folio_uuid`, `status=open`, order by dispute id.

Reports (≤ 8 SELECTs, expected 7): capacity COUNT; room-night pairs GROUP BY (check_in, check_out); arrivals/departures conditional aggregate; folio lines GROUP BY source with cents CASE sums; payments GROUP BY payable_type with cents sum; SR status GROUP BY; ticket status GROUP BY. All period-independent in count.

Indexes added (D-08, own migration): `folio_items(created_at)`, `payments(status, created_at)`. The draft's extra `reservations(status, check_in, check_out)` and `payments(…, payable_type)` composites are dropped: existing single-column reservation indexes serve the bounded queries, and the payments grouping runs over the index-filtered window.

## Money

SQLite returns REAL for `SUM(DECIMAL)` (same issue `Folio::refreshTotals` works around). Per-row `CAST(ROUND(col*100) AS INTEGER)` then integer SUM is exact because stored values are 2dp; MySQL `ROUND(DECIMAL*100)` stays DECIMAL and is exact. `fromCents` normalises to an integer string and divides with bcmath. Signed 64-bit integer cents cannot overflow for DECIMAL(10,2) rows over a ≤31-day window at hotel scale; the unit test exercises near-max values × many rows.

## Occupancy

Portable fold (D-17): group eligible room lines by `(check_in, check_out)` pairs overlapping the period, then in PHP `max(0, min(check_out, to+1) − max(check_in, from))` nights × count, parsing both storage formats with `CarbonImmutable::parse(...)->toDateString()`. Rows bounded by distinct pairs, not reservations.

## Risks and limits (survive into SUMMARY and guide)

- SQLite proves lock intent (`RecordsRowLocks`) and unique constraints only; concurrent first-open/close serialization is MySQL-only (manual check).
- Snapshot is current state at first open, not a cross-domain point-in-time cut.
- `posted_folio_lines` changes while folios are open; reservation lines are lump-sum.
- Occupancy uses present inventory and current reservation status; no no-show status.
- Dashboard mocks need adaptation (fields, gate, first-use `date`); the API does not reproduce mock ADR/RevPAR/MTD/YTD.
- Baseline test count: orchestrator states 2167; Phase 8 SUMMARY records 2165 — re-measured in 09-01 (R-5).
