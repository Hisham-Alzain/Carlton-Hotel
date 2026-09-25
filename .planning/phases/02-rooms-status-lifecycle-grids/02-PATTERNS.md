# Phase 2: Rooms — Status Lifecycle & Grids - Pattern Map

**Mapped:** 2026-09-25
**Files analyzed:** 22
**Analogs found:** 20 / 22

## File Classification

| New/Modified File | Role | Data Flow | Closest Analog | Match Quality |
|---|---|---|---|---|
| `database/migrations/*_change_rooms_status_to_string.php` | migration | transform | `database/migrations/2026_07_09_100002_create_rooms_table.php` | role-match |
| `database/migrations/*_create_room_status_history_table.php` (+ add `status_changed_at/by` to rooms) | migration | CRUD | `database/migrations/*_create_check_in_approvals_table.php`, `*_create_reservation_rooms_table.php` | exact |
| `app/Enums/RoomStatus.php` | model/enum | transform | itself (existing, modified) | exact |
| `app/Models/RoomStatusHistory.php` | model | CRUD | `app/Models/Room.php` (belongsTo shape) | role-match |
| `app/Models/Room.php` (add `status_changed_at`, `status_changed_by`, casts) | model | CRUD | itself (existing, modified) | exact |
| `app/Exceptions/RoomStatusTransitionException.php` | error/exception | request-response | `app/Exceptions/ReservationStateException.php` + `app/Exceptions/DomainException.php` | exact |
| `app/Actions/Cms/UpdateRoomStatusAction.php` | service/action | event-driven (transactional state transition) | pattern synthesized from `CheckAvailabilityAction`-style single-purpose Action + `reservation_rooms`/`check_in_approvals` FK idiom; no exact Actions/Cms precedent exists, closest sibling is `app/Actions/Booking/CheckAvailabilityAction.php` for Action shape | role-match |
| `app/Http/Requests/Cms/UpdateRoomStatusRequest.php` | controller/validation | request-response | `app/Http/Requests/Cms/UpdateRoomRequest.php` | exact |
| `app/Http/Requests/Cms/UpdateRoomRequest.php` (drop `status`) | controller/validation | request-response | itself (existing, modified) | exact |
| `app/Http/Requests/Cms/CreateRoomRequest.php` (no change needed, kept as reference) | controller/validation | request-response | n/a | n/a |
| `app/Http/Controllers/Admin/RoomController.php` (add `updateStatus`) | controller | request-response | itself; verb shape from `OperationsQueueController::updateStatus` | exact |
| `app/Http/Controllers/Admin/FrontDeskController.php` | controller | request-response (bounded read) | `app/Http/Controllers/Admin/OperationsQueueController.php` | exact |
| `app/Services/Operations/FrontDeskService.php` | service | request-response (bounded aggregate read) | `app/Services/Operations/OperationsQueueService.php` | exact |
| `app/Services/Booking/PricingService.php` (add `nightlyRate`) | service | transform | `app/Actions/Booking/QuoteReservationAction.php` (rule loop to clone) | role-match |
| `app/Actions/Booking/CheckAvailabilityAction.php` (reused, not modified) | service/action | CRUD-overlap query | itself | exact |
| `routes/api.php` (new `front-desk` group + status route) | route | request-response | existing `cms` group + pipe-OR permission usage at `routes/api.php:237` | exact |
| `database/seeders/RolesAndPermissionsSeeder.php` (add `rooms.status` + presets) | config/seeder | batch | itself (existing, modified) | exact |
| `lang/{ar,en,es,fr,tr}/custom.php` (add `room_status_transition_invalid` key) | config/i18n | transform | existing `errors` group entries in each locale file | exact |
| `tests/Feature/Rooms/RoomStatusTransitionTest.php` | test | request-response | `tests/Feature/Operations/OperationsQueueTest.php` | exact |
| `tests/Feature/Rooms/RoomBoardTest.php` | test | request-response | `tests/Feature/Operations/OperationsQueueTest.php` | role-match |
| `tests/Feature/Rooms/AvailabilityGridTest.php` | test | request-response | `tests/Feature/Operations/OperationsQueueTest.php` | role-match |
| `tests/Feature/Rooms/RatesGridTest.php` | test | request-response | `tests/Feature/Operations/OperationsQueueTest.php` | role-match |
| `tests/Unit/Booking/PricingServiceNightlyRateTest.php` | test | transform | none (new unit-test surface); closest shape is `QuoteReservationAction`'s own tested behavior | no analog |
| `docs/carlton-tree.html`, `backend/docs/API_GUIDE_DASHBOARD.md`, Postman collection | docs | transform | existing nodes/sections for other modules | role-match |

## Pattern Assignments

### `database/migrations/*_change_rooms_status_to_string.php` (migration, transform)

**Analog:** `database/migrations/2026_07_09_100002_create_rooms_table.php`

Current enum column (lines 9-18):
```php
Schema::create('rooms', function (Blueprint $table) {
    $table->id();
    $table->uuid('uuid')->unique();
    $table->foreignId('room_type_id')->constrained()->cascadeOnDelete()->index();
    $table->string('number', 10)->unique();
    $table->unsignedSmallInteger('floor')->nullable();
    $table->enum('status', ['available', 'occupied', 'maintenance'])->default('available')->index();
    $table->boolean('is_active')->default(true)->index();
    $table->timestamps();
});
```
New migration pattern (native, dbal-free `change()` — per RESEARCH.md Pattern 1):
```php
Schema::table('rooms', function (Blueprint $table) {
    $table->string('status', 20)->default('available')->index()->change();
});
DB::table('rooms')->where('status', 'occupied')->update(['status' => 'available']);
```
Down migration restores the original `enum()->change()` call for symmetry.

---

### `database/migrations/*_create_room_status_history_table.php` (migration, CRUD)

**Analogs:** `database/migrations/*_create_check_in_approvals_table.php`, `*_create_reservation_rooms_table.php`

FK/nullOnDelete idiom (check_in_approvals, full file):
```php
$table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
```
cascadeOnDelete idiom (reservation_rooms, line 10):
```php
$table->foreignId('reservation_id')->constrained('reservations')->cascadeOnDelete()->index();
```
Apply directly per D-03/RESEARCH Pattern 2 — `room_id` cascades, `changed_by` nullOnDelete, `created_at` via `useCurrent()` (immutable row, no `updated_at`), plus `rooms.status_changed_at`/`status_changed_by` added in the same migration file.

---

### `app/Exceptions/RoomStatusTransitionException.php` (exception, request-response)

**Analog:** `app/Exceptions/ReservationStateException.php` (full file, 8 lines) + `app/Exceptions/DomainException.php` (full file)

```php
class ReservationStateException extends DomainException
{
    public function errorCode(): string { return 'reservation_state'; }
    public function statusCode(): int   { return 422; }
}
```
`DomainException` base gives `context()`. Copy verbatim, swap error code:
```php
class RoomStatusTransitionException extends DomainException
{
    public function errorCode(): string { return 'room_status_transition_invalid'; }
    public function statusCode(): int   { return 422; }
}
```
Thrown with `new RoomStatusTransitionException(__('custom.errors.room_status_transition_invalid'), ['from' => ..., 'to' => ..., 'allowed' => [...]])` — constructor signature `(string $message, array $ctx, ...)` per `DomainException`.

---

### `app/Actions/Cms/UpdateRoomStatusAction.php` (action, event-driven transactional)

**No exact analog in `Actions/Cms`** — nearest sibling for "single-purpose Action class with a `handle()` method" is `app/Actions/Booking/CheckAvailabilityAction.php` (structural shape only, not transactional). Transaction + history-write shape is RESEARCH.md's synthesized Code Example, grounded in the `reservation_rooms`/`check_in_approvals` FK idiom and `DomainException` throw shape:

```php
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
            $room->update(['status' => $to, 'status_changed_at' => now(), 'status_changed_by' => $actor->id]);
            RoomStatusHistory::create([
                'room_id' => $room->id, 'from_status' => $from->value, 'to_status' => $to->value,
                'changed_by' => $actor->id, 'reason' => $reason,
            ]);
        });
        return ['data' => $room->fresh(), 'code' => 200];
    }
}
```
Return-array shape `['data' => ..., 'code' => 200]` matches `OperationsQueueService`'s action/service return convention (see below).

---

### `app/Http/Requests/Cms/UpdateRoomStatusRequest.php` (validation, request-response)

**Analog:** `app/Http/Requests/Cms/UpdateRoomRequest.php` (full file, 18 lines)

```php
class UpdateRoomRequest extends BaseRequest
{
    public function rules(): array
    {
        $room = $this->route('room');
        return [
            'status' => [Rule::enum(RoomStatus::class)],
            ...
        ];
    }
}
```
New request: `extends BaseRequest`, rules `{ 'status' => ['required', Rule::enum(RoomStatus::class)], 'reason' => ['nullable', 'string', 'max:255'] }`. Note `UpdateRoomRequest` itself must be edited to **drop** the `status` rule (D-01).

---

### `app/Http/Controllers/Admin/RoomController.php` (controller, request-response) — add `updateStatus`

**Analog:** existing file (full file, 51 lines) — CRUD verbs via `BaseCRUDController` delegation; new verb follows `OperationsQueueController::updateStatus` shape (non-CRUD verb on a controller):

```php
// OperationsQueueController.php lines 33-37
public function updateStatus(string $type, string $uuid, UpdateRequestStatusRequest $request): JsonResponse
{
    $result = $this->service->updateStatus($type, $uuid, $request->validated('status'), $request->user('users'));
    $result['data'] = new OperationsQueueItemResource($result['data']);
    return $this->respondFromService($result, request: $request);
}
```
New method on `RoomController` (bypasses `RoomService`, calls the Action directly or via a thin service wrapper per D-11):
```php
public function updateStatus(UpdateRoomStatusRequest $request, Room $room): JsonResponse
{
    $result = $this->updateStatusAction->handle(
        $room, RoomStatus::from($request->validated('status')), $request->validated('reason'), $request->user('users')
    );
    return $this->respondFromService($result, request: $request);
}
```
Note: `AdminRoomController` in `routes/api.php` is an import alias for this same class (Pitfall 4) — no separate file.

---

### `app/Http/Controllers/Admin/FrontDeskController.php` (controller, request-response)

**Analog:** `app/Http/Controllers/Admin/OperationsQueueController.php` (full file, 36 lines)

```php
class OperationsQueueController extends BaseController
{
    public function __construct(private readonly OperationsQueueService $service) {}

    public function summary(Request $request): JsonResponse
    {
        return $this->respondFromService($this->service->summary($request->user('users')), request: $request);
    }
}
```
Direct clone shape:
```php
class FrontDeskController extends BaseController
{
    public function __construct(private readonly FrontDeskService $service) {}

    public function board(Request $request): JsonResponse
    {
        return $this->respondFromService($this->service->board($request->query()), request: $request);
    }
    public function availabilityGrid(Request $request): JsonResponse { /* same shape */ }
    public function ratesGrid(Request $request): JsonResponse { /* same shape */ }
}
```

---

### `app/Services/Operations/FrontDeskService.php` (service, bounded aggregate read)

**Analog:** `app/Services/Operations/OperationsQueueService.php` (full file, 106 lines)

Bounded-query style (lines 30-41, `index()`):
```php
if ($user->can('service_requests.view')) {
    $items = $items->concat(
        ServiceRequest::with('assignedUser')
            ->whereIn('status', ServiceRequestStatus::active())
            ->latest()->limit(self::MERGE_FETCH_LIMIT)->get()
    );
}
```
Summary-style single-query-per-metric (lines 63-76, `summary()`):
```php
$summary['service_requests'] = ServiceRequest::selectRaw('status, count(*) as count')
    ->groupBy('status')->pluck('count', 'status');
```
`FrontDeskService::board/availabilityGrid/ratesGrid` should follow the same "eager-load once, assemble in PHP" style, with the overlap query reused verbatim from `CheckAvailabilityAction` (see below) rather than a fresh `whereHas` copy. Return shape: `['data' => [...], 'code' => 200]` matching this service's `index()`/`summary()` return convention.

---

### `app/Services/Booking/PricingService.php::nightlyRate` (service, transform)

**Analog:** `app/Actions/Booking/QuoteReservationAction.php` (full file, 55 lines) — rule loop to clone (lines 17-28):

```php
$rules = PricingRule::where('room_type_id', $roomType->id)
    ->where('is_active', true)
    ->where('starts_on', '<', $checkOut)
    ->where('ends_on', '>', $checkIn)
    ->get();

foreach ($rules as $rule) {
    if ($rule->modifier_type === ModifierType::PERCENTAGE) {
        $daily *= 1 + ((float) $rule->modifier_value / 100);
    } else {
        $daily += (float) $rule->modifier_value;
    }
}
```
`nightlyRate(RoomType $type, CarbonInterface $date, Collection $rules): array` must apply `$rules` in the exact order handed in — **no `usort`/`sortBy`** (Pitfall 1). Cast every decimal to `(float)` before arithmetic (Pitfall 5, `modifier_value` is `decimal:2` cast → string). `round($daily, 2)` on return, matching `round($daily, 2)` at line 33 of `QuoteReservationAction`. Track `rule_scope` = last applied rule's `scope` field or `null`.

---

### `app/Services/Operations/FrontDeskService.php` grid overlap query (data flow: CRUD-overlap)

**Analog:** `app/Actions/Booking/CheckAvailabilityAction.php` (full file, 78 lines) — private `overlapping()` (lines 63-73):

```php
private function overlapping(int $roomTypeId, string $checkIn, string $checkOut)
{
    return ReservationRoom::where('room_type_id', $roomTypeId)
        ->whereHas('reservation', function ($q) use ($checkIn, $checkOut) {
            $q->whereDate('check_in', '<', $checkOut)
              ->whereDate('check_out', '>', $checkIn)
              ->holdingInventory();
        });
}
```
Grid must reuse this exact predicate shape (whereDate, `holdingInventory()` scope) for its single windowed query — never re-derive with `whereBetween` or raw date math (Pitfall/Don't-Hand-Roll table, RESEARCH.md).

---

### `routes/api.php` (route, request-response)

**Analog:** existing pipe-OR usage at `routes/api.php:237` (`permission:cms.restore|cms.purge`) and the `cms` route group registering `AdminRoomController`.

```php
Route::middleware(['auth:users', 'permission:rooms.status'])
    ->patch('/cms/rooms/{room}/status', [AdminRoomController::class, 'updateStatus']);

Route::middleware('auth:users')->prefix('front-desk')->group(function () {
    Route::middleware('permission:rooms.status|reservations.view')
        ->get('/room-board', [FrontDeskController::class, 'board']);
    Route::middleware('permission:reservations.view')->group(function () {
        Route::get('/availability-grid', [FrontDeskController::class, 'availabilityGrid']);
        Route::get('/rates-grid', [FrontDeskController::class, 'ratesGrid']);
    });
});
```

---

### `database/seeders/RolesAndPermissionsSeeder.php` (seeder, batch)

**Analog:** itself (full file, 62 lines)

```php
$permissions = [
    'reservations.view', ...
    'cms.view', 'cms.edit', 'cms.restore', 'cms.purge',
    'service_requests.view', ...
];
$presets = [
    'reception'    => ['reservations.view', ..., 'service_requests.view'],
    'housekeeping' => ['service_requests.view', 'service_requests.update'],
];
```
Diff: add `'rooms.status'` to `$permissions` (new `rooms` group, comment explaining independence from `cms.edit`), add `'rooms.status'` into `reception` and `housekeeping` preset arrays.

---

### `tests/Feature/Rooms/*.php` (test, request-response)

**Analog:** `tests/Feature/Operations/OperationsQueueTest.php` (full file, 155 lines)

Required per-class private helper (lines 26-31, copy verbatim, not inherited — Pitfall 3):
```php
private function staffToken(string ...$permissions): string
{
    $user = User::factory()->create();
    $user->givePermissionTo($permissions);
    return $user->createToken('t')->plainTextToken;
}
```
`setUp()` seeding pattern (lines 18-22):
```php
protected function setUp(): void
{
    parent::setUp();
    $this->seed(RolesAndPermissionsSeeder::class);
}
```
403-without-permission pattern (lines 76-79):
```php
public function test_queue_requires_view_permission(): void
{
    $token = User::factory()->create()->createToken('t')->plainTextToken;
    $this->withToken($token)->getJson('/api/operations/queue')->assertStatus(403);
}
```
Use `$this->expectsDatabaseQueryCount(N)` as the **last statement** before the single HTTP call, in its own dedicated test method with no `assertDatabaseHas` calls afterward (Pitfall 2).

## Shared Patterns

### Domain exception shape
**Source:** `app/Exceptions/ReservationStateException.php` + `app/Exceptions/DomainException.php`
**Apply to:** `RoomStatusTransitionException`
```php
abstract class DomainException extends \Exception
{
    public function __construct(string $message = '', private readonly array $ctx = [], int $code = 0, ?\Throwable $previous = null) { parent::__construct($message, $code, $previous); }
    abstract public function errorCode(): string;
    abstract public function statusCode(): int;
    public function context(): array { return $this->ctx; }
}
```

### Non-CRUD controller/service pair for bounded operational reads
**Source:** `app/Http/Controllers/Admin/OperationsQueueController.php` + `app/Services/Operations/OperationsQueueService.php`
**Apply to:** `FrontDeskController` / `FrontDeskService` (board, availabilityGrid, ratesGrid)
- Controller extends `BaseController` (not `BaseCRUDController`), injects one service, calls `$this->respondFromService($this->service->X(...), request: $request)`.
- Service methods return `['data' => ..., 'code' => 200]`; permission checks happen in route middleware, never inline in the service (per SKILL.md).

### FK idioms for new tables
**Source:** `database/migrations/*_create_reservation_rooms_table.php`, `*_create_check_in_approvals_table.php`
**Apply to:** `room_status_history` migration
```php
$table->foreignId('room_id')->constrained()->cascadeOnDelete();
$table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
```

### Overlap predicate (single source of truth)
**Source:** `app/Actions/Booking/CheckAvailabilityAction.php::overlapping()`
**Apply to:** availability grid query in `FrontDeskService`
```php
$q->whereDate('check_in', '<', $checkOut)->whereDate('check_out', '>', $checkIn)->holdingInventory();
```

### Test scaffolding
**Source:** `tests/Feature/Operations/OperationsQueueTest.php`
**Apply to:** all four new Feature test files in `tests/Feature/Rooms/`
- Copy `staffToken()` private helper into each class (no shared base method exists).
- `setUp()` always seeds `RolesAndPermissionsSeeder::class`.

## No Analog Found

| File | Role | Data Flow | Reason |
|---|---|---|---|
| `app/Actions/Cms/UpdateRoomStatusAction.php` | service/action | event-driven transition | No existing Action combines a transition-table check + `DB::transaction` + history-row write in this codebase; synthesized from FK idiom + DomainException throw shape per RESEARCH.md (still role-matched to Action-class conventions) |
| `tests/Unit/Booking/PricingServiceNightlyRateTest.php` | test | transform | No prior unit test exists for a pricing-rule loop in isolation; must be written fresh, asserting no re-sort of rules (Pitfall 1) |

## Metadata

**Analog search scope:** `backend/app/{Models,Enums,Exceptions,Actions,Services,Http,Filters}`, `backend/database/migrations`, `backend/database/seeders`, `backend/routes/api.php`, `backend/tests/Feature/Operations`
**Files scanned:** 20
**Pattern extraction date:** 2026-09-25
