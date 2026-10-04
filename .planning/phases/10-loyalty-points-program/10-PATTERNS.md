# Phase 10: Loyalty Points Program - Pattern Map

**Mapped:** 2026-10-04
**Files analyzed:** ~75 new / ~14 modified (grouped below by role)
**Analogs found:** all groups matched; 3 sub-areas have no analog (see end)
**Analog policy:** all analogs below are committed (Phases 1-8). No untracked Phase 9 file is used as a source. Backend root is `D:\TupCode\Carlton\backend`; paths below are relative to it. URLs are `/api/...` (no `/v1`).

## File Classification

| New/Modified File(s) | Role | Data Flow | Closest Analog | Match |
|---|---|---|---|---|
| `database/migrations/2026_10_05_1000NN_create_loyalty_*` (7) | migration | schema | `2026_10_02_100100_create_event_inquiry_checklist_items_table.php` | exact |
| `app/Enums/Loyalty*.php` (6) + `NotificationType` (modify) | enum | n/a | `app/Enums/FolioStatus.php` | exact |
| `app/Models/LoyaltyReward.php` | model | CRUD, translatable, soft-delete | `app/Models/Faq.php` | exact |
| `app/Models/Loyalty{Setting,EarnBatch,LedgerEntry,Allocation,Voucher,ReservationApplication}.php` | model | status/ledger | `app/Models/Faq.php` (traits) + `EventInquiryChecklistItem.php` | role-match |
| `app/Actions/Loyalty/EarnLoyaltyPointsAction.php` | action | event-in-txn, idempotent | `Actions/Folio/RecordFolioPaymentAction.php` | role-match |
| `app/Actions/Loyalty/RedeemRewardAction.php`, `AdjustLoyaltyPointsAction.php` | action | request-response, idempotent write | `RecordFolioPaymentAction` + `Support/IdempotentWrite.php` | exact |
| `app/Actions/Loyalty/ApplyLoyaltyToReservationAction.php` | action | txn sub-step | `Actions/Booking/CreateReservationAction.php` | role-match |
| `app/Actions/Loyalty/ReverseLoyaltyFor{Reservation,Folio}Action.php` | action | txn reversal | `Actions/Booking/CancelReservationAction.php` | role-match |
| `app/Actions/Loyalty/{ExpireLoyaltyBatches,NotifyExpiringLoyaltyPoints}Action.php` + `Console/Commands/*` | action + command | batch/scheduled | `Console/Commands/ExpireDigitalKeys.php` | exact |
| `Actions/Folio/{SettleFolio,RecordFolioPayment}Action`, `Booking/{CreateReservation,CancelReservation}Action` (modify) | action | modify | themselves | n/a |
| `app/Support/Loyalty{Math,Program,Ledger}.php` | utility | transform | `Support/FolioLedger.php`, `HotelClock.php` | role-match |
| `app/Services/Loyalty/LoyaltyRewardService.php` | service | CRUD | `Services/Cms/FaqService.php` | exact |
| `app/Services/Loyalty/Loyalty{Account,Report}Service.php` | service | read/aggregate | `FaqService` (BaseService + `['data','code']`) | role-match |
| `app/Filters/Loyalty*Filter.php` | filter | query | `Filters/FaqFilter.php` | exact |
| `app/Http/Requests/Loyalty/*` | request | validation | `Cms/CreateFaqRequest.php`, `Folio/RecordFolioPaymentRequest.php` | exact |
| `app/Http/Resources/Loyalty/*` | resource | shaping | `Cms/FaqResource.php` | exact |
| `Admin/LoyaltyRewardController.php` | controller | CRUD + bin | `Admin/FaqController.php` | exact |
| `Admin/Loyalty{Setting,Guest,Report}Controller.php`, `Api/Loyalty*Controller.php` | controller | custom verbs | `Admin/FolioController.php`, `Api/ReservationController.php` | role-match |
| `app/Exceptions/Loyalty*Exception.php` (7) | exception | n/a | `Exceptions/FolioSettledException.php` | exact |
| `routes/api.php`, `routes/console.php` (modify) | route | n/a | folio block `:731-749`, reservations `:590-595`, console `:11-22` | exact |
| `tests/Feature/Loyalty/*`, `tests/Unit/Loyalty/*` | test | n/a | `tests/Feature/Folio/FolioPaymentTest.php` | exact |

## Pattern Assignments

### Migrations (7 tables)
**Analog:** `database/migrations/2026_10_02_100100_create_event_inquiry_checklist_items_table.php`
```php
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_inquiry_checklist_items', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('event_inquiry_id')->constrained()->cascadeOnDelete();
            $table->string('item', 20);
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('completed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['event_inquiry_id', 'item']);
            $table->index('completed_by');
        });
    }
    public function down(): void { Schema::dropIfExists('event_inquiry_checklist_items'); }
};
```
Apply: docblock citing decision ids; explicit `restrictOnDelete`/`nullOnDelete` on every FK (ledger FKs restrict; `loyalty_vouchers.loyalty_reward_id` nullOnDelete); every FK indexed; anonymous class; `down()` drops. Singleton idiom for `loyalty_settings`: `unsignedTinyInteger('singleton')->unique()` from `2026_10_04_100000_create_night_audit_tables.php` (Phase 9, untracked; idiom only, 1 line).

### Enums (`app/Enums/Loyalty*.php`)
**Analog:** `app/Enums/FolioStatus.php`
```php
namespace App\Enums;
use App\Enums\Concerns\HasValues;
enum FolioStatus: string
{
    use HasValues;
    case OPEN    = 'open';
    case SETTLED = 'settled';
}
```
String-backed, `HasValues`, lowercase values matching the data model strings. `NotificationType::LOYALTY_POINTS_EXPIRING` is one new case in the existing enum.

### `LoyaltyReward` model
**Analog:** `app/Models/Faq.php` lines 1-29
```php
use App\Traits\HasTranslations; use App\Traits\HasUuid; use App\Traits\LogsActivity;
class Faq extends Model
{
    use HasFactory, HasUuid, HasTranslations, LogsActivity, SoftDeletes;
    protected $translatable = ['question', 'answer'];
    protected $fillable = ['category','question','answer','is_active','sort_order'];
    protected $casts = ['is_active' => 'boolean'];
}
```
Apply with `name`/`description` translatable; cast `points_cost` int, `discount_usd` `decimal:2`; type cast to `LoyaltyRewardType`. Other models: `$fillable` required, `decimal:2` casts for money, enum casts for status, `LogsActivity` only on settings/vouchers/applications; ledger entries are append-only (`const UPDATED_AT = null`, no LogsActivity).

### `LoyaltyRewardController` (staff CRUD + recycle bin)
**Analog:** `app/Http/Controllers/Admin/FaqController.php`
```php
class FaqController extends BaseCRUDController
{
    use HandlesRecycleBin;
    protected ?string $resource = FaqResource::class;
    public function __construct(private readonly FaqService $service) {}
    protected function service(): BaseService { return $this->service; }
    public function show(Faq $faq, Request $request): JsonResponse { return $this->showResponse($faq, $request); }
    public function store(CreateFaqRequest $request): JsonResponse { return $this->storeResponse($request); }
    public function update(UpdateFaqRequest $request, Faq $faq): JsonResponse { return $this->updateResponse($request, $faq); }
    public function destroy(Faq $faq, Request $request): JsonResponse { return $this->destroyResponse($faq, $request); }
    public function restore(Faq $faq, Request $request): JsonResponse { return $this->restoreResponse($faq, $request); }
    public function forceDestroy(Faq $faq, Request $request): JsonResponse { return $this->forceDestroyResponse($faq, $request); }
}
```

### `LoyaltyRewardService`
**Analog:** `app/Services/Cms/FaqService.php`
```php
class FaqService extends BaseService
{
    protected string $model = Faq::class;
    protected ?string $filter = FaqFilter::class;
    public function indexPublic(?int $perPage = null): array
    {
        $query = Faq::query()->where('is_active', true)->orderBy('sort_order');
        return ['data' => $query->paginate($this->resolvePerPage($perPage)), 'code' => 200];
    }
    protected function query(): Builder { return Faq::query()->orderBy('sort_order'); }
}
```
Use `indexPublic`-style method for the guest catalog (active, non-trashed). Account/Report services follow the same `['data' => ..., 'code' => 200]` return shape; ledger/voucher listing services set `$with` (research Pitfall 9).

### Filters
**Analog:** `app/Filters/FaqFilter.php` (extends `CmsContentFilter`)
```php
protected array $safeParms = ['category' => ['eq', 'like', 'in']];
protected array $searchable = [];
protected array $translatable = ['question', 'answer'];
protected array $sortable = ['sort_order', 'created_at', 'updated_at'];
```
Reward filter: `type`, `is_active`, translatable `name`. Ledger/voucher filters: `type`/`status` (`eq`,`in`), `occurred_at` (`gte`,`lte`).

### Requests
**Analog A (translatable CMS):** `Cms/CreateFaqRequest.php`
```php
class CreateFaqRequest extends BaseRequest {
    public function rules(): array { return [
        ...TranslatableRules::for('question', ['string', 'max:500']),
        'is_active'  => ['boolean'], 'sort_order' => ['integer', 'min:0'],
    ]; }
}
```
**Analog B (idempotency header; redeem, adjust, and reservation-with-loyalty):** `Folio/RecordFolioPaymentRequest.php`
```php
use ReadsIdempotencyKey;
public function rules(): array { return [
    'amount_usd'      => ['required', 'decimal:0,2', 'min:0.01', 'max:99999.99'],
    'idempotency_key' => ['required', 'string', 'max:64'],
]; }
public function messages(): array { return array_merge(parent::messages(), $this->idempotencyKeyMessages()); }
```
`StoreReservationRequest` (modify): optional `loyalty_points`/`voucher_code`; `idempotency_key` required only when either is present (`required_with`). Report period validation: copy the strict `date_format:Y-m-d` both-or-none pattern described in research, not Phase 9 classes.

### Resources
**Analog:** `Cms/FaqResource.php`
```php
return [
    'uuid'     => $this->uuid,
    'question' => $this->getTranslations('question'),   // whole locale map
    'is_active'=> $this->is_active,
];
```
Never expose `id`; relations via `whenLoaded()`; ledger resource exposes `reservation.uuid`/`booking_code`/`folio.uuid` only.

### Exceptions (7, flat folder)
**Analog:** `app/Exceptions/FolioSettledException.php`
```php
class FolioSettledException extends DomainException
{
    public function errorCode(): string { return 'folio_settled'; }
    public function statusCode(): int   { return 422; }
}
```
Thrown as `new X(__('custom.errors.<code>'), ['context' => ...])`. Add `custom.errors.<code>` in all 5 locales.

### Custom-verb controllers (settings, guest, report, guest-facing)
**Staff analog:** `Admin/FolioController.php` (extends `BaseController`, service injected, `success()` direct when a specific message is needed)
```php
public function recordPayment(RecordFolioPaymentRequest $request, Folio $folio): JsonResponse
{
    $result = $this->service->adminRecordPayment($folio, $request->validated(), $request->user());
    return $this->success(new FolioResource($result['data']), 'custom.messages.folio_payment_recorded', $result['code'], $request);
}
```
**Guest analog:** `Api/ReservationController.php` lines 29-63. Ownership: foreign rows answer 404.
```php
$result = $this->service->store(auth('guests')->user(), $request->validated());
$result['data'] = new ReservationResource($result['data']);
return $this->respondFromService($result, request: $request);
...
if ($reservation->guest_id !== auth('guests')->id()) { throw new NotFoundException(); }
```
Voucher/ledger resolution: scope the query by `auth('guests')->id()` and `throw new NotFoundException()`.

### Idempotent keyed action (`RedeemRewardAction`, `AdjustLoyaltyPointsAction`)
**Analog:** `Actions/Folio/RecordFolioPaymentAction.php` lines 35-57 plus `Support/IdempotentWrite.php:31-55`
```php
return DB::transaction(function () use (...) {
    $locked = Folio::whereKey($folio->id)->lockForUpdate()->firstOrFail();
    [, $replayed] = IdempotentWrite::run(
        $key,
        fn () => Payment::where(...)->where('idempotency_key', $key)->first(),
        fn (Payment $p) => /* same-payload comparison */,
        fn () => $this->record($locked, ...),
    );
    return ['data' => $locked->fresh(), 'code' => $replayed ? 200 : 201];
});
```
Replace the folio lock with `Guest::whereKey($guest->id)->lockForUpdate()->firstOrFail()`. Find callback looks up `loyalty_ledger_entries.idempotency_key`; conflict throws `IdempotencyConflictException` (reuse).

### `EarnLoyaltyPointsAction` + modify settle sites
**Insertion points (exact):**
- `SettleFolioAction.php:46` and `:71`, immediately after `$locked->update(['status' => FolioStatus::SETTLED, 'settled_at' => now()]);` (constructor currently `private readonly RecordCashPaymentAction $recordCashPayment` at `:29`; add a second dep).
- `RecordFolioPaymentAction.php:80` inside `if (bccomp($locked->balanceDueUsd(), '0', 2) <= 0)` (constructor `:31`).
```php
$locked->update(['status' => FolioStatus::SETTLED, 'settled_at' => now()]);
$this->earnLoyalty->handle($locked);   // same txn, folio row already locked
```
Settled guard pattern already present (`SettleFolioAction.php:36-41`) makes a second settle throw before earn. Use `activity()->performedOn(...)->log('...')` for skipped/inactive notes (`RecordFolioPaymentAction.php:82-86`).

### `CreateReservationAction` (modify)
Existing shape: txn `:23`, room_type lock `:25-27`, quote `:42-47`, `Reservation::create` with `'total_usd' => $pricing['total_usd']` `:62`, room line `:67-71`, `load(...)` `:78`. Insert `ApplyLoyaltyToReservationAction::compute()` between quote and create (net `total_usd`), `::persist()` after the rooms line; lock guest after the room_type lock; add a constructor dep alongside `CheckAvailabilityAction`/`QuoteReservationAction` (`:15-18`). Voucher code generation: copy `generateBookingCode()` `:84-94` (Crockford alphabet loop, `exists()` uniqueness) with a `LOY-` prefix.
```php
$alphabet = '0123456789ABCDEFGHJKMNPQRSTVWXYZ';
do { $code = 'CARL-'; for ($i = 0; $i < 8; $i++) { $code .= $alphabet[random_int(0, 31)]; } }
while (Reservation::where('booking_code', $code)->exists());
```
Do not touch `QuoteReservationAction`.

### `CancelReservationAction` (modify) + reversal actions
Current (`:17-34`): status check at `:19` OUTSIDE the transaction, then `DB::transaction` with `$reservation->update(['status' => CANCELLED]); $this->revokeKey->handle(...)`. Change: lock `Reservation::whereKey()->lockForUpdate()` first, re-check `isCancellable()` inside the txn, then call `ReverseLoyaltyForReservationAction->handle($reservation)` after the status update and before the key revoke result is returned. Keep `['data' => null, 'code' => 204]`. Keep throwing `ReservationStateException(__('custom.errors.reservation_state'))`.

### Console commands + schedule
**Analog:** `Console/Commands/ExpireDigitalKeys.php`
```php
protected $signature   = 'stays:expire-digital-keys';
protected $description = 'Revoke digital keys whose expiry has passed';
public function handle(RevokeExpiredDigitalKeysAction $action): int
{
    $revoked = $action->handle();
    $this->info("Revoked {$revoked} expired digital key(s).");
    return Command::SUCCESS;
}
```
**Schedule (`routes/console.php:16,22`):**
```php
Schedule::command('stays:expire-digital-keys')->everyFifteenMinutes()->withoutOverlapping();
Schedule::command('cms:purge-bin')->dailyAt('03:15')->withoutOverlapping();
```
New: `->dailyAt('01:00')->timezone(config('hotel.timezone'))->withoutOverlapping()` (expire) and `09:00` (notify). Comment each line with the decision id, like the existing ones.

### Notification (expiry warning)
**Analog call site:** `Services/Notification/NotificationService.php:22` `pushToGuest(Guest $guest, NotificationType $type, string $title, string $body, array $data = []): GuestNotification`. Not transactional by itself; wrap in the caller's txn so the `expiry_warned_at` marker and the notification row commit together (research design). Titles/bodies from `custom.notifications.*` with explicit locale (`$guest->preferred_locale ?? config('app.locale')`).

### Routes
**Staff block analog (`routes/api.php:731-749`):**
```php
Route::middleware('auth:users')->prefix('cms/folios')->group(function () {
    Route::middleware('permission:folios.view')->group(function () { ... });
    Route::middleware('permission:folios.settle')->group(function () {
        Route::post('/{folio}/settle', [AdminFolioController::class, 'settle']);
        Route::post('/{folio}/payments', [AdminFolioController::class, 'recordPayment']);
    });
});
```
New block `Route::middleware('auth:users')->prefix('cms/loyalty')`; `permission:loyalty.view|loyalty.manage` for reads, `loyalty.manage` writes, `loyalty.adjust` for adjustments. Reward bin entries go inside the existing `cms` trashed/restore/force groups (research cites `:251-289`) declared BEFORE any `/{reward}` route (comment at `:230-232`).
**Guest block analog (`:590-595`):**
```php
Route::middleware('auth:guests')->prefix('reservations')->group(function () {
    Route::post  ('/',              [ReservationController::class, 'store']);
    Route::delete('/{reservation}', [ReservationController::class, 'cancel']);
});
```
New: `Route::middleware('auth:guests')->prefix('loyalty')`, redeem route with `throttle:30,1`. Re-read the file at execution time; Phase 9 is editing it.

### Support classes
`LoyaltyMath`: bcmath-string statics (research Pattern 2). Copy the style of `app/Support/FolioLedger.php` (`normalize`, string money, `bccomp(..., 2)`). `LoyaltyProgram`/expiry: `app/Support/HotelClock.php` (`timezone()`, `dayWindow()`).

### Tests
**Analog:** `tests/Feature/Folio/FolioPaymentTest.php` lines 29-79
```php
class FolioPaymentTest extends TestCase
{
    use RefreshDatabase, RecordsRowLocks;
    protected function setUp(): void { parent::setUp(); $this->seed(RolesAndPermissionsSeeder::class); }
    private function staffToken(string ...$permissions): string {
        $user = User::factory()->create(); $user->givePermissionTo($permissions);
        return $user->createToken('t')->plainTextToken;
    }
    private function pay(Folio $folio, array $body, ?string $key = 'K-1', ?string $token = null): TestResponse {
        $this->app['auth']->forgetGuards();
        $headers = ['Accept-Language' => 'en'];
        if ($key !== null) { $headers['Idempotency-Key'] = $key; }
        return $this->withToken($token ?? $this->presetToken('reception'))
            ->postJson("/api/cms/folios/{$folio->uuid}/payments", $body, $headers);
    }
    private function generatedStay(string $total = '300.00', string $state = 'checkedIn'): array {
        $reservation = Reservation::factory()->{$state}()->create(['total_usd' => $total]);
        $folio = app(GenerateFolioAction::class)->handle($reservation)['data'];
        return [$reservation, $folio];
    }
}
```
Real Sanctum tokens, `forgetGuards()` between calls, `Accept-Language: en`. Schedule tests: `DigitalKeyLifecycleTest.php:204-213`; command run: `RecycleBinRetentionTest.php:278-282`. Re-pin: `SeederTest`, `PermissionsGroupedTest`, `RecycleBinRetentionTest::SOFT_DELETABLE`.

## Shared Patterns

### Transaction + row lock
Source: `RecordFolioPaymentAction.php:35-36`, `CreateReservationAction.php:23-27`. All multi-step writes in `DB::transaction`; lock order `reservation -> folio -> guest -> batches`; `room_type -> guest` on booking.

### Return contract
`['data' => ..., 'code' => 200|201|204]` from every Action/Service; controllers use `respondFromService`/`success`.

### Domain exceptions + localisation
`DomainException` subclasses with `__('custom.errors.<code>')` in en/ar/fr/tr/es (parity enforced by `LocaleFoundationTest`).

### Audit
`activity()->performedOn($m)->causedBy($u)->withProperties([...])->log('loyalty.<event>')`; `LogsActivity` trait on settings/vouchers/applications/rewards only.

## No Analog Found

| File | Role | Data Flow | Reason |
|---|---|---|---|
| `LoyaltyLedger` FIFO consume/restore/clawback | support | ledger algorithm | No existing FIFO/batch ledger; use research Patterns 3-4 |
| `ReverseLoyaltyForFolioAction` | action | refund seam | No refund writer exists (`Refund` model unused); ship as tested seam with TODO |
| Request-memoised settings singleton reader (`LoyaltyProgram`) | support | read config | Only precedent is Phase 9 `night_audit_states` (untracked); `UpsertSiteSettingsAction` is the committed shape for the update action |

## Metadata

**Analog search scope:** `backend/app/{Actions,Base,Console,Enums,Exceptions,Filters,Http,Models,Services,Support}`, `backend/routes`, `backend/database/migrations`, `backend/tests/Feature/Folio`
**Files read:** 22
**Extraction date:** 2026-10-04
