# Phase 9: Night Audit & Reports — decisions (final)

Date: 2026-10-04. Mode: MVP. Consultant: **Opus 5.5 standing in for Fable; no ai-council was convened** (the owner delegated every decision; nothing goes back to the user). This file supersedes the Codex draft of 2026-10-03 (committed in `3886416`). Decisions the draft got right are kept; every decision marked **Δ** changes the draft and states why. The draft's 09-01..09-04 plans were deleted and re-planned as 09-01..09-10.

Binding source: the consultant decision set D-01..D-25 (scratchpad `09-consultant-decisions.md`, transcribed here). Read with `09-RESEARCH.md`, `09-PATTERNS.md`, `09-VALIDATION.md` and the plans.

Phase base: `3886416` (Phase 8 closed, full suite 2167 green per the orchestrator; the Phase 8 SUMMARY records 2165 at its final gate — 09-01 Task 1 re-measures and records the real baseline).

## Verified code premises (re-checked by the planner, 2026-10-04)

- Routes mount at `/api`; `bootstrap/app.php` sets no `apiPrefix`. All Phase 9 paths are `/api/...`, never `/api/v1/...` (code wins, as in Phases 2–8).
- `reports.view` is seeded (seeder line 34), in no preset, and listed in `CmsAccessControlTest::$notYetBuilt = ['pricing.edit','reports.view']` (line 124). Catalogue = 29 permissions / 12 groups: `SeederTest` pins 29 at lines 14 (test name), 43 and 231; `PermissionsGroupedTest:29` pins 12 groups. Groups come from the prefix before the first dot (`PermissionAssignmentService::groupedPermissions`); there are **no group-label translation keys**, so no locale work for the `night_audit` group.
- Presets: reception, kitchen, housekeeping, concierge, events, content_editor, content_manager. None is a management preset.
- `reservations.check_in/check_out` are `date` casts; the house idiom is `whereDate()` (`CheckAvailabilityAction:177`, `StayService:60`, `GuestFilter`, `CreateTurnoverTaskOnCheckOut`). `booking_code` is unique on reservations; `rooms.number` string(10), `rooms.uuid`; `Room` uses `SoftDeletes`; `rooms.status` is string(20) `available|dirty|maintenance` since Phase 2; `is_active` indexed.
- `payments`: `morphs('payable')`, `status` string default `completed`, index only on `recorded_by` besides the morph index. `RecordCashPaymentAction` writes `payable_type` with `get_class()`; the morph map (`AppServiceProvider:70`) holds only spa_service, restaurant_table, pool_cabana, transfer, so Reservation/Folio/EventInquiry morph classes are FQCNs.
- `folio_items`: indexes on folio_id, posted_by, reverses_item_id; **none on created_at**. `FolioItemSource` = reservation, service_booking, service_request, manual, credit. `folios.reservation_id` unique; `FolioStatus` = open, settled.
- `folio_item_disputes`: uuid, folio_item_id FK, status string(16) indexed (`FolioDisputeStatus` open/resolved/rejected).
- `tickets.priority` unsignedTinyInteger default 2; `ServiceRequestPriority::fromTicketScale` maps `>=3` to HIGH; `TicketStatus` open/assigned/in_progress/waiting_guest/resolved/closed. **Correction to the consultant facts:** tickets carry an index on `status` only — there is no `(status, priority)` composite. Planner ruling R-1: no new ticket index (the active-status filter uses `tickets_status_index`, ticket volume is small); revisit only if EXPLAIN on MySQL shows a scan.
- `ReservationStatus` = pending_verification, pending, confirmed, checked_in, checked_out, cancelled. No no-show status.
- `refunds` has a model and `Payment::refunds()` relation; nothing in `app/` writes it.
- `HotelClock::today()`, `::dayWindow(Y-m-d)` (strict round trip, half-open UTC pair), `::timezone()`. `FolioLedger::normalize/fromNumeric/sum/paid/balance` (bcmath). `tests/Concerns/RecordsRowLocks` (`lockedSelects`, `assertLocksRow`). House query-budget idiom: `expectsDatabaseQueryCount(n)` (Phase 8 `EventInquiryDetailTest`).
- Domain exceptions are flat in `app/Exceptions/`, extend `DomainException(message, ctx)`, and the handler falls back to `custom.errors.<code>` when the message is empty. `ForbiddenException` (`forbidden`, 403) exists. The error envelope is `{success:false, message, error_code, context, request_id}` (`bootstrap/app.php:57-64`), so D-23 contexts appear at `context.*`. `BaseController::respondFromService(array $result, string $messageKey = 'custom.messages.success', ?Request $request = null)`. `lang/{en,ar,fr,tr,es}/custom.php` are 219 lines each with sections messages, errors, auth, notifications, receipt, health, validation, event_checklist, attributes; `LocaleFoundationTest` pins parity.
- Staff controllers live in `app/Http/Controllers/Admin/` and extend `App\Base\BaseController` (`respondFromService`). `HasUuid` sets `getRouteKeyName() = uuid`. Existing `/operations/queue/...` routes use the `operations/queue` prefix, so `/operations/night-audit/...` does not collide.
- `docs/carlton-tree.html`: "night audit" (line 304) and "reports" (line 347) nodes are `api:false` with "(mock)" endpoints; 88 nodes are `api:true` today. API guide at `backend/docs/API_GUIDE_DASHBOARD.md`, Postman at `backend/docs/postman/carlton-api.postman_collection.json`.

## A. Routes and contract

**D-01 — Endpoints.** All `auth:users`, under `/api`, no `/v1`, no alias routes.

| # | Method + path | Route middleware | Body / query |
|---|---|---|---|
| 1 | GET `/operations/night-audit` | `permission:reports.view\|night_audit.manage` **Δ** | optional `date` (strict `Y-m-d`) |
| 2 | PATCH `/operations/night-audit/checks/{check}` | `permission:night_audit.manage` **Δ** | `status` ∈ {resolved, overridden}; `note` required, trimmed, 1..1000 |
| 3 | PATCH `/operations/night-audit/blockers/{blocker}` | `permission:night_audit.manage` **Δ** | `note` required, trimmed, 1..1000; `status` optional, if sent must be `resolved` |
| 4 | POST `/operations/night-audit/{audit}/close` | `permission:night_audit.manage` **Δ** | no body; any client actor, date, status or counts are ignored |
| 5 | GET `/reports/dashboard` | `permission:reports.view` | `date_from`, `date_to` |

`{check}`, `{blocker}`, `{audit}` bind by `uuid`; unknown uuid = standard 404 `not_found`. Route 4 is a necessary scope addition (AUDIT-04); closing via a checkbox tick is rejected. Δ: `night_audit.manage` replaces the draft's `reports.manage` (D-15).

**D-02 — Controllers and layers.** `Admin/NightAuditController` (`show`, `updateCheck`, `resolveBlocker`, `close`) and `Admin/ReportController::dashboard`, both extending `BaseController` (not CRUD). Services: `Services/Operations/NightAuditService` (reads + resource load), `Services/Reports/ReportService` (aggregates; money SQL via D-21). Actions in `Actions/NightAudit/`: `OpenNightAuditAction` (lazy creation + evaluation; evaluators in private methods or one `NightAuditEvaluator` support class, one method per type), `ResolveNightAuditCheckAction`, `ResolveNightAuditBlockerAction`, `CloseNightAuditAction`. Requests: `ShowNightAuditRequest`, `ResolveNightAuditCheckRequest`, `ResolveNightAuditBlockerRequest`, `ReportDashboardRequest`. Resources: `NightAuditResource`, `NightAuditCheckResource`, `NightAuditBlockerResource`, `ReportDashboardResource` (or array shaping in the service; no queries either way). No Filter classes.

## B. Business date and time

**D-03 — Persisted business-date state** (draft D-01 kept, with additions). Singleton `night_audit_states` row: `current_business_date`, nullable `last_closed_date`. The audit date comes from the request or this row, never the wall clock.
- If no state row: GET *with* `date` initializes it to that date and opens that audit. **Δ** Only a `night_audit.manage` holder may initialize; the controller passes `$user->can('night_audit.manage')` as a bool (super-admin passes via `Gate::before`). A `reports.view`-only actor gets **403 `forbidden`**, context `{reason:"night_audit_not_initialized"}`. Without `date`, everyone gets **422 `night_audit_not_initialized`**, context `{requires:"date"}`. Reason: choosing the accounting start date is a setup act, not AUDIT-01's immutable snapshot creation.
- After initialization an omitted `date` means `current_business_date`.
- No seeder/migration default, no reset endpoint, no backfill. Guide runbook: "the first night auditor opens `?date=<the night being closed>`".

**D-04 — Readable vs creatable dates; future guard.** `target = date ?? current_business_date`, then:
1. Audit exists for `target` → return it (history, anyone allowed on route 1).
2. Else `target ≠ current_business_date` → 422 `night_audit_date_mismatch` `{requested_date, current_business_date}`.
3. **Δ** Else `target > HotelClock::today()` → **200 with `audit: null`** and the state block (close D at 23:30 then reload is not an error).
4. Else create and evaluate (D-05).
The clock only bounds *creation*; it never picks a date. Initialization applies the same bound: a future initial date → 422 `night_audit_date_in_future` `{requested_date, hotel_today}`. Dissent noted (strict "no clock at all") and rejected.

**D-05 — Hotel-local date semantics.** `business_date` is a pure DATE. Arrival/departure matching uses `whereDate()` against `check_in/check_out` (required by SQLite's `Y-m-d 00:00:00` storage). `evaluated_at`, `acted_at`, `closed_at` stored UTC, serialized ISO-8601 Z. Reports convert a period to `[dayWindow(from)[0], dayWindow(to)[1])` (Phase 6 rule, Phase 8 D-22). Never SQL `DATE(created_at)`, never assume a 24-hour local day, never rely on server/DB timezone. DST tests use `hotel.timezone = Europe/London` on the October change (Phase 8 PR-5 precedent).

## C. Schema (additive only)

**D-06 — Check vs blocker. Δ, high stakes.** The draft's one blocker per non-empty check is replaced:
- A **check** is the persisted review of one category; always exactly 5. Empty → `passed` (no actor/note). Non-empty → `pending`; staff move it to `resolved` ("verified fixed in the source") or `overridden` ("accept this exception"), note mandatory for both.
- A **blocker** exists only for the two **blocking** categories, `unsettled_departures` and `unassigned_arrivals` (both date-scoped), and only when non-empty. States `open|resolved`; **no override**; resolving needs a note.
- `dirty_rooms`, `open_high_priority_tickets`, `open_folio_disputes` are **advisory** (check, no blocker): current-state, and FOLIO-03 says disputes are flagged but never block.
- Each check exposes `blocking: bool` and `blocker_uuid|null`. Resolving a check never touches its blocker and vice versa.
- Close requires every check terminal (`passed|resolved|overridden`) **and** every blocker `resolved`.
- Blocker resolution is an **attestation**, not a live re-check (GUEST_EXPRESS / STAFF_FORCE check-outs legitimately leave open folios for days). Dissent (live verification) deferred until a city-ledger/AR flow exists.
- Neither action edits source data (no settling, room changes, ticket closes, cancels, check-outs).
- Bounds: ≤ 5 checks and ≤ 2 blockers per audit.

**D-07 — Tables.** New, no soft deletes, no delete endpoints, bigint internal ids, `uuid` public, FKs indexed with explicit `restrictOnDelete`.
1. `night_audit_states`: id; `singleton` unsignedTinyInteger unique (always 1); `current_business_date` DATE; `last_closed_date` DATE nullable; timestamps. No uuid.
2. `night_audits`: id; uuid unique; `business_date` DATE **unique**; `status` string(16) indexed (`open|closed`); `snapshot_basis` string(32) (`current_state_at_open`); `evaluated_at` timestamp; **Δ** `opened_by` FK users nullable restrictOnDelete indexed; `closed_by` FK users nullable restrictOnDelete indexed; `closed_at` nullable; timestamps.
3. `night_audit_checks`: id; uuid unique; `night_audit_id` FK restrictOnDelete; `type` string(40); `blocking` boolean; `status` string(16); `issue_count` unsigned int; `evidence` JSON; `evidence_truncated` bool; `note` string(1000) nullable; `acted_by` FK users nullable restrictOnDelete indexed; `acted_at` nullable; timestamps; unique (`night_audit_id`,`type`) (also the FK index).
4. `night_audit_blockers`: id; uuid unique; `night_audit_id` FK restrictOnDelete indexed; `night_audit_check_id` FK **unique** restrictOnDelete; `status` string(16); `note` string(1000) nullable; `acted_by` FK users nullable restrictOnDelete indexed; `acted_at` nullable; timestamps. Type/count/evidence read through the check.
Snapshot columns (`type`, `blocking`, `issue_count`, `evidence`, `evidence_truncated`, `evaluated_at`, `snapshot_basis`) are written once by the opener and never updated.
Enums (`app/Enums`): `NightAuditStatus` (open, closed); `NightAuditCheckType` (5 cases, fixed order, `isBlocking()`); `NightAuditCheckStatus` (passed, pending, resolved, overridden, `isTerminal()`); `NightAuditBlockerStatus` (open, resolved).

**D-08 — Report indexes. Δ, additive.** Own migration `…_add_report_indexes.php`: `folio_items(created_at)` and `payments(status, created_at)`; `down()` drops only these two. Already indexed: reservations check_in/check_out/status, rooms status/is_active, `folio_item_disputes.status`, `tickets.status` (R-1: no composite needed). Rollback tested on a scratch DB only.

## D. Evaluators (the snapshot)

**D-09 — Snapshot semantics** (draft D-03 kept). Evaluate once on first open; persist `evaluated_at` and `snapshot_basis = current_state_at_open`. Re-open never re-evaluates or duplicates rows. Dirty rooms, tickets, disputes are current state at opening even for old dates (stated in response and guide). Operational review snapshot, not a serializable accounting cut-off; source domains are not globally locked.

**D-10 — The five evaluators.** `D` = business date. Each yields an exact `issue_count`, ≤ 20 evidence entries in stable order, `evidence_truncated = issue_count > 20`. Evidence carries public identifiers only (no names, phones, complaint text, dispute reasons).

| Order | type | blocking | Counts | Evidence entry, ordered by |
|---|---|---|---|---|
| 1 | `unsettled_departures` | yes | reservations `whereDate(check_out, D)`, status ∈ {confirmed, checked_in, checked_out}, and (no folio OR folio.status = open); once per reservation; zero-balance open folio counts | `{reservation_uuid, booking_code}` by `booking_code`, `id` |
| 2 | `unassigned_arrivals` | yes | reservations `whereDate(check_in, D)`, status ∈ {confirmed, checked_in}, and (no reservation_rooms line OR ≥1 line with `room_id IS NULL`); once per reservation | `{reservation_uuid, booking_code}` by `booking_code`, `id` |
| 3 | `dirty_rooms` | no | live (not trashed), `is_active` rooms with status `dirty`; maintenance/inactive/trashed excluded | `{room_uuid, number}` by `number` |
| 4 | `open_high_priority_tickets` | no | status ∈ `TicketStatus::active()` and `priority >= 3` | `{ticket_uuid}` by `id` |
| 5 | `open_folio_disputes` | no | `folio_item_disputes.status = open`, per dispute row, independent of reservation date/folio status | `{dispute_uuid, folio_uuid}` (one join via folio_items→folios) by `id` |

Pending, pending_verification and cancelled reservations are excluded everywhere. COUNT/EXISTS plus `LIMIT 20` sample (or `LIMIT 21` with count derived when fewer than 21 rows); never an unbounded `get()`. Phase 7 boundary: no `ticket_recoveries` / `recorded_value_usd`. Phase 6 boundary: `dirty_rooms` reads `rooms.status`, not housekeeping tasks. Labels `custom.night_audit.checks.<type>` in 5 locales.

**D-11 — Idempotent lazy creation** (draft D-02 refined). `OpenNightAuditAction` in one `DB::transaction`: (1) for initialization only `insertOrIgnore(['singleton'=>1, …])`; then select the state row `lockForUpdate` — every opener, resolver and closer serializes on it first. (2) Under the lock look up the audit by `business_date`; return if found. (3) Else apply D-04, insert the audit, run the 5 evaluators, bulk-insert 5 checks, then 0–2 blockers. Backstop: unique `night_audits.business_date`; if Laravel's `UniqueConstraintViolationException` (that class only) escapes, roll back and re-read the existing audit **once**; no other DB error is swallowed. Child uniques prevent duplicates. Tests: two sequential GETs → 1 audit, 5 checks, N blockers; lock intent via `RecordsRowLocks`; real concurrency is a MySQL-only manual check.

## E. Attestation and close

**D-12 — Mutations.** Each in a transaction, locking state → audit → child.
- **Check:** closed audit → 422 `night_audit_closed` (checked first); status ≠ `pending` → 422 `night_audit_item_resolved` `{item:"check", status}`; else set `status`, `note`, `acted_by` = auth user, `acted_at` = now (UTC). Client `acted_by`/`actor`/`done` ignored (only validated keys read).
- **Blocker:** closed → `night_audit_closed`; already `resolved` → 422 `night_audit_item_resolved` `{item:"blocker", status}`; else resolved + note + actor + time.
- **Close** (draft D-07 kept): lock state then audit. Already `closed` → **200 with the existing record**, timestamps unchanged, date not advanced. `business_date ≠ state.current_business_date` → 422 `night_audit_date_mismatch` (defence in depth). Any non-terminal check or open blocker → 422 `night_audit_not_ready` `{checks_pending, blockers_open}`. Else `status=closed`, `closed_by`, `closed_at`; state `last_closed_date = D`, `current_business_date = D + 1` (`CarbonImmutable` `addDay()` calendar arithmetic in the hotel timezone). No reopen.
- **Readiness** on every audit response: `{checks_pending, blockers_open, can_close}`; `can_close` = both 0 and status open.
- **Invariant:** at most one open audit, always for `current_business_date` (asserted by a test).

**D-13 — Response shape** (standard envelope):
```
data: {
  state: { current_business_date, last_closed_date|null },
  audit: null | {
    uuid, business_date, status, snapshot_basis, evaluated_at, opened_by:{uuid,name}|null,
    closed_at|null, closed_by:{uuid,name}|null,
    readiness: { checks_pending, blockers_open, can_close },
    checks: [ { uuid, type, label, blocking, status, issue_count, evidence:[…],
                evidence_truncated, note, acted_by:{uuid,name}|null, acted_at, blocker_uuid|null } ],  // fixed enum order
    blockers: [ { uuid, check_uuid, type, status, note, acted_by, acted_at } ]
  }
}
```
PATCH and close return the same payload. Messages `custom.messages.night_audit_check_updated`, `night_audit_blocker_resolved`, `night_audit_closed`. Eager loads in the service (`checks.actor`, `blockers.actor`, `blockers.check`, `closer`, `opener`), guarded by `whenLoaded`.

**D-14 — Activity log.** The four new models use `LogsActivity`, logging status, acted_by and closed_* changes only; `note` and `evidence` excluded. Activity inserts excluded from query budgets.

## F. Permissions

**D-15 — `night_audit.manage` new; `reports.view` first enforced; no preset changes. Δ, high stakes.**
- `night_audit.manage` gates routes 2–4 and lets its holder read via route 1's `reports.view|night_audit.manage`. Chosen over `reports.manage` because reports are read-only, a night auditor must attest/close without seeing revenue (least privilege), and a separate group keeps the picker clear. Route 1's gate is an additive widening of the roadmap's `reports.view`.
- `reports.view`: reports dashboard + read-only audit (and lazy snapshot creation per AUDIT-01, D-03/D-04).
- Presets: **none changed**; both strings assigned per account via the existing permission-assignment endpoint; super-admin via `Gate::before`. A test pins every preset's exact permission array and a 403 matrix (reception, concierge, kitchen, housekeeping, events presets) on all 5 routes.
- Catalogue **29 → 30 permissions, 12 → 13 groups** (`night_audit`). Re-pin `SeederTest` (lines 14/43/231 + list), `PermissionsGroupedTest` (13 + `night_audit` group = exactly `['night_audit.manage']`); seeder entry with a Phase 9 comment; `CmsAccessControlTest::$notYetBuilt` becomes `['pricing.edit']`. No group-label locale keys exist (verified), so none are added. Deploy [BLOCKING]: `php artisan db:seed --class=RolesAndPermissionsSeeder`.
- SUMMARY: "night-manager account = `reports.view` + `night_audit.manage`; night auditor without revenue access = `night_audit.manage` only". Dissent (single `reports.*` namespace) rejected; aliasing later is a one-line gate change plus one seeded string.

## G. Reports

**D-16 — Period.** `date_from`/`date_to` both present or both absent; each strict real date (`date_format:Y-m-d` + round trip); `date_to >= date_from`; inclusive length ≤ 31 days. Absent → `HotelClock::today()` for both (not the audit business date). Future dates allowed. Errors: 422 `validation_failed` with field errors; 31-day message `custom.validation.report_period_too_long` (5 locales). Response carries `period:{date_from, date_to, days, timezone}`, `generated_at` (UTC) and basis fields.

**D-17 — Occupancy. Δ on the method.**
- Occupied room-nights = booked room-nights: every `reservation_rooms` line of a reservation in {confirmed, checked_in, checked_out} counts one room, unassigned lines included. Stay `[check_in, check_out)` ∩ `[date_from, date_to+1)`.
- **Δ Portable method:** one query `SELECT r.check_in, r.check_out, COUNT(*) FROM reservation_rooms rr JOIN reservations r … WHERE r.status IN (…) AND whereDate(r.check_in,'<',to+1) AND whereDate(r.check_out,'>',from) GROUP BY r.check_in, r.check_out`, then in PHP `overlapNights(pair) × count`, parsing with `CarbonImmutable::parse(...)->toDateString()`. Bounded by distinct date pairs, exact. No `julianday`/`DATEDIFF`.
- Available room-nights = live `is_active` rooms × `days` (maintenance included; present inventory, documented).
- `occupancy_rate` = `bcdiv(occupied, available, 4)` string (e.g. `"0.7231"`); `"0.0000"` when nothing available; not clamped (>1 on overbooking).
- No no-show status: a past confirmed stay that never checked in still counts (documented).
- `arrivals` (check_in in [from,to]) and `departures` (check_out in [from,to]) from one conditional-aggregate query (or two counts) over reservations with the same statuses, once per reservation, whereDate-equivalent bounds, no room-line join.

**D-18 — Revenue and collections are separate** (draft D-12 + Phase 8 "For Phase 9").
- `revenue` — `basis:"posted_folio_lines"`, `currency:"USD"`: exact signed sum of `folio_items.amount_usd` with `created_at` in the UTC window; `charges_usd` (positive rows), `credits_usd` (negative rows, negative string), `net_usd`; **Δ** `by_source`: net per `FolioItemSource` value, every key present (`"0.00"` when absent), from the same grouped query. Reservation lines are lump-sum postings, re-priceable while open; the report never calls `GenerateFolioAction`. Only `folio_credit` recoveries are money (already `credit` lines); `recorded_value_usd` never added (Phase 7 D-13).
- `collections` — `basis:"completed_payments"`, `refunds_included:false`: `payments.amount_usd` where `status='completed'` and `created_at` in window, one query grouped by `payable_type`, mapped in PHP: `stays_usd` (Reservation + Folio morph classes; each payment once, never `ledgerPayments`'s OR-join), `event_deposits_usd` (EventInquiry), `other_usd`, `total_usd`. Event deposits never in `revenue` (Phase 8 D-18). Morph map untouched; a test pins EventInquiry's morph class = FQCN and absence from the map. Pending/failed excluded. Never labelled "net cash".
- Payments never joined to folio items; balances never added. Guide: operational posting/cash view, not audited accounting, may change while folios are open.

**D-19 — Open work** (draft D-14 kept). `open_work:{basis:"current_state", as_of: generated_at, service_requests:{new, in_progress, total}, tickets:{open, assigned, in_progress, waiting_guest, total}}` from two `status, count(*) GROUP BY status` queries restricted to `::active()`; every key present (0 when none). Period-independent. Counts only. Housekeeping backlog deferred.

**D-20 — No fabricated metrics** (draft D-15 kept). No ADR, RevPAR, MTD/YTD, per-room-type revenue, daily breakdown, booking-source revenue, mock `revenue_today`/`kpis`. Guide includes a mock→API mapping and omissions. Tree flips mean endpoint coverage, not React wiring.

## H. Money, queries, errors

**D-21 — Exact money. Δ, made concrete.** `App\Support\MoneyAggregate` (or equivalent `FolioLedger` methods): `centsExpression(string $column): string` → `CAST(ROUND(col*100) AS INTEGER)` on sqlite, `ROUND(col*100)` on mysql/mariadb, `LogicException` otherwise; conditional variants (`SUM(CASE WHEN col > 0 THEN <cents> ELSE 0 END)`); `fromCents(int|string|null): string` → `bcadd(…,'0',0)` then `bcdiv(…,'100',2)`, null → `"0.00"`. Every USD figure a 2dp string; no float. Unit tests: empty `"0.00"`, negatives, 0.10×10 = `"1.00"`, 0.29/0.57 edges, near-max DECIMAL(10,2) × many rows, mixed signs.

**D-22 — Query budgets** (domain statements; auth/session/permission/cache bookkeeping and activity-log inserts excluded; measure, then pin exactly with a justification comment). Reports ≤ 8 (expected 7: capacity, room-night pairs, arrivals/departures, folio lines by source, payments by type, SR statuses, ticket statuses). Audit first open ≤ 24 statements including inserts (draft's ≤16 not achievable with eager-loaded actors). Re-read ≤ 7. Each PATCH/close ≤ 12. Must hold with 0 issues and >20 issues in every category (test seeds 25 dirty rooms + 25 unsettled departures; identical counts).

**D-23 — Error codes.** One `DomainException` subclass per code, key `custom.errors.<code>` in all 5 locales.

| error_code | HTTP | context |
|---|---|---|
| `night_audit_not_initialized` | 422 | `{requires:"date"}` |
| `night_audit_date_mismatch` | 422 | `{requested_date, current_business_date}` |
| `night_audit_date_in_future` | 422 | `{requested_date, hotel_today}` (initialization only) |
| `night_audit_closed` | 422 | `{business_date, closed_at}` |
| `night_audit_item_resolved` | 422 | `{item: check\|blocker, status}` |
| `night_audit_not_ready` | 422 | `{checks_pending, blockers_open}` |
| `forbidden` (existing) | 403 | `{reason:"night_audit_not_initialized"}` |
| `validation_failed` (existing) | 422 | field errors |

Also in 5 locales: 5 check-type labels, check/blocker/audit status labels if exposed, 3 success messages, `report_period_too_long`. New keys added in the same order in every locale file.

## I. Tests and docs

**D-24 — Tests** (`tests/Feature/NightAudit/`, `tests/Feature/Reports/`, `tests/Unit/...`): contract gate per route (happy, 401, 403 incl. `reports.view`-only on writes, 422 cases); creation/state rules; evaluator inclusion/exclusion pairs incl. SQLite `Y-m-d 00:00:00` storage; snapshot immutability; attestation; close (not ready, month-end and Europe/London DST advance, repeat close, next GET targets D+1, one-open invariant); no source mutation (byte-identical source rows); reports occupancy/movement/revenue/collections/open-work and budget invariance; permission catalogue 30/13, preset arrays, 403 matrix, enforced-somewhere, `night_audit.manage`-only GETs audit but 403 on reports; migrations up/down on a scratch DB only. Full list in `09-VALIDATION.md`.

**D-25 — Docs** (integrator plan 09-10): `backend/docs/API_GUIDE_DASHBOARD.md` "Night audit" and "Reports" sections (endpoints, permissions, first-initialization runbook, check vs blocker, snapshot limits, future-date behaviour, close flow, error table, metric definitions/limits, mock→API mapping: `property_day`→`business_date`, `done`→`status`, mock `in_progress`→`open`, handoff notes/history/severity not provided, gate `reports.view|night_audit.manage` not `FOLIOS_VIEW`, client sends `date` on first use). Postman folder "Night Audit & Reports" (5 requests). `docs/carlton-tree.html`: both nodes `api:true`, real paths incl. POST close, api:true count 88 → 90. No change to `API_GUIDE_MOBILE.md` / `CHANGELOG_MOBILE_API.md`. SUMMARY: permissions section, [BLOCKING] deploy steps (migrate 4 tables + 1 index migration, re-seed permissions, assign strings, initialize business date), React-team note, MySQL-only lock caveat.

## Planner rulings (this re-plan)

- **R-1** No ticket composite index (consultant fact said one exists; only `tickets.status` does). Keep D-08 to its two indexes.
- **R-2** Phase 9 lang keys land with first use: all audit keys (6 error codes, check-type and status labels, 3 success messages) in 09-04, where the exceptions are born; `report_period_too_long` in 09-08. Each lands in all 5 locales in the same task.
- **R-3** The permission string is seeded in 09-05 together with route 1, so `CmsAccessControlTest` never sees an inert `night_audit.manage`; `reports.view` leaves `$notYetBuilt` in the same plan (route 1 enforces it).
- **R-4** Query budgets are asserted by a `DB::listen` counter (test concern `tests/Concerns/CountsDomainQueries.php`, created in 09-03) that ignores `activity_log` statements and runs only around the action/service call (house `expectsDatabaseQueryCount` cannot exclude activity inserts).
- **R-6** Plan split: 09-01 schema, 09-02 money helper, 09-03 evaluators, 09-04 open action, 09-05 GET route + permission, 09-06 attestation routes, 09-07 close route, 09-08 reports period + occupancy + route, 09-09 reports money + open work + budget, 09-10 docs + phase gate. Sequential waves 1–10.
- **R-5** The 2167 baseline from the orchestrator is re-measured at 09-01 Task 1; the measured number is the gate.

## Claude's Discretion (engineer chooses, QA verifies)

Evaluators as private methods vs one `NightAuditEvaluator`; the `LIMIT 21` optimisation (count stays exact); report shaping via Resource or array; message/label wording in 5 locales (fr/tr/es competent machine quality); key order inside evidence entries (stable, documented); arrivals/departures as one conditional query or two counts within budget; test file split and factory/state names.

## Deferred (not in Phase 9)

Live-verifying blocker resolution (needs city-ledger/AR); no-show check (needs a status/verb); reopening a closed date; resetting/re-initializing the business date; audit history list (`GET /operations/night-audits`); exports (PDF/CSV); scheduled/automatic audits; handoff-notes feed; event history timeline; ADR, RevPAR, MTD/YTD, revenue by room type or booking source, daily breakdown, nightly accrual; netting refunds into collections; housekeeping backlog in `open_work`; historical inventory/maintenance reconstruction; automatic ledger posting at close, room-and-tax posting; a management role preset (needs owner decision + blast-radius review). Carried from Phase 8: PR-9 generic table-booking path and ReserveTable timezone backfill.
