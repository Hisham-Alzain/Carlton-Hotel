# Phase 5: Folio Extensions - Pattern Map

**Mapped:** 2026-09-26
**Files analyzed:** ~45 (new + modified)
**Analogs found:** 40 / 45 (5 genuinely novel, pointed at RESEARCH.md code examples)

## File Classification

| New/Modified File | Role | Data Flow | Closest Analog | Match Quality |
|---|---|---|---|---|
| `database/migrations/..._add_ledger_columns_to_folio_items_table.php` | migration | CRUD | `database/migrations/2026_07_12_200001_create_folio_items_table.php` | role-match |
| `database/migrations/..._create_folio_item_disputes_table.php` | migration | CRUD | `database/migrations/2026_07_10_100005_create_payments_table.php` | role-match |
| `database/migrations/..._add_idempotency_key_to_payments_table.php` | migration | CRUD | same as above (additive column + unique index) | role-match |
| `app/Enums/FolioItemSource.php` | model/enum | transform | `app/Enums/FolioStatus.php` / `PaymentMethod.php` | exact |
| `app/Enums/FolioDisputeStatus.php` | model/enum | transform | `app/Enums/FolioStatus.php` | exact |
| `app/Models/Folio.php` (modify) | model | CRUD | itself (current file, extend) | exact |
| `app/Models/FolioItem.php` (modify) | model | CRUD | itself (current file, extend) | exact |
| `app/Models/FolioItemDispute.php` | model | CRUD | `app/Models/Folio.php`/`FolioItem.php` (HasUuid + LogsActivity shape) | role-match |
| `app/Support/IdempotentWrite.php` | utility | event-driven/CRUD | `app/Actions/Notification/RegisterDeviceTokenAction.php` (race-safe `updateOrCreate` + `QueryException` catch) | role-match (novel infra, see RESEARCH Pattern 3) |
| `app/Actions/Folio/PostFolioItemAction.php` | service/action | CRUD | `app/Actions/Folio/SettleFolioAction.php` (lock-then-check shape) + `UpdateRoomStatusAction.php` (transactional action + history/side-row) | exact (composite) |
| `app/Actions/Folio/RecordFolioPaymentAction.php` | service/action | CRUD | `app/Actions/Folio/SettleFolioAction.php` + `app/Actions/Payment/RecordCashPaymentAction.php` | exact |
| `app/Actions/Folio/RaiseFolioDisputeAction.php` | service/action | CRUD | `app/Actions/Notification/RegisterDeviceTokenAction.php` (lock + firstOrCreate-style race guard) | role-match |
| `app/Actions/Folio/ResolveFolioDisputeAction.php` | service/action | CRUD | `app/Actions/Cms/UpdateRoomStatusAction.php` (state-transition action with domain exception) | role-match |
| `app/Actions/Folio/GenerateFolioAction.php` (rewrite) | service/action | CRUD/batch | itself (current file) — target shape in RESEARCH.md Pattern 1 | exact |
| `app/Actions/Folio/SettleFolioAction.php` (modify) | service/action | CRUD | itself (current file) | exact |
| `app/Services/Folio/FolioService.php` (extend) | service | request-response | itself (current file) | exact |
| `app/Services/Folio/ReceiptService.php` (refactor) | service | request-response | itself (current `paymentsFor`, replaced by `Folio::ledgerPayments()`) | exact |
| `app/Http/Controllers/Admin/FolioController.php` (extend) | controller | request-response | itself (current file) | exact |
| `app/Http/Controllers/Api/FolioController.php` (extend) | controller | request-response | itself (current file) | exact |
| `app/Http/Requests/Folio/PostFolioItemRequest.php` | request | request-response | `app/Http/Requests/Folio/SettleFolioRequest.php` | exact |
| `app/Http/Requests/Folio/RecordFolioPaymentRequest.php` | request | request-response | `app/Http/Requests/Folio/SettleFolioRequest.php` | exact |
| `app/Http/Requests/Folio/StaffFolioDisputeRequest.php` | request | request-response | `app/Http/Requests/Folio/SettleFolioRequest.php` (action-discriminated body: pattern nearest is any `required_if`-style FormRequest in `app/Http/Requests/`) | role-match |
| `app/Http/Requests/Folio/GuestFolioDisputeRequest.php` | request | request-response | Phase 4 guest-scoped requests (`app/Http/Requests/Guest/*`) | role-match |
| `app/Http/Resources/Folio/FolioResource.php` (extend) | component/resource | transform | itself (current file) | exact |
| `app/Http/Resources/Folio/FolioItemResource.php` (extend) | component/resource | transform | itself (current file) | exact |
| `app/Filters/ReservationFilter.php` (extend) | filter | request-response | itself, `applyConditions()` `folio_status` block (current file) | exact |
| `app/Exceptions/FolioMissingException.php` | exception | request-response | `app/Exceptions/RoomStatusTransitionException.php` (404 variant: see `NotFoundException.php`) | exact |
| `app/Exceptions/FolioSettledException.php` | exception | request-response | `app/Exceptions/RoomStatusTransitionException.php` (422 + context) | exact |
| `app/Exceptions/FolioCreditExceedsItemException.php` | exception | request-response | `app/Exceptions/RoomStatusTransitionException.php` | exact |
| `app/Exceptions/FolioCreditExceedsBalanceException.php` | exception | request-response | `app/Exceptions/RoomStatusTransitionException.php` | exact |
| `app/Exceptions/FolioOverpaymentException.php` | exception | request-response | `app/Exceptions/RoomStatusTransitionException.php` | exact |
| `app/Exceptions/FolioItemDisputeOpenException.php` | exception | request-response | `app/Exceptions/RoomStatusTransitionException.php` | exact |
| `app/Exceptions/FolioDisputeStateException.php` | exception | request-response | `app/Exceptions/RoomStatusTransitionException.php` | exact |
| `app/Exceptions/IdempotencyConflictException.php` | exception | request-response | `app/Exceptions/RoomStatusTransitionException.php` (409 variant) | role-match |
| `database/seeders/RolesAndPermissionsSeeder.php` (modify) | config | batch | itself (current file, append 2 permissions to `reception` preset) | exact |
| `routes/api.php` (modify) | route | request-response | existing `cms/folios`/`cms/reservations`/guest folio groups (current file) | exact |
| `database/factories/FolioItemFactory.php` (extend states) | utility/factory | CRUD | itself (current file) | exact |
| `database/factories/FolioItemDisputeFactory.php` | utility/factory | CRUD | `database/factories/PaymentFactory.php` | role-match |
| `tests/Feature/Folio/FolioReadTest.php` | test | request-response | `tests/Feature/Folio/FolioTest.php` + `tests/Feature/Reservations/ExpressCheckoutTest.php` (query-count assertions) | exact |
| `tests/Feature/Folio/FolioLineItemTest.php` | test | CRUD | `tests/Feature/Booking/ConcurrencyTest.php` (interleaved/lock assertions) + `tests/Feature/Folio/FolioTest.php` | exact |
| `tests/Feature/Folio/FolioPaymentTest.php` | test | CRUD | `tests/Feature/Payment/PaymentTest.php` | exact |
| `tests/Feature/Folio/FolioDisputeTest.php` | test | CRUD | Phase 4 dispute-style tests (404-for-foreign-record pattern) + `tests/Feature/Folio/FolioTest.php` | role-match |
| `tests/Unit/Folio/GenerateFolioReconcileTest.php` | test | batch | `tests/Feature/Reservations/ExpressCheckoutTest.php` (`builds/refreshes_a_folio_exactly_once`) | exact |
| `tests/Feature/SeederTest.php` (modify assertions) | test | batch | itself (current hardcoded-count assertions) | exact |
| `tests/Feature/Staff/PermissionsGroupedTest.php` (modify) | test | batch | itself (current file) | exact |
| `docs/carlton-tree.html`, `API_GUIDE_DASHBOARD.md`, `API_GUIDE_MOBILE.md`, `CHANGELOG_MOBILE_API.md`, Postman collection | config/docs | transform | existing Folio nodes/sections in each file (grepped, see D-18) | exact |

## Pattern Assignments

### `app/Actions/Folio/PostFolioItemAction.php` (service/action, CRUD)

**Analog:** `app/Actions/Folio/SettleFolioAction.php` (lock-then-check) + `app/Actions/Cms/UpdateRoomStatusAction.php` (transactional write + side history row)

**Lock-then-check pattern** (current `SettleFolioAction`, full file):
```php
public function handle(Folio $folio, string $method, float $amount, User $recorder, ?string $notes = null): array
{
    return DB::transaction(function () use ($folio, $method, $amount, $recorder, $notes) {
        $locked = Folio::where('id', $folio->id)->lockForUpdate()->firstOrFail();

        if ($locked->status === FolioStatus::SETTLED) {
            throw new ReservationStateException(__('custom.errors.reservation_state')); // -> FolioSettledException this phase
        }

        $this->recordCashPayment->handle($locked, $method, $amount, $recorder, $notes);

        $locked->update(['status' => FolioStatus::SETTLED, 'settled_at' => now()]);

        return ['data' => $locked->fresh()->load(['items', 'payments']), 'code' => 200];
    });
}
```
Apply the same shape: `DB::transaction` -> `lockForUpdate` on `Folio` -> **idempotency replay check first** (D-08, see `IdempotentWrite` below) -> status guard (`open` else `FolioSettledException`) -> `bcmul`/credit-floor checks -> `FolioItem::create` -> `Folio::recalculateTotals()` -> return `['data' => ..., 'code' => 201]`.

**Transactional write + side-effect row pattern** (`UpdateRoomStatusAction`, full file):
```php
return DB::transaction(function () use ($room, $to, $reason, $actor) {
    $locked = Room::whereKey($room->getKey())->lockForUpdate()->firstOrFail();
    $from   = $locked->status;
    if (! $from->canTransitionTo($to)) {
        throw new RoomStatusTransitionException(__('custom.errors.room_status_transition_invalid'), [
            'from' => $from->value, 'to' => $to->value, 'allowed' => ...,
        ]);
    }
    $locked->forceFill([...])->save();
    RoomStatusHistory::create([...]);
    return ['data' => $locked->fresh(), 'code' => 200];
});
```
Use this shape for domain-exception-with-context (`errorCode()`/`statusCode()` + array context) and for "lock, validate against a rule table, write the row, write the audit trail" structure — directly informs `FolioCreditExceedsItemException`/`FolioCreditExceedsBalanceException` construction and the `posted_by`/`reason` stamping.

---

### `app/Actions/Folio/RecordFolioPaymentAction.php` (service/action, CRUD)

**Analog:** `app/Actions/Folio/SettleFolioAction.php` + `app/Actions/Payment/RecordCashPaymentAction.php` (full file, current):
```php
public function handle(Model $payable, string $method, float $amount, User $recorder, ?string $note = null): array
{
    return DB::transaction(function () use ($payable, $method, $amount, $recorder, $note) {
        $result = $this->gateway->charge($method, $amount, [
            'payable_type' => get_class($payable),
            'payable_id'   => $payable->id,
        ]);
        if ($result['status'] !== 'completed') {
            throw new PaymentFailedException(__('custom.errors.payment_failed'));
        }
        $payment = Payment::create([
            'payable_type' => get_class($payable),
            'payable_id'   => $payable->id,
            'method'       => $method,
            'amount_usd'   => $amount,
            'recorded_by'  => $recorder->id,
            'note'         => $note,
            'status'       => $result['status'],
        ]);
        return ['data' => $payment, 'code' => 200];
    });
}
```
`RecordFolioPaymentAction::handle` wraps this with the same lock-then-check-then-write shape as `SettleFolioAction`, plus: replay check first (D-08), `bccomp(amount, balanceDueUsd(), 2) === 1` -> `FolioOverpaymentException`, then delegate to `RecordCashPaymentAction::handle($folio, ...)` passing `idempotency_key` into the same `Payment::create` call, then auto-settle check via `bccomp(balanceDueUsd(), '0.00', 2) <= 0`. Per Pitfall 4/Open Question 1, keep `PaymentGatewayInterface::charge()`'s `float $amount` signature untouched — do all comparisons in bcmath and only cast to float at the `$gateway->charge()` call boundary.

---

### `app/Support/IdempotentWrite.php` (utility, event-driven/CRUD — NOVEL)

**Closest behavioral analog:** `app/Actions/Notification/RegisterDeviceTokenAction.php` (full file) — the only existing race-safe `updateOrCreate` + `QueryException`-unique-violation-catch precedent in this codebase:
```php
try {
    $deviceToken = DeviceToken::updateOrCreate(['token' => $token], $attributes);
} catch (QueryException $e) {
    if (! str_contains(strtolower($e->getMessage()), 'unique')) {
        throw $e;
    }
    $deviceToken = DeviceToken::where('token', $token)->firstOrFail();
    $deviceToken->update($attributes);
}
```
Note the string-based unique-violation detection (`str_contains(strtolower($e->getMessage()), 'unique')`) — reuse this exact detection idiom for `IdempotentWrite::isUniqueViolation()` instead of inventing SQLSTATE/driver-code matching, since it already works across this project's SQLite test DB and is driver-agnostic text matching. For the full target shape (check-existing -> compare payload -> write -> catch-and-replay), use RESEARCH.md "Pattern 3: Idempotency via unique index + replay-on-conflict" verbatim — this is genuinely new infrastructure with no closer analog.

---

### `app/Actions/Folio/RaiseFolioDisputeAction.php` / `ResolveFolioDisputeAction.php` (service/action, CRUD — NOVEL domain, familiar shape)

**Analog:** `UpdateRoomStatusAction` (lock -> validate state transition -> exception with context -> write) for `ResolveFolioDisputeAction`; `RegisterDeviceTokenAction` (lock parent row, check-then-create) for `RaiseFolioDisputeAction`'s one-open-dispute-per-item guard. No `hasManyThrough`/dispute precedent exists — see RESEARCH.md Pattern 4 and D-09/D-10/D-11 for the exact contract (guest ownership check must compare `$item->folio->reservation->guest_id` directly, never `GuestEntitlement::currentReservation()` — see Anti-Patterns in RESEARCH.md).

---

### `app/Actions/Folio/GenerateFolioAction.php` (rewrite, service/action, CRUD/batch)

**Analog:** itself — current full file quoted in RESEARCH.md "Current `GenerateFolioAction`" and the target reconcile shape in "Pattern 1: Reconcile-by-source instead of rebuild (D-06)". Key current body to replace:
```php
$folio->items()->delete();   // <-- must go
foreach ($lines as $line) {
    $folio->items()->create($line);   // <-- becomes updateOrCreate keyed by (source_type, source_id, source_line)
}
$total = array_sum(array_column($lines, 'amount_usd'));   // <-- becomes recalculateTotals() DB SUM
$folio->update(['subtotal_usd' => $total, 'total_usd' => $total]);
```
New shape: own `lockForUpdate()` first, `updateOrCreate` per computed line, delete-unless-referenced-or-disputed for stale generated rows (full sketch in RESEARCH.md Pattern 1), `recalculateTotals()` at the end. `CheckOutReservationAction`'s existing lock/call site (RESEARCH.md "Current `CheckOutReservationAction`'s folio lock/generate call") is the nested-lock analog — same connection, same transaction, safe to re-lock (Pitfall 2).

---

### `app/Models/Folio.php` (extend) — `ledgerPayments()`/`paidUsd()`/`balanceDueUsd()`/`recalculateTotals()`

**Analog:** `ReceiptService::paymentsFor` (current, full method, to be promoted onto the model):
```php
private function paymentsFor(Reservation $reservation, Folio $folio): Collection
{
    return Payment::query()
        ->where(function ($query) use ($reservation, $folio) {
            $query->where(fn ($q) => $q->where('payable_type', Folio::class)->where('payable_id', $folio->id))
                ->orWhere(fn ($q) => $q->where('payable_type', Reservation::class)->where('payable_id', $reservation->id));
        })
        ->orderBy('created_at')
        ->get();
}
```
Becomes `Folio::ledgerPayments(): Builder` (an OR-query on `$this->id`/`$this->reservation_id`, `status = completed`). Use RESEARCH.md Pattern 2's exact bcmath target code for `paidUsd()`/`balanceDueUsd()` (`bcadd`/`bcsub` at scale 2 over the decimal-cast string values). Current `Folio` model (full file, above) shows the existing `casts`/`fillable`/relation conventions (`HasUuid`, `LogsActivity`, `decimal:2` casts) to preserve when adding the new methods and the `disputes()` hasManyThrough / `scopeUnsettled()` / `scopeWithOpenDisputes()`.

---

### `app/Http/Resources/Folio/FolioResource.php` / `FolioItemResource.php` (extend)

**Analog:** themselves — current full files (above). `FolioResource` currently:
```php
'uuid' => $this->uuid,
'reservation_uuid' => $this->whenLoaded('reservation', fn () => $this->reservation->uuid),
'status' => $this->status,
'subtotal_usd' => $this->subtotal_usd,
'total_usd' => $this->total_usd,
'approved_by_guest_at' => $this->approved_by_guest_at?->toIso8601String(),
'settled_at' => $this->settled_at?->toIso8601String(),
'items' => FolioItemResource::collection($this->whenLoaded('items')),
```
Add `payments` (`PaymentResource::collection($this->whenLoaded('payments'))`), `paid_usd`/`balance_due_usd` (call `$this->paidUsd()`/`$this->balanceDueUsd()` directly — no queries, per D-02/D-03), `open_disputes_count`, all `whenLoaded`-guarded matching the existing `reservation_uuid` idiom exactly. `FolioItemResource` currently only has `uuid`/`description`/`amount_usd`/`source_type` — add `quantity`, `unit_price_usd`, `posted_by`, `posted_at`, `reason`, `reverses_item_uuid`, `dispute` (latest, `whenLoaded` guarded) in the same flat-array style.

---

### `app/Http/Controllers/Admin/FolioController.php` / `Api/FolioController.php` (extend)

**Analog:** themselves — current full files (above), both follow: constructor-injected `FolioService`, one-liner methods that call the service, wrap `$result['data']` in a Resource, `respondFromService($result, 'custom.messages.xxx', $request)`. New methods (`showForReservation`, `postItem`, `recordPayment`, `dispute`, guest `dispute`) follow this exact one-liner shape.

---

### `app/Http/Requests/Folio/PostFolioItemRequest.php` / `RecordFolioPaymentRequest.php` / dispute requests

**Analog:** `app/Http/Requests/Folio/SettleFolioRequest.php` (referenced throughout RESEARCH/CONTEXT as the existing FormRequest pattern for this module — read it directly when implementing for `authorize()`/`rules()` shape, `BaseRequest::messages()` overrides for `decimal`/`required_if`).

---

### `app/Filters/ReservationFilter.php` (extend) — `has_open_disputes`

**Analog:** itself, current `applyConditions()` (full method, RESEARCH.md Code Examples):
```php
protected function applyConditions(Builder $query): void
{
    parent::applyConditions($query);
    if (! array_key_exists('folio_status', $this->params)) { return; }
    $value = $this->params['folio_status'];
    if ($this->isBlank($value)) { return; }
    if (! is_string($value) || ! in_array($value, FolioStatus::values(), true)) {
        throw $this->reject('folio_status', __('custom.validation.in', ['attribute' => 'folio_status']));
    }
    $query->whereHas('folio', fn (Builder $q) => $q->where('status', $value));
}
```
`has_open_disputes=1|0` follows the identical shape: `array_key_exists` guard, `isBlank` early return, then `whereHas('folio.disputes', ...)` for `=1` or `whereDoesntHave` for `=0`.

---

### Exceptions (`FolioMissingException`, `FolioSettledException`, `FolioCreditExceedsItemException`, `FolioCreditExceedsBalanceException`, `FolioOverpaymentException`, `FolioItemDisputeOpenException`, `FolioDisputeStateException`, `IdempotencyConflictException`)

**Analog:** `app/Exceptions/RoomStatusTransitionException.php` (full file, 422 + context):
```php
class RoomStatusTransitionException extends DomainException
{
    public function errorCode(): string { return 'room_status_transition_invalid'; }
    public function statusCode(): int   { return 422; }
}
```
Every new exception is this exact two-method shape, varying only `errorCode()`/`statusCode()` (404 for `FolioMissingException` — pair with `NotFoundException`'s 404 shape; 409 for `IdempotencyConflictException`). Context arrays passed via the constructor as shown in `UpdateRoomStatusAction`'s `throw new RoomStatusTransitionException($msg, ['from' => ..., 'to' => ...])`.

---

## Shared Patterns

### Lock-then-check-then-write (all Folio writer actions)
**Source:** `app/Actions/Folio/SettleFolioAction.php` (full file, above)
**Apply to:** `PostFolioItemAction`, `RecordFolioPaymentAction`, `RaiseFolioDisputeAction`, `ResolveFolioDisputeAction`, rewritten `GenerateFolioAction`
```php
return DB::transaction(function () use (...) {
    $locked = Folio::where('id', $folio->id)->lockForUpdate()->firstOrFail();
    if ($locked->status === FolioStatus::SETTLED) { throw new FolioSettledException(...); }
    // ... domain logic, writes ...
    return ['data' => $locked->fresh()->load([...]), 'code' => 200/201];
});
```

### Money math: bcmath on decimal-cast strings, never float
**Source:** RESEARCH.md Pattern 2 (`Folio::ledgerPayments()/paidUsd()/balanceDueUsd()`)
**Apply to:** `PostFolioItemAction`, `RecordFolioPaymentAction`, `Folio::recalculateTotals()`, `ReceiptService`, `SettleFolioAction`
```php
public function balanceDueUsd(): string
{
    return bcsub((string) $this->total_usd, $this->paidUsd(), 2);
}
```

### Domain exception shape
**Source:** `app/Exceptions/RoomStatusTransitionException.php`
**Apply to:** All 8 new exceptions
```php
class XxxException extends DomainException
{
    public function errorCode(): string { return 'xxx'; }
    public function statusCode(): int   { return 422; }
}
```

### Race-safe check-then-write under a parent lock
**Source:** `app/Actions/Notification/RegisterDeviceTokenAction.php`
**Apply to:** `IdempotentWrite`, `RaiseFolioDisputeAction`'s one-open-dispute guard
```php
try {
    $row = Model::updateOrCreate([...unique key...], [...attrs...]);
} catch (QueryException $e) {
    if (! str_contains(strtolower($e->getMessage()), 'unique')) { throw $e; }
    $row = Model::where([...unique key...])->firstOrFail();
}
```

### Controller one-liner + Resource + respondFromService
**Source:** `app/Http/Controllers/Admin/FolioController.php`, `Api/FolioController.php`
**Apply to:** All new controller methods
```php
public function xxx(SomeRequest $request, Folio $folio): JsonResponse
{
    $result = $this->service->xxx($folio, $request->validated(), $request->user());
    $result['data'] = new FolioResource($result['data']);
    return $this->respondFromService($result, 'custom.messages.xxx', $request);
}
```

### Filter override
**Source:** `app/Filters/ReservationFilter.php::applyConditions()`
**Apply to:** `has_open_disputes` filter addition

## No Analog Found

| File | Role | Data Flow | Reason |
|---|---|---|---|
| `app/Support/IdempotentWrite.php` | utility | event-driven | No `Idempotency-Key`/replay precedent in codebase; use RESEARCH.md Pattern 3 verbatim, informed by `RegisterDeviceTokenAction`'s unique-violation catch idiom |
| `app/Models/FolioItemDispute.php` (`hasManyThrough` on `Folio`) | model | CRUD | No `hasManyThrough` usage anywhere in codebase; standard Eloquent relation, see Laravel docs cited in RESEARCH.md Pattern 4 |
| Staff dispute route `scopeBindings()` | route | request-response | No `scopeBindings()` usage in `routes/api.php` today; use RESEARCH.md Pattern 4 sketch + explicit fallback `abort_unless`/`NotFoundException` check |
| `tests/Feature/Folio/FolioDisputeTest.php` dispute matrix | test | CRUD | No dispute subsystem exists yet; compose from Phase 4's 404-foreign-record test style + `FolioTest.php`'s envelope assertions |
| Boundary bcmath drift test (`10.00 − 9.99 − 0.01`) | test | transform | No bcmath usage/tests exist yet; write fresh per D-19, asserting exact string equality `'0.00'` |

## Metadata

**Analog search scope:** `backend/app/{Actions,Models,Services,Http,Exceptions,Enums,Filters}`, `backend/database/{migrations,factories,seeders}`, `backend/tests/Feature/{Folio,Payment,Reservations,Booking,SeederTest.php,Staff}`, `backend/routes/api.php`
**Files scanned:** ~30 read directly (this session + RESEARCH.md's prior direct reads); RESEARCH.md's Primary Sources list covers the remainder
**Pattern extraction date:** 2026-09-26
