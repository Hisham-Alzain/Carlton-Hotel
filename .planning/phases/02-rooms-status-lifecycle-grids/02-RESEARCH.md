# Phase 2: Rooms — Status Lifecycle & Grids - Research

**Researched:** 2026-09-25
**Domain:** Laravel 13 backend — DB column-type migration, status+history pattern, domain exceptions, permission-gated bounded reads (board + two grids)
**Confidence:** HIGH (every claim below is grounded in this repository's own vendor source, migrations, models, seeders, and Phase 1 precedent — not external docs, since this phase touches zero new libraries)

## Summary

Phase 2 adds no new package to `composer.json`. Everything it needs — native column-type migration, `spatie/laravel-permission` pipe-OR middleware, `App\Exceptions\DomainException`, `expectsDatabaseQueryCount()` — already ships in the installed Laravel 13.19.0 / PHPUnit 12.5.12 stack, confirmed by reading `vendor/` directly rather than assuming from training data. The two things CONTEXT.md's canonical-refs list gets slightly wrong (both corrected below with evidence) are: (1) there is no `staffToken()` method on `tests/TestCase.php` — it is a private per-test-file helper pattern that every new test file must copy, and (2) `AdminRoomController` is not a class name, it is the `use ... as AdminRoomController` import alias for `App\Http\Controllers\Admin\RoomController` — both matter for the planner's exact file/method references.

The highest-risk technical finding is that `QuoteReservationAction`'s pricing-rule loop has **no explicit ordering** — it iterates whatever order the `PricingRule::where(...)->get()` query happens to return (no `orderBy`), applying each rule to a running total as it goes. D-10's phrase "same order as `QuoteReservationAction` (percentage modifiers first, then fixed)" describes the *typical* effect when a table happens to have percentage rules inserted before flat rules, not an enforced ordering. `PricingService::nightlyRate()` must replicate this literally (no re-sort) to stay in lock-step with the quote path, and the ambiguity when two overlapping rules exist should be flagged, not silently "fixed," or the grid and the quote will disagree — which is exactly the kind of contract drift Pitfall 10 warns about.

**Primary recommendation:** Build the migration as two native, dbal-free steps in one file (`change()` the column, then a plain `UPDATE ... WHERE status = 'occupied'`); build `room_status_history` with the same FK idiom as `reservation_rooms`/`check_in_approvals`; reuse `CheckAvailabilityAction`'s exact overlap predicate (`whereDate('check_in','<',...)->whereDate('check_out','>',...)->holdingInventory()`) for the grid's single windowed query; and copy `OperationsQueueController`'s `BaseController`-direct pattern (not `BaseCRUDController`) for the new `FrontDeskController`.

## Architectural Responsibility Map

| Capability | Primary Tier | Secondary Tier | Rationale |
|------------|-------------|----------------|-----------|
| Room housekeeping status transition (`PATCH /cms/rooms/{room}/status`) | API / Backend | Database / Storage | Single-writer business rule (`UpdateRoomStatusAction`) + the `room_status_history` row it writes; no client-side state |
| Room status history audit trail | Database / Storage | API / Backend | Append-only table; read only through the board's denormalized `status_changed_at`/`status_changed_by` columns, never joined live by clients |
| Occupancy derivation (`occupied`/`arriving_today`/`departing_today`/`stayover`) | API / Backend | — | Computed at read time from `reservation_rooms`/`reservation` state per D-02; never persisted, so it has no storage tier at all |
| Availability grid computation | API / Backend | Database / Storage | Reuses `CheckAvailabilityAction`'s overlap predicate; bounded SQL aggregation, no caching/CDN tier needed at this data volume |
| Rate grid computation | API / Backend | Database / Storage | `PricingService::nightlyRate()` reads `PricingRule` rows for the window; pure computation, no separate service |
| Permission gating (`rooms.status`, `reservations.view`) | API / Backend | — | Route middleware (`permission:`), enforced entirely server-side; dashboard/app only branch on the resulting 403 |
| Docs/tree/Postman sync (D-14) | N/A (documentation) | — | Not a runtime tier; a contract-parity gate consumed by the dashboard/Flutter teams, not by any running service |

No capability in this phase touches a Browser/Client, Frontend-SSR, or CDN/Static tier — the dashboard and Flutter app are separate repositories out of scope (per `PROJECT.md`/`REQUIREMENTS.md` "Frontend changes" exclusion), so every capability's primary tier is correctly API/Backend or Database/Storage. This rules out the most common misassignment risk (business logic leaking into a client) by construction.

## Standard Stack

This phase installs no new package. Everything below is already in `backend/composer.lock`, version-pinned and verified by reading the lock file and `vendor/` directly (not training-data recall).

### Core (already installed — no `composer require`)
| Library | Installed version | Purpose in this phase | Source |
|---------|--------------------|------------------------|--------|
| laravel/framework | 13.19.0 | Native `Schema::table()->change()` for the enum→string migration; `expectsDatabaseQueryCount()` test assertion | [VERIFIED: `backend/composer.lock` line 2319; `vendor/laravel/framework/...`] |
| spatie/laravel-permission | ^8.3 (installed) | `permission:rooms.status`, `permission:rooms.status\|reservations.view` middleware; `PermissionMiddleware` pipe-OR syntax | [VERIFIED: `backend/composer.json`; pipe-OR usage already live at `routes/api.php:237` `permission:cms.restore\|cms.purge`] |
| spatie/laravel-activitylog | ^5.0 (installed) | `LogsActivity` trait — **not** used on `RoomStatusHistory` (history rows are themselves the audit log; adding `LogsActivity` would double-log) | [VERIFIED: composer.json] |
| phpunit/phpunit | ^12.5.12 (installed) | `expectsDatabaseQueryCount($expected, $connection=null)` for D-09/D-13 bounded-query tests | [VERIFIED: `vendor/laravel/framework/src/Illuminate/Foundation/Testing/Concerns/InteractsWithDatabase.php:266`] |

### Explicitly NOT needed
| Package | Why it looks needed | Why it isn't |
|---------|----------------------|---------------|
| doctrine/dbal | Historically required for `Schema::table()->change()` on enum columns | Not in `composer.json`, not in `composer.lock`'s installed set, not in `vendor/doctrine/` (only `inflector`/`lexer` are present — dev-requires of unrelated packages). Laravel 13's `MySqlGrammar::compileChange()` builds a native `ALTER TABLE ... MODIFY` string; `SQLiteGrammar::compileChange()` is a no-op stub because SQLite changes are handled by a full table-rebuild path elsewhere in the grammar (`create temp table → copy → drop → rename`). Neither path touches Doctrine. [VERIFIED: `vendor/laravel/framework/src/Illuminate/Database/Schema/Grammars/{MySqlGrammar,SQLiteGrammar}.php`] |

### Alternatives Considered
| Instead of | Could use | Tradeoff |
|------------|-----------|----------|
| Native `Schema::table()->change()` | Manual add-new-column → copy-data → drop-old → rename-column | More migration boilerplate for no benefit now that native `change()` is dbal-free on both drivers in this Laravel version; only worth it if a future Laravel drops the native rebuild path |
| `PermissionMiddleware`'s built-in pipe-OR | Two separate route registrations, each gated by one permission, both pointing at the same controller method | Doubles the route table and the 403 test matrix for zero behavioral gain; pipe-OR is already the established idiom in this codebase |

**Installation:** none — no `composer.json` changes this phase.

## Package Legitimacy Audit

**Not applicable.** This phase installs zero new Composer packages. All functionality is built from Laravel 13.19.0 core, `spatie/laravel-permission` (already installed and already used elsewhere in this exact codebase), and first-party `App\*` classes. Skip the legitimacy gate; do not add a `checkpoint:human-verify` task for package installation.

## Architecture Patterns

### System Architecture Diagram

```
PATCH /cms/rooms/{room}/status                    GET /front-desk/room-board?date&status&floor&room_type
        │                                                    │
        ▼                                                    ▼
AdminRoomController::updateStatus            FrontDeskController::board
        │ (permission:rooms.status)                          │ (permission:rooms.status|reservations.view)
        ▼                                                    ▼
UpdateRoomStatusRequest (body validation)         FrontDeskService::roomBoard()
        │                                                    │
        ▼                                                    ├─► Room::where(is_active)+filters      (query 1)
UpdateRoomStatusAction::handle()                              ├─► reservation_rooms→reservation→guest  (query 2, eager, keyed by room_id)
        │                                                     └─► status_changed_by users              (via denormalized cols, no extra query)
        ├─► transition table lookup (from,to) ──► reject? ──► throw RoomStatusTransitionException (422)
        │                                                    ▼
        ▼                                          assemble board rows in PHP (occupied/arriving/departing/stayover derived, D-02)
   DB::transaction {
     Room::status = to                                       │
     Room::status_changed_at/by = now()/actor                ▼
     RoomStatusHistory::create(from,to,changed_by,reason) }   JSON envelope { data: { date, items[] } }
        │
        ▼
   JSON envelope { data: RoomResource-like row }


GET /front-desk/availability-grid?from&days             GET /front-desk/rates-grid?from&days
        │                                                          │
        ▼                                                          ▼
FrontDeskController::availabilityGrid                    FrontDeskController::ratesGrid
        │ (permission:reservations.view)                            │ (permission:reservations.view)
        ▼                                                          ▼
FrontDeskService::availabilityGrid()                     FrontDeskService::ratesGrid()  (or RatesGridService)
        ├─► RoomType+rooms-count (active)         (q1)     ├─► RoomType (active)                    (q1)
        ├─► ReservationRoom rows overlapping        (q2)   └─► PricingRule rows overlapping window   (q2)
        │     the WHOLE [from, from+days) window,                    │
        │     holdingInventory(), expanded per-night in PHP           ▼
        ├─► Room::where(status=maintenance)          (q3)   PricingService::nightlyRate(type, date, rules)
        │     grouped by room_type_id                        per (room_type × date) cell, in PHP only
        ▼                                                          ▼
assemble cells in PHP: free = total − booked            assemble cells in PHP: rate_usd, rule_scope
        ▼                                                          ▼
JSON envelope { data: { from, days, room_types[] } }     JSON envelope { data: { from, days, room_types[] } }
```

### Recommended Project Structure
```
app/
├── Actions/Cms/
│   └── UpdateRoomStatusAction.php        # transition table + DB::transaction (status, history, denorm cols)
├── Exceptions/
│   └── RoomStatusTransitionException.php # extends DomainException, 422, error_code room_status_transition_invalid
├── Http/Controllers/Admin/
│   ├── RoomController.php                # add updateStatus() — file name is RoomController, aliased AdminRoomController in routes
│   └── FrontDeskController.php           # new — board, availabilityGrid, ratesGrid (extends BaseController like OperationsQueueController)
├── Http/Requests/Cms/
│   └── UpdateRoomStatusRequest.php       # { status, reason? }
├── Models/
│   └── RoomStatusHistory.php             # new — room(), changedBy() belongsTo
├── Services/Cms/
│   └── RoomService.php                   # unchanged CRUD; status verb bypasses it entirely
├── Services/Booking/
│   └── PricingService.php                # add nightlyRate(RoomType, CarbonInterface, Collection $rules): array
├── Services/Operations/
│   └── FrontDeskService.php              # new — board/grid assembly, all bounded queries live here
database/migrations/
├── 2026_09_25_HHMMSS_change_rooms_status_to_string.php
└── 2026_09_25_HHMMSS_create_room_status_history_table.php  # + add status_changed_at/status_changed_by to rooms
```

### Pattern 1: Native dbal-free enum→string column change (D-01)
**What:** `Schema::table()` with `change()` on a MySQL `ENUM` column, running unmodified against SQLite `:memory:` in tests.
**When to use:** Any future "loosen an enum to a string" migration in this codebase — this phase sets the precedent.
**Example:**
```php
// Source: vendor/laravel/framework MySqlGrammar::compileChange (native ALTER ... MODIFY)
// and SQLiteGrammar's table-rebuild path (compileChange() is a documented no-op —
// "// Handled on table alteration..." — the rebuild happens elsewhere in the grammar)
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rooms', function (Blueprint $table) {
            $table->string('status', 20)->default('available')->index()->change();
        });

        // Idempotent by construction: the second run matches zero rows.
        DB::table('rooms')->where('status', 'occupied')->update(['status' => 'available']);
    }

    public function down(): void
    {
        Schema::table('rooms', function (Blueprint $table) {
            $table->enum('status', ['available', 'occupied', 'maintenance'])
                ->default('available')->index()->change();
        });
    }
};
```
Do **not** add `doctrine/dbal` to `composer.json` for this — it is not installed and not required by either grammar path in Laravel 13.19.0. `RoomStatus` enum drops `OCCUPIED`, keeps `AVAILABLE`/`MAINTENANCE`, adds `DIRTY` (`app/Enums/RoomStatus.php`, uses the shared `HasValues` trait — no change needed there).

### Pattern 2: Status + history table with denormalized "current" columns (D-03)
**What:** `room_status_history` is the append-only ledger; `rooms.status_changed_at`/`status_changed_by` are a denormalized read-cache the board reads without a join.
**When to use:** Phase 6 (housekeeping tasks) and Phase 7 (tickets) are explicitly meant to copy this pattern (CONTEXT.md `<specifics>`).
**Example:**
```php
// Source: FK idiom copied verbatim from backend/database/migrations/2026_07_10_100004_create_reservation_rooms_table.php
// and .../2026_07_12_100009_create_check_in_approvals_table.php (approved_by nullOnDelete)
Schema::create('room_status_history', function (Blueprint $table) {
    $table->id();
    $table->foreignId('room_id')->constrained()->cascadeOnDelete();
    $table->string('from_status', 20)->nullable();
    $table->string('to_status', 20);
    $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
    $table->string('reason', 255)->nullable();
    $table->timestamp('created_at')->useCurrent();
    $table->index(['room_id', 'created_at']);
});

Schema::table('rooms', function (Blueprint $table) {
    $table->timestamp('status_changed_at')->nullable();
    $table->foreignId('status_changed_by')->nullable()->constrained('users')->nullOnDelete();
});
```
Use `$table->timestamp('created_at')->useCurrent()` rather than `$table->timestamps()` — the row is immutable (no `updated_at` concept for a history entry); this is a new idiom for this codebase (no prior table omits `updated_at` on purpose), so document the choice inline in the migration for the next reader.

### Pattern 3: Domain exception for a rejected transition (D-05)
**What:** `RoomStatusTransitionException` follows `ReservationStateException`'s exact shape, adding a `context` payload.
**Example:**
```php
// Source: backend/app/Exceptions/ReservationStateException.php (sibling pattern)
// and backend/app/Exceptions/DomainException.php (abstract base, context() accessor)
class RoomStatusTransitionException extends DomainException
{
    public function errorCode(): string { return 'room_status_transition_invalid'; }
    public function statusCode(): int   { return 422; }
}

// Thrown from UpdateRoomStatusAction:
throw new RoomStatusTransitionException(
    __('custom.errors.room_status_transition_invalid'),
    ['from' => $from->value, 'to' => $to->value, 'allowed' => array_map(fn ($s) => $s->value, $allowedTargets)]
);
```
Envelope shape confirmed from `bootstrap/app.php`'s `withExceptions()` closure: `{success:false, message, error_code, context, request_id}` at the exception's own `statusCode()`. Add `'room_status_transition_invalid' => '...'` to the `errors` array in **all five** `lang/{ar,en,es,fr,tr}/custom.php` files — confirmed the five locale directories exist (`backend/lang/{ar,en,es,fr,tr}`).

### Pattern 4: Transition table as a plain array/match, not a DB table
**What:** D-04's five allowed transitions are a fixed, small rule set — encode as a `match`/array constant, not a database table (this codebase's own "status + history over boolean flags" rule is about the *status column*, not about needing a transitions table for five hardcoded edges).
**Example:**
```php
private const ALLOWED = [
    'available'   => ['dirty', 'maintenance'],
    'dirty'       => ['available', 'maintenance'],
    'maintenance' => ['dirty'], // never back to available directly
];

private function assertAllowed(RoomStatus $from, RoomStatus $to): void
{
    $allowed = self::ALLOWED[$from->value] ?? [];
    if (! in_array($to->value, $allowed, true)) {
        throw new RoomStatusTransitionException(/* ... */);
    }
}
```
Same-state (`dirty → dirty`) is naturally rejected because `dirty` is absent from its own allowed-targets list — no separate same-state check needed.

### Pattern 5: Bounded grid query — reuse `CheckAvailabilityAction`'s exact predicate
**What:** D-09 requires ≤5 queries regardless of room-type count or window length. The overlap predicate must be byte-for-byte the one `CheckAvailabilityAction::occupiedCount` uses, or the grid's `free` will disagree with `/availability` (the specific regression D-08 forbids).
**Example:**
```php
// Source: backend/app/Actions/Booking/CheckAvailabilityAction.php (private overlapping())
// reused, not reimplemented, for the grid's single windowed query
$rows = ReservationRoom::query()
    ->whereHas('reservation', function ($q) use ($windowStart, $windowEnd) {
        $q->whereDate('check_in', '<', $windowEnd)
          ->whereDate('check_out', '>', $windowStart)
          ->holdingInventory(); // Reservation::scopeHoldingInventory — same hold-expiry semantics
    })
    ->select('room_type_id', 'reservation_id')
    ->with('reservation:id,check_in,check_out')
    ->get(); // ONE query for the whole [from, from+days) window

// Expand per night in PHP — never re-query per date or per room type:
foreach ($rows as $row) {
    $stayStart = max($row->reservation->check_in, $windowStart);
    $stayEnd   = min($row->reservation->check_out, $windowEnd);
    for ($d = $stayStart->copy(); $d->lt($stayEnd); $d->addDay()) {
        $booked[$row->room_type_id][$d->toDateString()] = ($booked[$row->room_type_id][$d->toDateString()] ?? 0) + 1;
    }
}
```
Total query budget: room types + active-room totals (1, via `withCount`) + the overlap query above (1) + maintenance counts grouped by room_type_id (1) = **3 queries**, inside the ≤5 budget with headroom.

### Pattern 6: Non-CRUD controller precedent (`FrontDeskController`)
**What:** `OperationsQueueController` is the exact precedent CONTEXT.md points to — a controller on plain `App\Base\BaseController` (not `BaseCRUDController`), injecting one service, using `respondFromService()`/`success()` directly because its verbs (`board`, `availabilityGrid`, `ratesGrid`) are not the five CRUD verbs.
**Example:**
```php
// Source: backend/app/Http/Controllers/Admin/OperationsQueueController.php (structural twin)
class FrontDeskController extends BaseController
{
    public function __construct(private readonly FrontDeskService $service) {}

    public function board(Request $request): JsonResponse
    {
        return $this->respondFromService($this->service->board($request->query()), request: $request);
    }
    // availabilityGrid(), ratesGrid() follow the same shape
}
```

### Anti-Patterns to Avoid
- **Reading `rooms.status` as an availability gate:** Pitfall 1 (PITFALLS.md) — `CheckAvailabilityAction`'s own comment already states status is independent of availability; do not let the grid or board logic add a `where('status', ...)` into any availability-affecting query.
- **Per-cell or per-room queries in the grids/board:** Pitfall 8 — a `foreach ($roomTypes as $t) { $t->rooms()->... }` loop silently reintroduces N+1 and blows the D-09/D-13 query budget the moment a second room type exists.
- **Re-sorting `PricingRule` rows by `modifier_type` inside `nightlyRate()`:** would silently diverge from `QuoteReservationAction`'s actual (unordered) behavior — see Pitfall/Common-Pitfall section below.
- **Giving `RoomStatusHistory` the `LogsActivity` trait:** the history row already *is* the audit record; stacking `LogsActivity` on top double-logs the same fact in `activity_log` for no reader.

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| "Is this reservation currently holding inventory" | A fresh status-list `whereIn` per call site | `Reservation::scopeHoldingInventory()` | Already the single source of truth shared by availability and assignment; a second copy risks drifting out of sync with hold-expiry semantics |
| Overlap-date predicate | `whereBetween`/raw date math | `whereDate('check_in','<',$end)->whereDate('check_out','>',$start)` exactly as `CheckAvailabilityAction` does | The `whereDate()` cast strips time components — a documented fix for a real SQLite/MySQL mismatch bug (PITFALLS.md); reinventing the comparison risks reintroducing it |
| Enum→string data migration on two DB engines | Doctrine DBAL, or hand-written `ALTER TABLE` per driver | Laravel's native `Blueprint::change()` | Both grammars already do the right native thing (MySQL: `MODIFY`; SQLite: full rebuild); DBAL was only ever needed pre-Laravel-9/10 and is not installed here |
| Percentage/flat rate stacking | A new pricing engine for the grid | `PricingService::nightlyRate()` extracted from `QuoteReservationAction`'s exact loop | D-10 explicitly requires bit-identical semantics to the quote path; a parallel implementation is a second place to keep in sync forever |

**Key insight:** every "don't hand-roll" item above already has exactly one implementation in this codebase; the entire job of Phase 2's business logic is *extracting and reusing* those single sources of truth (`CheckAvailabilityAction`, `Reservation::scopeHoldingInventory`, `QuoteReservationAction`'s loop), not writing new predicates.

## Common Pitfalls

### Pitfall 1: `QuoteReservationAction`'s rule loop has no enforced order
**What goes wrong:** `PricingService::nightlyRate()` is built to "sort percentage rules before flat rules" because that's how D-10 phrases the requirement, but the actual code (`app/Actions/Booking/QuoteReservationAction.php`) has **no `orderBy`** on its `PricingRule::where(...)->get()` query — it applies whichever rule the DB happens to return first, in a running-total loop. If two overlapping rules exist for the same room type and date, `nightlyRate()` computing a "sorted" result diverges from what `QuoteReservationAction` would actually charge for the same night, which is precisely the two-source-of-truth drift D-08 exists to prevent.
**Why it happens:** D-10's English gloss ("percentage modifiers first, then fixed") describes the loop's typical *effect* when rule insertion order happens to put percentage rules first, not a `sort()` call anywhere in the code.
**How to avoid:** `nightlyRate(RoomType $type, CarbonInterface $date, Collection $rules)` must apply `$rules` in the exact order the `Collection` argument is handed to it — no internal re-sort by `modifier_type`. The caller (`FrontDeskService`/`RatesGridService`) should load the window's rules with the *same* absence-of-`orderBy()` as `QuoteReservationAction`, so both code paths are equally order-dependent (equally "wrong" in the same way) rather than one being fixed and the other not.
**Warning signs:** A `usort()`/`->sortBy('modifier_type')` call inside `nightlyRate()`; a test that only ever seeds one rule per date (which would hide the ordering question entirely — the plan should include a test with two overlapping rules on the same date/type to make the behavior explicit either way).

### Pitfall 2: `expectsDatabaseQueryCount()` counts every query for the *rest of the test*, not just a wrapped block
**What goes wrong:** It is not a block-scoped counter — `beforeApplicationDestroyed()` asserts at test teardown, so any `assertDatabaseHas()`/factory call made *after* the assertion call also counts, silently inflating the number and producing a flaky-looking off-by-N failure.
**Why it happens:** Read directly from source (`vendor/.../InteractsWithDatabase.php:266`): it registers a `QueryExecuted` listener at call time and asserts the running total once the app is torn down — there is no scoping parameter to limit it to a callback.
**How to avoid:** Call `$this->expectsDatabaseQueryCount(N)` as the *last* statement before the single HTTP call under test, and put the query-count test in its own dedicated test method that does nothing else afterward except assert on the `$response` object (never `assertDatabaseHas` in the same method).
**Warning signs:** A query-count test that also does DB assertions after the request — those extra queries push the actual count above the asserted number even though the endpoint itself is correctly bounded.

### Pitfall 3: `staffToken()` does not exist on `Tests\TestCase`
**What goes wrong:** A new test file calls `$this->staffToken('rooms.status')` expecting a shared base-class helper (as CONTEXT.md's canonical-refs list implies) and gets an "undefined method" error.
**Why it happens:** Read `backend/tests/TestCase.php` directly — it has no `staffToken` method. The helper is a **private, per-test-class** copy-pasted pattern; confirmed present independently in six existing test files (e.g. `tests/Feature/Operations/OperationsQueueTest.php:36`) with the identical body:
```php
private function staffToken(string ...$permissions): string
{
    $user = User::factory()->create();
    $user->givePermissionTo($permissions);
    return $user->createToken('t')->plainTextToken;
}
```
**How to avoid:** Copy this exact private method into each new Phase 2 test class (`RoomStatusTransitionTest`, `RoomBoardTest`, `AvailabilityGridTest`, `RatesGridTest`) — do not attempt to call an inherited method, and do not add it to `Tests\TestCase` (that would be an unplanned, out-of-scope refactor of shared test infrastructure per every convention in this repo).

### Pitfall 4: `AdminRoomController` is an import alias, not a class name
**What goes wrong:** Searching the codebase for a class named `AdminRoomController.php` finds nothing, causing confusion about where to add `updateStatus()`.
**Why it happens:** `routes/api.php:43` reads `use App\Http\Controllers\Admin\RoomController as AdminRoomController;` — the file is `app/Http/Controllers/Admin/RoomController.php`, class `RoomController`, and every route in `routes/api.php` refers to it via the `AdminRoomController` alias (consistent with the sibling `AdminRoomTypeController`, `AdminFacilityController`, etc., which are the *same* pattern, not distinct classes).
**How to avoid:** Add `public function updateStatus(UpdateRoomStatusRequest $request, Room $room): JsonResponse` to `app/Http/Controllers/Admin/RoomController.php`; register the route via the existing `AdminRoomController::class` import already in scope at `routes/api.php`.

### Pitfall 5: `PricingRule.modifier_value` is cast `decimal:2`, which returns a string in PHP
**What goes wrong:** Arithmetic like `$daily *= 1 + ($rule->modifier_value / 100)` on an uncast decimal string works in PHP due to loose typing but is fragile and inconsistent with the existing code.
**Why it happens:** `QuoteReservationAction` already guards against this with an explicit `(float)` cast on every read (`(float) $rule->modifier_value`, `(float) $roomType->base_price_usd`).
**How to avoid:** `nightlyRate()` must cast every decimal-typed value to `float` the same way before arithmetic, and `round(..., 2)` the final `rate_usd` the same way `QuoteReservationAction` rounds `daily_rate_usd` — money re-enters as a rounded 2-decimal figure, never a raw float.

## Code Examples

### `UpdateRoomStatusAction` — transaction + history + denormalized columns
```php
// Source: pattern synthesized from DomainException base + reservation_rooms/check_in_approvals FK idiom
class UpdateRoomStatusAction
{
    private const ALLOWED = [
        'available'   => ['dirty', 'maintenance'],
        'dirty'       => ['available', 'maintenance'],
        'maintenance' => ['dirty'],
    ];

    public function handle(Room $room, RoomStatus $to, ?string $reason, User $actor): array
    {
        $from = $room->status;
        $allowed = self::ALLOWED[$from->value] ?? [];

        if (! in_array($to->value, $allowed, true)) {
            throw new RoomStatusTransitionException(
                __('custom.errors.room_status_transition_invalid'),
                ['from' => $from->value, 'to' => $to->value, 'allowed' => $allowed]
            );
        }

        DB::transaction(function () use ($room, $from, $to, $reason, $actor) {
            $room->update([
                'status'            => $to,
                'status_changed_at' => now(),
                'status_changed_by' => $actor->id,
            ]);

            RoomStatusHistory::create([
                'room_id'     => $room->id,
                'from_status' => $from->value,
                'to_status'   => $to->value,
                'changed_by'  => $actor->id,
                'reason'      => $reason,
            ]);
        });

        return ['data' => $room->fresh(), 'code' => 200];
    }
}
```

### Permission seeder diff (D-06/XCUT-01)
```php
// Source: backend/database/seeders/RolesAndPermissionsSeeder.php — additive diff
$permissions = [
    'reservations.view', 'reservations.create', 'reservations.cancel',
    'folios.view', 'folios.settle',
    'cms.view', 'cms.edit', 'cms.restore', 'cms.purge',
    'rooms.status', // new — housekeeping status transitions, independent of cms.edit
    'service_requests.view', 'service_requests.assign', 'service_requests.update',
    'tickets.view', 'tickets.assign', 'tickets.respond',
    'pricing.edit', 'reports.view', 'staff.manage',
];

$presets = [
    'reception'    => ['reservations.view', 'reservations.create', 'reservations.cancel', 'folios.view', 'folios.settle', 'rooms.status', 'service_requests.view'],
    'housekeeping' => ['service_requests.view', 'service_requests.update', 'rooms.status'],
    // ... other presets unchanged
];
```
Note: sort the final `$permissions` array entry alphabetically per module (`rooms.status` next to no other `rooms.*` entry today — it establishes a new `rooms` group) to satisfy CONVENTIONS.md's "Rows are sorted ascending by permission name" rule in the summary table (the array itself has no enforced sort order in code, only the SUMMARY.md table does).

### Route registration
```php
// Inside the existing cms permission:cms.view|cms.edit / write group — status verb needs ONLY rooms.status,
// so it must NOT sit inside the existing cms.edit-gated block; register it standalone:
Route::middleware(['auth:users', 'permission:rooms.status'])
    ->patch('/cms/rooms/{room}/status', [AdminRoomController::class, 'updateStatus']);

// New group, routes/api.php — no existing front-desk group to extend
Route::middleware('auth:users')->prefix('front-desk')->group(function () {
    Route::middleware('permission:rooms.status|reservations.view')
        ->get('/room-board', [FrontDeskController::class, 'board']);
    Route::middleware('permission:reservations.view')->group(function () {
        Route::get('/availability-grid', [FrontDeskController::class, 'availabilityGrid']);
        Route::get('/rates-grid', [FrontDeskController::class, 'ratesGrid']);
    });
});
```

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|---------------|--------|
| `doctrine/dbal` required for any `Schema::table()->change()` | Native, dbal-free `change()` for both MySQL (native `MODIFY`) and SQLite (grammar-level table rebuild) | Laravel 9–11 era (this project is on 13.19.0, long past the cutover) | No `composer require doctrine/dbal` needed for D-01; older tutorials/StackOverflow answers recommending it are stale for this Laravel version |

**Deprecated/outdated:** None specific to this phase beyond the doctrine/dbal point above — the codebase itself is current (Laravel 13.19.0, PHPUnit 12.5.12, both the latest installed versions per `composer.lock`).

## Assumptions Log

| # | Claim | Section | Risk if Wrong |
|---|-------|---------|----------------|
| A1 | `RoomStatusHistory` should NOT use `LogsActivity` (recommended in Anti-Patterns / Pattern 2) | Architecture Patterns | Low — if the planner disagrees and wants both, it's an additive change (add the trait), not a rework; flagged because CONTEXT.md doesn't state this explicitly and it's a judgment call from the "don't double-log" reasoning, not a verified project rule |
| A2 | New "Module: Front Desk" docs section should be inserted between "Module: Folios & Express Checkout" and "Module: Chat" in `API_GUIDE_DASHBOARD.md`, and a new Postman folder "18 - Front Desk (Admin)" appended after "17 - Stays (Guest)" | Code Examples / Docs mechanics (D-14) | Low — cosmetic placement only; verified the *numbering sequence* (folders 01–17 exist, confirmed by grep) but the exact insertion point for the new prose section is a style choice, not load-bearing |
| A3 | Board's `room_type` query filter (a UUID) needs its own resolution step (uuid→id) before being handed to `RoomFilter`/a plain `where()`, mirroring `RoomService::store()`'s `room_type_uuid` handling | Don't Hand-Roll / Route placement | Medium — if the planner instead extends `RoomFilter`'s `$safeParms` naively with `'room_type' => ['eq']`, it will filter on a UUID string against an integer FK column and silently return zero rows; needs an explicit test |

## Open Questions

1. **Does `FrontDeskService` own a single `board()`/`availabilityGrid()`/`ratesGrid()` service, or should the rate grid be a separate `RatesGridService`?**
   - What we know: CONTEXT.md D-11 names one service, `App\Services\Operations\FrontDeskService`, for all three reads.
   - What's unclear: whether the ~300-line-service threshold (guide's "Actions vs Service" rule) is hit once all three assembly methods land in one class.
   - Recommendation: start with one `FrontDeskService`; split only if it approaches the codebase's own ~300-line service-size convention during implementation — do not pre-split.

2. **Where does `PricingService::nightlyRate()` live if it needs a `Collection $rules` the caller already loaded — does it also need to fetch its own rules for the single-quote path, or only ever receive them?**
   - What we know: D-10 specifies the exact signature `nightlyRate(RoomType $type, CarbonInterface $date, Collection $rules): array` — rules are always passed in, never fetched internally.
   - What's unclear: whether `QuoteReservationAction` itself should be refactored to call the new `nightlyRate()` per night (D-10 explicitly says no — "`QuoteReservationAction` is not changed in this phase") — so the two code paths will temporarily NOT share the extracted method, only its *logic*, until a future phase makes the quote per-night.
   - Recommendation: extract the modifier-application loop into `nightlyRate()` as new code cloned from (not called by) `QuoteReservationAction`'s loop, exactly as D-10 specifies; add a code comment noting the duplication is deliberate and temporary (points at "Per-night pricing inside QuoteReservationAction" in Deferred Ideas).

## Environment Availability

| Dependency | Required By | Available | Version | Fallback |
|------------|--------------|-----------|---------|----------|
| doctrine/dbal | Historically, `Schema::table()->change()` | Not installed, not needed | — | Laravel 13's native grammar-level `change()` (see State of the Art) — no fallback needed, this is a non-issue |
| SQLite (`:memory:`) | Test suite (`RefreshDatabase`) | ✓ | bundled with PHP | — |
| MySQL | Production schema target for the same migration | Assumed per project constraints (not locally probed — no DB connection tool available in this research session) | — | None needed; migration is written driver-agnostically via Laravel's grammar abstraction, verified against both grammars' source directly |

**Missing dependencies with no fallback:** none.

## Validation Architecture

### Test Framework
| Property | Value |
|----------|-------|
| Framework | PHPUnit 12.5.12 |
| Config file | `backend/phpunit.xml` |
| Quick run command | `php artisan test --filter=RoomStatusTransitionTest` (swap class name per file) |
| Full suite command | `php artisan test` (must stay green — 966/966 baseline from Phase 1) |

### Phase Requirements → Test Map
| Req ID | Behavior | Test Type | Automated Command | File Exists? |
|--------|----------|-----------|---------------------|-------------|
| ROOMS-01 | Board returns rows with housekeeping status + occupancy/arrival/departure state | Feature | `php artisan test --filter=RoomBoardTest` | ❌ Wave 0 |
| ROOMS-01 | Board query bound ≤4 regardless of room count (D-13) | Feature | `php artisan test --filter=test_board_query_count` (same file, `expectsDatabaseQueryCount(4)`) | ❌ Wave 0 |
| ROOMS-02 | Allowed transitions succeed and write one history row + denorm columns | Feature | `php artisan test --filter=RoomStatusTransitionTest` | ❌ Wave 0 |
| ROOMS-02 | Disallowed transition (incl. same-state) → 422 `room_status_transition_invalid`, no history row written | Feature | `php artisan test --filter=test_disallowed_transition_returns_422` | ❌ Wave 0 |
| ROOMS-02 | Transition table itself (all 5 edges + rejections) | Unit | `php artisan test --filter=RoomTransitionTableTest` (Claude's Discretion naming) | ❌ Wave 0 |
| ROOMS-03 | Grid `free` count matches `/availability` for the same type/night | Feature (cross-endpoint agreement) | `php artisan test --filter=test_grid_agrees_with_availability_endpoint` | ❌ Wave 0 |
| ROOMS-03 | Grid query bound ≤5 regardless of days/room-type count (D-09) | Feature | `php artisan test --filter=test_availability_grid_query_count` | ❌ Wave 0 |
| ROOMS-03 | `from`/`days` validation (bad date, days out of 1..31, from >365 days in past) | Feature | `php artisan test --filter=AvailabilityGridTest` | ❌ Wave 0 |
| ROOMS-04 | Rate grid cell matches `PricingService::nightlyRate` unit result for the same type/date | Feature + Unit | `php artisan test --filter=RatesGridTest`, `php artisan test --filter=PricingServiceNightlyRateTest` | ❌ Wave 0 |
| DOCS-01/XCUT-01 | Docs/tree/Postman parity for all 4 routes + new permission | Manual verification (per Phase 1 contract) | `docs verification script` (per 01-04-PLAN.md precedent, if one exists) | N/A — contract check, not a PHPUnit test |

Every route needs the standard happy/401/403/422 quartet per `TESTING.md`'s Test-Driven checklist — the table above lists only the phase-specific behaviors beyond that baseline.

### Sampling Rate
- **Per task commit:** the specific `--filter=<ClassName>` for the file just written
- **Per wave merge:** `php artisan test tests/Feature/Rooms` (whichever directory the plan places these in) plus `php artisan test tests/Feature/Booking/AvailabilityTest.php` (regression check — Pitfall 10, availability contract must stay identical)
- **Phase gate:** full `php artisan test` green before `/gsd-verify-work`, matching the 966/966 Phase 1 baseline plus this phase's new tests

### Wave 0 Gaps
- [ ] `tests/Feature/Rooms/RoomStatusTransitionTest.php` — covers ROOMS-02
- [ ] `tests/Feature/Rooms/RoomBoardTest.php` — covers ROOMS-01
- [ ] `tests/Feature/Rooms/AvailabilityGridTest.php` — covers ROOMS-03
- [ ] `tests/Feature/Rooms/RatesGridTest.php` — covers ROOMS-04
- [ ] `tests/Unit/Booking/PricingServiceNightlyRateTest.php` (or `tests/Unit/Cms/...`) — unit coverage for `nightlyRate()`'s ordering behavior (Pitfall 1 above), including the two-overlapping-rules case
- [ ] Framework install: none — PHPUnit 12.5.12 and `RefreshDatabase` already fully configured; no Wave 0 infra gap beyond the test files themselves
- [ ] `staffToken()` private helper must be copied into each of the four new test files (Pitfall 3) — not a framework gap, but a required boilerplate addition before any 403 test in this phase can compile

## Security Domain

### Applicable ASVS Categories
| ASVS Category | Applies | Standard Control |
|----------------|---------|--------------------|
| V2 Authentication | Yes (indirect) | `auth:users` guard on every new route — Sanctum token, already project-standard |
| V3 Session Management | No | No new session/token issuance in this phase |
| V4 Access Control | Yes | `permission:rooms.status`, `permission:reservations.view`, pipe-OR combos — `spatie/laravel-permission`'s `PermissionMiddleware`, never a hand-rolled role check (per `tupcode-laravel-backend` SKILL.md "Security" section: "Access checks in route middleware... never inline a role check in a controller") |
| V5 Input Validation | Yes | `UpdateRoomStatusRequest` (status enum + reason max:255), grid query params (`from` date format + 365-day lower bound, `days` integer 1..31) via `BaseRequest`-extending FormRequests, never manual `$request->input()` checks in the controller |
| V6 Cryptography | No | No secrets/tokens/hashing introduced this phase |

### Known Threat Patterns for this stack
| Pattern | STRIDE | Standard Mitigation |
|---------|--------|-----------------------|
| Permission escalation via a coarser existing permission (e.g., reusing `cms.edit` to gate the status verb) | Elevation of Privilege | D-06 deliberately mints a dedicated `rooms.status` permission instead of reusing `cms.edit` — keep it that way; do not gate `updateStatus()` behind `cms.edit` even as a "belt and suspenders" addition, since that would silently grant status-change rights to every content editor |
| Mass-assignment of `status` via the general room-update endpoint after this phase ships | Tampering | D-01 explicitly drops `status` from `UpdateRoomRequest` — verify with a test that `PUT /cms/rooms/{room}` with a `status` field in the body is silently ignored (not rejected, not applied) so the transition endpoint remains the single writer |
| Query-string injection into the board's `room_type`/`status`/`floor` filters | Tampering / Information Disclosure | Reuse `BaseFilter`'s whitelist-and-422-on-uninterpretable-value discipline (see A3 above) rather than passing raw query values into a `where()` clause unfiltered |
| Information disclosure via `context` payload on the 422 (allowed transitions list) | Information Disclosure (low severity, accepted) | D-05 explicitly wants `{from, to, allowed}` in `context` — this is intentional UX (client can show "you can only go to X, Y"), not a leak, since transition rules are not secret |

## Sources

### Primary (HIGH confidence — read directly from this repository)
- `backend/composer.json`, `backend/composer.lock` — exact installed versions (laravel/framework 13.19.0, spatie/laravel-permission ^8.3, phpunit/phpunit ^12.5.12), confirmed no `doctrine/dbal`
- `backend/vendor/laravel/framework/src/Illuminate/Database/Schema/Grammars/{MySqlGrammar,SQLiteGrammar}.php` — `compileChange()` implementations
- `backend/vendor/laravel/framework/src/Illuminate/Foundation/Testing/Concerns/InteractsWithDatabase.php` — `expectsDatabaseQueryCount()` full source
- `backend/app/{Models,Enums,Exceptions,Actions,Services,Http}/**` — Room, RoomStatus, ReservationStatus, PricingRule, PricingScope, ModifierType, CheckAvailabilityAction, QuoteReservationAction, PricingService, RoomService, RoomFilter, CreateRoomRequest, UpdateRoomRequest, ReservationStateException, DomainException, OperationsQueueService/Controller
- `backend/database/migrations/*` — FK/index idioms (`reservation_rooms`, `check_in_approvals`, `rooms`)
- `backend/database/seeders/RolesAndPermissionsSeeder.php`, `backend/routes/api.php`, `backend/bootstrap/app.php`, `backend/tests/TestCase.php`, `backend/tests/Feature/Operations/OperationsQueueTest.php`
- `backend/docs/API_GUIDE_DASHBOARD.md`, `backend/docs/postman/carlton-api.postman_collection.json`, `docs/carlton-tree.html` — exact section/folder/node structure for D-14
- `.planning/phases/01-access-settings/01-04-SUMMARY.md`, `.planning/codebase/CONVENTIONS.md` (Phase Summary Contract), `.planning/codebase/TESTING.md`, `.planning/research/PITFALLS.md`
- `backend/.claude/skills/tupcode-laravel-backend/SKILL.md`, `.claude/skills/{laravel-conventions,module-slice,test-discipline,naive-reviewer}/SKILL.md`

### Secondary (MEDIUM confidence)
- None used this phase — no web search was needed since every technical claim was verifiable against the local codebase/vendor source.

### Tertiary (LOW confidence)
- MySQL production-target behavior was not verified against a live MySQL instance in this session (no DB connection tool available) — verified instead by reading `MySqlGrammar`'s SQL-generation code directly, which is a strong but not runtime-executed guarantee (see Environment Availability).

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH — no new packages; every claim checked against installed `vendor/` source or `composer.lock`
- Architecture: HIGH — every pattern is a direct extraction of existing, running code in this exact repository (`CheckAvailabilityAction`, `OperationsQueueController`, `ReservationStateException`, migration FK idioms)
- Pitfalls: HIGH for codebase-specific findings (rule ordering, `staffToken`, controller alias, query-count semantics — all confirmed by reading source); MEDIUM for the MySQL-production-only half of the migration claim (not runtime-verified)

**Research date:** 2026-09-25
**Valid until:** 30 days (stable — no external library surface to go stale; re-verify only if `composer.lock` changes before planning)
