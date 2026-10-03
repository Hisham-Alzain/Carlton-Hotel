# Phase 8: Events & Dining - Context

**Gathered:** 2026-10-02
**Status:** Locked decisions captured. Gate MET (Phase 7 committed 0961153, full suite 2023 green). Ready for execution. **Post-research rulings (PR-1..PR-9, end of file) override earlier text where they differ.**
**Decided by:** Opus 5.5 consultant standing in for Fable (owner delegated all decisions; no Fable consultant and no ai-council were available for this phase). High-stakes items (schema, money, permissions, a timezone write fix) are resolved here with explicit **Risk** and **Dissent** notes in place of a council vote. The post-research rulings were made by the Opus planning crew under the same delegation and are flagged for after-the-fact review. Full audit trail: `08-DISCUSSION-LOG.md`.
**Phase base:** `08-BASE.txt` (0961153).
**Ids:** D-01..D-32 (the consultant brief announced D-34; only 32 were issued, none is missing — see the discussion log), then PR-1..PR-9.

<domain>
## Phase Boundary

Event staff tick an inquiry's five-item checklist, edit internal notes (`staff_notes`, the guest's `notes` untouched) and record one ledger-backed cash deposit (Idempotency-Key, replay-safe) on quoted/confirmed inquiries. All event-inquiry staff routes move from `tickets.*` to a new `events.view|manage|deposit` group (29 permissions / 12 groups; reception and concierge lose event access). Restaurant staff list table reservations (`GET /cms/table-reservations`, hotel-local date filters, default today) after `ReserveTableAction` is fixed to store true UTC. Guests download a venue's menu file (`GET /public/dining-venues/{venue}/menu/download`, 200 URL / 204 / 404) that staff upload through `POST|DELETE /cms/dining-venues/{venue}/menu-file`, stored as a `media` row with the new `collection = menu`. Out of scope: everything in the Deferred list (agreed/partial/refunded deposits, checklist due dates, inquiry list filters, table-reservation staff verbs, `CreateServiceBookingAction` tz, signed menu URLs, the conversations half of the A4 debt).

</domain>

## Gate

Research and planning start only after Phase 7 is committed (0961153) and the full suite is green on that commit (2023 tests). **MET.** Plan 08-01 Task 1 re-runs the full suite as the executor's baseline before any change. Phase 8 re-gates routes that Phase 7 pinned (D-12), so a red Phase 7 would invalidate D-12..D-14.

---

## Ground truth found in code (read-only inspection)

- `event_inquiries`: `uuid, guest_id, event_space_id, assigned_user_id, name, email, phone, company, event_type, event_date, expected_guests, budget_usd DECIMAL(10,2), notes text, status (new|in_review|quoted|confirmed|cancelled), department default events, timestamps`. **`notes` is the guest-submitted RFP text** (`SubmitInquiryRequest` max 5000, written by `SubmitInquiryAction`) and is exposed as `notes` by `EventInquiryResource`. Nothing staff-side exists yet: no checklist, no deposit, no internal notes.
- `event_requirements` (`type` free string, `notes`) are guest-submitted needs from the public form. They are **not** a staff checklist and have no done-state.
- `EventInquiryService` is legacy (it does not extend `BaseService`). `adminIndex()` does `paginate(20)` with no filter. `updateStatus()` holds the transition table inline and throws `InquiryStateException` (`inquiry_state`, 422, no context). `assign()` takes any user with no eligibility check and no lock.
- Routes (`routes/api.php` ~517-531): public `POST /event-inquiries`. Under `auth:users` with prefix `cms/event-inquiries`, index/show are gated by `permission:tickets.view` and status/assign by `permission:tickets.assign`. This is the Phase 7 A4 blast-radius item: reception and concierge read event RFP leads, and concierge can re-status and assign them.
- Permissions: 26 strings in 11 groups. No `events.*` group exists. The `events` preset holds `service_requests.view, tickets.view, tickets.assign, tickets.respond`. Presets are applied with `syncPermissions`, and roles are preset-only (no role CRUD route), so re-seeding fully defines role grants.
- Payments: `payments` is polymorphic (`payable_type/id`, `method`, `amount_usd DECIMAL(10,2)`, `recorded_by` FK, `note`, `status`, `idempotency_key` with a unique `(payable_type, payable_id, idempotency_key)`). `RecordCashPaymentAction::handle(Model $payable, method, string amount, User, note, idempotencyKey)` writes `payable_type = get_class()` (FQCN). `EventInquiry` is not in the morph map and **must not be added to it**: the map is process-wide and would rewrite activity-log `subject_type` (see the AppServiceProvider comment). `RecordFolioPaymentAction` is the pattern to copy: lock the row, then `IdempotentWrite::run(key, find, matches, write)`, where a mismatching replay throws `IdempotencyConflictException` (`idempotency_conflict`, 409). `PaymentMethod` = `cash|on_arrival`.
- Table reservations: these are `service_bookings` rows with `bookable_type = 'restaurant_table'` (morph alias), plus `scheduled_at`, `guest_count`, `status` (`ServiceBookingStatus` with a transition table), `notes` (the special request), `guest_id`, and `reservation_id` (required). `RestaurantTable` has `dining_venue_id, table_number, capacity, is_active`. The only list route is public `GET /public/dining-venues/{v}/tables` (bookables). No staff list exists.
- **Latent timezone bug:** `ReserveTableAction` does `Carbon::parse("{$date} {$time}")` with `app.timezone = UTC`, so the guest's hotel-local 19:00 is stored as 19:00Z, which is 22:00 in Asia/Damascus. `ReserveTableRequest` also uses `after_or_equal:today`, which is a UTC day. A hotel-local `date` filter built on `HotelClock::dayWindow()` would be shifted by the hotel offset. See D-22.
- Dining venues: `DiningVenue::images()` is `morphMany(Media)` filtered only on the morph columns. `media` has no collection/role column. `PurgesMedia` deletes every media row whose morph matches on force-delete. Upload requests accept images only (`UploadMediaRequest`: `image`, max 5120). Public `GET /public/dining-venues/{v}` returns 404 for inactive venues. **No menu file concept exists.** The mobile `downloadMenu()` shows a "coming soon" snackbar.
- Dashboard mocks (`dashboard/src/mocks/data/events.js`, `services/eventsService.js`) call `/events/{id}/...`. Checklist item ids are `contract, deposit, guarantee, beo, av` with `{id,label,owner,done,due_at,note,completed_at,completed_by}`. The checklist PATCH body is `{done}`, and the mock's unknown-item error is `checklist_item_not_found` 404. The deposit PATCH body is `{amount}` and sets `deposit_paid`, `deposit_paid_at`, `deposit_received_by` and also the `deposit` checklist item. Notes PATCH body is `{notes}`. The dashboard has no table-reservations screen. PROJECT.md Key Decision: the dashboard adopts backend paths (`/cms/...`) and gets no alias routes.
- Lang has five locales: en, ar, fr, tr, es.

---

## 1. Schema

**D-01: Staff notes go in a new column `event_inquiries.staff_notes` and `notes` is left alone.** stakes: high (contract)
- Additive migration: `staff_notes` text nullable.
- `notes` stays the guest's RFP message: immutable from staff routes and still exposed as `notes`. Overwriting it would destroy the client's original brief and silently change what an existing field means, which would break the contract.
- `PATCH /cms/event-inquiries/{inquiry}/notes` takes body `{staff_notes: string|null}` (`present`, `nullable`, `string`, `max:5000`). Sending null clears it.
- `staff_notes` stays in `LogsActivity` (it is staff-authored operational text, and who changed it has audit value). Unlike Phase 7 A6, it is not guest complaint PII.
- **Dissent:** the dashboard mock sends and reads `notes`. Rejected because it is a breaking semantic overload. Handoff: the dashboard renders `notes` as "Client brief" (read-only) and `staff_notes` as editable "Internal notes".

**D-02: Checklist table `event_inquiry_checklist_items`. Rows are created lazily and the template is an enum.** `stakes: high`
- Columns:
  - `id`, `uuid` unique
  - `event_inquiry_id` FK `cascadeOnDelete`
  - `item` string(20) (`EventChecklistItem` value)
  - `completed_at` timestamp nullable
  - `completed_by` FK users nullable `restrictOnDelete()` (Phase 7 A6 parity: staff are deactivated, never hard-deleted)
  - timestamps
- Indexes: unique `(event_inquiry_id, item)` and `completed_by`.
- Model `EventInquiryChecklistItem` with `HasUuid` and `LogsActivity`. The activity log is the toggle history. Un-ticking nulls `completed_at` and `completed_by`, and the diff keeps who and when. A separate history table is not justified for a five-item checklist.
- **Lazy rows:** a row is upserted on the first toggle (`firstOrCreate` under the inquiry lock, with the unique index as backstop). Show merges the enum template with the existing rows, so a read always returns all items in enum order. No backfill migration is needed and `SubmitInquiryAction` is untouched.
- **Rejected alternatives:**
  - Reusing `event_requirements` with a done flag. Those rows are guest input with free-text types, so this would mix guest and staff data.
  - A JSON column on `event_inquiries`. Filterable state does not go in JSON (Phase 7 D-01 rule).
  - A configurable template table. That is YAGNI, and it is deferred.

**D-03: `EventChecklistItem` enum = `contract, deposit, guarantee, beo, av`.** stakes: medium
- The values match the dashboard ids exactly, so there is no rename in the handoff.
- Each case has `label()` (lang `custom.event_checklist.{item}`, five locales), `ownerDepartment(): Department` (`contract`→sales, `deposit`→events, `guarantee`→sales, `beo`→events, `av`→maintenance) and `isDerived(): bool` (true only for `deposit`).
- `due_at` is not modelled. The mock derives it from fields that do not exist (`final_guarantee_due_at`, `setup_date`), and inventing offset policy is out of scope (Deferred).

**D-04: The `deposit` checklist item is derived from deposit state (single source of truth).**
- Its `done` = `deposit_status === paid`, `completed_at` = `deposit_paid_at`, `completed_by` = the deposit payment's recorder.
- No checklist row is ever written for `deposit`. `PATCH …/checklist/deposit` returns 422 `event_checklist_item_derived` with context `{item}`.
- This replaces the mock's "deposit PATCH also ticks the checklist row" behaviour, which would create two stores for one fact.

**D-05: Deposit state on `event_inquiries`: `deposit_status` + `deposit_paid_at`. There is no `deposit_usd` column; the amount lives on the `payments` row.** `stakes: high (money)`
- Additive columns:
  - `deposit_status` string(20) default `unpaid`, indexed (`EventDepositStatus { UNPAID, PAID }`)
  - `deposit_paid_at` timestamp nullable
- New relation `EventInquiry::payments()` `morphMany(Payment::class, 'payable')`. In this phase every payment on an inquiry is a deposit. A `depositPayment()` `morphOne` returns the latest completed one.
- Rationale:
  - The folio precedent: `folios.status` + `settled_at` hold current state over a `payments` ledger.
  - `deposit_status` is current state that the list can show and filter without a join, and it leaves room for `refunded|forfeited` later. A payment row alone cannot express those.
  - `deposit_paid_at` is the business transition moment, the analogue of `settled_at`.
  - The amount is not copied to the inquiry. That would break 3NF, and the two values could drift.
- **Risk:** status and ledger could disagree if some future writer inserts a payment without flipping the status. Mitigation: `RecordEventDepositAction` is the single writer (D-15), and a unit test asserts the invariant `deposit_status = paid ⇔ exactly one completed payment exists`.
- **Dissent (recorded and rejected):** the brief named `deposit_usd`. The plausible meanings were (a) a copy of the paid amount, rejected as a duplicate of `payments.amount_usd`, or (b) the agreed/required deposit shown on unpaid events in the dashboard mock (`deposit_amount` while `deposit_paid=false`). (b) belongs to a quote/contract feature (no `revenue_estimate` or quote fields exist) and is **Deferred**. Until then an unpaid inquiry exposes `deposit.amount_usd = null`.

**D-06: No other schema changes to `event_inquiries`.** No `staff_notes_updated_by` (the activity log covers it), no quote amount, no due dates.

**D-07: `media.collection` column for venue menu files.** `stakes: high (shared table)`
- Additive: `collection` string(20) **default `'images'`**, not null, plus an index `(mediable_type, mediable_id, collection)`. Every existing row becomes `images` with no behaviour change.
- `DiningVenue::images()` gains `->where('collection', 'images')`. A new `DiningVenue::menuFile()` `morphOne(Media)` is `where('collection','menu')`, latest by id.
- **No other model's relation changes.** Only venues can receive `menu` rows, through a dedicated route. Library scopes (`unattached`) are untouched because menu rows are always attached.
- Rationale: a morph-attached row inherits `PurgesMedia` (force-deleting a venue purges its menu file) and the shared-file guard in `Media::purgeFileIfUnreferenced()` for free.
- **Rejected:**
  - `dining_venues.menu_media_id` FK. If the media row were morph-attached it would leak into `images`. If it were unattached it would leak into the media library and escape `PurgesMedia`.
  - A `menu_url` string. It has no file lifecycle.
- **Risk:** the venue's nested `DELETE /cms/dining-venues/{v}/images/{media}` and `attach` must not touch menu rows. The planner scopes the `{media}` lookup and the images listing to `collection = images`, and a test pins that the images route returns 404 for the menu media uuid.
- **Dissent:** touching the shared `media` table for one feature. Accepted because the change is additive with a default, and `MediaScopingTest`/`MediaLibraryTest` must stay green unchanged. That is the guard.

**D-08: Indexes for the table-reservation list.** `service_bookings` already has `(bookable_type, bookable_id)` and `status`. Add a composite `(bookable_type, scheduled_at)` index (additive migration) for date-window scans. No other service-booking schema changes.

---

## 2. Routes (under `/cms` convention, `{inquiry}` bound by uuid)

**D-09: Event routes**, in the existing `auth:users` + `cms/event-inquiries` group:

| Verb + path | Permission | Body | Response |
|---|---|---|---|
| `PATCH /cms/event-inquiries/{inquiry}/checklist/{item}` | `events.manage` | `{done: boolean}` required | 200, full inquiry detail |
| `PATCH /cms/event-inquiries/{inquiry}/notes` | `events.manage` | `{staff_notes: string\|null}` | 200, full inquiry detail |
| `PATCH /cms/event-inquiries/{inquiry}/deposit` | `events.deposit` | `{amount_usd, method?, note?}` + `Idempotency-Key` header **required** | 200, full inquiry detail |

- `{item}` is constrained with `->whereIn('item', EventChecklistItem::values())`. An unknown item misses the route and returns **404 `not_found`** (house standard, Phase 7 precedent for unknown `{type}`). The mock's `checklist_item_not_found` is not adopted, which goes in the handoff.
- **`done` is explicit, never a blind toggle.** A blind toggle is not retry-safe: a retried request would undo itself. Setting the current state again is a 200 no-op that writes nothing. Setting `done:false` on an untouched item is also a no-op, with no row created.
- All three return the same `EventInquiryDetailResource` as `GET show`, so the dashboard store can replace the inquiry wholesale, as the mock does.
- No alias `/events/...` routes (PROJECT.md Key Decision). The dashboard moves to `/cms/event-inquiries/{uuid}/...`.

**D-10: `GET /cms/table-reservations`** (`auth:users`, `permission:service_requests.view`). Paginated through `paginatedSuccess`, with filters in D-24. Read-only in this phase.

**D-11: Menu routes.**
- Public: `GET /public/dining-venues/{diningVenue}/menu/download`. No auth. Lives in the existing `public` prefix group.
  - 200 with `{url, file_name, mime_type, size, updated_at}` in the envelope (a URL, **not** a stream or redirect: the requirement says "returns the menu media URL", and the Flutter app opens it externally).
  - **204, empty body** (no envelope) when the venue has no menu file.
  - 404 `not_found` for an unknown uuid, a soft-deleted venue, or an **inactive** venue (parity with public `show`).
- Staff (new, needed so that "when one exists" can ever be true), in the existing `cms.edit` CMS write group next to the venue `images` routes:
  - `POST /cms/dining-venues/{diningVenue}/menu-file`: multipart `file`, `mimes:pdf,jpg,jpeg,png,webp`, `max:10240`, plus optional `title`. It **replaces** any existing menu row in one transaction: insert the new row, then delete the old row, whose file is purged after commit if unreferenced. Returns 201 with `MediaResource`.
  - `DELETE /cms/dining-venues/{diningVenue}/menu-file`: 200 when a file was removed. When there is none, 404 `not_found`.
  - These are scope additions, flagged in the roadmap fix. Without them DINING-02 can only ever return 204.

---

## 3. Permissions (high stakes, Phase 7 A4 debt)

**D-12: Add an `events.*` group and re-gate ALL event-inquiry staff routes off `tickets.*`.** `stakes: high`
- New strings: `events.view`, `events.manage`, `events.deposit`. The catalogue goes 26 → **29 permissions, 11 → 12 groups**.
- Gates:
  - `GET /cms/event-inquiries`, `GET /cms/event-inquiries/{i}` → `events.view`
  - `PATCH …/status`, `PATCH …/assign`, `…/checklist/{item}`, `…/notes` → `events.manage`
  - `PATCH …/deposit` → `events.deposit`
- Presets:
  - `events` += `events.view, events.manage, events.deposit`. Its `tickets.*` stays, because the events team also does guest relations.
  - `reception`, `concierge` **lose** event-inquiry access. They gain no `events.*`.
  - `kitchen` and `housekeeping` are unchanged. Super admin bypasses as today.
- Rationale:
  - Recording money on an event under `tickets.assign` would hand concierge a money write that no one intended. That is the exact failure Phase 7 A4 flagged as debt.
  - Adding the money verb is the moment the coupling becomes unacceptable, so we split the event slice now. The `/cms/conversations` part of the debt is left as it is.
  - `events.deposit` is split from `events.manage` on the folio precedent (`folios.settle` vs `folios.view`): money writes get their own permission. PITFALLS #9 (coarse permissions) is respected, because there are three strings for a whole domain, not one per verb.
- **Risk:** this narrows access on existing routes (reception/concierge, who gained access in Phase 7). Phase 7 is committed locally and may not be pushed, but treat it as a contract tightening anyway. Record it in the SUMMARY and the dashboard handoff (the Events nav gate moves from `tickets.view` to `events.view`). Roles are preset-only and synced by the seeder, so there is no orphaned custom-role grant to migrate.
- **Dissent (rejected):**
  - (a) Keep `tickets.*` and add only `events.deposit`. Rejected because it leaves RFP PII readable by reception/concierge.
  - (b) A transitional `events.view|tickets.view` OR-gate. Rejected because it preserves the debt indefinitely.
- **Docs:** update the PROJECT.md debt row. The event-inquiry part is resolved in Phase 8, and the conversations part remains pending.

**D-13: Table-reservation list reuses `service_requests.view`.**
- Service bookings are already exposed operationally under `service_requests.*` (departure services, queue).
- Kitchen (restaurant staff), reception, concierge, housekeeping and events all hold it today.
- A new `dining.view` would be permission sprawl for one read route.
- **Risk accepted:** housekeeping can see dinner reservations (guest name and party size). This is low sensitivity and parity with the departure-services list they already see. Deferred: `dining.*` if a dining-only role ever appears.

**D-14: Re-pins.**
- `RolePresetsTest`: the events preset gains the three permissions; reception/concierge assert **no** `events.*` and get **403** on `GET /cms/event-inquiries` (this replaces the Phase 7 A4 "can read" pins); kitchen/housekeeping are unchanged.
- `PermissionGuideAccuracyTest` = 29/12. `SeederTest`. The existing `tests/Feature/Events/EventInquiryTest.php` actors move from `tickets.*` to `events.*`.
- Assignee eligibility on `PATCH …/assign` is unchanged in this phase (Deferred).

---

## 4. Money semantics: the deposit (high stakes)

**D-15: Ledger-backed, single writer `RecordEventDepositAction`. The deposit is not record-only.** `stakes: high`
- A deposit is real money received, so it is a `payments` row (`payable = EventInquiry`, FQCN `payable_type`) written by `RecordCashPaymentAction` (gateway boundary, `PaymentFailedException`). It is not a number on the inquiry.
- This differs from Phase 7 recovery, which was record-only because the credit already lived in the folio ledger. Here no other ledger holds the money.
- `RecordEventDepositAction::handle(EventInquiry, User $recorder, array $data, string $key)`:
  1. `DB::transaction(fn, 3)`; `EventInquiry::whereKey()->lockForUpdate()->firstOrFail()`.
  2. `IdempotentWrite::run($key, find, matches, write)`:
     - `find` = Payment by `(payable_type = $locked->getMorphClass(), payable_id, idempotency_key)`.
     - `matches` = same method, `bccomp` amount equal at 2dp, same note, same `recorded_by` (folio D-08 parity).
     - A mismatch → 409 `idempotency_conflict`.
  3. `write` (first time only), under the lock:
     - Status guard: the inquiry must be `quoted` or `confirmed`, otherwise 422 `inquiry_state` with **additive context** `{status, allowed: ['quoted','confirmed']}`.
     - Already-paid guard: `deposit_status === paid` → 422 `event_deposit_already_recorded` `{payment_uuid, paid_at}`.
     - Call `RecordCashPaymentAction->handle($locked, method, amount, recorder, note, idempotencyKey: $key)`, then `update(['deposit_status' => paid, 'deposit_paid_at' => now()])`.
  4. Return `['data' => $locked->fresh(<detail relations>), 'code' => 200]`.
- **The replay check runs before the guards** (folio order). A retried request after success returns 200 with the same body and no second row, even though the status is now `paid`.

**D-16: Amount and method rules.**
- `amount_usd`: `required|decimal:0,2|min:0.01|max:99999.99`, normalised with `FolioLedger::normalize()` (bcmath, no floats except at the gateway, Phase 5 ruling).
- No check against `budget_usd`: a budget is the client's indicative figure, not a contract.
- `method`: optional, default `cash`, `Rule::in(['cash'])` only. `on_arrival` is meaningless for a pre-event deposit. Bank transfer and card are Deferred, because adding `PaymentMethod` cases would widen folio payment validation too.
- `note` ≤ 1000. `Idempotency-Key`: same `prepareForValidation` merge and `custom.errors.idempotency_key_required` message as `RecordFolioPaymentRequest`. Extract a small shared trait if it stays clean (discretion).

**D-17: One deposit per inquiry, no partials, no refunds in this phase.**
- Recording a deposit does **not** auto-transition `quoted → confirmed`. Confirmation stays an explicit staff status change. `RecordCashPaymentAction` already auto-confirms *reservations*, and that coupling is not copied.
- Cancelling an inquiry with a paid deposit leaves `deposit_status = paid`. The resource shows it, and a refund or forfeit flow is Deferred.
- **Dissent:** sales teams often collect deposits in instalments. Deferred (it needs `deposit_required_usd` and a `partially_paid` status, which go together with D-05's deferred agreed-amount).

**D-18: Cross-domain isolation.**
- The payments on an inquiry never touch folios. `Folio::balanceDueUsd()` already scopes payments to its own payable or reservation, and a regression test proves an event deposit does not change any folio balance.
- **Phase 9 note:** revenue reports must segment `payments` by `payable_type`. An event deposit is money received, not room revenue. This is written into the SUMMARY "for Phase 9" list.

---

## 5. Lifecycle guards for checklist and notes

**D-19: The checklist writer `ToggleEventChecklistItemAction`** locks the inquiry, then:
- `cancelled` inquiry → 422 `inquiry_state` `{status, allowed}`
- `deposit` item → 422 `event_checklist_item_derived`
- same state → no-op
- otherwise upserts the row with `completed_at = now()`, `completed_by = actor` (or nulls both)

Allowed in `new`, `in_review`, `quoted` and `confirmed`.

**D-20: Notes writer.** Notes are editable in **every** status, including `cancelled` (post-mortem notes are legitimate). This is a plain single-column update inside the service. There is no lock and no concurrency token: last write wins, which is documented. `If-Match`/version is Deferred.

**D-21: Existing status/assign behaviour is unchanged apart from the gate (D-12)** and the additive `inquiry_state` context `{status, allowed}` on `updateStatus()` (same exception, same code).

---

## 6. Dining: table reservations

**D-22: Fix the `ReserveTableAction` timezone (hotel-local in, UTC stored).** `stakes: high (write semantics)`
- Parse with `CarbonImmutable::createFromFormat('Y-m-d H:i', "$date $time", HotelClock::timezone())->utc()`. `ReserveTableRequest`'s `after_or_equal:today` becomes the hotel-local today (`after_or_equal:` + `HotelClock::today()->toDateString()`).
- Rationale:
  - The staff list's hotel-local `date` filter (`HotelClock::dayWindow`, the Phase 6 house rule) is only correct if stored instants are real UTC.
  - Today a 19:00 booking is stored as 22:00 local in Damascus, so it is already wrong for any client that renders the instant in local time.
  - The seating-overlap logic is offset-invariant, so availability does not change.
- **Risk:** existing `service_bookings` table rows keep their wrong instants. This is pre-production, and demo seed data is regenerated. No data migration is done, which is noted in the SUMMARY.
- **Contract:** the response `scheduled_at` instant shifts by the hotel offset for new bookings. That is a correctness fix, and it gets a `CHANGELOG_MOBILE_API.md` entry. Regression test: book 19:00 local → stored `16:00Z` (fixed `HOTEL_TIMEZONE=Asia/Damascus`, UTC+3) → listed under that local date.
- **Dissent:** "leave it and filter on the naive UTC date". Rejected because it makes Phase 9 and every hotel-local consumer wrong. Scope is limited to `ReserveTableAction`. The planner checks `CreateServiceBookingAction` (it takes a client `scheduled_at` as-is), logs it, and does not fix it here (Deferred, since it is not on this phase's read path).

**D-23: The `TableReservationService` (extends `BaseService`) list query.**
- Base: `ServiceBooking::where('bookable_type', 'restaurant_table')`.
- Venue filter as a subquery, not a uuid lookup: `whereIn('bookable_id', RestaurantTable::select('restaurant_tables.id')->join('dining_venues', …)->where('dining_venues.uuid', $venueUuid))`.
- Eager loads:
  - `bookable` via `morphWith([RestaurantTable::class => ['diningVenue' => withTrashed]])`
  - `guest:id,uuid,first_name,last_name` (columns per model)
  - `reservation:id,uuid,booking_code`
- Default sort is `scheduled_at asc, id asc`.

**D-24: `TableReservationFilter`.**
- `venue` (uuid eq)
- `table` (uuid eq)
- `status` (eq/in, `ServiceBookingStatus`)
- `date` (strict `Y-m-d`, hotel-local, via `HotelClock::dayWindow`)
- **or** `from`/`to` (hotel-local dates, inclusive, `to ≥ from`, span ≤ 31 days, else 422 `validation_failed`)
- When neither `date` nor `from`/`to` is given, it defaults to **`HotelClock::today()`** (the restaurant's "tonight" view; it is never unbounded). `date` combined with `from`/`to` is a 422.
- An unknown venue/table uuid returns an empty page, not a 422 (filter semantics, Phase 6 parity).
- Sortable by `scheduled_at` and `guest_count`. `per_page` defaults to 50, max 100.

**D-25: `TableReservationResource` fields:**
- `uuid, status, scheduled_at` (ISO-8601 UTC), `local_date`, `local_time` (hotel tz, `H:i`), `guest_count`, `special_request` (= `notes`)
- `venue{uuid,name}` (translated name in the request locale, full map not needed)
- `table{uuid,table_number,capacity}`
- `guest{uuid,name}`
- `reservation{uuid,booking_code}`
- `created_at`

There is no `allowed_statuses` (no staff verb exists this phase; it would advertise unusable actions). The resource does no queries.

---

## 7. Dining: menu download

**D-26: The `DiningVenueService::menuFile()` / public controller method `menuDownload()`** reuses the binding plus an `is_active` check, then `menuFile` (1 query).
- The 204 is returned as `response()->noContent()` (no envelope; documented as the one envelope exception in the guide).
- The URL comes from `Media::url` (`FileTrait::fileUrl`). Menus are stored on the `public` disk, and signed or private URLs are Deferred.
- No throttle beyond the global defaults (discretion: `throttle:60,1` is allowed if the public group already uses one).

**D-27: Menu upload writer `ReplaceVenueMenuFileAction`** works inside a transaction:
1. Store the file through `MediaService` with `collection = 'menu'`.
2. Delete the previous `menu` row(s). `Media::deleted` purges after commit if unreferenced.

The upload validation is a new `UploadMenuFileRequest` (PDF allowed). `UploadMediaRequest` is not widened, because images stay image-only.

---

## 8. Error codes (five locales: en, ar, fr, tr, es; one exception class each)

**D-28:**

| Code | Status | Context | New? |
|---|---|---|---|
| `event_checklist_item_derived` | 422 | `{item}` | new (`EventChecklistItemDerivedException`) |
| `event_deposit_already_recorded` | 422 | `{payment_uuid, paid_at}` | new (`EventDepositAlreadyRecordedException`) |
| `inquiry_state` | 422 | `{status, allowed}` (additive context) | existing |
| `idempotency_conflict` | 409 | `{idempotency_key}` | existing (Phase 5) |
| `payment_failed` | existing | — | existing (gateway) |
| `validation_failed` | 422 | `errors.idempotency_key` = `custom.errors.idempotency_key_required` | existing |
| `not_found` | 404 | unknown inquiry/item/venue/inactive venue/no menu on DELETE | existing |

Message keys for success (5 locales): checklist updated, notes updated, deposit recorded, menu file uploaded, menu file removed. Checklist labels go under `custom.event_checklist.*`.

---

## 9. Resources and query budgets

**D-29: Inquiry resources.**
- `EventInquiryResource` (list) gains, additively:
  - `staff_notes`
  - `deposit_status`, `deposit_paid_at`
  - `checklist_done_count` (`withCount` of `completed_at` not null rows) and `checklist_total` (= number of enum cases; the derived `deposit` counts as done when paid, computed in the resource from loaded data)
- **`EventInquiryDetailResource`** (show and the three PATCHes) additionally carries:
  - `checklist[]`: `{item, label, owner_department, derived, done, completed_at, completed_by{uuid,name}|null}`, in enum order
  - `deposit{status, amount_usd (string 2dp)|null, method|null, paid_at|null, received_by{uuid,name}|null, payment_uuid|null}`
  - `assigned_user{uuid,name}|null` (additive; `assigned_to` uuid kept)
  - the existing `requirements`, `guest`, `event_space`
- Field names are additive only. `notes` keeps its meaning.

**D-30: Budgets, asserted with `expectsDatabaseQueryCount` on the service path** (Phase 7 method):
- Inquiry list ≤ 6: count, rows, requirements, assignedUser, plus the `withCount` subselect in the row query.
- Inquiry show ≤ 9: inquiry, requirements, assignedUser, guest, eventSpace, checklistItems, checklistItems.completedBy, depositPayment, depositPayment.recorder. The planner may collapse the two user loads into one batched users query and then pin 8.
- Each PATCH: writes plus a re-load ≤ 9 reads.
- Table reservations ≤ 6: count, rows, tables, venues, guests, reservations.
- Menu download ≤ 2 (venue binding, menu row).
- These budgets are hard caps, and the planner may not raise them (PR-8 precedent).

---

## 10. Tests (every new route: happy / 401 / 403 / 422 where applicable; the public route has no 401/403)

**D-31:**
- `tests/Feature/Events/EventChecklistTest.php`:
  - tick/untick
  - no-op same state (no row, no activity)
  - lazy row creation and the unique backstop
  - derived `deposit` 422
  - unknown item 404
  - cancelled 422 `inquiry_state` with context
  - `done` missing/non-bool 422
  - show returns 5 items in order
  - 401/403 (`events.view`-only gets 403)
- `EventNotesTest`: set/clear, `notes` (guest) untouched, allowed when cancelled, >5000 422, 401/403.
- `EventDepositTest`:
  - happy path: payment row with FQCN payable, status/paid_at set, checklist `deposit` done
  - **replay same key → 200, exactly one payment**
  - same key with a different amount → 409 `idempotency_conflict`
  - missing key 422 `idempotency_key_required`
  - amount 0 / 3dp / >99999.99 → 422
  - `method: on_arrival` 422
  - second deposit with a new key → 422 `event_deposit_already_recorded`
  - `new`/`in_review`/`cancelled` → 422 `inquiry_state`
  - no auto-confirm
  - an unrelated folio balance unchanged
  - `events.manage` without `events.deposit` → 403
  - 401
  - row lock asserted with `RecordsRowLocks`
- `EventInquiryPermissionsTest` (or extend the existing test): reception/concierge 403 on all event routes, events preset 200, super admin 200, list/show budgets.
- `tests/Feature/Dining/TableReservationIndexTest.php`:
  - default today
  - `date`, `from/to`, span >31 422, `date`+range 422, bad date 422
  - venue/table/status filters
  - other bookable types excluded
  - trashed venue still renders
  - order
  - budget ≤ 6
  - 401, 403 (no `service_requests.view`)
- `TableReservationTimezoneTest` (D-22 regression).
- `MenuDownloadTest`: 200 shape + URL, 204 empty body, 404 unknown/inactive/trashed, budget.
- `VenueMenuFileTest`:
  - upload pdf 201, replace purges the old file (fake disk), delete 200, delete none 404, non-pdf/oversize 422, 401/403
  - **`images` listing and `DELETE …/images/{menuMediaUuid}` do not see the menu row**
  - force-deleting a venue purges the menu file
- Unit tests:
  - `EventChecklistItem` (labels in 5 locales, derived flag)
  - `RecordEventDepositAction` (status ⇔ ledger invariant)
  - `TableReservationFilter` window maths across the DST edge
- Re-pins: `RolePresetsTest`, `PermissionGuideAccuracyTest` (29/12), `SeederTest`, existing `EventInquiryTest`, `TableReservationTest` (tz), `MediaScopingTest`/`MediaLibraryTest` (must stay green unchanged), lang parity test (5 locales).
- The full suite is green before commit.

---

## 11. Docs and contract

**D-32:**
- `API_GUIDE_DASHBOARD.md`:
  - **Events** module: the routes and gates, the `events.*` permissions, the checklist (enum, explicit `done`, derived deposit), deposit money rules (Idempotency-Key required, replay, single deposit, statuses, no auto-confirm, no refunds), `notes` vs `staff_notes`, shapes.
  - New **Dining → Table reservations** section: filters, hotel-local dates, default today, shape.
  - **Menu file** CMS routes.
  - The permission catalogue goes to 29/12 and notes the event-inquiry re-gate (the reception/concierge loss).
- `API_GUIDE_MOBILE` (or the equivalent guest guide): menu download (200 `{url,…}` / 204 / 404).
- `CHANGELOG_MOBILE_API.md`: the new menu download route, and the **table reservation `scheduled_at` now true UTC of the hotel-local slot** (D-22).
- Postman folder "Events & Dining" (checklist, notes, deposit with an Idempotency-Key variable, table reservations, menu file upload/delete, public menu download).
- `docs/carlton-tree.html`:
  - "checklist · deposit · notes" → `api:true` with the 3 real `/cms/event-inquiries/{i}/…` endpoints
  - "inquiry pipeline" → `api:true`, meta "dashboard must adopt /cms/event-inquiries (no alias); gated events.*"
  - "table reservations for staff" → `api:true`, `GET /cms/table-reservations`
  - "download menu" → `api:true`, the public endpoint
  - "venues · menus CMS" eps += `menu-file`
- Dashboard handoff:
  - `/events/{id}` → `/cms/event-inquiries/{uuid}`
  - notes body `{staff_notes}`; `notes` = client brief (read-only)
  - checklist body `{done}` required, unknown item `not_found` (not `checklist_item_not_found`), `deposit` is not toggleable
  - deposit body `{amount_usd, method?:'cash', note?}` + `Idempotency-Key`, and the response replaces `deposit_paid`, `deposit_amount`, `deposit_received_by` with the `deposit{}` object
  - unpaid deposits have no amount (D-05)
  - nav gate `events.view`
- PROJECT.md: debt row updated (event part resolved by D-12). Key Decision rows: deposit ledger-backed single writer (D-15); `media.collection` (D-07).
- SUMMARY lists:
  - `[BLOCKING] migrate + seed RolesAndPermissionsSeeder`
  - the 3 new permissions and preset diffs
  - contract tightenings (event routes re-gated; additive `inquiry_state` context)
  - the D-22 tz fix with no backfill
  - the Phase 9 note (D-18)

---

## Roadmap wording fixes (Phase 8): apply to ROADMAP.md and REQUIREMENTS.md

- **Depends on:** "Phase 5 (payment action pattern)" → "Phase 5 (payment action + Idempotency-Key pattern) and Phase 7 (event-inquiry gates and role presets re-pinned)".
- **SC-1:** "Staff toggle checklist items via `PATCH /cms/event-inquiries/{inquiry}/checklist/{item}` (body `{done}`; items `contract|deposit|guarantee|beo|av`, where `deposit` is derived from the deposit and read-only) and update internal notes (`staff_notes`, the guest's `notes` unchanged) via `PATCH …/notes`. The inquiry detail reflects both."
- **SC-2:** "Staff with `events.deposit` record a deposit on a quoted or confirmed inquiry via `PATCH …/deposit`. It is written as a `payments` row through `RecordCashPaymentAction` with a required `Idempotency-Key`. A retried request returns 200 without a second payment, the same key with a different payload returns 409 `idempotency_conflict`, a second deposit returns 422 `event_deposit_already_recorded`, and an invalid amount returns 422."
- **SC-3:** "Staff with `service_requests.view` list restaurant table reservations via `GET /cms/table-reservations`, filtered by venue, status and hotel-local date or date range (default: today). Stored seating times are true UTC of the hotel-local slot."
- **SC-4:** "`GET /public/dining-venues/{venue}/menu/download` returns 200 with the menu file URL when one exists, 204 when none exists, and 404 for an unknown or inactive venue. Staff upload/replace/remove the file via `POST|DELETE /cms/dining-venues/{venue}/menu-file`."
- **SC-5:** "AR/EN keys exist" → "keys exist in all five locales". "new permissions are listed in the summary" → "`events.view|manage|deposit` are seeded (29 permissions / 12 groups), event-inquiry routes move from `tickets.*` to `events.*` (reception/concierge lose event access), and the preset diffs are listed in the summary".
- **Reuses:** replace "`RecordCashPaymentAction` as extended in Phase 5" with "`RecordCashPaymentAction`, `IdempotentWrite` and `FolioLedger::normalize` (Phase 5)". Add `HotelClock::dayWindow`, `PurgesMedia`/`MediaService`, `RecordsRowLocks`. Drop `RestaurantTableService` (not needed). Keep `ReserveTableAction` (tz fix).
- **REQUIREMENTS:**
  - EVENT-02: "…using the existing payment action, idempotent via `Idempotency-Key`"
  - EVENT-03: "…update internal event inquiry notes (`staff_notes`)…"
  - DINING-02: "…(media URL, 204 when none, 404 for unknown/inactive venue; staff manage the file via `/cms/dining-venues/{venue}/menu-file`)"

## Claude's Discretion

- Class/file names: `App\Actions\Events\{ToggleEventChecklistItemAction, RecordEventDepositAction}`, `App\Actions\Dining\ReplaceVenueMenuFileAction`, `App\Services\Dining\TableReservationService`, `Admin\TableReservationController`, requests under `Http/Requests/Events|Dining/`, factories (`EventInquiryChecklistItemFactory`, a `withDeposit()` state on `EventInquiryFactory`).
- Whether `EventInquiryService` is migrated to `BaseService` now. Allowed only if the existing tests stay green unchanged; otherwise add methods to it as is.
- Shared trait for the `Idempotency-Key` header merge.
- Lang wording, Postman layout, `DemoShowcaseSeeder` additions (an inquiry with ticked items, one with a paid deposit, today's table reservations, a sample menu PDF).
- Whether the menu-file `DELETE` with no file returns 404 (decided) or 204. Keep 404 unless a test convention contradicts it.
- Collapsing the two user eager-loads in show into one batched query (budget 9 → 8).

## Deferred

- Agreed/required deposit amount (`deposit_required_usd`), partial or instalment deposits, deposit refunds or forfeits on cancellation, bank-transfer/card deposit methods, and auto-confirm on deposit.
- Checklist due dates and owners per inquiry, and a configurable checklist template table.
- Event inquiry list filters (status/deposit/assignee/date), assignee eligibility on event assign, staff-created inquiries (`POST /events` in the mock), and notes concurrency (`If-Match`).
- Table reservation staff verbs (confirm/seat/cancel, `allowed_statuses`), a walk-in reservation creation by staff, a `dining.*` permission, and table-reservation rows in the operations queue.
- Fixing `CreateServiceBookingAction`'s client-supplied `scheduled_at` timezone handling, and a backfill of existing table-reservation instants.
- Signed/private menu URLs, multiple menus per venue (lunch/dinner), and per-locale menu files.
- Splitting `tickets.*` for `/cms/conversations` (the remaining half of the Phase 7 A4 debt).
- (PR-9) The generic `POST /service-bookings` path accepting `bookable_type = restaurant_table`.

---

## Post-research rulings (PR-1..PR-9) — override earlier text where they differ

**Decided by:** the Opus planning crew, standing in for the Fable consultant under the owner's standing delegation (no consultant or council was available). Each ruling closes a gap that research found in a decision's premise (`08-RESEARCH.md` § Premise checks). Flagged for after-the-fact review; none widens a contract beyond what D-01..D-32 already grant, and each one is the narrowest fix that keeps the decision's intent.

**PR-1: Menu rows are invisible to every images path (refines D-07 Risk).** Research found three leaks that D-07's Risk note did not list: (a) the library list `GET /cms/media` (`MediaService::index`) returns attached rows too, so a venue's menu PDF would show up in the image picker; (b) `MediaService::attachExisting()` would copy a menu row (a PDF) onto any parent's `images`, and its "already placed" check would hand a venue's own menu row back as an image; (c) the nested `MediaService::destroy()` used by `DELETE /cms/dining-venues/{v}/images/{media}` checks only the morph columns. Ruling:
- `destroy()` 404s `not_found` when `$media->collection !== 'images'` (generic: every parent's nested images route, since only venues ever hold `menu` rows).
- `attachExisting()` treats a source whose `collection !== 'images'` as missing (404 `not_found`, same context as today) and scopes its `$existing` lookup to `collection = 'images'`. New placements are written with `collection = 'images'` explicitly.
- `MediaService::index()` (the library list) adds `where('collection', 'images')`. Menu files are managed only through the venue's `menu-file` routes.
- The library's `PATCH /cms/media/{media}` and `DELETE /cms/media/{media}` stay unscoped by design (addressed by uuid alone, `cms.edit`); deleting a menu row there is equivalent to `DELETE …/menu-file`. Documented, not guarded.
- `MediaScopingTest`, `MediaLibraryTest`, `MediaLibraryGapsTest`, `MediaCleanupTest` must stay green **unchanged**; the new behaviour is pinned in `VenueMenuFileTest`.

**PR-2: The dashboard summary's `event_inquiries` key moves to `events.view` (extends D-12).** `OperationsQueueService::summary()` currently adds `event_inquiries` status counts inside the tickets arm, so they ride on `tickets.view`. D-12's intent is that every event-inquiry staff surface moves off `tickets.*`. Ruling: the key is emitted when `$user->can('events.view')`, independent of the tickets arm. Reception and concierge lose the key (listed as a contract tightening); the events preset and super admin keep it. `RolePresetsTest::test_ticket_preset_blast_radius` is re-pinned accordingly.

**PR-3: Table-reservation guest name (refines D-23/D-25).** `guests` has a `name` column (also `first_name`, `last_name`; the Phase 7 ticket list loads `guest:id,uuid,name`). Ruling: eager-load `guest:id,uuid,name,first_name,last_name`; `guest.name` = `name`, falling back to `trim(first_name . ' ' . last_name)`, else null.

**PR-4: Orphaned table bookings (refines D-23/D-25).** `RestaurantTable` has no `SoftDeletes`, and `service_bookings.bookable_id` has no FK (morph), so a hard-deleted table leaves bookings whose `bookable` is null. Ruling: the list still returns such a row with `table: null` and `venue: null`; the resource is null-safe; a `venue`/`table` filter naturally excludes it. Pinned by a test.

**PR-5: DST test zone (refines D-31).** `Asia/Damascus` has been fixed UTC+3 with no DST since 2022, so a "DST edge" cannot be exercised in the hotel's own zone. Ruling: the `TableReservationFilter` window unit test sets `hotel.timezone = Europe/London` and a date on the October change; the D-22 regression (`TableReservationTimezoneTest`) uses `Asia/Damascus` (+3, 19:00 local → 16:00Z) as decided.

**PR-6: `EventInquiryService` stays legacy (exercises the D-discretion item).** It is not migrated to `BaseService` this phase (smallest risk to the Phase 6/7 pins). New reads (`show` detail loads, list `withCount`) and the notes writer (`updateStaffNotes()`) are added to it as-is. The two new writers are actions (`ToggleEventChecklistItemAction`, `RecordEventDepositAction`). The existing controller-side `User::where('uuid')` lookup in `assign()` is left alone (pre-existing, out of scope; noted in SUMMARY).

**PR-7: Resource base class.** `EventInquiryResource` extends `JsonResource` today. The new `EventInquiryDetailResource` extends `EventInquiryResource` (so list and detail share one field map); `TableReservationResource` extends `App\Base\BaseResource`. Switching the existing list resource's parent class is out of scope.

**PR-8: Deposit payable type (confirms D-15).** `RecordCashPaymentAction` writes `payable_type = get_class($payable)`; the replay `find` uses `$locked->getMorphClass()`. They are identical only while `EventInquiry` stays out of the morph map (it must, per D-15). A test pins `payments.payable_type === App\Models\EventInquiry::class`, so adding the model to the map later fails loudly instead of silently breaking replay.

**PR-9: Generic service-booking path (logs D-22's "planner checks" item).** `POST /service-bookings` (`CreateServiceBookingRequest`: `bookable_type` = any `BookableType`, `scheduled_at` = `date|after:now`) can create a `restaurant_table` booking that bypasses the table picker and stores a client-supplied instant as-is. Such rows will appear in `GET /cms/table-reservations`. Not fixed here (Deferred, D-22 scope); written into the SUMMARY known-gaps list and the dashboard guide's table-reservations section.
