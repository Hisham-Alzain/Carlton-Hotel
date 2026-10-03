# Phase 8: Events & Dining - Pattern Map

**Mapped:** 2026-10-02
**Files analyzed:** ~45 new/modified files
**Analogs found:** 44 / 45 (the checklist merge helper has no direct analog; it is a read-only projection over loaded relations)

## File Classification

| New/Modified File | Role | Data Flow | Closest Analog | Match Quality |
|---|---|---|---|---|
| `database/migrations/*_add_staff_columns_to_event_inquiries_table.php` | migration | CRUD | Phase 7 `*_add_support_columns_to_tickets_table.php` (additive columns + index) | exact |
| `database/migrations/*_create_event_inquiry_checklist_items_table.php` | migration | CRUD | Phase 7 `*_create_ticket_recoveries_table.php` (child table, unique, `restrictOnDelete` user FK) | exact |
| `database/migrations/*_add_collection_to_media_table.php` | migration | CRUD | `2026_07_30_130000_add_library_columns_to_media_table.php` (additive media columns + index, reversible) | exact |
| `database/migrations/*_add_scheduled_index_to_service_bookings_table.php` | migration | CRUD | `2026_07_26_100009_add_guest_count_to_service_bookings.php` | role-match |
| `app/Enums/EventChecklistItem.php` | enum | transform | Phase 7 `app/Enums/TicketRecoveryType.php` (`HasValues`, `label()`, per-case helpers) | exact |
| `app/Enums/EventDepositStatus.php` | enum | transform | `app/Enums/FolioStatus.php` | exact |
| `app/Models/EventInquiryChecklistItem.php` | model | CRUD | Phase 7 `app/Models/TicketRecovery.php` (HasUuid, belongsTo user, LogsActivity) | role-match |
| `app/Models/EventInquiry.php` (relations, casts, fillable) | model | CRUD | `app/Models/Folio.php` (`payments` morph + status/settled_at) | role-match |
| `app/Models/DiningVenue.php` (`images` scoped, `menuFile`) | model | CRUD | itself (`images()` morphMany) | exact |
| `app/Models/Media.php` (`collection` fillable) | model | CRUD | itself | exact |
| `database/factories/EventInquiryChecklistItemFactory.php`, `EventInquiryFactory` states | factory | — | Phase 7 `TicketRecoveryFactory`, `EventInquiryFactory::inReview()` | exact |
| `database/seeders/RolesAndPermissionsSeeder.php` | seeder | CRUD | itself (Phase 6 `housekeeping.*` addition) | exact |
| `routes/api.php` (event re-gate, 3 PATCH, table list, menu routes) | route | — | `routes/api.php:522-531`, `:416-418`, `:816-819` | exact |
| `app/Services/Operations/OperationsQueueService.php` (`summary`, PR-2) | service | read | itself | exact |
| `app/Services/Events/EventInquiryService.php` (`adminIndex`, `show`, `updateStatus` context, `updateStaffNotes`) | service | CRUD | itself (legacy, PR-6) | exact |
| `app/Http/Resources/Events/EventInquiryResource.php` (additive keys) | resource | transform | itself | exact |
| `app/Http/Resources/Events/EventInquiryDetailResource.php` | resource | transform | Phase 7 `TicketResource` (show-only keys from loaded relations) | role-match |
| `app/Actions/Events/ToggleEventChecklistItemAction.php` | action | request-response | Phase 6 `AssignHousekeepingTaskAction` (lock → guard → write → reload) + `CreateHousekeepingTaskAction::ensureOpen` (unique backstop) | role-match |
| `app/Actions/Events/RecordEventDepositAction.php` | action | request-response | `app/Actions/Folio/RecordFolioPaymentAction.php` | exact |
| `app/Exceptions/EventChecklistItemDerivedException.php`, `EventDepositAlreadyRecordedException.php` | exception | — | `app/Exceptions/FolioSettledException.php` (context array) | exact |
| `app/Exceptions/InquiryStateException.php` (callers pass context) | exception | — | itself | exact |
| `app/Http/Requests/Events/UpdateEventChecklistItemRequest.php` | request | validation | `app/Http/Requests/Events/UpdateInquiryStatusRequest.php` | exact |
| `app/Http/Requests/Events/UpdateEventStaffNotesRequest.php` | request | validation | Phase 3 `UpdateReservationNotesRequest` (`present|nullable|string`) | exact |
| `app/Http/Requests/Events/RecordEventDepositRequest.php` (+ optional `ReadsIdempotencyKey` trait) | request | validation | `app/Http/Requests/Folio/RecordFolioPaymentRequest.php` | exact |
| `app/Http/Controllers/Admin/EventInquiryController.php` (3 methods) | controller | request-response | itself | exact |
| `app/Actions/Service/ReserveTableAction.php`, `app/Http/Requests/Service/ReserveTableRequest.php` | action / request | request-response | `HotelClock::checkOutAt()` (hotel-local → UTC) | role-match |
| `app/Services/Dining/TableReservationService.php` | service | read | Phase 6 `app/Services/Operations/ServiceRequestBoardService.php` / Phase 7 `TicketService::index` | role-match |
| `app/Filters/TableReservationFilter.php` | filter | transform | `app/Filters/ServiceRequestFilter.php` (`applyDate` via `HotelClock::dayWindow`, reject) | exact |
| `app/Http/Resources/Dining/TableReservationResource.php` | resource | transform | Phase 6 `ServiceRequestBoardResource` | role-match |
| `app/Http/Controllers/Admin/TableReservationController.php` | controller | request-response | Phase 6 `ServiceRequestBoardController::index` (`paginatedSuccess`) | exact |
| `app/Http/Requests/Dining/UploadMenuFileRequest.php` | request | validation | `app/Http/Requests/Cms/UploadMediaRequest.php` | exact |
| `app/Actions/Dining/ReplaceVenueMenuFileAction.php` | action | file-I/O | `MediaService::attach()` + Phase 6 single-writer shape | role-match |
| `app/Services/Cms/MediaService.php` (`attach` collection param, PR-1 scopes) | service | CRUD | itself | exact |
| `app/Http/Controllers/Admin/MediaController.php` or `Admin/DiningVenueMenuFileController.php` | controller | request-response | `MediaController::storeDiningVenue/destroyDiningVenue` | exact |
| `app/Services/Cms/DiningVenueService.php` (`menuFile`) | service | read | itself | exact |
| `app/Http/Controllers/Api/DiningVenueController.php` (`menuDownload`) | controller | request-response | itself (`show` inactive → 404) | exact |
| `lang/{en,ar,fr,tr,es}/custom.php` | lang | — | Phase 7 ticket keys | exact |
| Tests (see VALIDATION) | test | — | `tests/Feature/Tickets/*`, `tests/Feature/Folio/FolioPaymentTest` (Idempotency-Key cases), `tests/Feature/Operations/ServiceRequestBoardTest` (hotel-local date) | exact |
| `backend/docs/API_GUIDE_DASHBOARD.md`, `API_GUIDE_MOBILE.md`, `CHANGELOG_MOBILE_API.md`, `docs/postman/*`, `docs/carlton-tree.html` | docs | — | Phase 7 07-11 edits | exact |

## Key excerpts to copy

### Idempotent money write (copy from `RecordFolioPaymentAction::handle`)
```php
return DB::transaction(function () use ($inquiry, $recorder, $data, $key) {
    $locked = EventInquiry::whereKey($inquiry->id)->lockForUpdate()->firstOrFail();
    $method = (string) ($data['method'] ?? 'cash');
    $amount = FolioLedger::normalize($data['amount_usd']);
    $note   = $data['note'] ?? null;

    IdempotentWrite::run(
        $key,
        fn () => Payment::where('payable_type', $locked->getMorphClass())
            ->where('payable_id', $locked->id)
            ->where('idempotency_key', $key)
            ->first(),
        fn (Payment $p) => $p->method === $method
            && bccomp(FolioLedger::normalize($p->amount_usd), $amount, 2) === 0
            && $p->note === $note
            && (int) $p->recorded_by === (int) $recorder->id,
        fn () => $this->record($locked, $recorder, $method, $amount, $note, $key),
    );

    return ['data' => $locked->fresh(EventInquiryService::DETAIL_RELATIONS), 'code' => 200];
}, 3);
```

### Header merge (copy from `RecordFolioPaymentRequest`)
`prepareForValidation()` trims `Idempotency-Key` into `idempotency_key` (null when blank); rule `required|string|max:64`; `messages()` maps `idempotency_key.required` to `custom.errors.idempotency_key_required`.

### Hotel-local day (copy from `ServiceRequestFilter::applyDate`)
`[$start, $end] = HotelClock::dayWindow($value)` inside `try`, `InvalidArgumentException` → `throw $this->reject('date', __('custom.validation.date_format', ['attribute' => 'date', 'format' => 'Y-m-d']))`; then `where('scheduled_at', '>=', $start)->where('scheduled_at', '<', $end)`.

### Parent-scoped media guard (extend `MediaService::destroy`)
Existing morph check plus `|| $media->collection !== 'images'` → `NotFoundException` with the same context.

## Shared patterns

- **Envelope:** controllers use `respondFromService($result, 'custom.messages.<key>', $request)` and `paginatedSuccess($paginator, Resource::class, $request)`; the one exception is the public 204 (`response()->noContent()`), documented.
- **Single writers + row locks:** every write that has a guard locks its parent row first (Phase 5/6/7). Notes are the documented exception (D-20).
- **Budgets:** `$this->expectsDatabaseQueryCount(n)` immediately before the service call, resolve the resource inside the measured window.
- **Tests:** real Sanctum tokens (`createToken`), never `actingAs`; explicit-permission users plus preset users for the role matrix.
