# Phase 10: Loyalty Points Program - Research

**Researched:** 2026-10-04
**Domain:** Laravel 13 / PHP 8.3 points ledger (earn, FIFO expiry, redeem, reversal) bolted onto the existing folio, booking, notification and CMS layers
**Confidence:** HIGH on integration points (read from the live tree), MEDIUM on the proposed schema (design, not yet run), LOW on nothing that is load-bearing

> Tree state caveat: Phase 9 is being executed in the same working tree while this research ran. Between two reads, `PermissionsGroupedTest` flipped from 12 to 13 groups and `routes/api.php` gained the night-audit block (lines 799-811). Every "current tree" claim below is a snapshot of 2026-10-04; the planner/executor must re-read `RolesAndPermissionsSeeder`, `SeederTest`, `PermissionsGroupedTest`, `routes/api.php` and `lang/*/custom.php` at execution time.

<user_constraints>
## User Constraints (from CONTEXT.md)

### Locked Decisions
Source: user Q&A on 2026-10-04 (decisions are locked; planner must not re-litigate).

**Earning**
- Sources: room stays (per USD), services/F&B charges, manual staff award/deduct (reason required, audited).
- Credited **once on folio settlement**. No pending balance.
- Points are integers, **round half up**.
- No tiers: a single flat balance.
- No backfill of historical folios; the program starts at launch.

**Reversals (full)**
- Folio refund / reservation cancel: earned points are clawed back, spent points are refunded, and a used voucher is restored.

**Expiry**
- Rolling, per earn batch, consumed FIFO. Expiry months configurable, default 24.
- Daily scheduled job expires batches.
- Guest notification N days before expiry (default 30, configurable) via the existing notifications system.

**Redemption**
- Free-form points to USD discount on a reservation at booking time (DECIMAL USD, redeem rate configurable).
- Staff-managed rewards catalog (AR/EN names, points cost, type: discount voucher / free night / room upgrade).
- Redeeming a reward creates a **voucher** (code, status, expiry) that the guest applies at booking.
- Redeem is idempotent and transactional.

**Configurable via dashboard settings (no seeded rates)**
Earn rate, redeem value, expiry months, expiry-warning days, minimum points to redeem, max % of a booking payable with points.

**API surface**
- Guest: balance (available / expiring soon) + ledger; browse catalog; redeem reward to voucher; my vouchers; preview points earned / discount at booking.
- Staff: settings get/update; rewards CRUD (+ recycle bin if soft-deleted); view a guest's balance and ledger; manual adjust; reports (issued / redeemed / expired over a period).

**Constraints (project-wide)**
TupCode conventions (Base* classes, services/actions returning `['data','code']`, domain exceptions, AR/EN keys, UUID public ids, `/api/v1`, routes grouped by role), additive migrations, 3NF, ledger with status/history (no boolean flags), every new route has happy/401/403/422 tests, full suite green, commit after the phase, never push.

### Claude's Discretion
CONTEXT.md has no explicit discretion section. Everything not listed above is open and collected in `## Open Questions / Gray Areas`.

### Deferred Ideas (OUT OF SCOPE)
None listed in CONTEXT.md. (Tiers and historical backfill are explicitly excluded by the locked decisions.)

### Open items for planning (from CONTEXT.md, answered here)
- Does Phase 10 really depend on Phase 9? Answered in `## Phase 9 Dependency Ruling`: ordering only, no code dependency.
- Where the booking discount hooks into `CreateReservationAction` and where earn/reversal hook into `SettleFolioAction`: answered in `## Integration Points`.
</user_constraints>

## Project Constraints (from CLAUDE.md)

Sources: `D:\TupCode\Carlton\.claude\CLAUDE.md`, `D:\TupCode\Carlton\backend\CLAUDE.md`, `backend/.claude/skills/tupcode-laravel-backend/SKILL.md` (wins over the older project skills where they disagree), `laravel-conventions`, `module-slice`, `test-discipline`.

- Every backend change obeys the `tupcode-laravel-backend` skill and the guide section 17 PR checklist as a gate.
- Layers: Controller -> FormRequest -> Service -> Model; Actions are plain classes with `handle()` returning `['data' => ..., 'code' => ...]`, no `BaseAction`, no `execute()`. Services/actions never read `request()` and never return HTTP responses.
- New controllers go on the base pair (`BaseCRUDController` + `HandlesRecycleBin` for soft-deletable) or stay on `BaseController` when verbs are not plain CRUD. Resource verbs are `index/show/store/update/destroy/restore/forceDestroy`; custom verbs camelCase.
- Domain exceptions extend `App\Exceptions\DomainException` (`errorCode()`, `statusCode()`), one class per `error_code`, key `custom.errors.<code>` in every locale file; never return error arrays.
- Responses only through `BaseController::success()/paginatedSuccess()/respondFromService()`; resources expose `uuid` never `id`, `whenLoaded()` for relations, no queries in `toArray()`.
- Money: DECIMAL USD, bcmath on strings (`FolioLedger`), never floats; additive migrations; every FK indexed with explicit ON DELETE; UUID public ids; UTC timestamps.
- `$fillable` on every model; `LogsActivity` on state-changing models; `HasTranslations` for AR/EN content; `HandlesRecycleBin` for soft-deletable CMS content.
- Security: permission middleware on routes (not roles), ownership in `authorize()`/scoped queries, throttle where a secret is verified, payment-like writes idempotent and transactional.
- Tests: every route happy / 401 / 403 / 422; non-trivial actions have unit tests; real Sanctum bearer tokens (not `actingAs`); full suite green before commit.
- Git: commit after the phase, never push. GSD workflow enforcement: edits only through a GSD command.
- Prefix reality check: CLAUDE.md says `/api/v1`, but the code and every test use `/api` with no `/v1` (see Integration Points, "Route prefix"). Follow the code.

## Summary

Phase 10 is a self-contained ledger module with four seams into existing code: three folio-settlement sites (earn), `CreateReservationAction` (booking discount), `CancelReservationAction` (reversal), and `routes/console.php` plus `NotificationService` (expiry job and warning). Nothing in it needs Phase 9 code. The only coupling to Phase 9 is shared files that both phases edit (permission catalogue pins in `SeederTest`/`PermissionsGroupedTest`, `routes/api.php`, five `lang/*/custom.php` files, docs, the tree html), so Phase 10 should be planned now but executed after Phase 9 is committed.

Three facts from the live tree shape the design more than any library choice. First, there is no folio refund path and no folio reopen: `Refund` is a model and table with no writer, and `FolioStatus::SETTLED` is terminal, so "settled, refunded, re-settled" cannot occur today; the folio-refund reversal must be shipped as a callable, tested seam, with `CancelReservationAction` as the only live trigger. Second, folio settlement is decided in exactly three statements (`SettleFolioAction.php:46`, `:71`, `RecordFolioPaymentAction.php:80`), all under the folio row lock inside a transaction, so earn can be an atomic inline call with a unique-key backstop. Third, the room charge on a folio is a single lump-sum line equal to `reservations.total_usd` (already net of promo), so a points discount applied by reducing `total_usd` at booking automatically means the guest earns only on cash-equivalent spend.

**Primary recommendation:** Build seven additive tables (settings singleton, rewards, earn batches, ledger entries, allocations, vouchers, reservation applications), one pure `LoyaltyMath` support class shared by preview and booking (bcmath, half-up, no floats), inline atomic earn at the three settlement sites keyed `earn:folio:{id}` with a unique index, FIFO consumption under a `guests` row lock, expiry as a daily command that is bookkeeping only (availability queries already filter `expires_at > now()`), and new `loyalty.view / loyalty.manage / loyalty.adjust` permissions with no preset changes.

## Architectural Responsibility Map

| Capability | Primary Tier | Secondary Tier | Rationale |
|------------|-------------|----------------|-----------|
| Earn on settlement | API / Backend (action inside folio settle transaction) | Database (unique key backstop) | Must be atomic with the status flip and idempotent; never client-driven |
| FIFO consume / refund / clawback / expiry | API / Backend (actions under guest row lock) | Database (CHECK-like invariants via unsigned ints, unique keys) | Money-like ledger; correctness lives in one lock order |
| Balance, expiring-soon, ledger listing | API / Backend (service queries) | Database (composite indexes) | Derived from batches; no stored balance column |
| Discount math, cap, minimum | API / Backend (`LoyaltyMath`, shared by preview and booking) | - | One implementation prevents preview/actual drift |
| Settings (six values) | API / Backend (singleton row + action) | Database | Dashboard-editable, audited via activity log, read per request |
| Rewards catalog (AR/EN) | API / Backend (BaseCRUD + recycle bin) | Database (soft deletes) | Standard CMS-style content |
| Vouchers (code, status, expiry) | API / Backend | Database (unique code, unique reservation_id) | Guest-owned secret; ownership enforced in query |
| Expiry sweep and warning push | API / Backend (scheduled commands) | Queue/FCM via `NotificationService` | Daily jobs, at-least-once with per-batch marker |
| Booking discount UI / preview display | Browser / Client (React dashboard, Flutter app) | API (`/loyalty/preview`) | Clients render only what the API returns; no math client side |
| Reports | API / Backend (2 aggregate queries) | Database (index on type, occurred_at) | Staff dashboard read |

## Standard Stack

No new packages. Everything below is already installed and in use. [VERIFIED: backend/composer.json lines 12-30]

### Core
| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| laravel/framework | ^13.8 | routing, scheduler (`routes/console.php`), validation | already the stack |
| spatie/laravel-permission | ^8.3 | `loyalty.*` permissions via `permission:` middleware | existing RBAC |
| spatie/laravel-activitylog | ^5.0 | `LogsActivity` on settings, rewards, vouchers, applications | existing audit trail |
| spatie/laravel-translatable | ^6.14 | AR/EN `name`/`description` on rewards (`HasTranslations`) | existing CMS pattern |
| PHP bcmath | ext | exact decimal math (`FolioLedger` precedent) | no floats for money; also used for half-up integer points |
| phpunit/phpunit | ^12.5.12 | tests, SQLite in-memory | existing |

### Supporting
| Library | Purpose | When to Use |
|---------|---------|-------------|
| `App\Support\HotelClock` | hotel-local day windows for reports, expiry end-of-day | report period -> UTC window; `expires_at` computation |
| `App\Support\FolioLedger` | `normalize`, `sum`, string money | folio spend sums, 2dp normalisation |
| `App\Support\IdempotentWrite` | replay-safe keyed writes | reward redeem, manual adjust |
| `App\Http\Requests\Concerns\ReadsIdempotencyKey` | `Idempotency-Key` header to `idempotency_key` | redeem, adjust, booking-with-loyalty |
| `App\Services\Notification\NotificationService` | `pushToGuest` | expiry warning |
| `Tests\Concerns\RecordsRowLocks` | assert `for update` on rows | lock-order tests |

### Alternatives Considered
| Instead of | Could Use | Tradeoff |
|------------|-----------|----------|
| Dedicated `loyalty_settings` singleton | `site_settings` rows | `site_settings` is the public website copy table: `SiteSettingService::publicMap()` exposes every active row to anonymous callers and `SettingType` is a CMS widget hint. Putting rates there leaks config and mixes concerns. Rejected. |
| Event + queued listener for earn | inline action call | Listener runs after commit, so a failure leaves a settled folio with no points and needs a reconcile job. Inline is atomic. Chosen: inline. |
| Stored `balance` column | `SUM(points_remaining)` over batches | A stored balance is a denormalised copy that must be synced under the same lock; derived sum is 3NF and index-served. Chosen: derived. |
| `MoneyAggregate` for report sums | integer sums | Points are integers; SQL `SUM` of integers is exact on SQLite and MySQL. No need for Phase 9's cents helper. |

**Installation:** none. `composer.json` is untouched.

**Version verification:** no external package is added, so registry checks do not apply. Local CLI probe: PHP 8.4.1 NTS with `bcmath`, `pdo_mysql`, `pdo_sqlite` loaded (composer requires `^8.3`); `mysql` client not installed locally. [VERIFIED: `php -v` / `php -m` in this session; app/Support/FolioLedger.php:21-94]

## Package Legitimacy Audit

No external packages are installed by this phase. Nothing to check.

| Package | Registry | Age | Downloads | Source Repo | Verdict | Disposition |
|---------|----------|-----|-----------|-------------|---------|-------------|
| (none) | - | - | - | - | - | - |

**Packages removed due to [SLOP] verdict:** none
**Packages flagged as suspicious [SUS]:** none

## Phase 9 Dependency Ruling

**Verdict: SOFT (ordering) dependency only. No code symbol of Phase 9 is required. Do not couple Phase 10 code to Phase 9.**

What Phase 10 would reuse, checked against the current tree:

| Candidate | Exists now? | Where / which Phase 9 plan | Phase 10 uses it? |
|-----------|-------------|----------------------------|-------------------|
| `HotelClock::today()/dayWindow()/timezone()` | yes, committed Phase 3 | `app/Support/HotelClock.php:20,26,42` | YES (report window, expiry end-of-day). Not a Phase 9 symbol. |
| `FolioLedger` | yes, committed Phase 5 | `app/Support/FolioLedger.php` | YES (spend sums, 2dp strings) |
| `IdempotentWrite`, `ReadsIdempotencyKey` | yes, committed Phase 5/8 | `app/Support/IdempotentWrite.php:31`, `app/Http/Requests/Concerns/ReadsIdempotencyKey.php` | YES |
| `MoneyAggregate` | in tree, **untracked** (Phase 9 plan 09-01..09-03 territory, `09-CONTEXT D-21`) | `app/Support/MoneyAggregate.php` | NO. Points are integers; discount USD sums are not needed in reports. Avoid. |
| `night_audit_states.current_business_date` (persisted business date) | migration created (`2026_10_04_100000`), untracked | `database/migrations/2026_10_04_100000_create_night_audit_tables.php` | NO. Ledger `occurred_at` is an instant; report windows use `HotelClock` dates. The business date is only advanced by an explicit night-audit close and may be uninitialised; loyalty must not depend on it. |
| `reports.view` permission | seeded since P0; now enforced by the night-audit route (`routes/api.php:805`) | Phase 9 plan 09-0x | NO. Use dedicated `loyalty.view` so a night auditor/reports holder does not automatically see guest balances and a loyalty manager needs no revenue access. |
| Report controller/service/request (`ReportController`, `ReportService`, `ReportDashboardRequest`) | **do not exist yet** (`routes/api.php` has no `/reports` route; only night-audit routes at 799-811) | Phase 9 plans 09-08/09-09 | NO. Copy the period-validation *pattern* (strict `date_format:Y-m-d` + round trip, both-or-none, `HotelClock::dayWindow`), not the class. |
| `tests/Concerns/CountsDomainQueries`, `RecordsRowLocks` | `RecordsRowLocks` committed; `CountsDomainQueries` untracked | `tests/Concerns/` | `RecordsRowLocks` yes; `CountsDomainQueries` optional (only if the planner wants query-budget tests; do not make it a requirement) |

Shared-file collisions (the real reason to order Phase 10 after Phase 9 is committed):

| Shared file | Phase 9 change | Phase 10 change |
|-------------|----------------|-----------------|
| `database/seeders/RolesAndPermissionsSeeder.php` | adds `night_audit.manage` (29 -> 30 permissions) | appends 3 `loyalty.*` (-> 33) |
| `tests/Feature/SeederTest.php` (count pins ~lines 14/43/231 per 09-RESEARCH), `tests/Feature/Staff/PermissionsGroupedTest.php:29` (13 groups) | re-pinned to 30 / 13 | re-pin to 33 / 14 and add a `loyalty` group assertion |
| `tests/Feature/Cms/CmsAccessControlTest.php:124` `$notYetBuilt` | now `['pricing.edit']` | unchanged; loyalty permissions must be enforced by route middleware or the test fails (`:159`) |
| `routes/api.php` | night-audit block at 799-811, reports block pending | new guest block + `cms/loyalty` staff block + reward bin entries inside the `cms` bin groups (251-289) |
| `lang/{en,ar,fr,tr,es}/custom.php` (252 lines each, Phase 9 keys already present) | `night_audit` section, `errors`, `report_period_too_long` | new `loyalty` section, `errors`, `messages`, `notifications`, `validation` in all five |
| `docs/carlton-tree.html`, `backend/docs/API_GUIDE_DASHBOARD.md`, `backend/docs/carlton-api.postman_collection.json` | api:true 88 -> 90 | append loyalty nodes/sections |
| Migrations | `2026_10_04_100000`, `2026_10_04_100100` (latest existing, verified by `ls`) | start at `2026_10_05_100000` |

**Ruling for ROADMAP:** keep "Depends on: Phase 9" only as an execution-order note ("shared catalogue/lang/docs files"), not as a technical dependency. If the orchestrator wants Phase 10 executed before Phase 9 finishes, the only change is the permission count arithmetic (32 / 13 instead of 33 / 14) and a second re-pin by Phase 9.

## Architecture Patterns

### System Architecture Diagram

```
                     STAFF (auth:users, loyalty.*)                 GUEST (auth:guests)
   PUT /cms/loyalty/settings  GET/POST /cms/loyalty/guests/{g}/...   GET /loyalty/account|ledger|rewards|vouchers
   CRUD /cms/loyalty/rewards  GET /cms/loyalty/reports               POST /loyalty/rewards/{r}/redeem  (Idempotency-Key)
            |                              |                          GET /loyalty/preview
            v                              v                          POST /reservations (+loyalty_points|voucher_code)
   UpdateLoyaltySettings   LoyaltyReward/Account/ReportService                |                     |
   Action (LogsActivity)            |                                         v                     v
            |                       |                               RedeemRewardAction     CreateReservationAction
            v                       v                                    |                    | (room_type lock -> guest lock)
   loyalty_settings <---- LoyaltyProgram (request-memoised reader)       |                    v
            ^                                                            |        QuoteReservationAction (unchanged)
            |                                                            |                    |
            |      +------------------ LoyaltyLedger (FIFO consume / restore / expire) <------+---- ApplyLoyaltyToReservationAction
            |      |                          ^   ^                                                    (LoyaltyMath cap/min/half-up)
            |      |                          |   |
   SettleFolioAction (46, 71) ---+            |   +---- CancelReservationAction -> ReverseLoyaltyForReservationAction
   RecordFolioPaymentAction (80) +--> EarnLoyaltyPointsAction                          (refund spent, restore voucher, clawback earn)
            (folio row lock, same txn)        |
                                              v
        loyalty_earn_batches  <-- loyalty_allocations --> loyalty_ledger_entries (immutable, idempotency_key unique)
                  ^                                              ^
                  |                                              |
   loyalty:expire-points (daily 01:00 hotel tz) ------------------+      loyalty_vouchers <-- loyalty_reservation_applications
   loyalty:notify-expiring (daily 09:00) --> NotificationService::pushToGuest --> guest_notifications + FCM
```

Lock order (document in the actions and assert in tests): `reservation -> folio -> guest(loyalty) -> batches`. `CreateReservationAction`: `room_type -> guest`. No path takes a lock earlier in the list while holding a later one.

### Recommended Project Structure
```
app/
├── Actions/Loyalty/        # Earn, Redeem, Apply, Reverse*, Adjust, Expire, Notify, UpdateSettings, Preview
├── Console/Commands/       # ExpireLoyaltyPoints, NotifyExpiringLoyaltyPoints
├── Enums/                  # Loyalty* string-backed enums
├── Exceptions/             # Loyalty* domain exceptions (flat folder, like NightAudit*)
├── Filters/                # LoyaltyRewardFilter, LoyaltyLedgerFilter, LoyaltyVoucherFilter
├── Http/Controllers/Admin/ # LoyaltySettingController, LoyaltyRewardController, LoyaltyGuestController, LoyaltyReportController
├── Http/Controllers/Api/   # LoyaltyController (account, ledger, vouchers, preview), LoyaltyRewardController (catalog, redeem)
├── Http/Requests/Loyalty/  # one request per write/query shape
├── Http/Resources/Loyalty/ # rewards, account, ledger entry, voucher, settings, preview, report
├── Models/                 # Loyalty* models
├── Services/Loyalty/       # LoyaltyRewardService (BaseService), LoyaltyAccountService, LoyaltyReportService
└── Support/                # LoyaltyMath, LoyaltyProgram, LoyaltyLedger
```

### Pattern 1: Atomic inline earn under the folio lock
**What:** Call `EarnLoyaltyPointsAction::handle(Folio $locked)` immediately after each `status => SETTLED` update, inside the same transaction. The action is a no-op (one settings read) when earning is not configured, when the reservation has no guest, or when the batch for `(folio_id, source)` already exists.
**When to use:** all three settlement statements.
**Example:**
```php
// Source: SettleFolioAction.php:46 / :71 and RecordFolioPaymentAction.php:80 (existing), earn call is the new line
$locked->update(['status' => FolioStatus::SETTLED, 'settled_at' => now()]);
$this->earnLoyalty->handle($locked);   // same DB::transaction, folio row already locked
```

### Pattern 2: One pure calculator for preview and booking
**What:** `LoyaltyMath` has only static, side-effect-free methods taking decimal strings and ints. The preview endpoint and `ApplyLoyaltyToReservationAction` both call it with the same inputs (quote total as `number_format($v, 2, '.', '')`), so they cannot drift.
**Example (bcmath only, never `round()`; behaviour must be pinned by unit tests, it was not executed in this read-only session):**
```php
// non-negative decimal strings only
public static function halfUpInt(string $x): int
{
    // bcadd truncates toward zero; adding 0.5 first makes truncation half-up
    return (int) bcadd(bcadd($x, '0.5', 6), '0', 0);
}

public static function pointsForSpend(string $spendUsd, string $earnRate): int
{
    return self::halfUpInt(bcmul($spendUsd, $earnRate, 6));   // 2dp x 4dp is exact at scale 6
}

public static function discountForPoints(int $points, string $redeemValueUsd): string
{
    $raw = bcmul((string) $points, $redeemValueUsd, 6);        // value has 4dp
    return bcadd(bcadd($raw, '0.005', 6), '0', 2);             // half-up to cents
}

public static function maxDiscount(string $totalUsd, string $percent): string
{
    // floor to cents: the hotel never exceeds the cap by rounding
    return bcadd(bcmul($totalUsd, bcdiv($percent, '100', 6), 6), '0', 2);
}
```

### Pattern 3: FIFO consume under a guest row lock
```php
// inside DB::transaction; caller already holds the guest lock
// Guest::whereKey($guestId)->lockForUpdate()->firstOrFail();
$batches = LoyaltyEarnBatch::where('guest_id', $guestId)
    ->where('status', LoyaltyBatchStatus::ACTIVE)
    ->where('expires_at', '>', now())
    ->orderBy('expires_at')->orderBy('id')       // equals FIFO while expiry months is constant (Open Question Q14)
    ->lockForUpdate()->get();

$need = $points;
foreach ($batches as $batch) {
    if ($need === 0) { break; }
    $take = min($need, $batch->points_remaining);
    $batch->points_remaining -= $take;
    if ($batch->points_remaining === 0) { $batch->status = LoyaltyBatchStatus::DEPLETED; }
    $batch->save();
    $entry->allocations()->create(['batch_id' => $batch->id, 'points' => $take]);
    $need -= $take;
}
if ($need > 0) { throw new LoyaltyInsufficientPointsException(...); }   // pre-check SUM first for the clean error context
```

### Pattern 4: Expiry instant is hotel-local end of day, no month overflow
```php
// Carbon 3 addMonthsNoOverflow: Jan 31 + 1 month = Feb 28/29 (verify against installed Carbon)
$expiresAt = CarbonImmutable::now(HotelClock::timezone())
    ->addMonthsNoOverflow($settings->expiry_months)
    ->endOfDay()->utc();
```

### Anti-Patterns to Avoid
- **Stored balance column or float math:** balance is `SUM(points_remaining)`; discounts are bcmath strings. `QuoteReservationAction` itself uses floats (`QuoteReservationAction.php:18,35,53`), so convert its output with `number_format($v, 2, '.', '')` at the boundary and do not modify that action (STATE.md Phase 2 decision keeps it byte-unmodified).
- **Route-binding vouchers/ledger by uuid without ownership:** resolve through a query scoped to `auth('guests')->id()` and answer 404 `NotFoundException` for foreign rows (precedent: `ReservationController::show/cancel`, `Api/ReservationController.php` ownership checks).
- **Earning via an after-commit event:** leaves settled folios with no points on failure.
- **Reading `site_settings`/cache for rates:** use the singleton row; do not add caching (one indexed read per earn/redeem; invalidation strategy would be needed otherwise).
- **Treating the expiry job as the correctness gate:** every availability query must filter `expires_at > now()` itself.

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Replay-safe keyed writes | custom dedupe | `IdempotentWrite::run` + `ReadsIdempotencyKey` | unique-violation re-read and conflict semantics already tested (`IdempotentWriteTest`) |
| Money strings | float math / `round()` | `FolioLedger` + bcmath in `LoyaltyMath` | SQLite REAL drift documented in `Folio::recalculateTotals`; PHP `round()` pre-rounds floats |
| Hotel-local day to UTC window | manual tz math | `HotelClock::dayWindow()` | DST-safe, strict date validation |
| Recycle bin routes/purge | custom soft-delete UI | `HandlesRecycleBin` + `RecycleBin::modelsWithBin()` auto-discovery + `cms:purge-bin` | purge command walks every `SoftDeletes` model automatically |
| Push + persisted notification | direct FCM call | `NotificationService::pushToGuest` | creates `guest_notifications` row, skips push without tokens, tests use `FakeFirebaseService` |
| Permission checks | role checks in code | route `permission:` middleware | project rule |
| Voucher code generation | uuid/short hash | the Crockford alphabet loop from `CreateReservationAction::generateBookingCode` (`:84-94`) with a `LOY-` prefix and unique index | same collision handling, readable |
| Audit trail for settings/rewards | custom history table | `LogsActivity` (logs old/new via Spatie) | project convention; the ledger itself is the audit trail for points |

**Key insight:** the only genuinely new algorithms are FIFO consume/restore and the clawback; everything else is composition of existing helpers. Keep those two in one class (`LoyaltyLedger`) so the lock-order and invariants live in a single reviewed place.

## Integration Points

Verified against the current tree. Line numbers are 2026-10-04 snapshots.

| Hook | File | Method | Line | What to change |
|------|------|--------|------|----------------|
| Earn site 1 (settle, nothing due) | `app/Actions/Folio/SettleFolioAction.php` | `handle` | 45-58 (`update` at 46) | After `$locked->update([... SETTLED ...])`, call `EarnLoyaltyPointsAction::handle($locked)`; inject via constructor (currently one dep, `:29`) |
| Earn site 2 (settle, with payment) | same | `handle` | 69-73 (`update` at 71) | same call after the update |
| Earn site 3 (auto-settle on last payment) | `app/Actions/Folio/RecordFolioPaymentAction.php` | `record` | 79-87 (`update` at 80) | same call inside the `if` after the update; constructor currently one dep (`:31`) |
| Settled decision / lock | `SettleFolioAction.php:34,36`; `RecordFolioPaymentAction.php:36,61` | - | - | **Only these writers set SETTLED** (grep of `FolioStatus::SETTLED` across `app/`). `PaymentService::settleReservation` (`app/Services/Payment/PaymentService.php:26-53`) only guards (`:33`), never settles. `CheckOutReservationAction` states it never writes folio status (`:44`). |
| Folio spend source | `app/Actions/Folio/GenerateFolioAction.php` | `computeLines` | 105-166 (reservation line `:119` = `reservations.total_usd`) | read-only: spend per source is derived from `folio_items.source_type` (`reservation`, `service_booking`, `service_request`, `manual`, `credit`; `app/Enums/FolioItemSource.php`) |
| Credits | `app/Actions/Folio/PostFolioItemAction.php` | `write` | 71-108 (credit row negative `:95`, `reverses_item_id` `:101`, settled guard `:73`) | read-only: a credit reduces the bucket of the row it reverses; after settlement no credit can be posted |
| Booking discount | `app/Actions/Booking/CreateReservationAction.php` | `handle` | txn `:23`, room_type lock `:25`, quote `:42-47`, `Reservation::create` `:51-64` (`total_usd` `:62`) | After the quote and before `create`, call `ApplyLoyaltyToReservationAction::compute()` to get the net `total_usd`; after `create` and room line (`:67-71`), call `::persist()` (consume points / mark voucher, insert application row). Lock guest row after the room_type lock. Inject a third constructor dep (`:15-18`). Do NOT change `QuoteReservationAction`. |
| Quote/preview source | `app/Services/Booking/PricingService.php` | `quote` | 14-21 | preview endpoint calls the same `QuoteReservationAction` then `LoyaltyMath`; public `GET /quote` (`routes/api.php:201-202`) stays unauthenticated and unchanged |
| Reservation entry (guest) | `app/Services/Booking/ReservationService.php` / `app/Http/Controllers/Api/ReservationController.php` | `store` | service `:51-55`, controller `:25-31` | pass `loyalty_points`, `voucher_code`, `idempotency_key` through `$data`; `StoreReservationRequest` (`app/Http/Requests/Booking/StoreReservationRequest.php`) gains optional fields and uses `ReadsIdempotencyKey` conditionally |
| Entries that must NOT accept loyalty | `ReservationService::storeAsGuest` (`:57-109`, unverified OTP flow), `adminStore` (`:169-187`) | - | - | no change; loyalty fields rejected/ignored there (Q17) |
| Cancel reversal | `app/Actions/Booking/CancelReservationAction.php` | `handle` | status check `:19` (outside txn), txn `:27-31` | move the check inside the transaction after `Reservation::whereKey()->lockForUpdate()`; add `ReverseLoyaltyForReservationAction::handle($reservation)` inside the txn after the status update (`:28`). Two callers reach it through `ReservationService::cancel` (`:229-232`): guest `DELETE /reservations/{reservation}` (`routes/api.php:592`) and staff `DELETE /cms/reservations/{reservation}` (`:622`). |
| Hold release (bulk update, bypasses models) | `app/Actions/Booking/ReleaseExpiredHoldsAction.php` | `handle` | whole file | **no change**: holds are `pending_verification` created only by the unauthenticated `storeAsGuest`, which cannot redeem. Add a test that proves it. |
| Folio-refund reversal | (does not exist) | - | - | `grep Refund::` finds only the `Payment::refunds()` relation; `Refund` model/table have no writer; `FolioStatus` has no reopen. Ship `ReverseLoyaltyForFolioAction` as a tested seam (module-slice rule: visible and testable) with a `// TODO` pointing at the future refund phase. |
| Notification | `app/Services/Notification/NotificationService.php` | `pushToGuest` | `:19-` | called by `NotifyExpiringLoyaltyPointsAction`; add `NotificationType::LOYALTY_POINTS_EXPIRING` (`app/Enums/NotificationType.php`; `guest_notifications.type` is a plain string column) |
| Scheduler | `routes/console.php` | - | existing entries `:11`, `:16`, `:21` | add two `Schedule::command(...)->dailyAt(...)->timezone(config('hotel.timezone'))->withoutOverlapping()` |
| Command analog | `app/Console/Commands/ExpireDigitalKeys.php` | `handle` | whole file | `signature` + invokable action injected into `handle` |
| Permissions | `database/seeders/RolesAndPermissionsSeeder.php` | `run` | permission array `:15-43`, presets `:58-79` | append `loyalty.view`, `loyalty.manage`, `loyalty.adjust`; no preset change |
| Recycle bin | `routes/api.php` | bin groups | `trashed` group `:251-269`, `restore` `:271-289`, `force` group follows (`cms.purge`, from `:291`) | add reward entries to each group, declared before any `/{reward}` route; `tests/Feature/Cms/RecycleBinRetentionTest.php:62-81` `SOFT_DELETABLE` and `:252-269` must list `LoyaltyReward::class` |
| Route prefix | `bootstrap/app.php` | `withRouting(api: ...)` | `:21-24` (no `apiPrefix`) | URLs are `/api/...`, **not** `/api/v1`. Tests post to `/api/cms/folios/...` (`FolioPaymentTest.php:62`). Use the same. |
| Guest groups | `routes/api.php` | - | `auth:guests` reservations `:588-593`; stays `:569`; device tokens `:753-758` | new `Route::middleware('auth:guests')->prefix('loyalty')` block |
| Staff groups | `routes/api.php` | - | `cms/folios` `:730-747`; `cms/event-inquiries` `:531-548`; `guests` `:635-645` | new `Route::middleware('auth:users')->prefix('cms/loyalty')` block |
| Exception rendering | `bootstrap/app.php` | `render` | `:55-66` | no change; `DomainException` renders `custom.errors.<errorCode>` fallback |
| Reservation resource | `app/Http/Resources/Booking/ReservationResource.php` | `toArray` | whole | additive `loyalty` block via `whenLoaded('loyaltyApplication')` |

### Earn: answers to Question 2 (verified)

- **Where "settled" is decided:** `SettleFolioAction` (two statements) and `RecordFolioPaymentAction` auto-settle (one). All under `Folio::whereKey(...)->lockForUpdate()` inside `DB::transaction`.
- **Money storage:** DECIMAL(10,2) USD strings via `decimal:2` casts (`Folio.php:27-33`, `FolioItem.php`, `Reservation.php:53`); bcmath through `FolioLedger`. No integer cents anywhere in the domain tables.
- **Taxes/discounts:** no tax or service-charge lines exist (`Folio::recalculateTotals` says "total equals subtotal until tax/service lines exist", `Folio.php:105-117`). Promo discount is folded into `reservations.total_usd` (`QuoteReservationAction.php:39-53`), so the folio room line is net. Credits are negative rows (`PostFolioItemAction.php:95`).
- **Payments/partial payments:** `ledgerPayments()` unions folio and reservation payables (`Folio.php:79-88`); `balanceDueUsd()` is signed. Partial payments leave the folio open; the settling payment (or the zero-balance settle) is the only trigger.
- **Idempotency pattern:** `IdempotentWrite` + `payments.idempotency_key` unique `(payable, key)`; `folio_items.idempotency_key`. Repeat settle answers `FolioSettledException` (`SettleFolioAction.php:36-41`), so a retried or concurrent settlement can never reach the earn line twice: the second caller blocks on the folio lock, then sees `SETTLED` and throws before earning.
- **Spend per source (proposal, Open Question Q10/Q11):** bucket `stay` = `reservation` lines; bucket `service` = `service_booking`, `service_request`, positive `manual`; a `credit` row reduces the bucket of the row it reverses, a standalone credit reduces `service`; each bucket clamped at `0.00`; `points = halfUp(spend * earn_rate)` per bucket, so at most two earn batches per folio.
- **Double-earn invariants (all three must hold):** (1) call inside the folio-locked settle transaction; (2) ledger `idempotency_key` `earn:folio:{folio_id}:{bucket}` unique; (3) unique `(folio_id, source)` on `loyalty_earn_batches`.

### Reversal: answers to Question 3 (verified)

- **Folio refund:** nothing exists to hook. `Refund` (`app/Models/Refund.php`, `2026_07_10_100006_create_refunds_table.php`) is referenced only by `Payment::refunds()`; `ResolveFolioDisputeAction` states a refund-worthy dispute is settled by posting a credit and "never moves money".
- **Reservation cancel:** `CancelReservationAction` only allows `pending_verification`, `pending`, `confirmed` (`ReservationStatus::isCancellable`, `ReservationStatus.php`); leaves `status = cancelled`, revokes the digital key, writes nothing to the folio or payments. Existing promo behaviour for comparison: `used_count` is incremented at booking (`CreateReservationAction.php:74-76`) and **never decremented on cancel**, so promo is not restored today. Loyalty must not copy that.
- **Settled, refunded, re-settled today:** impossible. Settled is terminal; `PostFolioItemAction` (`:73`), `RecordFolioPaymentAction` (`:61`), `PaymentService` (`:33`) all throw `folio_settled`. Therefore a folio can earn at most once, and earned points can only exist for a reservation that is cancelled *after* a pre-arrival settle (zero-balance settle of a prepaid stay is allowed because `SettleFolioAction` does not check reservation status).
- **Keying reversals to originals:** `loyalty_ledger_entries.reverses_entry_id` (unique) points at the earn or redeem entry reversed; allocations give the exact batches to restore; `loyalty_reservation_applications.status` (`applied` -> `reversed`) is the idempotent switch for the cancel path.

## Proposed Data Model

All additive, migration prefix `2026_10_05_1000NN_` (latest existing is `2026_10_04_100100_add_report_indexes`, verified by `ls database/migrations`). Convention analogs: `2026_10_02_100100_create_event_inquiry_checklist_items_table.php` (explicit `restrictOnDelete`, `down()` drops), `2026_10_04_100000_create_night_audit_tables.php` (singleton idiom `unsignedTinyInteger('singleton')->unique()`). Staff and guests are never hard-deleted, so ledger FKs are `restrictOnDelete`; recycle-bin-purgeable `loyalty_rewards` is referenced `nullOnDelete` with snapshot columns on vouchers.

| # | Migration | Table | Purpose |
|---|-----------|-------|---------|
| 1 | `2026_10_05_100000_create_loyalty_settings_table` | `loyalty_settings` | singleton program config |
| 2 | `2026_10_05_100100_create_loyalty_rewards_table` | `loyalty_rewards` | AR/EN catalog, soft deletes |
| 3 | `2026_10_05_100200_create_loyalty_earn_batches_table` | `loyalty_earn_batches` | points lots with remaining balance and expiry |
| 4 | `2026_10_05_100300_create_loyalty_vouchers_table` | `loyalty_vouchers` | redeemed rewards |
| 5 | `2026_10_05_100400_create_loyalty_ledger_entries_table` | `loyalty_ledger_entries` | immutable signed ledger |
| 6 | `2026_10_05_100500_create_loyalty_allocations_table` | `loyalty_allocations` | which batch a spend/expiry/clawback took points from |
| 7 | `2026_10_05_100600_create_loyalty_reservation_applications_table` | `loyalty_reservation_applications` | what was applied to a reservation |

FK graph is acyclic: settings; rewards; batches -> guests, folios, users; vouchers -> guests, rewards, reservations; ledger -> guests, batches, vouchers, reservations, folios, users, ledger(self); allocations -> ledger, batches; applications -> reservations, guests, vouchers, ledger.

### Columns

**`loyalty_settings`** (no uuid, singleton like `night_audit_states`): `id`; `singleton` unsignedTinyInteger unique; `earn_rate` decimal(8,4) null (points per 1 USD); `redeem_value_usd` decimal(10,4) null (USD per 1 point); `expiry_months` unsignedSmallInteger default 24; `expiry_warning_days` unsignedSmallInteger default 30; `min_redeem_points` unsignedInteger null; `max_redeem_percent` decimal(5,2) null (0.01-100.00); `updated_by` foreignId users nullable restrict, index; timestamps. Model `LoyaltySetting` with `LogsActivity` (every change is audited with old/new). "No seeded rates": the migration inserts nothing; `LoyaltyProgram::settings()` returns an unsaved defaults instance when the row is absent.

**`loyalty_rewards`**: `id`; `uuid` unique; `name` json (HasTranslations); `description` json null; `type` string(20) index (`discount_voucher|free_night|room_upgrade`); `points_cost` unsignedInteger; `discount_usd` decimal(10,2) null (required for `discount_voucher`); `voucher_valid_days` unsignedSmallInteger; `is_active` boolean default true; `sort_order` unsignedInteger default 0; timestamps; softDeletes. Index `(is_active, sort_order)`. `is_active` follows the CMS content idiom (`CmsContentFilter` merges it); lifecycle state of points/vouchers uses `status` columns instead (Q18).

**`loyalty_earn_batches`**: `id`; `uuid` unique; `guest_id` FK guests restrict; `source` string(16) (`stay|service|manual|refund`); `folio_id` FK folios nullable restrict; `awarded_by` FK users nullable restrict; `reason` text null; `points` unsignedInteger; `points_remaining` unsignedInteger; `earned_at` timestamp; `expires_at` timestamp; `expiry_warned_at` timestamp null; `status` string(16) (`active|depleted|expired|reversed`); timestamps. Indexes: `(guest_id, status, expires_at)` (balance, FIFO), `(status, expires_at)` (expiry sweep), `(status, expiry_warned_at, expires_at)` (warning scan), `folio_id`, `awarded_by`; **unique `(folio_id, source)`** (NULL folio rows are exempt on both SQLite and MySQL).

**`loyalty_vouchers`**: `id`; `uuid` unique; `code` string(16) unique; `guest_id` FK restrict index; `loyalty_reward_id` FK nullOnDelete index; snapshot columns `type` string(20), `reward_name` json, `value_usd` decimal(10,2) null, `points_spent` unsignedInteger; `status` string(16) index (`active|used|expired|void`); `expires_at` timestamp; `reservation_id` FK reservations nullable restrict, **unique**; `used_at` timestamp null; timestamps. Indexes `(guest_id, status)`, `(status, expires_at)`. `LogsActivity` (status transitions).

**`loyalty_ledger_entries`** (append-only; `UPDATED_AT = null`; no `LogsActivity`, the ledger is the audit): `id`; `uuid` unique; `guest_id` FK restrict; `type` string(16) (`earn|redeem|expire|adjust|reversal|refund`); `points` integer (signed, never 0); `batch_id` FK nullable restrict (earn/expire/adjust); `voucher_id`, `reservation_id`, `folio_id` nullable FKs restrict; `reverses_entry_id` self-FK nullable restrict, **unique**; `performed_by` FK users nullable restrict; `reason` text null; `shortfall_points` unsignedInteger default 0; `discount_usd` decimal(10,2) null; `idempotency_key` string(120) **unique**; `occurred_at` timestamp; `created_at`. Indexes `(guest_id, occurred_at, id)` (ledger listing), `(type, occurred_at)` (reports), `reservation_id`, `folio_id`, `batch_id`.
Idempotency key shapes: `earn:folio:{folio_id}:{bucket}`, `redeem:reward:{guest_id}:{client_key}`, `redeem:booking:{guest_id}:{client_key}`, `adjust:{guest_id}:{client_key}`, `expire:batch:{batch_id}`, `reversal:{reversed_entry_id}`, `refund:{redeem_entry_id}`. Client keys are `max:64` (`ReadsIdempotencyKey` precedent), hence `string(120)`.

**`loyalty_allocations`**: `id`; `ledger_entry_id` FK restrict; `batch_id` FK restrict, index; `points` unsignedInteger. Unique `(ledger_entry_id, batch_id)`.

**`loyalty_reservation_applications`**: `id`; `uuid` unique; `reservation_id` FK restrict **unique**; `guest_id` FK restrict index; `redeem_entry_id` FK ledger nullable restrict; `points_redeemed` unsignedInteger default 0; `points_discount_usd` decimal(10,2) default 0; `voucher_id` FK vouchers nullable restrict **unique**; `voucher_discount_usd` decimal(10,2) default 0; `status` string(16) index (`applied|reversed`); `reversed_at` timestamp null; timestamps. `LogsActivity`.

**Why no change to `reservations`:** `total_usd` is already the net snapshot (promo precedent); the discount is a derived fact on the application row. This keeps the migration purely additive and the `CreateReservationAction` change local.

### Algorithms

**Balance:** `SUM(points_remaining) WHERE guest_id=? AND status='active' AND expires_at > now()`. Expiring soon: same with `expires_at <= now() + expiry_warning_days`. Two queries, index-served.

**Earn (`EarnLoyaltyPointsAction`, inside the settle txn):**
1. Resolve program: if `earn_rate` null, return no-op (and log `loyalty.earn_skipped_inactive` via `activity()`); if `reservation.guest_id` null, no-op.
2. Load folio items once (`id, amount_usd, source_type, reverses_item_id`); bucket as in Integration Points; clamp each bucket at `0.00`.
3. For each bucket with `points > 0`: insert `loyalty_earn_batches` (`points = points_remaining`, `earned_at = now()`, `expires_at` per Pattern 4) and one `earn` ledger entry (`idempotency_key = earn:folio:{id}:{bucket}`). On `UniqueConstraintViolationException` for either key, treat as already earned (savepoint, as `IdempotentWrite` does).

**Redeem reward to voucher (`RedeemRewardAction`):** `IdempotentWrite` keyed `redeem:reward:{guest}:{key}` -> lock guest -> validate program redeeming, reward active and not trashed, `points >= min_redeem_points` (cost is the reward's own, the minimum applies to free-form; see Q5), enough available -> FIFO consume -> `redeem` ledger entry (negative, allocations) -> create voucher (snapshot, `expires_at = now + voucher_valid_days`) -> return voucher (201, replay 200).

**Apply at booking (`ApplyLoyaltyToReservationAction`):** inputs: guest, quote total (string), `loyalty_points` or `voucher_code`. Free-form: require redeeming enabled, `points >= min`, `discount(points) <= maxDiscount(total, percent)` and `<= total`, available >= points. Voucher: must belong to the guest, `status = active`, `expires_at > now()`; `discount_usd` => fixed `value_usd` capped at total; `free_night` => `daily_rate_usd` from the same quote, capped at total; `room_upgrade` => `0.00`, flag only (Q12). Net `total_usd = total - discount`, floor `0.00`. Persist: consume + `redeem` entry (`reservation_id`, `discount_usd`) or mark voucher `used`, then insert the application row. All inside `CreateReservationAction`'s transaction, so a failed availability check rolls everything back.

**Clawback (`ReverseLoyaltyForFolioAction`)** for each earn entry of the folio not yet reversed: take up to `points` from the originating batch's remaining, then (default policy Q2) from the guest's other active batches FIFO; write one `reversal` entry per earn entry (`reverses_entry_id`, negative points = amount actually taken, `shortfall_points` = remainder), set an emptied originating batch to `reversed`. Never produces a negative balance and never throws because points were already spent.

**Refund spent points (`ReverseLoyaltyForReservationAction`)** when the application is `applied`: for each allocation of the redeem entry, if the batch is `active` or `depleted` and `expires_at > now()` add the points back and set `active`; otherwise create a `refund` batch with a full new term (Q3); write a `refund` entry (`reverses_entry_id` = redeem entry); restore the voucher (`status = active`, `reservation_id = null`, `used_at = null`; if past `expires_at`, apply the Q13 grace); set application `reversed`.

**Expire (`loyalty:expire-points`, daily, bookkeeping only):** `chunkById(200)` over `status='active' AND expires_at <= now()`; per batch a transaction: lock guest, re-select the batch `lockForUpdate()`, skip if no longer active, write `expire` entry (`-points_remaining`, key `expire:batch:{id}`, allocation row), set `points_remaining = 0`, `status = expired`. The same command sweeps vouchers (`active` and `expires_at <= now()` -> `expired`).

**Warn (`loyalty:notify-expiring`, daily):** per guest with unwarned active batches expiring within `expiry_warning_days`: in one transaction lock guest, select those batches, `pushToGuest(...)` with locale `$guest->preferred_locale ?? config('app.locale')`, then set `expiry_warned_at = now()` on exactly those batches. A push failure rolls back both the `guest_notifications` row and the marker, so the next day retries (at-least-once, no duplicates from the marker).

**Half-up rounding on SQLite and MySQL:** all rounding happens in PHP bcmath on strings fetched from DECIMAL columns, never in SQL, so both engines behave identically. The only SQL arithmetic is integer `SUM`.

## Settings (Question 5, verified)

- Today's only settings store is `site_settings` (`SiteSetting` model: `group/key/value json/type/is_active`, soft deletes), read by `SiteSettingService::grouped()` (staff) and `publicMap()` (anonymous website). Writes go through `UpsertSiteSettingsAction` (one atomic bulk PUT, `routes/api.php:509`). It is website copy, publicly exposed, and has no typed numeric columns. Not suitable (see Alternatives).
- Pattern to follow instead: the singleton idiom from `night_audit_states` (`singleton` unique) plus the `UpsertSiteSettingsAction` shape (one atomic action, `['data' => null|model, 'code' => 200]`), `LogsActivity` for old/new audit. Endpoints: `GET /api/cms/loyalty/settings` (`loyalty.view|loyalty.manage`), `PUT /api/cms/loyalty/settings` (`loyalty.manage`; full-form PUT like `/cms/settings`). Partial updates are not needed.
- **Unset behaviour (recommended, Q4):** earning is active iff `earn_rate` is set and > 0; redeeming is active iff `redeem_value_usd` and `max_redeem_percent` are set; `min_redeem_points` null means 1. Guest `GET /loyalty/account` still works when inactive and returns `program: {earning: bool-like status, redeeming: ...}` expressed as `"status": "inactive|earning_only|active"` (a derived string, not a stored flag). Redeem/apply while redeeming is inactive -> 422 `loyalty_program_inactive`.
- Changes apply to future events only: `expires_at` is stored per batch at earn time, so changing `expiry_months` never rewrites old batches (Q15).

## Scheduled jobs and notifications (Question 6, verified)

- Registration: `routes/console.php` uses `Schedule::command('name')->cadence()->withoutOverlapping()` (`:11`, `:16`, `:21`). Commands are thin: `signature`, `handle(Action $action): int` (`app/Console/Commands/ExpireDigitalKeys.php`, `ReleaseExpiredHolds.php`).
- Testing pattern: find the event in `app(Schedule::class)->events()` and assert `expression` and `withoutOverlapping` (`tests/Feature/Stays/DigitalKeyLifecycleTest.php:204-213`); run with `$this->artisan('cms:purge-bin', [...])->expectsOutputToContain(...)->assertSuccessful()` (`RecycleBinRetentionTest.php:278-282`); freeze time with `$this->travelTo(...)`.
- Notification path: `NotificationService::pushToGuest(Guest, NotificationType, title, body, data)` creates the `guest_notifications` row, then pushes to the guest's device tokens through `FirebaseServiceInterface` (bound to `NullFirebaseService` when no credentials, `AppServiceProvider.php:34-39`). Tests bind `Tests\Support\FakeFirebaseService` (`->pushes`). Existing listeners call it from queued listeners with no request locale (`SendRoomReadyNotification`), so the command must pass the guest's locale explicitly (`Guest.preferred_locale` is `en|ar`, `UpdateGuestProfileRequest.php:39`).
- Strings: `custom.notifications.*` in all five locale files (nested array style like `check_in_approved.title/body`).
- Cadence (recommended): expire 01:00 and warn 09:00 in the hotel timezone: `->dailyAt('01:00')->timezone(config('hotel.timezone'))`. `withoutOverlapping()` needs a cache store with locks in production (`CACHE_STORE=array` in tests is fine).

## Auth, permissions and routing (Question 7, verified)

- Guards: `auth:guests` and `auth:users` only (no `sanctum` guard). Guest loyalty routes are plain `auth:guests` like `/reservations` (`routes/api.php:588`); they are not tied to `is_checked_in`/`has_booking` because a guest earns and redeems between stays.
- Spatie catalogue: flat list in the seeder (`:15-43`), groups derived by the permission-name prefix in `PermissionAssignmentService::groupedPermissions`; no group-label translation keys exist (09-RESEARCH). `super_admin` bypasses via `Gate::before` (`AppServiceProvider.php`). Presets (`:58-79`) are untouched by Phase 9 and should be untouched here.
- Proposed new permissions (Q7): `loyalty.view` (settings read, rewards read, guest balance/ledger, reports), `loyalty.manage` (settings write, rewards CRUD/restore of own rows via the cms bin permissions), `loyalty.adjust` (manual award/deduct, money-like write, own string per the `folios.settle` / `events.deposit` precedent). Catalogue arithmetic: Phase 9 closes at 30 permissions / 13 groups; Phase 10 -> **33 / 14** (re-read the real numbers at execution).
- Bin routes follow precedent: `cms/loyalty/rewards/trashed` under `permission:cms.restore|cms.purge`, `.../restore` under `cms.restore`, `.../force` under `cms.purge`, declared inside the existing `cms` bin groups before the `{reward}` routes (the comment at `routes/api.php:230-232` explains the ordering bug otherwise).
- `CmsAccessControlTest::test_every_seeded_permission_is_enforced_somewhere` (`:118-165`) fails if any seeded permission is not in a route `permission:` string or a PHP string literal under `app/`; all three `loyalty.*` strings appear in route middleware.

### Proposed routes (all under `/api`, no `/v1`)

Guest (`auth:guests`):
| Method | Path | Notes |
|--------|------|-------|
| GET | `/loyalty/account` | available, expiring_soon (+ window days), next expiry instant, redeem value, program status |
| GET | `/loyalty/ledger` | paginated, filters `type`, `date_from`, `date_to` |
| GET | `/loyalty/rewards` | active, non-trashed, paginated, AR/EN maps |
| POST | `/loyalty/rewards/{reward}/redeem` | `Idempotency-Key` required; 201 new, 200 replay; `throttle:30,1` |
| GET | `/loyalty/vouchers` | own only; filter `status` |
| GET | `/loyalty/preview` | query `room_type_uuid, check_in, check_out, promo_code?, points?, voucher_code?`; returns quote + `points_earnable_estimate` + discount + net total + `max_points` |
| POST | `/reservations` (existing) | additive optional `loyalty_points`, `voucher_code`; `Idempotency-Key` required when either is present |

Staff (`auth:users`, prefix `cms/loyalty`):
| Method | Path | Permission |
|--------|------|------------|
| GET / PUT | `/settings` | view or manage / manage |
| GET | `/rewards`, `/rewards/{reward}` | `loyalty.view\|loyalty.manage` |
| POST / PUT / DELETE | `/rewards`, `/rewards/{reward}` | `loyalty.manage` |
| GET / POST | `/rewards/trashed`, `/rewards/{reward}/restore`, `DELETE /rewards/{reward}/force` | `cms.restore\|cms.purge`, `cms.restore`, `cms.purge` |
| GET | `/guests/{guest}` (balance), `/guests/{guest}/ledger` | `loyalty.view` |
| POST | `/guests/{guest}/adjustments` | `loyalty.adjust`, `Idempotency-Key` required, `reason` required |
| GET | `/reports?date_from&date_to` | `loyalty.view` |

## Runtime State Inventory

Not a rename/refactor/migration phase. Greenfield additive module: **None** for all five categories (stored data, live service config, OS-registered state, secrets/env vars, build artifacts). Two deploy-time items instead: re-run `php artisan db:seed --class=RolesAndPermissionsSeeder` so the three new permissions exist, and the scheduler cron (`schedule:run`) must already be running in production for the two new commands (it already must be for the existing three).

## Common Pitfalls

### Pitfall 1: Double-earn on a retried or concurrent settlement
**What goes wrong:** points credited twice for one folio.
**Why it happens:** earning from an event/listener or outside the folio lock, or without a unique key.
**How to avoid:** inline call inside the locked settle transaction; `earn:folio:{id}:{bucket}` unique on the ledger and unique `(folio_id, source)` on batches; a second settle attempt already throws `folio_settled` before reaching the earn line.
**Warning signs:** two `earn` rows with the same `folio_id`; SQLite tests cannot prove concurrency, so assert the lock with `RecordsRowLocks` and the unique indexes, and list the MySQL concurrent check as a manual verification (same limitation Phase 9 records).

### Pitfall 2: Double redeem
**What goes wrong:** a retried POST spends points twice or creates two vouchers/reservations.
**Why it happens:** `POST /reservations` is not idempotent today.
**How to avoid:** require `Idempotency-Key` whenever loyalty fields are sent; ledger key `redeem:booking:{guest}:{key}`; replay returns the existing reservation with 200 (resolve via the ledger entry's `reservation_id`). Reward redeem uses `IdempotentWrite` like `RecordFolioPaymentAction.php:41-53`, comparing reward uuid as the payload.

### Pitfall 3: Negative balance on clawback of already-spent points
**What goes wrong:** cancelling a stay claws back points the guest already spent.
**How to avoid:** never let `points_remaining` go below 0; take from the originating batch, then other batches FIFO; record `shortfall_points`; never block the cancel (Q2). Surface the shortfall in the activity log and the ledger resource so staff can see it.

### Pitfall 4: Refunded points returning with a stale or lost expiry
**What goes wrong:** the original batch expired while the points were committed to a reservation, so a naive restore revives expired points or loses them.
**How to avoid:** restore only into batches that are still unexpired; otherwise create a `refund` batch with a fresh full term (Q3). Test both branches.

### Pitfall 5: Expiry boundary and timezone
**What goes wrong:** a batch looks valid for a few hours after expiry, or a Jan 31 + 1 month overflows into March.
**How to avoid:** `expires_at` = hotel-local end of day via `addMonthsNoOverflow`, stored UTC; every availability query filters `expires_at > now()` so the sweep is not the gate; boundary test at `expires_at - 1s`, `expires_at`, `expires_at + 1s` using `travelTo`.

### Pitfall 6: Preview and actual drifting
**What goes wrong:** the guest sees one discount and is charged another.
**How to avoid:** one `LoyaltyMath` used by both; convert the float quote with `number_format($v, 2, '.', '')`; a test feeds identical inputs through the preview endpoint and `POST /reservations` and asserts identical `total_usd`. Min/cap compare on recomputed discount, not on a points ceiling alone (Q5).

### Pitfall 7: Half-up edge cases
**What goes wrong:** 0.5 rounds down, or a float sneaks in.
**How to avoid:** bcmath only; table-driven unit tests for `x.5`, `x.4999`, `0`, huge spend, rate with 4 decimals, discount `0.005` boundary; assert the result is `int`/string, never float.

### Pitfall 8: SQLite vs MySQL differences
**What goes wrong:** unique index on nullable columns, `lockForUpdate` ignored by SQLite, date string comparison.
**How to avoid:** nullable uniques (`reservation_id`, `reverses_entry_id`, `(folio_id, source)`) behave the same on both; locks are asserted with `RecordsRowLocks` (records MySQL `for update` in a comment); all timestamps are UTC and compared against Carbon bindings; no SQL date functions (Phase 9 learned the same: no `julianday`/`DATEDIFF`).

### Pitfall 9: N+1 in ledger and voucher listings
**How to avoid:** service `$with = ['reservation:id,uuid,booking_code', 'folio:id,uuid', 'voucher:id,uuid,code']`; resource uses `whenLoaded`; mobile ledger needs `bookingRef`, so booking code must be eager-loaded. Add a query-count assertion on the listing (use `expectsDatabaseQueryCount`, `EventInquiryDetailTest.php:233`).

### Pitfall 10: Lock-order deadlock
**What goes wrong:** cancel (reservation -> guest) and booking (room_type -> guest) and settle (folio, insert batch) interleave.
**How to avoid:** guest lock is always last; settle earn needs no guest lock (insert-only). MySQL takes a shared lock on the parent `guests` row for an FK insert, so an earn insert can wait for a redeem transaction; that is a wait, not a cycle, because redeem never waits on a folio. [ASSUMED: InnoDB FK shared-lock behaviour, standard but not exercised here]

### Pitfall 11: Cancel check outside the transaction
`CancelReservationAction.php:19` checks status before `DB::transaction`; two concurrent cancels both pass and the reversal could run twice. Lock the reservation first and re-check inside; the application row `status` plus the unique `reverses_entry_id` make the reversal idempotent regardless.

### Pitfall 12: Rewards force-purged while vouchers exist
`cms:purge-bin` force-deletes every soft-deletable model after retention. `loyalty_vouchers.loyalty_reward_id` is `nullOnDelete` and vouchers carry snapshot columns, so purge never fails and history survives. `RecycleBinRetentionTest::test_every_soft_deletable_model_is_covered_by_the_purge` will fail until `LoyaltyReward::class` is added to its `SOFT_DELETABLE` list; that is expected.

### Pitfall 13: Promo precedent copied by mistake
`promo_codes.used_count` is never decremented on cancel. Do not mirror that for vouchers; voucher restore is a locked requirement.

### Pitfall 14: Ledger rows carrying PII or internal ids
Resources expose UUIDs only (`reservation.uuid`, `folio.uuid`), never names/phones; ledger `reason` is staff free text shown to the guest only if the product wants it (Q16 decides which fields the guest sees; default hide `performed_by` and show a localised label for `manual`).

## Code Examples

### Idempotent reward redeem skeleton
```php
// Source pattern: app/Actions/Folio/RecordFolioPaymentAction.php:35-56
return DB::transaction(function () use ($guest, $reward, $key) {
    Guest::whereKey($guest->id)->lockForUpdate()->firstOrFail();

    [$voucher, $replayed] = IdempotentWrite::run(
        $key,
        fn () => LoyaltyVoucher::whereHas('redeemEntry', fn ($q) => $q->where('idempotency_key', "redeem:reward:{$guest->id}:{$key}"))->first(),
        fn (LoyaltyVoucher $v) => $v->loyalty_reward_id === $reward->id,
        fn () => $this->issue($guest, $reward, $key),
    );

    return ['data' => $voucher->load('reward'), 'code' => $replayed ? 200 : 201];
});
```
(The planner may simplify the lookup by finding the ledger entry by `idempotency_key` and loading its voucher.)

### Command + schedule + test
```php
// routes/console.php (pattern of :16)
Schedule::command('loyalty:expire-points')->dailyAt('01:00')->timezone(config('hotel.timezone'))->withoutOverlapping();

// test (pattern of DigitalKeyLifecycleTest.php:204-213)
$events = collect(app(Schedule::class)->events())
    ->filter(fn ($e) => str_contains((string) $e->command, 'loyalty:expire-points'))->values();
$this->assertCount(1, $events);
$this->assertTrue($events[0]->withoutOverlapping);
```

### Gate for a locked-order test
```php
use Tests\Concerns\RecordsRowLocks;
// $this->lockedSelects(fn () => app(RedeemRewardAction::class)->handle($guest, $reward, 'k1'));
// assert order: guests -> loyalty_earn_batches
```

## Proposed File Inventory

For the pattern mapper. **Create** unless marked *(modify)*. Closest analog in brackets.

**Migrations (7):** `database/migrations/2026_10_05_100000..100600_*` listed in Proposed Data Model [`2026_10_02_100100_create_event_inquiry_checklist_items_table.php`, `2026_10_04_100000_create_night_audit_tables.php`].

**Enums (`app/Enums/`)** [`FolioStatus.php`, `EventChecklistItem.php`; all use `Concerns/HasValues`]: `LoyaltyEntryType`, `LoyaltyBatchStatus`, `LoyaltyBatchSource`, `LoyaltyRewardType`, `LoyaltyVoucherStatus`, `LoyaltyApplicationStatus`; *(modify)* `NotificationType` (+ `LOYALTY_POINTS_EXPIRING`).

**Models (`app/Models/`)** [`Faq.php` for translatable soft-deletable; `EventInquiryChecklistItem.php`, `NightAudit*.php` for status models; `Folio.php` for relations]: `LoyaltySetting`, `LoyaltyReward` (`HasUuid, HasTranslations, LogsActivity, SoftDeletes, HasFactory`), `LoyaltyEarnBatch`, `LoyaltyLedgerEntry`, `LoyaltyAllocation`, `LoyaltyVoucher`, `LoyaltyReservationApplication`; *(modify)* `Reservation` (+ `loyaltyApplication(): HasOne`), `Guest` (+ `loyaltyBatches()`, optional).

**Factories (`database/factories/`)** [`FaqFactory`, `NightAuditFactory`]: one per model (7), with states `expired()`, `depleted()`, `used()`.

**Support (`app/Support/`)** [`FolioLedger`, `HotelClock`]: `LoyaltyMath`, `LoyaltyProgram`, `LoyaltyLedger`.

**Actions (`app/Actions/Loyalty/`)** [`Events/RecordEventDepositAction` (lock + replay), `Folio/RecordFolioPaymentAction`, `Cms/UpsertSiteSettingsAction`]: `EarnLoyaltyPointsAction`, `RedeemRewardAction`, `ApplyLoyaltyToReservationAction`, `ReverseLoyaltyForReservationAction`, `ReverseLoyaltyForFolioAction`, `AdjustLoyaltyPointsAction`, `ExpireLoyaltyBatchesAction`, `NotifyExpiringLoyaltyPointsAction`, `UpdateLoyaltySettingsAction`, `PreviewLoyaltyAction`.
*(modify)* `Folio/SettleFolioAction`, `Folio/RecordFolioPaymentAction`, `Booking/CreateReservationAction`, `Booking/CancelReservationAction`.

**Services (`app/Services/Loyalty/`)** [`Cms/FaqService` (BaseService + filter), `Operations/OperationsQueueService`]: `LoyaltyRewardService`, `LoyaltyAccountService`, `LoyaltyReportService`. *(modify)* `Booking/ReservationService::store` (pass loyalty args).

**Filters (`app/Filters/`)** [`FaqFilter` / `CmsContentFilter`, `GuestFilter`]: `LoyaltyRewardFilter`, `LoyaltyLedgerFilter`, `LoyaltyVoucherFilter`.

**Requests (`app/Http/Requests/Loyalty/`)** [`Cms/CreateFaqRequest` + `TranslatableRules`, `Events/RecordEventDepositRequest` + `ReadsIdempotencyKey`]: `CreateLoyaltyRewardRequest`, `UpdateLoyaltyRewardRequest`, `UpdateLoyaltySettingsRequest`, `RedeemLoyaltyRewardRequest`, `AdjustLoyaltyPointsRequest`, `LoyaltyPreviewRequest`, `LoyaltyReportRequest`. *(modify)* `Booking/StoreReservationRequest`.

**Resources (`app/Http/Resources/Loyalty/`)** [`Cms/FaqResource`, `Guest/GuestDirectoryResource`]: `LoyaltyRewardResource` (locale maps via `getTranslations`), `LoyaltyAccountResource`, `LoyaltyLedgerEntryResource`, `LoyaltyVoucherResource`, `LoyaltySettingsResource`, `LoyaltyPreviewResource`, `LoyaltyReportResource`. *(modify)* `Booking/ReservationResource`.

**Controllers** [`Admin/FaqController` (BaseCRUDController + HandlesRecycleBin), `Admin/FolioController` (BaseController custom verbs), `Api/ReservationController`]: `Admin/LoyaltyRewardController`, `Admin/LoyaltySettingController`, `Admin/LoyaltyGuestController`, `Admin/LoyaltyReportController`, `Api/LoyaltyController`, `Api/LoyaltyRewardController`. *(modify)* `Api/ReservationController::store`.

**Exceptions (`app/Exceptions/`, flat)** [`FolioSettledException`, `NightAuditClosedException`; all 422 unless noted]: `LoyaltyProgramInactiveException`, `LoyaltyInsufficientPointsException`, `LoyaltyBelowMinimumException`, `LoyaltyOverCapException`, `LoyaltyVoucherInvalidException`, `LoyaltyRewardUnavailableException`, `LoyaltyAdjustmentInvalidException`; reuse `IdempotencyConflictException`, `NotFoundException`.

**Console** [`ExpireDigitalKeys`]: `ExpireLoyaltyPoints`, `NotifyExpiringLoyaltyPoints`; *(modify)* `routes/console.php`.

**Routes/seed/lang** *(modify)*: `routes/api.php`, `database/seeders/RolesAndPermissionsSeeder.php`, `lang/{en,ar,fr,tr,es}/custom.php` (sections: `messages`, `errors`, `notifications`, `validation`, new `loyalty` for enum labels; same key order in every file; see `LocaleFoundationTest` parity).

**Tests** *(modify, re-pin)*: `tests/Feature/SeederTest.php`, `tests/Feature/Staff/PermissionsGroupedTest.php`, `tests/Feature/Cms/RecycleBinRetentionTest.php` (`SOFT_DELETABLE`). New tests listed in Validation Architecture.

**Docs** *(modify)*: `backend/docs/API_GUIDE_DASHBOARD.md` (settings, rewards, guest ledger, adjust, reports), `backend/docs/API_GUIDE_MOBILE.md` and `CHANGELOG_MOBILE_API.md` (guest endpoints; `POST /reservations` additive fields; notify Flutter team about `error_code` additions and that no tier fields exist), `backend/docs/carlton-api.postman_collection.json` (folder "Loyalty"), `docs/carlton-tree.html` (api:true flags; no loyalty node exists today, add one).

## Proposed Requirements

Suggested additions to REQUIREMENTS.md (IDs `LOY-nn`). The locked CONTEXT decisions are the source.

**Program settings**
- LOY-01: Staff can read and update the six program settings (earn rate, redeem value, expiry months, expiry-warning days, minimum points to redeem, max % payable with points); changes are audited.
- LOY-02: With no rates configured the program is inactive (no earning, redemption refused); no rates are seeded.

**Earning**
- LOY-03: Settling a folio credits integer points (round half up) on room-stay spend and on services/F&B spend, once, atomically with settlement, under every settlement path.
- LOY-04: Earning is idempotent: a retried or concurrent settlement never double-credits; guests with no account and unconfigured programs earn nothing; no historical backfill.
- LOY-05: Staff can award or deduct points manually with a mandatory reason, idempotently and audited; a deduction can never exceed the available balance.

**Balance, ledger, expiry**
- LOY-06: A guest sees available points, points expiring soon, and a paginated ledger (earn / redeem / expire / adjust / reversal / refund) tied to bookings.
- LOY-07: Staff can view any guest's balance and ledger.
- LOY-08: Each earn batch expires after the configured months (hotel-local end of day); points are consumed FIFO; expired points are never spendable even before the sweep runs.
- LOY-09: A daily job expires batches and writes expire ledger entries idempotently.
- LOY-10: A guest is notified once per batch N days before expiry through the existing notification system, in the guest's language.

**Rewards and vouchers**
- LOY-11: Staff manage a rewards catalog (AR/EN name and description, points cost, type discount voucher / free night / room upgrade) with a recycle bin.
- LOY-12: A guest can browse active rewards.
- LOY-13: A guest redeems a reward into a voucher (code, status, expiry); the redeem is idempotent and transactional and spends points FIFO.
- LOY-14: A guest lists their own vouchers by status.

**Booking discount**
- LOY-15: A guest can preview points earnable and the discount for a prospective booking without side effects.
- LOY-16: A guest can pay part of a booking with points (free-form) subject to the minimum and the max-% cap, or apply one voucher; the discount reduces the reservation total and is atomic with reservation creation.

**Reversals**
- LOY-17: Cancelling a reservation refunds spent points, restores a used voucher and claws back points earned from its settled folio, idempotently, never producing a negative balance.
- LOY-18: A callable, tested folio-refund reversal exists for the future refund flow.

**Reporting**
- LOY-19: Staff can report points issued, redeemed and expired over a period, plus outstanding points.

**Cross-cutting (gate in every plan)**
- LOY-20: New `loyalty.*` permissions are seeded, enforced by route middleware and shown in the permission picker; no preset changes.
- LOY-21: Every new string exists in all five locale files; every new route has happy / 401 / 403 / 422 tests; docs, Postman and the tree are updated.

## Open Questions / Gray Areas

Format: question -> options -> recommended default (reason) -> risk. HIGH = money, ledger integrity, idempotency, permissions or API contract. **HIGH count: 11** (Q1-Q10 and Q16).

1. **Q1 (HIGH) Folio-refund reversal has no code path.** Options: (a) build a staff refund endpoint, (b) ship a callable tested seam only, (c) ignore. **Default (b), wired live only from reservation cancel**, because inventing a refund endpoint is money-flow scope the roadmap never granted (`Refund` has no writer).
2. **Q2 (HIGH) Clawing back earned points the guest already spent.** Options: (a) allow negative balance, (b) take only the originating batch's remainder, (c) block the cancel, (d) take from the originating batch then other batches FIFO, never below zero, record `shortfall_points`. **Default (d)**: it recovers the value without ever blocking a cancel or creating debt.
3. **Q3 (HIGH) Refunded spent points: original expiry or fresh?** Options: (a) always fresh batch, (b) restore into the original batch, expired ones are lost, (c) restore into original while unexpired, else fresh full term. **Default (c)**: exact undo when possible, never revives expired points, never punishes the guest for a cancelled booking.
4. **Q4 (HIGH) Behaviour with unset settings.** Options: (a) all-or-nothing, (b) per-capability. **Default (b)**: earn needs `earn_rate`; redeem needs `redeem_value_usd` and `max_redeem_percent`; `min_redeem_points` null = 1; `expiry_months` 24 and `expiry_warning_days` 30 are real column defaults (CONTEXT says "default"), the other four are null. Reason: an unset cap must never mean "100%".
5. **Q5 (HIGH) Cap base and stacking.** Options: cap on gross vs post-promo total; voucher inside vs outside the cap; stackable with free-form points or not. **Default: cap base = post-promo total; cap and minimum apply to free-form points only; at most one voucher per reservation; a voucher and free-form points cannot be combined (422 `loyalty_discount_conflict`); total floored at 0.00.** Reason: simplest rule set a front-desk agent can explain; revisitable additively.
6. **Q6 (HIGH) Idempotency of booking with loyalty.** Options: (a) none, (b) required `Idempotency-Key` whenever loyalty fields are present, (c) always required. **Default (b)**: (c) would break existing clients on `POST /reservations`; replay returns the same reservation with 200.
7. **Q7 (HIGH) Permission strings and who gets them.** **Default:** `loyalty.view`, `loyalty.manage`, `loyalty.adjust`; no preset changes (assigned per account, Phase 9 precedent); reward bin uses `cms.restore` / `cms.purge`; reports under `loyalty.view`, not `reports.view` (least privilege). Alternative: reuse `reports.view` for the report (couples to Phase 9).
8. **Q8 (HIGH) Earn placement and failure semantics.** Options: inline atomic vs after-commit event vs inline with swallowed errors. **Default inline atomic**: a failure rolls the settlement back, which is correct for a ledger and nearly impossible when the program is inactive (one read, no writes).
9. **Q9 (HIGH) Gate earning on reservation status?** Options: (a) only `checked_in/checked_out`, (b) any status at settlement. **Default (b)**: matches the locked rule "credited once on folio settlement" and the locked cancel clawback (which implies earn can precede cancel); (a) would silently never earn for an early prepaid settle.
10. **Q10 (HIGH) Earn rate application and rounding.** Options: one rate on total vs per source bucket vs per-line rounding. **Default: a single configured rate on net spend, rounded half up per bucket (`stay`, `service`), so at most two earn batches and a source label per ledger line.** Reason: lets the client show stay vs service without per-line rounding drift. Net spend means points-discounted amounts do not earn.
11. **Q11 (MED) Do manual folio lines and standalone credits earn/reduce?** **Default:** positive `manual` lines count in `service`; credits reduce the bucket of the line they reverse, standalone credits reduce `service`; buckets clamp at 0.
12. **Q12 (MED) Free night and room upgrade semantics.** Free night: **default = discount of one night at the quote's `daily_rate_usd`**, capped at total. Upgrade: options (a) price difference, (b) flag-only for staff fulfilment, (c) reject at booking. **Default (b): voucher is consumed, discount 0.00, reservation shows `loyalty.upgrade_requested`; staff upgrade through room assignment.** Reason: no upgrade-target data model exists and (a) needs a from/to room type on the reward.
13. **Q13 (MED) Voucher validity and restore.** Per-reward `voucher_valid_days`; expired vouchers are not refunded. On cancel restore: **default restore with original expiry; if already past, grant `config('loyalty.restored_voucher_grace_days')` = 30 days** (new tiny config file).
14. **Q14 (MED) Consumption order: `earned_at` vs `expires_at`.** CONTEXT says FIFO. **Default `ORDER BY expires_at, id`**, identical to FIFO while expiry months is constant and safer when it changes; state this in the guide.
15. **Q15 (MED) Does changing expiry months or warning days touch existing batches?** **Default no**: stored per batch at earn time; warning window is read at scan time.
16. **Q16 (HIGH) Ledger/API contract shape.** The Flutter mock (`mobile/lib/models/loyalty.dart`, `controllers/account/loyalty_controller.dart`) expects tier labels, `earnedTotal/redeemedTotal/staysCount` and entry kinds `earned|redeemed|expired` with sources `stay|dining|spa|other`. CONTEXT locks "no tiers". **Default:** account returns `available_points`, `expiring_soon_points`, `lifetime_earned_points`, `lifetime_redeemed_points`; ledger `type` in `earn|redeem|expire|adjust|reversal|refund`, `source` in `stay|service|manual|refund`; no tier fields. These strings are a contract: write them in the API guide and tell the Flutter team (the mock is explicitly "the only screen not backed by the API").
17. **Q17 (MED) Staff booking on a guest's behalf and the OTP booking flow.** **Default:** loyalty fields are accepted only on authenticated guest `POST /reservations`; `adminStore` and `storeAsGuest` do not accept them.
18. **Q18 (LOW) Reward visibility flag.** `is_active` boolean (CMS idiom) vs a status enum. **Default `is_active`**.
19. **Q19 (LOW) Report period and metrics.** **Default:** `date_from/date_to` both-or-none, strict dates, `HotelClock::dayWindow` bounds, absent = today, max 366 days; `issued` = earn + positive adjust + refund; also `redeemed`, `expired`, `reversed`, `adjusted_out`, and `outstanding_points` (current unexpired remaining) with an estimated liability in USD computed by bcmath.
20. **Q20 (MED) Manual deduct beyond balance and magnitude limits.** **Default refuse** (`loyalty_insufficient_points`), per-call magnitude capped (e.g. 1,000,000), reason `min:3`.
21. **Q21 (LOW) Route namespaces.** **Default:** guest `/loyalty/*`, staff `/cms/loyalty/*`, matching `/folio` vs `/cms/folios` and `/reservations` vs `/cms/reservations`.
22. **Q22 (MED) Expiry warning content.** **Default:** one push per guest per run aggregating unwarned batches in the window (total points, earliest expiry date), localised by `preferred_locale`.
23. **Q23 (MED) Execution order vs Phase 9.** **Default:** execute after Phase 9 is committed; if run earlier, expect two re-pins of the permission-count tests.
24. **Q24 (MED) Voucher/points on reservations whose guest is merged or null.** **Default:** guest-null reservations earn nothing; no guest-merge feature exists.
25. **Q25 (LOW) Rate-limit on redeem/apply.** **Default:** `throttle:30,1` on redeem and on `POST /reservations` is unchanged; voucher-code attempts are limited by the `422` path plus the same throttle on `/loyalty/preview`.

## Validation Architecture

Nyquist validation is enabled (no `workflow.nyquist_validation: false` found; assume enabled). All commands run from `D:\TupCode\Carlton\backend`. Test classes share the `Loyalty` prefix so `--filter=Loyalty` selects the phase suite. The research session itself did not run any test or migration (concurrent Phase 9 execution).

### Test Framework
| Property | Value |
|----------|-------|
| Framework | PHPUnit ^12.5.12 on Laravel 13, SQLite in-memory (`phpunit.xml:31-32`), `QUEUE_CONNECTION=sync` |
| Config file | `backend/phpunit.xml` |
| Quick run command | `php artisan test --filter=Loyalty` |
| Full suite command | `php artisan test` |
| Style gate | `composer run pint` |
| Auth in tests | real Sanctum bearer tokens (`$user->createToken('t')->plainTextToken`, `$guest->createToken('guest')->plainTextToken`), `RolesAndPermissionsSeeder` seeded in `setUp`, never `actingAs` (Phase 8 standing rule) |

### Phase Requirements -> Test Map
| Req group | Behaviours to sample | Test file(s) | Command |
|-----------|---------------------|--------------|---------|
| LOY-01/02 settings | get/update, validation (rate bounds, percent 0-100, ints), inactive when unset, activity log row, 401/403/422 | `tests/Feature/Loyalty/LoyaltySettingsTest.php`, `tests/Unit/Loyalty/LoyaltyProgramTest.php` | `php artisan test --filter=LoyaltySettings` |
| LOY-03/04 earn | earn on each of the 3 settle sites; per-bucket split; half-up edges; credit reduces bucket; zero-balance settle earns; inactive/no-guest no-op; second settle throws and earns once; unique-index backstop; lock asserted with `RecordsRowLocks` | `tests/Feature/Loyalty/LoyaltyEarnOnSettleTest.php`, `tests/Unit/Loyalty/EarnLoyaltyPointsActionTest.php`, `tests/Unit/Support/LoyaltyMathTest.php` | `php artisan test --filter=LoyaltyEarn` |
| LOY-05 manual adjust | award/deduct, reason required (422), idempotent replay and conflict, over-deduct 422, 401/403, ledger + batch written, audited | `tests/Feature/Loyalty/LoyaltyAdjustTest.php`, `tests/Unit/Loyalty/AdjustLoyaltyPointsActionTest.php` | `php artisan test --filter=LoyaltyAdjust` |
| LOY-06/07 balance and ledger | available excludes expired-but-unswept; expiring-soon window; pagination; filters; own-data only (guest A cannot read B); staff view; no N+1 (query count) | `tests/Feature/Loyalty/LoyaltyAccountTest.php`, `tests/Feature/Loyalty/LoyaltyStaffGuestViewTest.php` | `php artisan test --filter=LoyaltyAccount` |
| LOY-08/09 expiry | `expires_at` hotel-local end of day, Jan 31 + 1 month, DST zone (`config(['hotel.timezone' => 'Europe/London'])`), boundary at -1s/0/+1s, job idempotent on rerun, writes `expire` entry and allocation, vouchers swept, schedule registered (`expression`, `withoutOverlapping`) | `tests/Feature/Loyalty/LoyaltyExpiryTest.php`, `tests/Unit/Loyalty/ExpireLoyaltyBatchesActionTest.php` | `php artisan test --filter=LoyaltyExpiry` |
| LOY-10 warning | notified once per batch, aggregated per guest, localised AR/EN, push via `FakeFirebaseService`, no token still records notification, failure rolls back marker, rerun sends nothing | `tests/Feature/Loyalty/LoyaltyExpiryWarningTest.php` | `php artisan test --filter=LoyaltyExpiryWarning` |
| LOY-11/12 rewards | CRUD, AR/EN maps, validation per type (`discount_usd` required for voucher type), recycle bin trashed/restore/force, public list excludes inactive/trashed, 401/403/422 | `tests/Feature/Loyalty/LoyaltyRewardTest.php`; re-pin `tests/Feature/Cms/RecycleBinRetentionTest.php` | `php artisan test --filter=LoyaltyReward` |
| LOY-13/14 redeem and vouchers | FIFO across 3 batches with allocations, insufficient 422, idempotent replay 200 vs conflict, concurrent same key (lock asserted), voucher code format and uniqueness, own vouchers only, trashed/inactive reward refused, throttle | `tests/Feature/Loyalty/LoyaltyRedeemTest.php`, `tests/Unit/Loyalty/RedeemRewardActionTest.php`, `tests/Unit/Loyalty/LoyaltyLedgerFifoTest.php` | `php artisan test --filter=LoyaltyRedeem` |
| LOY-15/16 preview and booking | preview == booking total (drift test), min points, cap edge (cent rounding), balance check, voucher types (discount, free night capped at total, upgrade flag), one voucher, conflict, expired/used/foreign voucher 422, failed availability rolls back points, `Idempotency-Key` required only with loyalty fields, replay returns same reservation, `QuoteReservationAction` untouched | `tests/Feature/Loyalty/LoyaltyPreviewTest.php`, `tests/Feature/Loyalty/LoyaltyBookingTest.php`, `tests/Unit/Loyalty/ApplyLoyaltyToReservationActionTest.php` | `php artisan test --filter=LoyaltyBooking` |
| LOY-17/18 reversals | cancel refunds into active batch; into fresh batch when original expired; voucher restored (and grace branch); clawback partial after spend with shortfall; cancel twice idempotent; hold-expiry cannot hold loyalty; folio seam action unit-tested; ledger `reverses_entry_id` unique | `tests/Feature/Loyalty/LoyaltyCancelReversalTest.php`, `tests/Unit/Loyalty/ReverseLoyaltyForFolioActionTest.php` | `php artisan test --filter=LoyaltyReversal` |
| LOY-19 reports | sums per type in window (hotel-local day bounds), outstanding, empty period, period validation, 401/403/422, query count fixed | `tests/Feature/Loyalty/LoyaltyReportTest.php` | `php artisan test --filter=LoyaltyReport` |
| LOY-20 permissions | seeded, grouped (`loyalty` group = view/manage/adjust), enforced by routes, 403 matrix for every preset and a `reports.view`-only user | `tests/Feature/Loyalty/LoyaltyPermissionsTest.php`; re-pin `SeederTest`, `PermissionsGroupedTest`; `CmsAccessControlTest` must stay green | `php artisan test --filter=LoyaltyPermissions` |
| LOY-21 i18n/docs | five locale files have identical loyalty keys; every `error_code` has `custom.errors.<code>` | covered by existing `LocaleFoundationTest` / `ValidationMessageLocalizationTest` plus `tests/Feature/Loyalty/LoyaltyLocaleTest.php` | `php artisan test --filter=Locale` |
| Schema | migrations up/down on a scratch DB (never `database/database.sqlite`), unique/FK constraints (`(folio_id, source)`, `reverses_entry_id`, `reservation_id`) | `tests/Feature/Database/LoyaltySchemaTest.php` [analog `NightAuditSchemaTest.php`] | `php artisan test --filter=LoyaltySchema` |

### Tricky behaviours that need dedicated tests (must not be folded into happy paths)
1. FIFO consumption spanning three batches, each partially drained, plus allocation rows summing to the spend.
2. Half-up edges: spend*rate = x.5, x.4999999, 0.0049; discount cent boundary 0.005; huge spend; rate with 4 decimals.
3. Expiry boundary at `expires_at` +/- 1 s and a month-end earn date; DST timezone.
4. Idempotent redeem: same key twice (200 replay), same key different reward (409/422 conflict), concurrent attempt (lock asserted, MySQL-only true concurrency noted).
5. Concurrent settle: second attempt throws `folio_settled`, exactly one earn row per bucket; unique index violation path handled.
6. Reversal after partial spend: shortfall recorded, balance never negative.
7. Voucher restore on cancel including already-expired voucher.
8. Preview versus booking equality for the same inputs.
9. Cancel with no loyalty application is a no-op that does not add queries beyond a fixed budget.
10. Hold release (bulk update) leaves loyalty tables untouched.

### Sampling Rate
- **Per task commit:** `php artisan test --filter=Loyalty<Area>` for the area touched (table above).
- **Per wave merge:** `php artisan test --filter=Loyalty` plus the regression files whose behaviour the modified actions affect: `--filter="FolioPaymentTest|FolioTest|CheckOutTest|ReservationTest|GuestBookingTest|ConcurrencyTest|RoomAssignmentAtBookingTest|SeederTest|PermissionsGroupedTest|CmsAccessControlTest|RecycleBinRetentionTest|LocaleFoundationTest|ValidationMessageLocalizationTest"`.
- **Phase gate:** full `php artisan test` green and `composer run pint` clean before the commit; baseline test count must be re-measured at execution (Phase 9 notes disagreeing counts 2165/2167).

### Wave 0 Gaps
- [ ] `tests/Feature/Loyalty/` and `tests/Unit/Loyalty/` directories and a shared `tests/Concerns/BuildsLoyaltyFixtures.php` (settled folio with items, guest with batches) - no equivalent exists
- [ ] Factories for the seven models (no loyalty factories exist)
- [ ] Re-pin plan for `SeederTest`, `PermissionsGroupedTest`, `RecycleBinRetentionTest::SOFT_DELETABLE`
- [ ] No framework install needed

## Security Domain

`security_enforcement` is not disabled in `.planning/config.json` as far as this research could determine (file not opened; treat as enabled).

### Applicable ASVS Categories

| ASVS Category | Applies | Standard Control |
|---------------|---------|-----------------|
| V2 Authentication | no new flows | existing Sanctum guards; redeem is behind `auth:guests` |
| V3 Session Management | no | unchanged |
| V4 Access Control | yes | route `permission:loyalty.*` for staff; guest ownership via queries scoped to `auth('guests')->id()` (404 on foreign rows); voucher apply checks `guest_id` |
| V5 Input Validation | yes | FormRequests (`BaseRequest`): integer bounds on points, decimal bounds on rates/percent, `TranslatableRules` for AR/EN, strict dates for reports |
| V6 Cryptography | limited | voucher code from `random_int` over a 32-char alphabet, unique index, ownership bound (guessing another guest's code is useless); no hand-rolled crypto |
| V8 Data protection | yes | resources expose UUIDs only; ledger `performed_by`/`reason` hidden from guests by default (Q16) |
| V11 Business logic | yes | idempotency keys, row locks, unique indexes, cap and minimum enforced server side, no client-trusted amounts (discount is computed, not accepted) |
| V13 API | yes | throttle on redeem/preview, error codes stable, additive changes only to `POST /reservations` |

### Known Threat Patterns

| Pattern | STRIDE | Standard Mitigation |
|---------|--------|---------------------|
| Replay / double-submit of redeem or booking-with-points | Tampering | `Idempotency-Key` + unique ledger key + row locks |
| Concurrent spend of the same points (race) | Tampering | guest row lock first, batch `lockForUpdate`, unsigned columns |
| Client-supplied discount amount | Tampering | server computes discount from points/voucher; request never carries USD |
| Voucher code enumeration / stealing | Information disclosure | guest-scoped lookup, uniform 422 for invalid/foreign/expired/used, throttle |
| Staff abuse of manual adjust | Elevation / Repudiation | separate `loyalty.adjust`, mandatory reason, immutable ledger with `performed_by`, activity log |
| Earn farming via post-settlement edits | Tampering | settled folios are immutable (`folio_settled` guards); clawback on cancel |
| Rate/cap misconfiguration | Tampering | validation bounds on settings; unset cap disables redeem; every change in activity log |
| Information leak via public settings | Information disclosure | settings not stored in `site_settings`/`publicMap` |

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| Stored point balance updated in place | Derived balance from lots plus immutable ledger | standard practice for expiring points | auditability; expiry per lot; reversal keyed to originals |
| Promo-style counters that never decrement on cancel (`promo_codes.used_count`) | status + application row reversed on cancel | this phase | vouchers and points are restorable |
| Float discount math in quotes (`QuoteReservationAction`) | bcmath strings for anything new | Phase 5 (`FolioLedger`) | only the quote boundary converts |

**Deprecated/outdated:** none in scope. `/api/v1` in CLAUDE.md is outdated relative to the code (no prefix).

## Assumptions Log

| # | Claim | Section | Risk if Wrong |
|---|-------|---------|---------------|
| A1 | bcmath add-then-truncate yields half-up for non-negative inputs (`bcadd(bcadd(x,'0.5',6),'0',0)`), and `bcadd(...,'0',2)` after adding `0.005` rounds cents half-up | Pattern 2 | wrong rounding on edges; mitigated by the mandatory edge-case unit tests before use (code was not executed in this read-only session) |
| A2 | Carbon 3 provides `addMonthsNoOverflow` on `CarbonImmutable` | Pattern 4 | expiry date wrong at month ends; verify in the installed Carbon, else clamp manually |
| A3 | `Schedule::command(...)->timezone(...)` is accepted in `routes/console.php` on Laravel 13 | Scheduler | cadence off by the UTC offset; assert `expression`/timezone in the schedule test |
| A4 | InnoDB takes a shared lock on the parent `guests` row for an FK insert | Pitfall 10 | only affects wait behaviour, not correctness |
| A5 | `.planning/config.json` has `nyquist_validation` enabled and `security_enforcement` not false | Validation / Security | missing section if wrong; the user prompt states Nyquist is enabled |
| A6 | The mobile mock fields (`tierLabel`, `staysCount`, kinds) are only a design hint, not a frozen contract | Q16 | contract mismatch with Flutter; mitigated by documenting and notifying |
| A7 | Production runs `schedule:run` every minute and uses a lock-capable cache | Scheduler | `withoutOverlapping` ineffective; same assumption as the three existing commands |

## Environment Availability

| Dependency | Required By | Available | Version | Fallback |
|------------|------------|-----------|---------|----------|
| PHP + bcmath | all money math | yes | PHP 8.4.1 locally (composer `^8.3`), `bcmath` loaded | - |
| SQLite (tests) | test suite | yes | `pdo_sqlite` loaded, in-memory per `phpunit.xml` | - |
| MySQL 8 (prod) | production | driver `pdo_mysql` loaded; no local server/client (`mysql` not found) | per project stack | MySQL-only concurrency checks documented as manual |
| Firebase credentials | push | not needed in tests (`FakeFirebaseService`) | - | `NullFirebaseService` when unconfigured |
| Scheduler cron | two new commands | assumed already running for the 3 existing commands | - | run commands manually |

**Missing dependencies with no fallback:** none.
**Missing dependencies with fallback:** MySQL concurrency proof (manual).

## Open Risks for the Planner (not questions)

- The working tree is moving under Phase 9: re-read counts and shared files at execution; use unique, additive edits (append sections, do not reorder lang keys).
- Phase 9's `MoneyAggregate` and report classes may be committed or reshaped before Phase 10 runs; this plan deliberately does not use them.
- The hold-expiry sweep uses a bulk `update()` and fires no model events; keep loyalty out of that path (Integration Points).
- `CancelReservationAction` currently returns `['data' => null, 'code' => 204]`; keep that contract when adding the reversal.

## Sources

### Primary (HIGH confidence, read from the tree this session)
- `backend/app/Actions/Folio/SettleFolioAction.php`, `RecordFolioPaymentAction.php`, `PostFolioItemAction.php`, `GenerateFolioAction.php`, `ApproveFolioAction.php`
- `backend/app/Actions/Booking/CreateReservationAction.php`, `QuoteReservationAction.php`, `CancelReservationAction.php`, `CheckOutReservationAction.php`, `ReleaseExpiredHoldsAction.php`
- `backend/app/Services/Payment/PaymentService.php`, `Booking/ReservationService.php`, `Booking/PricingService.php`, `Notification/NotificationService.php`, `Cms/SiteSettingService.php`
- `backend/app/Models/Folio.php`, `FolioItem.php`, `Payment.php`, `Refund.php`, `Reservation.php`, `PromoCode.php`, `SiteSetting.php`, `GuestNotification.php`
- `backend/app/Support/FolioLedger.php`, `HotelClock.php`, `IdempotentWrite.php`, `MoneyAggregate.php`, `RecycleBin.php`
- `backend/routes/api.php` (1-140, 219-293, 500-880), `routes/console.php`, `bootstrap/app.php`, `app/Providers/AppServiceProvider.php`, `database/seeders/RolesAndPermissionsSeeder.php`, `lang/en/custom.php`
- Tests: `FolioPaymentTest.php`, `NotificationTriggersTest.php`, `RecycleBinRetentionTest.php`, `CmsAccessControlTest.php`, `PermissionsGroupedTest.php`, `DigitalKeyLifecycleTest.php`, `tests/TestCase.php`
- `.planning/phases/09-night-audit-reports/` (09-CONTEXT D-15..D-25, 09-PATTERNS, 09-RESEARCH, 09-01/02/03-SUMMARY), `.planning/STATE.md`, `.planning/ROADMAP.md`
- `mobile/lib/models/loyalty.dart`, `mobile/lib/controllers/account/loyalty_controller.dart` (client mock)
- Project skills: `backend/.claude/skills/tupcode-laravel-backend/SKILL.md`, `.claude/skills/{laravel-conventions,module-slice,test-discipline}/SKILL.md`

### Secondary (MEDIUM)
- Design reasoning for lot-based expiring-points ledgers (general industry practice, no external source consulted; no web research was needed because no external library is introduced)

### Tertiary (LOW)
- none relied upon

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH, no new packages, all verified in `composer.json`
- Integration points and Phase 9 ruling: HIGH, read from the live tree (with the moving-tree caveat)
- Architecture and schema: MEDIUM, design validated against conventions but not executed
- Pitfalls: HIGH for those grounded in existing code (settle sites, cancel check, promo counter), MEDIUM for MySQL-specific ones

**Research date:** 2026-10-04
**Valid until:** 2026-10-11 for tree-state claims (Phase 9 in flight); 30 days for the design
