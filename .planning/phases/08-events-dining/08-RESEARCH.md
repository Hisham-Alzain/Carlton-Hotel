# Phase 8: Events & Dining - Research

**Researched:** 2026-10-02
**Base:** 0961153 (Phase 7 closed, full suite 2023 green) — `08-BASE.txt`
**Domain:** event-inquiry checklist / notes / deposit, staff table-reservation list, venue menu file
**Confidence:** HIGH (every premise below was checked in the working tree; file:line references are at the base commit)

## Summary

The consultant's decision set (D-01..D-32) holds against the code with nine refinements, all recorded as PR-1..PR-9 in `08-CONTEXT.md`. The three premises the brief asked to verify specifically:

1. **`ReserveTableAction` timezone bug — CONFIRMED.** `app/Actions/Service/ReserveTableAction.php:30` is `Carbon::parse("{$data['date']} {$data['time']}")` with `config/app.php:68 'timezone' => 'UTC'` and `config/hotel.php:36 HOTEL_TIMEZONE` default `Asia/Damascus`. The parsed instant is a UTC wall-clock, so a guest's 19:00 is stored as `19:00Z` (22:00 local). `ReserveTableRequest.php:12` uses `after_or_equal:today` (a UTC day). The overlap search `findFreeTable()` compares stored instants with a symmetric ±120 min window, so shifting every new instant by the same offset leaves availability unchanged (D-22's "offset-invariant" claim holds) — *except* that old (unshifted) rows and new (shifted) rows can now collide or miss each other by 3 h. Pre-production, demo data regenerated (D-22 Risk) — acceptable, noted in SUMMARY.
2. **`media.collection` migration — SAFE, but D-07 under-counted the leak surface (→ PR-1).** `media` (`2026_07_09_100000_create_media_table.php`, widened by `2026_07_30_130000_add_library_columns_to_media_table.php`) has `morphs('mediable')` (nullable since the library change), no collection/role column. An additive `string('collection', 20)->default('images')` + composite index is backward compatible on SQLite and MySQL. But `MediaService::index()` (library list) returns attached rows too, `attachExisting()` would copy a menu PDF onto any parent and its "already placed" check matches on disk+path across the venue's rows, and `destroy()` checks only the morph columns. All three need a `collection = images` scope (PR-1).
3. **Payment / IdempotentWrite pattern — CONFIRMED, reusable verbatim.** `RecordFolioPaymentAction::handle()` (lock → `FolioLedger::normalize` → `IdempotentWrite::run(key, find, matches, write)` → guards inside `write`) is exactly the shape D-15 needs. `IdempotentWrite` already wraps `write` in a savepoint and re-reads on `UniqueConstraintViolationException`. `RecordCashPaymentAction::handle(Model $payable, string $method, string $amount, User $recorder, ?string $note, ?string $idempotencyKey)` writes `payable_type = get_class($payable)` (FQCN) and auto-confirms only `Reservation` payables — an `EventInquiry` payable is not touched (D-17 "no auto-confirm" holds without any change). Gateway: `ManualDriver` bound in `AppServiceProvider:31`.

**Primary recommendation:** ten sequential plans (foundation → permissions → read shapes → checklist/notes → deposit → tz fix → table list → menu staff → menu public → docs/gate), each RED-then-GREEN, full suite green at the end of every plan.

## Premise checks (decision by decision)

| Decision | Premise | Verdict | Evidence / note |
|---|---|---|---|
| D-01 | `notes` is the guest RFP text | holds | `SubmitInquiryAction` writes it; `EventInquiryResource` exposes `notes` |
| D-02 | no staff checklist exists | holds | `event_requirements` (`type`, `notes`) only; no done-state |
| D-02 | `restrictOnDelete` users parity | holds | Phase 7 `ticket_actions.user_id` precedent |
| D-03 | `Department` has `sales`, `events`, `maintenance` | holds | `app/Enums/Department.php` |
| D-05 | `payments` polymorphic with unique `(payable_type, payable_id, idempotency_key)` | holds | `2026_09_26_130100_add_idempotency_key_to_payments_table.php` |
| D-05 | `EventInquiry` not in morph map | holds | `AppServiceProvider:70` maps only 4 bookables; comment forbids Guest/User aliasing — same reasoning applies (PR-8 pins FQCN) |
| D-07 | Only venues will hold `menu` rows | holds | new route only; but see PR-1 for read/attach leaks |
| D-07 | `PurgesMedia` purges every row of the parent on force-delete | holds | `app/Traits/PurgesMedia.php` — no collection filter, so the menu row is purged too (desired) |
| D-07 | Public venue `show` 404s when inactive | holds | `Api\DiningVenueController::show` throws `NotFoundException` |
| D-08 | `service_bookings` has `(bookable_type, bookable_id)` and `status` indexes | holds | `2026_07_12_100004_create_service_bookings_table.php:25-26` |
| D-09 | routes in `cms/event-inquiries` group | holds | `routes/api.php:522-531` (`tickets.view` / `tickets.assign`) |
| D-11 | public group exists with `dining-venues/{diningVenue}` | holds | `routes/api.php:141-166`; `/menu` and `/menu-categories` already used, so `/menu/download` is a distinct literal path, no binding clash |
| D-11 | venue `images` write routes in the `cms.edit` group | holds | `routes/api.php:416-418` |
| D-12 | 26 permissions / 11 groups; presets synced | holds | `RolesAndPermissionsSeeder` (count verified: 3+4+4+1+3+3+3+2+3); `syncPermissions` per preset |
| D-12 | event-inquiry staff surfaces | **gap** | `OperationsQueueService::summary()` (`:76-79`) emits `event_inquiries` inside the tickets arm → PR-2 |
| D-13 | kitchen/reception/concierge/housekeeping/events hold `service_requests.view` | holds | seeder presets |
| D-14 | pins to move | holds + more | `RolePresetsTest:104-147`, `SeederTest:14,41,218` (26), `PermissionsGroupedTest:29` (11 groups), `EventInquiryTest:38` (`tickets.view`, `tickets.assign`), **`DashboardSummaryTest:50-61`** (tickets.view unlocks event_inquiries — PR-2 re-pin) |
| D-15 | `RecordFolioPaymentAction` order: replay before guards | holds | `IdempotentWrite` docblock + `RecordFolioPaymentAction::record()` |
| D-16 | `FolioLedger::normalize` exists | holds | `app/Support/FolioLedger.php` |
| D-16 | `RecordFolioPaymentRequest` merges header | holds | `prepareForValidation()` + `messages()` override; `idempotency_key` `max:64` (reuse) |
| D-18 | `Folio::balanceDueUsd()` scoped to its own payments | holds | `Folio::paidUsd()` reads `ledgerPayments()` |
| D-19/20 | `InquiryStateException` has no context | holds | `app/Exceptions/InquiryStateException.php`; DomainException accepts a context array (Phase 5/7 usage) |
| D-22 | bug as described | **confirmed** | see Summary §1 |
| D-22 | `CreateServiceBookingAction` takes client `scheduled_at` | **confirmed + wider** | `CreateServiceBookingRequest:14-16` allows `bookable_type = restaurant_table` → such rows appear in the staff list (PR-9, deferred) |
| D-23 | guest columns `first_name,last_name` | **refined** | `guests.name` also exists and is what Phase 7 loads → PR-3 |
| D-23 | `bookable` may be trashed venue | holds + more | `DiningVenue` soft-deletes; `RestaurantTable` does **not** (hard delete, no FK on morph) → orphan bookings, PR-4 |
| D-24 | `HotelClock::dayWindow` strict `Y-m-d` | holds | throws `InvalidArgumentException`; `ServiceRequestFilter::applyDate` (`:126-137`) shows the reject pattern |
| D-26 | `Media::url` via `FileTrait::fileUrl` | holds | `Media::getUrlAttribute()` |
| D-30 | Phase 7 budget method | holds | `expectsDatabaseQueryCount` around the service call |
| D-31 | DST edge in hotel zone | **refined** | Asia/Damascus has no DST since 2022 → PR-5 |
| Discretion | migrate `EventInquiryService` to `BaseService` | declined | PR-6 |

## Architecture notes for the planner

### Event inquiry reads
- `EventInquiryService::adminIndex()` → add `withCount(['checklistItems as checklist_done_count' => fn ($q) => $q->whereNotNull('completed_at')])` and keep `with(['requirements', 'assignedUser'])`; also eager `depositPayment` is **not** needed on the list (resource derives the deposit tick from `deposit_status`). Budget: count, rows (+ withCount subselect), requirements, assignedUser = 4 ≤ 6.
- `show()` → `load(['requirements', 'assignedUser', 'guest', 'eventSpace', 'checklistItems.completedBy', 'depositPayment.recorder'])` = 1 (binding) + 7 = 8 reads after binding; the budget is measured on the service call (≤ 9; D-30 lets the planner collapse users to one batched query and pin 8). `checklistItems.completedBy` and `depositPayment.recorder` are both user loads — collapsing them is optional.
- `EventInquiry::depositPayment()` = `morphOne(Payment::class, 'payable')->where('status', 'completed')->latestOfMany()`; `payments()` = `morphMany(Payment::class, 'payable')`.
- The checklist read merges `EventChecklistItem::cases()` with the loaded rows keyed by `item`; the `deposit` entry is computed from `deposit_status`, `deposit_paid_at` and `depositPayment.recorder` (D-04). This merge lives in the resource (from loaded data only — no query), or in a small `EventChecklist::forInquiry()` helper on the model that reads only loaded relations.

### Writers
- `ToggleEventChecklistItemAction::handle(EventInquiry, EventChecklistItem, bool $done, User $actor)` → `DB::transaction` → lock inquiry → guards (cancelled → `InquiryStateException` with `{status, allowed}`; derived → `EventChecklistItemDerivedException {item}`) → `firstWhere` row; no-op if state equal (including `done:false` with no row) → `firstOrCreate` + `update` (unique `(event_inquiry_id, item)` backstop: catch `UniqueConstraintViolationException` and re-read, Phase 6 `ensureOpen` precedent) → return the inquiry fresh with detail relations.
- Because the derived check must win over "unknown item", the route constraint `whereIn('item', EventChecklistItem::values())` sends unknown values to 404 before the action runs; `deposit` is a known value, so it reaches the action and gets 422.
- `EventInquiryService::updateStaffNotes(EventInquiry, ?string)` → `update(['staff_notes' => …])`, no lock (D-20).
- `RecordEventDepositAction` — copy `RecordFolioPaymentAction` line for line, replace guards (D-15 step 3), return 200 always.

### Permissions
- Seeder: add `'events.view', 'events.manage', 'events.deposit'` after `tickets.*`; `events` preset += the three; reception/concierge unchanged in the seeder **text** (they never had `events.*`) — the loss of access comes from the route re-gate.
- Group derivation: `PermissionsGroupedTest` counts groups by the prefix before the dot, so `events` becomes the 12th group automatically.
- Routes: replace the two inner `permission:` groups in `routes/api.php:522-531` with `events.view` (index, show), `events.manage` (status, assign, checklist, notes), `events.deposit` (deposit).
- PR-2: `OperationsQueueService::summary()` — move the `event_inquiries` block out of the loop: `if ($user->can('events.view')) { … }`.

### Table reservations
- `TableReservationService extends BaseService` with `$model = ServiceBooking::class`, `$filter = TableReservationFilter::class`, `$perPage = 50`, `$maxPerPage = 100` (check `BaseService` property names: `$perPage`, `$maxPerPage` are used by `resolvePerPage()`), `query()` = `ServiceBooking::query()->where('bookable_type', BookableType::RESTAURANT_TABLE->value)->with([...])`.
- Eager loads: `bookable` via `MorphTo::morphWith([RestaurantTable::class => ['diningVenue' => fn ($q) => $q->withTrashed()]])` (tables + venues = 2 queries), `guest:id,uuid,name,first_name,last_name`, `reservation:id,uuid,booking_code`. With count + rows = 6.
- `TableReservationFilter`: custom params `venue`, `table`, `date`, `from`, `to`; `status` eq/in through `$safeParms`. The default-today window is applied by the filter when none of `date|from|to` is present, so `BaseService::index()` must always build the filter (it does: `makeFilter($params)` even for `[]`). Range: parse both with `HotelClock::dayWindow` (start of `from`, end of `to`), reject `to < from` and span > 31 days (count days inclusive: `from->diffInDays(to) + 1 > 31`). `date` + (`from`|`to`) → reject. Sort: `$sortable = ['scheduled_at', 'guest_count']`; default `scheduled_at asc, id asc`.
- Venue filter subquery: `whereIn('bookable_id', RestaurantTable::query()->select('restaurant_tables.id')->join('dining_venues', 'dining_venues.id', '=', 'restaurant_tables.dining_venue_id')->where('dining_venues.uuid', $uuid))` — note the join ignores soft-deletes (a trashed venue's reservations remain listable by uuid, consistent with "trashed venue still renders").
- Resource: `local_date`/`local_time` from `$this->scheduled_at->copy()->setTimezone(HotelClock::timezone())`.

### Menu file
- Migration adds `collection`; `Media::$fillable` += `collection`.
- `MediaService::attach(Model, UploadedFile, int $sortOrder = 0, string $collection = 'images')` — additive optional parameter, writes `collection`. Directory stays `cms/DiningVenue/{uuid}`.
- `ReplaceVenueMenuFileAction::handle(DiningVenue, UploadedFile, ?string $title)` → store the file first (outside the transaction, like `MediaService::attach`), then `DB::transaction`: create the new row (`collection = menu`, `title`), delete every older `menu` row of the venue (`Media::deleted` → `purgeFileIfUnreferenced()` → `PurgeMediaFile::dispatch(...)->afterCommit()`), return `['data' => $new, 'code' => 201]`.
- Delete: `DiningVenue::menuFile` null → `NotFoundException`; else delete in a transaction, `code 200` with `data: null` (D-11 says 200).
- Public: `menuDownload(DiningVenue $diningVenue)` → inactive → `NotFoundException`; `$diningVenue->menuFile` null → `response()->noContent()`; else 200 `{url, file_name, mime_type, size, updated_at}`. Binding = 1 query (soft-deleted excluded by default → 404), menuFile = 1.
- `UploadMenuFileRequest`: `file` `required|file|mimes:pdf,jpg,jpeg,png,webp|max:10240`, `title` `nullable|string|max:255`.

## Common pitfalls

1. **`Carbon` vs `CarbonImmutable` in `findFreeTable()`.** Its signature takes `Illuminate\Support\Carbon`; D-22's `CarbonImmutable::createFromFormat(...)` needs the signature widened to `CarbonInterface` (and `copy()` is harmless on immutables). Also `createFromFormat('Y-m-d H:i', …)` without `!` keeps seconds at 0 because the format has no seconds — use `'!Y-m-d H:i'` to zero every unspecified field.
2. **`after_or_equal:` with a dynamic date** must be built in `rules()` (`'after_or_equal:' . HotelClock::today()->toDateString()`), not a static string.
3. **Replay before guards.** The already-paid guard must sit inside `write`, otherwise a retried successful request returns 422 instead of 200.
4. **`IdempotentWrite` savepoint.** Keep the action's outer `DB::transaction(fn, 3)` — the savepoint inside `run()` needs an outer transaction to be meaningful on the unique-violation path.
5. **The summary key (PR-2)** is pinned by `DashboardSummaryTest` and `RolePresetsTest`; both must be re-pinned in the same plan as the gate change.
6. **SQLite lock no-op.** `RecordsRowLocks` proves the SQL carries `for update`; race safety is MySQL-only (Phase 7 A9 caveat carried).
7. **Media default.** `Schema::table(...)->string('collection', 20)->default('images')` fills existing rows on both engines; do not use `->change()` and do not backfill in code.
8. **`morphWith` + `withTrashed`.** Pass a closure constraint for `diningVenue` (`'diningVenue' => fn ($q) => $q->withTrashed()`); a plain string would apply the soft-delete scope and null the venue for trashed parents.
9. **Five locales.** `lang/{en,ar,fr,tr,es}/custom.php`; `LocaleFoundationTest` enforces key parity.
10. **`EventInquiryResource` is a `JsonResource`.** Extending it for the detail keeps one field map (PR-7). The `assigned_to` key must stay.

## Validation Architecture

- Framework: PHPUnit 12 via `php artisan test`, SQLite `:memory:`, `QUEUE_CONNECTION=sync`. Full suite ~5 min (2023 tests at base).
- New feature test files: `Events/EventInquiryPermissionsTest`, `Events/EventInquiryDetailTest`, `Events/EventChecklistTest`, `Events/EventNotesTest`, `Events/EventDepositTest`, `Dining/TableReservationIndexTest`, `Dining/TableReservationTimezoneTest`, `Dining/VenueMenuFileTest`, `Dining/MenuDownloadTest`, `Database/EventsDiningSchemaTest`.
- New unit test files: `Unit/Events/EventChecklistItemTest`, `Unit/Events/RecordEventDepositActionTest`, `Unit/Dining/TableReservationFilterTest`.
- Re-pinned: `Staff/RolePresetsTest`, `SeederTest`, `Staff/PermissionsGroupedTest`, `Docs/PermissionGuideAccuracyTest` (if it counts), `Operations/DashboardSummaryTest`, `Events/EventInquiryTest`.
- Must stay green unchanged: `Cms/MediaScopingTest`, `Cms/MediaLibraryTest`, `Cms/MediaLibraryGapsTest`, `Cms/MediaCleanupTest`, `Service/TableReservationTest`, `Folio/*`.

## Sources

All in-repo at 0961153: files cited inline. No external sources were needed (Laravel 13 APIs used are the same ones Phases 5–7 already use).
