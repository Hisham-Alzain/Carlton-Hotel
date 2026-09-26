# Phase 5: Folio Extensions - Research

**Researched:** 2026-09-26
**Domain:** Laravel 13 / PHP 8.3 money-ledger backend feature (folio line items, payments, disputes) extending an existing folio/payment subsystem
**Confidence:** HIGH (all claims below are grounded in direct reads of this codebase's current source, migrations and tests — see inline citations; a small number of framework-behavior claims are flagged `[ASSUMED]` and listed in the Assumptions Log)

<user_constraints>
## User Constraints (from CONTEXT.md)

**Source:** `.planning/phases/05-folio-extensions/05-CONTEXT.md`, decided by the Fable 5.1 consultant with two ai-council amendments (`wf_681ece81-b59` ledger design, `wf_ab8560d3-cb5` money semantics). These decisions are LOCKED — plan to implement them exactly, not alternatives.

### Phase Boundary
Staff read a reservation's folio, post charges and credits, record manual payments, and raise/resolve line-item disputes; guests dispute their own items. Routes: `GET /cms/reservations/{reservation}/folio`, `POST /cms/folios/{folio}/line-items`, `POST /cms/folios/{folio}/payments`, `PATCH /cms/folios/{folio}/line-items/{item}/dispute`, `PATCH /folio/items/{item}/dispute`; plus a payment-free close path on the existing settle shortcut and a guard on the legacy reservation-level settle. New permissions `folios.post`, `folios.dispute`. Three additive migrations (folio_items ledger columns, `folio_item_disputes`, `payments.idempotency_key`). The ledger is append-only: no PATCH/DELETE of money rows, corrections are credit rows. Out of scope: refunds, card/online payments, taxes, localized item descriptions, per-item categories, reopening settled folios, receivables/write-off.

### Locked Decisions

**Folio read (FOLIO-01)**
- **D-01:** `GET /cms/reservations/{reservation}/folio` inside the `cms/reservations` group under `permission:folios.view`; `AdminFolioController::showForReservation`, `FolioService::adminShow`. Pure read for any status; no auto-generate. Missing folio → new `FolioMissingException` (`folio_missing`, 404, context `{reservation_uuid, reservation_status}`); the dashboard then calls the existing generate route.
- **D-02:** One shape: `FolioResource` gains (all `whenLoaded`-guarded) `payments` (`PaymentResource::collection`), `paid_usd`, `balance_due_usd` (signed strings, 2dp, computed from the loaded relation, no queries in `toArray`), `open_disputes_count`. `FolioItemResource` gains `quantity`, `unit_price_usd`, `posted_by {uuid,name}|null`, `posted_at`, `reason`, `reverses_item_uuid`, `dispute` (latest: `{uuid, status, reason, raised_by: guest|staff, raised_at, resolved_at, resolution_note}|null`). Guest `GET /folio` shows dispute status per item for free.
- **D-03 (council):** `balance_due_usd = total_usd − Σ completed payments whose payable is the folio OR its reservation` (reservation-level deposits count). Implemented once: `Folio::ledgerPayments(): Builder`, `paidUsd()`, `balanceDueUsd()` using `bcadd`/`bcsub` at scale 2 on decimal strings (never float; signed, never clamped). `ReceiptService::paymentsFor` and `FolioResource` call these; the check-out gate stays status-based. Refunds are not subtracted (pinned by a test comment so the refund phase must revisit). Read path bound: `expectsDatabaseQueryCount(≤ 6)`.

**Manual line items (FOLIO-02)**
- **D-04 (council):** Additive migration on `folio_items`: `quantity` unsignedSmallInteger default 1; `unit_price_usd` decimal(10,2) nullable (null on generated rows); `posted_by` FK users nullOnDelete; `reason` string(255) nullable; `reverses_item_id` self FK **restrictOnDelete**; `idempotency_key` string(64) nullable; `source_line` unsignedSmallInteger default 0; narrow `source_type` to string(32); unique `(folio_id, idempotency_key)`; unique `(folio_id, source_type, source_id, source_line)`. Enum `FolioItemSource { RESERVATION, SERVICE_BOOKING, SERVICE_REQUEST, MANUAL, CREDIT }`; `amount_usd` stays the signed line total (`manual > 0`, `credit < 0`); posted rows have `source_id` null. No `voided_at`, no tax, no category; `subtotal_usd == total_usd`.
- **D-05 (council):** `POST /cms/folios/{folio}/line-items`, permission `folios.post` (new; every preset holding `folios.settle`, today `reception`, also gets it). `PostFolioItemRequest`: `{ kind: charge|credit (default charge), description: required|string|max:255, quantity: integer|min:1|max:999 (default 1), unit_price_usd: required|decimal:0,2|min:0.01|max:99999.99, reason: required_if:kind,credit|string|max:255, reverses_item_uuid: uuid|nullable (credit only, same folio) }`; optional `Idempotency-Key` header (D-08). `PostFolioItemAction::handle(Folio, User $poster, array $data)`: `DB::transaction` → `lockForUpdate` folio → **replay check first** (D-08) → status `open` else new `FolioSettledException` (`folio_settled`, 422, `{folio_uuid, settled_at}`); reservation status is not a guard; `amount = bcmul(quantity, unit_price, 2)` negated for credit; a credit referencing a charge must satisfy `|credit| + Σ existing credits referencing it ≤ charge.amount_usd` else 422 `folio_credit_exceeds_item`; whole-folio floor: `balanceDueUsd()` after the credit must be `≥ 0.00` else 422 `folio_credit_exceeds_balance`; insert (`source_type` manual|credit, `posted_by`), `Folio::recalculateTotals()` (D-07); 201 with the full `FolioResource`. No PATCH/DELETE routes for items, ever.
- **D-06 (council):** `GenerateFolioAction` reconciles instead of rebuilding: it takes its **own** `lockForUpdate` on the folio row on every path (guest `GET /folio`, admin generate, check-out; nested lock inside check-out's transaction is fine), then for each computed line `(source_type, source_id, source_line)` → `updateOrCreate` (touching `description`, `amount_usd` only, DECIMAL strings, no float casts); generated rows whose source is no longer billable are deleted **unless referenced by any `reverses_item_id` or any dispute**, in which case they are frozen (neither deleted nor repriced); `manual`/`credit` rows are never touched; totals via `recalculateTotals()`. Existing settled/checked-out guards unchanged. Item uuids are stable across refreshes; `ExpressCheckoutTest` exactly-once tests assert uuid stability across two refreshes.
- **D-07:** `Folio::recalculateTotals()` = one `SELECT COALESCE(SUM(amount_usd),0)` under the held lock, written to `subtotal_usd` and `total_usd`, formatted to 2dp (never a PHP sum of a stale collection). Concurrency test: interleaved `post → generate → post → credit → generate` from two staff users asserting `total_usd == SUM(items)` after every step; MySQL-grammar assertions (query listener proving the compiled SQL contains `for update`) for `GenerateFolioAction`, `PostFolioItemAction`, `RecordFolioPaymentAction` since SQLite ignores `lockForUpdate`.
- **D-08 (council):** `Idempotency-Key` header (≤ 64 chars, merged into the request as `idempotency_key`), optional on line items, **required** on payments (422 `idempotency_key_required`), stored per row with the unique indexes above. Replay with an identical full validated payload (items: kind, description, quantity, unit_price_usd, reason, reverses_item_uuid; payments: method, amount_usd, note, **recorded_by**) → 200 with the current `FolioResource`, no write; any difference → new `IdempotencyConflictException` (`idempotency_conflict`, 409, `{idempotency_key}`). Checked first under the lock; a unique-violation `QueryException` is re-read and returned as replay. No TTL. One shared `IdempotentWrite` helper used by both actions. Document that replay returns current folio state, not a byte-identical original body. Settle routes stay without the header this phase (deferred).

**Disputes (FOLIO-03)**
- **D-09 (council):** Table `folio_item_disputes`: `id`, `uuid`, `folio_item_id` FK cascadeOnDelete, `status` (`FolioDisputeStatus { OPEN, RESOLVED, REJECTED }`), `reason` string(500), `guest_id` FK nullable nullOnDelete, `user_id` FK nullable nullOnDelete (exactly one set, app-enforced), `resolved_by` FK users nullable nullOnDelete, `resolved_at`, `resolution_note` string(1000) nullable, timestamps; indexes `(folio_item_id, status)`, `status`. Model `FolioItemDispute` (`HasUuid`, `LogsActivity`); `FolioItem::disputes()` + `latestDispute()`; `Folio::disputes()` hasManyThrough; `FolioItemDispute::scopeOpen()`. One open dispute per item enforced under the folio lock (no partial index).
- **D-10:** Guest `PATCH /folio/items/{item}/dispute` in the existing `auth:guests` + `is_checked_in` group, body `{ reason: required|string|max:500 }`; item not on the guest's own folio → 404 `not_found` (Phase 4 precedent); allowed on open and settled folios; an open dispute already present → new `FolioItemDisputeOpenException` (`folio_item_dispute_open`, 422, `{item_uuid, dispute_uuid}`); re-dispute allowed after resolved/rejected. `RaiseFolioDisputeAction::handle(FolioItem, Guest|User, string)` locks the folio row only. 200 with `FolioItemResource`.
- **D-11:** Staff `PATCH /cms/folios/{folio}/line-items/{item}/dispute` with `scopeBindings()` (item outside folio → 404), permission `folios.dispute` (new; same presets as `folios.post`). Body `{ action: raise|resolve|reject, reason: required_if:action,raise|max:500, note: required_if:action,resolve,reject|max:1000 }`. `ResolveFolioDisputeAction` requires an open dispute else new `FolioDisputeStateException` (`folio_dispute_state`, 422, `{item_uuid, status}`); stamps resolver/at/note/status. Resolution never moves money; a refund-worthy dispute is settled by posting a credit line with `reverses_item_uuid` (documented in the guide). 200 with `FolioItemResource`.
- **D-12:** Flags, never a gate: `Folio::scopeWithOpenDisputes()`, `openDisputesCount()`, `open_disputes_count` in `FolioResource` and in the check-out response's reservation `folio` summary; `ReservationFilter` gains `has_open_disputes=1|0`. `CheckOutReservationAction` untouched; a test proves a settled folio with an open dispute checks out 200 and an open folio with a dispute is refused with `folio_unsettled` only.

**Payments (FOLIO-04)**
- **D-13 (council):** `POST /cms/folios/{folio}/payments`, permission `folios.settle`. `RecordFolioPaymentRequest`: `{ method: enum PaymentMethod, amount_usd: required|decimal:0,2|min:0.01|max:99999.99, note: nullable|max:1000 }`, `Idempotency-Key` required. `RecordFolioPaymentAction::handle(Folio, User $recorder, array $data, string $key)`: transaction → lock folio → replay check first (D-08) → `settled` → 422 `folio_settled` → `bccomp(amount, balanceDueUsd(), 2) === 1` → new `FolioOverpaymentException` (`folio_overpayment`, 422, `{balance_due_usd, amount_usd}`) (a zero-balance folio therefore rejects payments) → `RecordCashPaymentAction::handle($folio, ...)` with `idempotency_key` set in the same `Payment::create` (payable = Folio) → if `bccomp(balanceDueUsd(), '0.00', 2) <= 0` set `status = settled`, `settled_at = now()`, log `folio.auto_settled` `{folio_uuid, paid_usd}`. 201 with the full `FolioResource`; replay → 200 same shape. Migration: `payments.idempotency_key` string(64) nullable, unique `(payable_type, payable_id, idempotency_key)`. `RecordCashPaymentAction`/`PaymentService` float parameters become decimal strings (no `(float)` casts on the folio path).
- **D-14 (council):** Payment-free close path: `SettleFolioAction` (behind the existing `POST /cms/folios/{folio}/settle`) settles without a `Payment` row when `balanceDueUsd() <= 0.00`, logging `folio.settled_no_payment`; `SettleFolioRequest.amount_usd` becomes nullable (`decimal:0,2|min:0.01` when present). The legacy `POST /cms/reservations/{reservation}/settle` gains one guard: reservation's folio exists and is settled → 422 `folio_settled`. Other settle behaviour unchanged (tightening deferred). Documented: pre-departure money is taken through the reservation-level deposit route; the folio payments route is for the departure desk (auto-settle closes the folio; there is no reopen).
- **D-15:** Lock discipline: every Phase 5 writer locks only the folio row (reservation → folio order preserved for callers that also lock the reservation). `LogsActivity` on `FolioItem`, `Payment`, `FolioItemDispute` records causer (staff user or guest); explicit `activity()` entries `folio.auto_settled`, `folio.settled_no_payment`.

**Night-audit hook (Phase 9 reads only)**
- **D-16:** Ship and document: `Folio::scopeUnsettled()`, `scopeWithOpenDisputes()`, `FolioItemDispute::scopeOpen()`, `ledgerPayments()/paidUsd()/balanceDueUsd()`, `FolioResource.open_disputes_count`, `has_open_disputes` filter. Suggested Phase 9 queries recorded in the summary (unsettled departures; open disputes on in-house/checked-out stays).

**Contract, docs, tests**
- **D-17:** Actions `app/Actions/Folio/{PostFolioItemAction, RecordFolioPaymentAction, RaiseFolioDisputeAction, ResolveFolioDisputeAction}.php`; `FolioService` extended (`adminShow`, `adminPostItem`, `adminRecordPayment`, `adminDispute`, `guestDispute`); requests `app/Http/Requests/Folio/{PostFolioItemRequest, RecordFolioPaymentRequest, StaffFolioDisputeRequest, GuestFolioDisputeRequest}.php`; enums `FolioItemSource`, `FolioDisputeStatus`; exceptions (one per code) `FolioMissing` 404, `FolioSettled`, `FolioCreditExceedsItem`, `FolioCreditExceedsBalance`, `FolioOverpayment`, `FolioItemDisputeOpen`, `FolioDisputeState` (422), `IdempotencyConflict` 409, plus `idempotency_key_required` as a validation error; `App\Support\IdempotentWrite`; migrations `add_ledger_columns_to_folio_items_table`, `create_folio_item_disputes_table`, `add_idempotency_key_to_payments_table`; factories `FolioItemDisputeFactory`, `FolioItemFactory` states `manual()`/`credit()`. Lang keys in all five locales: `messages.{folio_item_posted, folio_payment_recorded, folio_dispute_raised, folio_dispute_resolved, folio_dispute_rejected, folio_settled_no_payment}`, `errors.{folio_missing, folio_settled, folio_credit_exceeds_item, folio_credit_exceeds_balance, folio_overpayment, folio_item_dispute_open, folio_dispute_state, idempotency_conflict, idempotency_key_required}`, attribute names, `BaseRequest::messages()` for `decimal`/`required_if` if missing.
- **D-18:** `docs/carlton-tree.html` node "folio line items · payments" → `api:true`, `ep`: `GET /cms/reservations/{r}/folio`, `POST /cms/folios/{f}/line-items`, `POST /cms/folios/{f}/payments`, `PATCH /cms/folios/{f}/line-items/{i}/dispute`, `PATCH /folio/items/{i}/dispute`; meta "charges · credits · disputes · payments". `API_GUIDE_DASHBOARD.md` Folios module retitled `(folios.view, folios.post, folios.settle, folios.dispute)` with the four staff routes, the `Idempotency-Key` contract, extended shapes, "corrections are credit rows, never edits", the settle close path and reservation-settle guard, error rows, `has_open_disputes` filter; `API_GUIDE_MOBILE.md` + `CHANGELOG_MOBILE_API.md`: additive item fields, new guest dispute route, non-breaking `GET /folio` changes (stable uuids, `paid_usd`, `balance_due_usd`, `open_disputes_count`). Postman: four staff + one guest request with an `Idempotency-Key` pre-request `{{$guid}}`. Summary lists `folios.post`, `folios.dispute`, preset changes, production notes (`[BLOCKING] php artisan migrate`, seeder re-run).
- **D-19:** Tests: `tests/Feature/Folio/{FolioReadTest, FolioLineItemTest, FolioPaymentTest, FolioDisputeTest}.php` (happy / 401 / 403 / 422 / 404, envelope), the D-07 interleaved invariant test, replay + 409 tests, replay-before-settled ordering, credit floors, frozen-row survival, overpayment, exact-balance auto-settle with log entry, boundary `10.00 − 9.99 − 0.01` without drift, prepaid folio closes without a payment then checks out 200, reservation-level settle refused after folio settled, disputes matrix (own 200, foreign 404, not checked-in 403 `no_active_reservation`, double-open 422, staff raise/resolve/reject, non-open 422, item outside folio 404, check-out not blocked); `tests/Unit/Folio/GenerateFolioReconcileTest` (manual/credit rows and disputes survive refresh, cancelled sources drop unless referenced, uuids stable, MySQL-grammar lock assertion); receipt balance equals `FolioResource.balance_due_usd`; `SeederTest`/`PermissionsGroupedTest` updated for the two permissions.

### Claude's Discretion
- Class/method names above are defaults; `ledgerPayments()` may live on a small `App\Support\FolioLedger` helper shared by receipt and resource.
- `recalculateTotals()` via DB `SUM` or `bcadd` over a freshly loaded collection (under the lock either way).
- Whether the close path lives in `SettleFolioAction` or a `MarkFolioSettledAction` shared with auto-settle; how `Idempotency-Key` reaches the FormRequest (prepareForValidation vs middleware).
- `dispute` on the item resource via `latestOfMany` or the last of the loaded collection; five-locale wording; Postman ordering; test method names.

### Deferred Ideas (OUT OF SCOPE)
- Tightening `POST /cms/folios/{folio}/settle` to the exact balance; `Idempotency-Key` on both settle routes
- Refunds (table exists), change-making/overpayment credit, online payments; reopening settled folios; receivables/write-off closing path
- Localized `folio_items.description`; taxes/service charges; per-item categories; guest withdrawing a dispute; `under_review` state; dispute notifications; dispute re-raise cap
- Freezing all generated rows after any payment activity (Skeptic); per-night room postings (the `source_line` column is ready for it); a MySQL CI job
</user_constraints>

<phase_requirements>
## Phase Requirements

| ID | Description | Research Support |
|----|-------------|------------------|
| FOLIO-01 | Staff read a reservation's folio (line items + payments); 404 `folio_missing` if none | `GenerateFolioAction`/`FolioService`/`FolioController` current bodies documented below; `FolioResource`/`FolioItemResource` current shapes and the exact fields D-02 adds; `Folio::ledgerPayments()`/`balanceDueUsd()` bcmath pattern (D-03); query-count precedent (`expectsDatabaseQueryCount`) from `AvailableRoomsTest` |
| FOLIO-02 | Post a charge/credit line to an open folio; totals recalced under lock; `folio_settled` on settled folios; credit floors; `Idempotency-Key` replay; reconcile-not-rebuild | Current `GenerateFolioAction` (rebuild-by-delete) fully quoted below with the exact reconcile changes needed; bcmath verified available (`php -m`); `decimal:0,2` Laravel validation rule verified in framework source; no existing idempotency/lock precedent in this codebase — new pattern documented with a proposed `IdempotentWrite` helper |
| FOLIO-03 | Line-item disputes (guest + staff), one open dispute per item, flags only, never a gate | Phase 4's 404-for-foreign-record precedent (`NotFoundException`, `EnsureIsCheckedIn`, `GuestEntitlement`) documented; `scopeBindings()` mechanics verified via Laravel docs web search; no `hasManyThrough`/`latestOfMany` precedent in codebase — flagged as new pattern |
| FOLIO-04 | Manual payment recording with idempotency, overpayment refusal, auto-settle, prepaid close path | Current `RecordCashPaymentAction`, `PaymentGatewayInterface`, `ManualDriver`, `SettleFolioAction`, `PaymentService.settleReservation` fully quoted; float-to-decimal-string migration path documented; `payments` migration/model current shape documented |
| DOCS-01 | Guides/Postman/tree updated each phase | Exact current `carlton-tree.html` folio node (mock state) and `API_GUIDE_DASHBOARD.md`/`API_GUIDE_MOBILE.md` Folio sections located and quoted; Postman "10 - Folio & Express Checkout" folder located |
| XCUT-01 | New permissions `{domain}.{action}`, seeded with presets, listed in summary | Current `RolesAndPermissionsSeeder` state (21 permissions, 7 presets) quoted; `SeederTest`/`PermissionsGroupedTest` exact assertions quoted so the planner knows precisely what changes |
</phase_requirements>

## Summary

This phase extends an existing, working folio/payment subsystem rather than building one from scratch. Today `Folio`, `FolioItem`, `Payment`, `Refund` models, their migrations, `GenerateFolioAction`, `SettleFolioAction`, `ApproveFolioAction`, `RecordCashPaymentAction`, `FolioService`, `ReceiptService`, `PaymentService`, both `FolioController`s, `PaymentController`, `FolioResource`/`FolioItemResource`/`PaymentResource`, `ReservationFilter`, `EnsureIsCheckedIn`, `GuestEntitlement` and `RolesAndPermissionsSeeder` all exist and are read in full below. The phase's job is: (1) turn `GenerateFolioAction` from a delete-and-rebuild into a reconcile-by-`(source_type,source_id,source_line)` `updateOrCreate`, because posted/manual/credit rows and disputed rows must survive a refresh; (2) introduce money math on DECIMAL **strings** via `bcmath` (verified installed, verified unused anywhere in this codebase today — this is new ground) instead of the current `(float)` casts throughout `GenerateFolioAction`, `RecordCashPaymentAction`, `ReceiptService::paymentsFor`; (3) add an idempotency mechanism that also does not exist anywhere in this codebase yet (no `Idempotency-Key` handling, no `QueryException`-based replay precedent) — D-08's shared `IdempotentWrite` helper is genuinely new infrastructure; (4) add a dispute subsystem (new table, new model, `hasManyThrough`, `scopeBindings()` on a nested route) that also has no precedent in this codebase; (5) wire two new permissions into the existing, well-tested `RolesAndPermissionsSeeder`/`SeederTest`/`PermissionsGroupedTest` triangle, which has exact hardcoded counts (21 permissions, 7 roles) that must be bumped precisely.

The biggest correctness risk is not the business rules (they're precisely specified in CONTEXT.md) but faithfully converting existing float-based money code to decimal-string bcmath without breaking the three passing test files that exercise the current code (`FolioTest`, `PaymentTest`, `ExpressCheckoutTest`) — all of which assert exact string totals like `'380.00'` today, so the reconcile rewrite must preserve output shape exactly while changing internal arithmetic. The second biggest risk is `GenerateFolioAction`'s new self-locking requirement interacting with `CheckOutReservationAction`, which already locks the same folio row before calling it — this is safe (same connection, same transaction, PostgreSQL/MySQL/SQLite all permit a session to re-acquire its own row lock) but must be verified with a lock-order test, not assumed.

**Primary recommendation:** Build the ledger math (`Folio::ledgerPayments()/paidUsd()/balanceDueUsd()`, `recalculateTotals()`) and the `IdempotentWrite` helper FIRST as isolated, directly-unit-tested pieces before wiring `PostFolioItemAction`/`RecordFolioPaymentAction`/`GenerateFolioAction` reconcile around them — every downstream action's correctness (credit floors, overpayment, auto-settle) depends on `balanceDueUsd()` being exactly right under bcmath from the start.

## Architectural Responsibility Map

| Capability | Primary Tier | Secondary Tier | Rationale |
|------------|-------------|----------------|-----------|
| Folio read/aggregate (balance, payments, disputes count) | API / Backend (Service + Model) | Database (SUM under lock) | `FolioService::adminShow` composes; `Folio::balanceDueUsd()` does the arithmetic; DB does the `lockForUpdate` + `SUM` |
| Line-item posting (charge/credit) | API / Backend (Action) | Database (unique indexes for idempotency + credit-reference FK) | `PostFolioItemAction` owns the transaction/lock/validation; DB enforces `(folio_id, idempotency_key)` and `(folio_id, source_type, source_id, source_line)` uniqueness as a second line of defense against races the app-level check might miss |
| Folio reconciliation (regenerating computed lines) | API / Backend (Action) | — | `GenerateFolioAction` is pure server-side computation from `Reservation`/`ServiceBooking`/`ServiceRequest` state; no client input |
| Payment recording + auto-settle | API / Backend (Action) | Database (idempotency unique index) | `RecordFolioPaymentAction` → `RecordCashPaymentAction` → `PaymentGatewayInterface` (currently a stub `ManualDriver`); DB is the idempotency backstop |
| Dispute state machine | API / Backend (Action + Model) | Database (one-open-dispute-per-item invariant, enforced under lock, no partial index per D-09) | `RaiseFolioDisputeAction`/`ResolveFolioDisputeAction` own the transition rules; DB has no unique constraint for "one open dispute" (deliberately app-enforced under lock per D-09) |
| Route-level ownership scoping (item belongs to folio; folio belongs to reservation's guest) | API / Backend (Routing + FormRequest) | — | `scopeBindings()` for staff nested routes; `FormRequest`/service-level guest_id comparison for the guest route (never trust the URL alone) |
| Permission gating | API / Backend (Middleware) | — | `permission:folios.post` / `permission:folios.dispute` middleware, seeded via `RolesAndPermissionsSeeder` |

## Standard Stack

No new third-party packages are introduced by this phase. The relevant "stack" is PHP core (`bcmath` extension) plus the framework primitives already in use.

### Core
| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| PHP `bcmath` extension | bundled with PHP 8.3 (already enabled — `php -m` confirms `bcmath` present) [VERIFIED: local `php -m`] | Arbitrary-precision decimal-string arithmetic (`bcmul`, `bcadd`, `bcsub`, `bccomp`) for money math, avoiding float rounding drift | D-03/D-05/D-07/D-13 all specify `bcmul`/`bcadd`/`bcsub`/`bccomp` at scale 2 explicitly; this is the only correct way to do exact-cent arithmetic in PHP without a third-party decimal library |
| `laravel/framework` ^13.8 [VERIFIED: `backend/composer.json`] | 13.8+ | Eloquent decimal casts, `decimal:0,2` validation rule, `lockForUpdate()`, `scopeBindings()`, `DB::transaction` | Already the project's framework; no upgrade needed |
| `phpunit/phpunit` ^12.5.12 [VERIFIED: `backend/composer.json`] | 12.5.12 | Test runner | Already in use |

### Supporting
| Library | Version | Purpose | When to Use |
|---------|---------|---------|-------------|
| None new | — | — | — |

### Alternatives Considered
| Instead of | Could Use | Tradeoff |
|------------|-----------|----------|
| `bcmath` string arithmetic | `brick/money` or a dedicated decimal-object library (this codebase's vendor tree already ships `brick/math` transitively, used internally by Laravel's `decimal` validation rule per `ValidatesAttributes.php`) | CONTEXT.md's decisions explicitly name `bcmul`/`bcadd`/`bcsub`/`bccomp` — a wrapper library would be an unrequested dependency and a deviation from the locked decision. Stick with `bcmath` function calls directly, optionally wrapped in a small `App\Support\Money`/`FolioLedger` helper (Claude's Discretion permits this) so call sites read `Money::add($a, $b)` instead of raw `bcadd($a, $b, 2)`. |

**Installation:**
```bash
# No installation needed — bcmath is already compiled into this PHP build.
php -m | grep -i bcmath   # confirms: bcmath
```

**Version verification:** `php -m` run locally on 2026-09-26 confirms `bcmath` present in the loaded extension list [VERIFIED: local shell]. `composer.json` confirms `"php": "^8.3"` and `"laravel/framework": "^13.8"` [VERIFIED: `backend/composer.json`]. No package registry lookup is needed since nothing is being installed.

## Package Legitimacy Audit

**Not applicable — this phase installs no external packages.** `bcmath` is a PHP core/bundled extension already enabled in the target environment, not a Composer dependency; there is no `composer.json` entry to audit and no registry verdict to obtain. `doctrine/dbal` is confirmed **not** installed (`composer.lock` shows it only as a sub-dependency of unrelated packages; `vendor/doctrine/` contains only `inflector`/`lexer`) [VERIFIED: local `composer.lock`/`vendor` inspection] — relevant because D-04's migration narrows `folio_items.source_type` to `string(32)` via a column `->change()`, and Laravel 11+ no longer requires `doctrine/dbal` for simple type/length changes on supported drivers [ASSUMED — based on Laravel 11's documented removal of the doctrine/dbal migration dependency for common alterations; not independently re-verified against Laravel 13's migration source in this session]. If `change()` proves fragile under SQLite in tests, the planner may skip narrowing `source_type` (it is a nice-to-have in D-04, not load-bearing) rather than adding a new dependency.

**Packages removed due to [SLOP] verdict:** none (nothing installed).
**Packages flagged as suspicious [SUS]:** none (nothing installed).

## Architecture Patterns

### System Architecture Diagram

```
                     STAFF (auth:users)                              GUEST (auth:guests + is_checked_in)
                            │                                                    │
        ┌───────────────────┼───────────────────┐                              │
        ▼                   ▼                   ▼                              ▼
GET /cms/reservations  POST /cms/folios/   POST /cms/folios/   PATCH /cms/folios/{f}/  PATCH /folio/items/
  /{r}/folio            {f}/line-items      {f}/payments        line-items/{i}/dispute  {i}/dispute
        │                   │                   │                       │                   │
        ▼                   ▼                   ▼                       ▼                   ▼
 FolioService          FolioService        FolioService           FolioService         FolioService
 ::adminShow           ::adminPostItem     ::adminRecordPayment   ::adminDispute       ::guestDispute
        │                   │                   │                       │                   │
        │                   ▼                   ▼                       ▼                   ▼
        │            PostFolioItemAction  RecordFolioPaymentAction ResolveFolioDisputeAction RaiseFolioDisputeAction
        │                   │                   │                  (or Raise, per `action`)        │
        │                   │                   │                       │                   │
        │           ┌───────┴───────┐   ┌───────┴────────┐              │                   │
        │           ▼               │   ▼                │              │                   │
        │   IdempotentWrite::check  │  IdempotentWrite::check            │                   │
        │   (replay? → 200 old)     │  (replay? → 200 old)               │                   │
        │           │               │   │                │              │                   │
        │           ▼               │   ▼                │              ▼                   ▼
        │   Folio::lockForUpdate ───┴── Folio::lockForUpdate ──── Folio::lockForUpdate (row only, D-15)
        │           │                       │                          │                   │
        │           ▼                       ▼                          ▼                   ▼
        │   status==open? else       balanceDueUsd() via         one open dispute    one open dispute
        │   FolioSettledException    ledgerPayments()/bcmath     per item? else       per item? else
        │           │                       │                    FolioItemDisputeOpen  FolioItemDisputeOpen
        │           ▼                       ▼                          │                   │
        │   bcmul(qty,price,2) ──►   bccomp(amt,balance,2)==1?          ▼                   ▼
        │   credit-floor checks      → FolioOverpaymentException  FolioItemDispute::create (status=open, causer=user|guest)
        │           │                       │
        │           ▼                       ▼
        │   FolioItem::create        RecordCashPaymentAction
        │   (source=manual|credit)   ::handle(folio, ..., idempotency_key)
        │           │                       │
        │           │                       ▼
        │           │                bccomp(newBalance,'0.00',2)<=0?
        │           │                → auto-settle (status=settled, settled_at, log folio.auto_settled)
        │           ▼                       │
        │   Folio::recalculateTotals() ◄────┘   (SELECT COALESCE(SUM(amount_usd),0) under lock)
        │           │
        └───────────┴─────────────────────────────────────────────────────────────► FolioResource
                                                                                     (payments, paid_usd,
                                                                                      balance_due_usd,
                                                                                      open_disputes_count)

Reconcile path (unchanged trigger points, changed internals):
  guest GET /folio ──┐
  admin POST /generate ├──► GenerateFolioAction::handle(reservation)
  CheckOutReservationAction ┘        │
                                      ▼
                          Folio::lockForUpdate() (own lock, D-06)
                                      │
                    ┌─────────────────┼──────────────────┐
                    ▼                 ▼                  ▼
          compute reservation   compute service    compute service
          total line            bookings lines     request lines
          (source_line=0)       (source_line=0      (source_line=0
                                 per booking id)      per request id)
                    │                 │                  │
                    └────────┬────────┴──────────────────┘
                             ▼
              updateOrCreate by (source_type, source_id, source_line)
                             │
              ┌──────────────┴───────────────┐
              ▼                               ▼
   no-longer-billable generated row   referenced by reverses_item_id
   AND not referenced/disputed        OR has a dispute row
   → DELETE                            → FREEZE (leave as-is)
              │                               │
              └──────────────┬────────────────┘
                             ▼
                Folio::recalculateTotals()
```

### Recommended Project Structure
```
backend/app/
├── Actions/Folio/
│   ├── GenerateFolioAction.php          # MODIFIED: rebuild → reconcile, own lock, bcmath
│   ├── SettleFolioAction.php            # MODIFIED: nullable amount, payment-free close (D-14)
│   ├── ApproveFolioAction.php           # UNCHANGED (guest express checkout)
│   ├── PostFolioItemAction.php          # NEW (D-05)
│   ├── RecordFolioPaymentAction.php     # NEW (D-13)
│   ├── RaiseFolioDisputeAction.php      # NEW (D-10)
│   └── ResolveFolioDisputeAction.php    # NEW (D-11)
├── Support/
│   ├── IdempotentWrite.php              # NEW (D-08) — shared replay/conflict helper
│   └── FolioLedger.php                  # OPTIONAL (Claude's discretion) — bcmath helpers if not inlined on Folio
├── Models/
│   ├── Folio.php                        # MODIFIED: ledgerPayments/paidUsd/balanceDueUsd/recalculateTotals/
│   │                                     #           scopeUnsettled/scopeWithOpenDisputes/openDisputesCount/disputes()
│   ├── FolioItem.php                    # MODIFIED: postedBy()/reversesItem()/disputes()/latestDispute() relations
│   ├── FolioItemDispute.php             # NEW (D-09)
│   └── Payment.php                      # UNCHANGED (idempotency_key added via $fillable + cast)
├── Enums/
│   ├── FolioItemSource.php              # NEW (D-04)
│   └── FolioDisputeStatus.php           # NEW (D-09)
├── Exceptions/
│   ├── FolioMissingException.php        # NEW (404)
│   ├── FolioSettledException.php        # NEW (422)
│   ├── FolioCreditExceedsItemException.php     # NEW (422)
│   ├── FolioCreditExceedsBalanceException.php  # NEW (422)
│   ├── FolioOverpaymentException.php    # NEW (422)
│   ├── FolioItemDisputeOpenException.php # NEW (422)
│   ├── FolioDisputeStateException.php   # NEW (422)
│   └── IdempotencyConflictException.php # NEW (409)
├── Http/
│   ├── Controllers/Admin/FolioController.php  # MODIFIED: + showForReservation, postItem, recordPayment, dispute
│   ├── Controllers/Api/FolioController.php    # MODIFIED: + dispute (guest)
│   ├── Requests/Folio/
│   │   ├── PostFolioItemRequest.php     # NEW
│   │   ├── RecordFolioPaymentRequest.php # NEW
│   │   ├── StaffFolioDisputeRequest.php # NEW
│   │   ├── GuestFolioDisputeRequest.php # NEW
│   │   └── SettleFolioRequest.php       # MODIFIED: amount_usd nullable
│   └── Resources/Folio/
│       ├── FolioResource.php            # MODIFIED: + payments, paid_usd, balance_due_usd, open_disputes_count
│       └── FolioItemResource.php        # MODIFIED: + quantity, unit_price_usd, posted_by, posted_at, reason,
│                                         #           reverses_item_uuid, dispute
├── Filters/ReservationFilter.php        # MODIFIED: + has_open_disputes filter
database/
├── migrations/
│   ├── ..._add_ledger_columns_to_folio_items_table.php   # NEW (D-04)
│   ├── ..._create_folio_item_disputes_table.php          # NEW (D-09)
│   └── ..._add_idempotency_key_to_payments_table.php     # NEW (D-13)
└── factories/
    ├── FolioItemFactory.php             # MODIFIED: + manual()/credit() states
    └── FolioItemDisputeFactory.php      # NEW
tests/
├── Feature/Folio/{FolioReadTest,FolioLineItemTest,FolioPaymentTest,FolioDisputeTest}.php  # NEW
└── Unit/Folio/GenerateFolioReconcileTest.php  # NEW
```

### Pattern 1: Reconcile-by-source instead of rebuild (D-06)

**What:** `GenerateFolioAction` currently deletes ALL `folio_items` and recreates them from scratch on every call (see Code Examples below — this is the exact current body). It must instead `updateOrCreate` keyed on `(folio_id, source_type, source_id, source_line)`, deleting only rows whose computed source disappeared AND that are unreferenced/undisputed, and never touching `manual`/`credit` rows at all.

**When to use:** Any regeneration trigger: guest `GET /folio`, admin `POST /cms/folios/{reservation}/generate`, and the check-out path inside `CheckOutReservationAction`.

**Example (target shape, not yet in codebase):**
```php
// Pattern sketch — not verbatim final code, illustrates the updateOrCreate key
foreach ($computedLines as $line) {
    $folio->items()->updateOrCreate(
        [
            'source_type' => $line['source_type'],
            'source_id'   => $line['source_id'],
            'source_line' => $line['source_line'] ?? 0,
        ],
        [
            'description' => $line['description'],
            'amount_usd'  => $line['amount_usd'], // decimal string, e.g. bcmul-derived
        ],
    );
}

// Then: delete generated rows whose source is gone, UNLESS referenced/disputed.
$stillBillableKeys = collect($computedLines)->map(fn ($l) => "{$l['source_type']}:{$l['source_id']}:" . ($l['source_line'] ?? 0));
$folio->items()
    ->whereIn('source_type', [FolioItemSource::RESERVATION->value, FolioItemSource::SERVICE_BOOKING->value, FolioItemSource::SERVICE_REQUEST->value])
    ->get()
    ->reject(fn ($item) => $stillBillableKeys->contains("{$item->source_type}:{$item->source_id}:{$item->source_line}"))
    ->each(function (FolioItem $item) {
        $isReferenced = FolioItem::where('reverses_item_id', $item->id)->exists() || $item->disputes()->exists();
        if (! $isReferenced) {
            $item->delete();
        }
        // else: leave frozen in place — never delete, never reprice.
    });
```

### Pattern 2: Ledger balance via bcmath under a held lock (D-03/D-07)

**What:** `Folio::balanceDueUsd()` must never do PHP float subtraction. It sums payments via `ledgerPayments()` (payable is the folio OR its reservation, matching `ReceiptService::paymentsFor`'s existing OR-query — see Code Examples) and subtracts from `total_usd` with `bcsub`, at scale 2, working entirely on the string values Eloquent's `decimal:2` cast already returns.

**When to use:** Every read/write path that needs "how much is still owed" — `FolioResource`, `PostFolioItemAction`'s balance floor, `RecordFolioPaymentAction`'s overpayment check and auto-settle check, `SettleFolioAction`'s payment-free close check, `ReceiptService`.

**Example:**
```php
// Source: this codebase's ReceiptService::paymentsFor (existing OR-query, to be reused as ledgerPayments())
public function ledgerPayments(): Builder
{
    return Payment::query()
        ->where(fn ($q) => $q->where('payable_type', self::class)->where('payable_id', $this->id))
        ->orWhere(fn ($q) => $q->where('payable_type', Reservation::class)->where('payable_id', $this->reservation_id))
        ->where('status', 'completed');
}

public function paidUsd(): string
{
    return $this->ledgerPayments()->get()->reduce(
        fn (string $carry, Payment $p) => bcadd($carry, (string) $p->amount_usd, 2),
        '0.00',
    );
}

public function balanceDueUsd(): string
{
    return bcsub((string) $this->total_usd, $this->paidUsd(), 2);
}
```

### Pattern 3: Idempotency via unique index + replay-on-conflict (D-08)

**What:** No precedent exists in this codebase for `Idempotency-Key` handling (verified: no `Idempotency-Key`, `idempotency_key`, or `QueryException`-based replay logic anywhere in `app/` before this phase). The pattern must be built from Laravel primitives: a nullable `idempotency_key` column with a unique composite index, a pre-write existence check under the lock, and a `QueryException` catch as backstop for a race between the check and the insert.

**When to use:** `PostFolioItemAction` (optional key) and `RecordFolioPaymentAction` (required key).

**Example (sketch):**
```php
class IdempotentWrite
{
    /**
     * @param  callable(): Model  $write  Performs the actual insert; runs only on a fresh key.
     * @param  callable(Model $existing): bool  $matches  True if $existing's stored payload equals the incoming request.
     */
    public static function handle(?string $key, Builder $existingQuery, callable $write, callable $matches): array
    {
        if ($key !== null) {
            $existing = $existingQuery->first();
            if ($existing) {
                if (! $matches($existing)) {
                    throw new IdempotencyConflictException(__('custom.errors.idempotency_conflict'), ['idempotency_key' => $key]);
                }
                return ['data' => $existing, 'replayed' => true];
            }
        }

        try {
            return ['data' => $write(), 'replayed' => false];
        } catch (QueryException $e) {
            // Unique-violation race: another request won between our check and our insert.
            if (self::isUniqueViolation($e) && $key !== null) {
                $existing = $existingQuery->firstOrFail();
                return ['data' => $existing, 'replayed' => true];
            }
            throw $e;
        }
    }
}
```
`QueryException::getCode()` for a unique-constraint violation is driver-specific: SQLite reports SQLSTATE `23000` with an error message containing `UNIQUE constraint failed`; MySQL reports SQLSTATE `23000` with driver error code `1062`. [ASSUMED — standard, well-documented Laravel/PDO behavior, not independently re-verified against this exact PHP/PDO build in this session; recommend a quick isolated test asserting the exception shape on SQLite before relying on it, since this project's tests run on SQLite `:memory:` and any MySQL-only behavior needs the `MySQL-only backstop` docblock convention this project already uses for lock assertions.]

### Pattern 4: `scopeBindings()` for nested staff routes (D-11)

**What:** No `scopeBindings()` usage exists anywhere in `routes/api.php` today (verified via grep) — this is new to the codebase. Laravel's automatic child-scoping for implicit route-model binding requires the **parent model to expose a relationship named as the plural of the child route parameter** (or an explicit key), and both `Folio`/`FolioItem` already use `uuid` as a custom route key via `HasUuid` [CITED: Laravel 13.x routing docs — laravel.com/docs/routing#scoping-eloquent-relationships]. For `Route::patch('/cms/folios/{folio}/line-items/{item}/dispute', ...)`, naming the second parameter `{item}` (not `{lineItem}` or similar) matters because `Folio::items()` is the existing relation name, and Laravel's convention-based guess pluralizes the parameter name to find it.

**When to use:** The staff dispute route only (D-11 explicitly calls for it). Apply `->scopeBindings()` on the route or route group explicitly rather than relying solely on the custom-key auto-scoping, since the auto-scoping behavior with custom keys is a documented-but-subtler mechanism [CITED: Laravel routing docs] and an explicit `.scopeBindings()` call plus a dedicated "item outside this folio → 404" feature test removes ambiguity.

**Example:**
```php
// routes/api.php — sketch
Route::middleware('auth:users')->prefix('cms/folios')->scopeBindings()->group(function () {
    Route::middleware('permission:folios.dispute')->group(function () {
        Route::patch('/{folio}/line-items/{item}/dispute', [AdminFolioController::class, 'dispute']);
    });
});
```
A belt-and-suspenders explicit check inside the controller/service (`abort_unless($item->folio_id === $folio->id, 404)` or throwing `NotFoundException`) is recommended as a fallback regardless of `scopeBindings()`, matching this project's general preference for explicit domain exceptions over relying purely on framework 404 behavior — and it removes any doubt about the auto-scoping edge case.

### Anti-Patterns to Avoid
- **Float money math anywhere on the folio path:** The current `GenerateFolioAction` casts every amount `(float)`, `ReceiptService::paymentsFor`'s caller sums with `->sum(fn ($p) => (float) $p->amount_usd)`, and `RecordCashPaymentAction`/`PaymentService` take `float $amount` parameters. All of this must become decimal-string bcmath on the Phase 5 path (D-03/D-13 are explicit: "never float"). Existing non-folio float usage elsewhere in the codebase is out of scope to change.
- **Rebuilding the folio (current behavior) instead of reconciling:** The current `$folio->items()->delete()` followed by full recreation is exactly the bug D-06 exists to fix — it already causes the class of bug the two `ExpressCheckoutTest::test_express_checkout_builds/refreshes_a_folio_exactly_once` regression tests guard against (added in Phase 3 hardening) for the *check-out* path; extending it to line items/disputes would blow away manual/credit/disputed rows, which is precisely what D-06 forbids.
- **Trusting `GuestEntitlement::currentReservation()` for dispute ownership:** This method `sortByDesc('check_in')->first()`s across all booked reservations and is a known-buggy carry-forward (FA-06-1, documented in `.planning/STATE.md` and `03-CONTEXT.md`) that can resolve the wrong stay. The guest dispute route (D-10) must verify ownership by comparing `$item->folio->reservation->guest_id` directly against the authenticated guest's id, not by asking `GuestEntitlement` "what's your current reservation."
- **Reusing the generic `ReservationStateException` for folio state errors:** `SettleFolioAction` currently throws `ReservationStateException` (`reservation_state`) when a folio is already settled — this is a pre-existing shortcut that predates this phase's error-code contract. D-05/D-13 require a dedicated `FolioSettledException` (`folio_settled`); the planner should replace the existing misuse in `SettleFolioAction` too so the error code is consistent across every "already settled" path (existing `FolioTest::test_settling_already_settled_folio_returns_422` currently asserts `reservation_state` and **will need updating** to assert `folio_settled` — this is a deliberate, in-scope contract change per D-14, not an accidental regression).

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Exact-cent decimal arithmetic | A custom `Money` value object with internal float storage, or manual `round()` chains | PHP's `bcmath` functions (`bcmul`, `bcadd`, `bcsub`, `bccomp`) on the decimal-cast strings Eloquent already returns | `bcmath` is already installed, needs no dependency, and is exactly what the locked decisions specify; float `round()` chains reintroduce the exact drift class this phase exists to eliminate (e.g. `10.00 − 9.99 − 0.01` must land on exactly `0.00`, a case explicitly named in D-19's test list) |
| Idempotent-write detection | A custom in-memory cache of "recently seen keys" or a Redis-based dedupe (no Redis in this stack) | A unique composite DB index (`(folio_id, idempotency_key)` / `(payable_type, payable_id, idempotency_key)`) plus a `QueryException`-driven replay read, matching this project's SQLite-testing / MySQL-production duality | An in-memory cache doesn't survive across app server instances or restarts and doesn't protect against a true concurrent double-submit the way a DB unique constraint does; this project has no Redis dependency to introduce one |
| Nested-resource ownership checking | Manually querying `Folio::find($folioUuid)->items->firstWhere('uuid', $itemUuid)` in the controller with hand-rolled 404 logic | Laravel's `scopeBindings()` (Pattern 4) plus an explicit fallback assertion | Route-level scoping is a one-line, framework-tested mechanism; hand-rolling it in the controller duplicates logic across the two dispute-mutation entry points (staff nested, guest flat) inconsistently |
| One-open-dispute-per-item enforcement | A unique partial index (`WHERE status='open'`) — SQLite and MySQL support partial/filtered unique indexes differently and this project tests exclusively against SQLite in-memory | Application-level check under the folio row lock (D-09 explicitly says "no partial index") | D-09 already made this call: partial unique indexes are non-portable across this project's SQLite-test/MySQL-prod split, and the lock already serializes concurrent dispute-raise attempts on the same folio |
| Enum-backed status/source columns | Raw strings compared ad hoc (`if ($item->source_type === 'manual')`) | PHP backed enums (`FolioItemSource`, `FolioDisputeStatus`), matching the existing `FolioStatus`/`PaymentMethod`/`CheckOutMode` pattern already in this codebase (`app/Enums/*.php`, each using the `HasValues` trait for a `values()` helper) | Consistent with every other status field in this codebase; `ReservationFilter`'s existing `folio_status` handling already calls `FolioStatus::values()` for exactly this reason |

**Key insight:** Every "don't hand-roll" item above already has a directly analogous, working precedent somewhere else in this codebase (enums, filters, domain exceptions, lock-then-check actions) — the work in this phase is applying those existing patterns to money/idempotency/disputes, not inventing new architecture.

## Common Pitfalls

### Pitfall 1: Float money math silently reintroduced during the bcmath migration
**What goes wrong:** A single missed spot — e.g. `GenerateFolioAction`'s `(float) $reservation->total_usd` or `ReceiptService`'s `->sum(fn ($p) => (float) $p->amount_usd)` — left un-migrated causes a cent-level drift that only shows up on specific boundary amounts (repeating binary fractions like `0.1 + 0.2`), passing most tests while failing the specific boundary test D-19 calls for (`10.00 − 9.99 − 0.01`).
**Why it happens:** This codebase's *only* existing money code is float-based (confirmed: no `bcmath` usage anywhere in `app/` before this phase), so "the pattern already in the file" is the wrong pattern to copy from during this phase.
**How to avoid:** Grep the diff for `(float)` and `(int)` casts touching any `*_usd` variable before considering the phase done; write the boundary-drift test (`10.00 − 9.99 − 0.01 == 0.00`) early, not last.
**Warning signs:** Any new code in `PostFolioItemAction`/`RecordFolioPaymentAction`/`GenerateFolioAction` that does `$a - $b` or `(float)` on money instead of `bcsub`/`bcmul`.

### Pitfall 2: `GenerateFolioAction`'s new self-lock deadlocking or double-locking with `CheckOutReservationAction`
**What goes wrong:** `CheckOutReservationAction::handle()` already does `Folio::where('reservation_id', ...)->lockForUpdate()->first()` **before** calling `$this->generateFolio->handle($locked)` (see exact current body below). If `GenerateFolioAction` now also takes its own `lockForUpdate()` on the same row inside the same connection/transaction, this is safe (a transaction can re-acquire its own row lock) — but the planner must not assume this without a test, since a *naive* re-implementation might accidentally open a **new** transaction/connection for the nested lock (e.g. via a queued job or a second `DB::transaction()` on a different connection), which would deadlock against the outer lock.
**Why it happens:** D-06 says "nested lock inside check-out's transaction is fine" — true only because `DB::transaction()` nests via savepoints on the *same* connection in Laravel; a new top-level `DB::transaction()` call inside `GenerateFolioAction::handle()` on the *same* facade/connection is fine, but any refactor that resolves a different DB connection would not be.
**How to avoid:** Keep `GenerateFolioAction::handle()` using the default `DB::transaction()` / default connection exactly as today; write an explicit test that check-out (which already locks + calls generate) does not deadlock or throw, plus the MySQL-grammar `for update` assertion D-07 calls for.
**Warning signs:** Any timeout or deadlock exception surfacing only under the MySQL test path (not reproducible on SQLite, since SQLite's `lockForUpdate()` is a documented no-op in this project's test environment).

### Pitfall 3: `SettleFolioAction`'s existing settled-guard exception code is wrong for the new contract
**What goes wrong:** `SettleFolioAction::handle()` today throws `ReservationStateException` (`error_code: reservation_state`) when the folio is already settled. `FolioTest::test_settling_already_settled_folio_returns_422` currently asserts exactly this. D-05/D-13/D-14 all standardize on `folio_settled` as the code for "this folio is already settled" everywhere. If the planner adds `FolioSettledException` for the *new* line-item/payment paths but leaves `SettleFolioAction`'s old exception alone, the API will have two different error codes for the same underlying condition depending on which route hit it — an inconsistency a client would have to special-case.
**Why it happens:** `SettleFolioAction` predates this phase's exception-per-domain-condition contract; it was written before `folios.post`/dispute existed and reused a coarser, pre-existing exception.
**How to avoid:** Replace `SettleFolioAction`'s `ReservationStateException` throw with the new `FolioSettledException`, and update `FolioTest::test_settling_already_settled_folio_returns_422`'s assertion from `'error_code' => 'reservation_state'` to `'error_code' => 'folio_settled'` as an explicit, deliberate part of this phase's diff (not an accidental break — call it out in the phase summary's contract-change table per DOCS-01, since `error_code` values are a stated frozen contract per `PITFALLS.md` Pitfall 10, and this is a deliberate, documented exception to that rule).
**Warning signs:** The full suite failing on `FolioTest::test_settling_already_settled_folio_returns_422` after the change — this is expected and the test must be updated, not reverted.

### Pitfall 4: `PaymentGatewayInterface::charge()`'s `float $amount` parameter is a shared interface, not folio-only code
**What goes wrong:** D-13 says "`RecordCashPaymentAction`/`PaymentService` float parameters become decimal strings" but `RecordCashPaymentAction::handle()` calls `$this->gateway->charge($method, $amount, [...])`, and `PaymentGatewayInterface::charge(string $method, float $amount, array $context = []): array` is a shared interface also used by the pre-existing `PaymentTest`/`PaymentService::settleReservation()` (the legacy, non-folio reservation-level settle path) and its only implementation, `ManualDriver`. Changing the interface's type hint from `float` to `string` is a breaking signature change that ripples to every caller and to the `PaymentTest::test_interface_resolves_manual_driver` test, which currently calls `$driver->charge('cash', 100.0)` with a float literal.
**Why it happens:** The interface is shared infrastructure between the legacy `/cms/reservations/{r}/settle` route (untouched business logic per D-14, "other settle behaviour unchanged") and the new folio payment path — CONTEXT.md's instruction to convert "float parameters" to "decimal strings" is scoped to "the folio path" but the interface itself is not folio-specific.
**How to avoid:** Two viable approaches — (a) widen `PaymentGatewayInterface::charge()`'s `$amount` parameter type to `string|float` (or `int|string|float` matching PHP's numeric union) so both callers keep working while `RecordCashPaymentAction`'s own internal handling of the value it receives becomes string-safe, or (b) keep the interface `float` but ensure the folio path only ever converts an already-precise decimal string to float at the single `charge()` call boundary and does its own balance/overpayment/credit-floor math in bcmath *before* that boundary (float is only used to satisfy the interface for the amount actually being charged, never for a comparison or accumulation). Approach (b) is lower-risk since it changes no interface and no existing test. Document whichever is chosen in the phase summary since CONTEXT.md's wording under-specifies it (flagged in Open Questions below).
**Warning signs:** `PaymentTest::test_interface_resolves_manual_driver` or `PaymentTest::test_cash_settlement_creates_payment_and_records_actor` failing after this phase's changes — both must stay green (D-19 requires the full existing suite green).

### Pitfall 5: The `decimal:0,2` validation rule and Eloquent's `decimal:2` cast are two different Laravel features
**What goes wrong:** The FormRequests use the *validation* rule `decimal:0,2` (verified present in `Illuminate\Validation\Concerns\ValidatesAttributes::validateDecimal()`, comparing `decimal_places >= 0 && <= 2`), while the models use the *cast* `'amount_usd' => 'decimal:2'` (an Eloquent attribute cast that formats the stored/retrieved value to a fixed-scale string). These are unrelated mechanisms with similar names; conflating them (e.g. assuming the validation rule also rounds the incoming value to 2dp before it reaches the action) is a mistake — the validation rule only *checks* decimal-place count, it does not transform the value. `PostFolioItemAction`/`RecordFolioPaymentAction` must not skip explicit `bcmul`/`bcadd` normalization on the assumption validation already normalized the string.
**Why it happens:** The naming similarity (`decimal:0,2` vs `decimal:2`) invites the assumption they're the same feature.
**How to avoid:** Treat the validated `unit_price_usd`/`amount_usd` request values as untrusted raw strings needing explicit `bcmul($quantity, $unitPrice, 2)` computation, never as pre-normalized decimals.
**Warning signs:** A test posting `unit_price_usd: "10.5"` (one decimal place, valid per `decimal:0,2`) producing an unexpected line total if downstream code assumed two-decimal-place input.

### Pitfall 6: `folio_items.source_type` is currently an unconstrained `string()` column, not an enum column
**What goes wrong:** The existing migration (`2026_07_12_200001_create_folio_items_table.php`) declares `source_type` as `$table->string('source_type')` with no length limit and no CHECK/enum constraint at the DB level — values like `'reservation'`, `'service_booking'`, `'service_request'` are written as plain strings today by `GenerateFolioAction`. D-04's new `FolioItemSource` PHP enum (`RESERVATION, SERVICE_BOOKING, SERVICE_REQUEST, MANUAL, CREDIT`) must have `->value` strings that exactly match the existing literal strings already in the database for old rows to keep working after this migration (i.e. `FolioItemSource::RESERVATION->value` must equal `'reservation'`, not e.g. `'RESERVATION'` or `'Reservation'`) — otherwise `updateOrCreate`'s reconcile keying breaks for every pre-existing row.
**Why it happens:** The literal strings are currently hardcoded inline in `GenerateFolioAction` (`'source_type' => 'reservation'`, etc.) rather than backed by an enum, so there is no compile-time link forcing the new enum's values to match.
**How to avoid:** Set the new enum's case values to the exact existing lowercase snake-case strings (`'reservation'`, `'service_booking'`, `'service_request'`, `'manual'`, `'credit'`), and add a migration-safety test asserting `Folio_Item::where('source_type', FolioItemSource::RESERVATION->value)` matches rows created by the *old* code path (or, since this is a fresh v1 milestone with a currently-empty production folios table per `STATE.md`, confirm with the team whether backward data compatibility is even a real concern — flagged in Open Questions).
**Warning signs:** Any test seeding a `FolioItem` with a raw string literal for `source_type` that doesn't match the new enum's values.

### Pitfall 7: `SeederTest`/`PermissionsGroupedTest` have exact hardcoded counts that must be updated precisely, not approximately
**What goes wrong:** `SeederTest::test_all_21_permissions_seeded()` asserts `assertCount(21, ...)` and lists all 21 permission names explicitly; `test_seeder_idempotent()` asserts the `content_editor`/`content_manager` role permission counts (3 and 4) explicitly. Adding `folios.post`/`folios.dispute` bumps the total to 23, but if the planner only appends the new permissions to the `$permissions` array in the seeder and forgets to update `SeederTest`'s hardcoded `21`/`23` expectations and permission list, the full suite (which D-19 requires green) fails on a test that has nothing to do with folios.
**Why it happens:** This is a deliberately strict "closed list" test pattern this project uses everywhere (see also `test_all_7_role_presets_seeded`) specifically to catch permission sprawl (per `PITFALLS.md` Pitfall 9) — it is working as intended, but it *will* fail on any addition until updated.
**How to avoid:** Update `SeederTest::test_all_21_permissions_seeded` (rename method and array/count to reflect 23), confirm `test_every_seeded_permission_is_reachable_through_some_role`'s role-less exclusion list doesn't need `folios.post`/`folios.dispute` added (it doesn't — both are granted via the `reception` preset per D-05/D-11, so they're reachable), and check whether `PermissionsGroupedTest`'s existing `folios` module assertion (not directly quoted in the current file — it groups by permission-name prefix before the first `.`) needs a new assertion for the enlarged `folios` group's permission list, though the group *count* (10) does not change since `folios` already exists as a module.
**Warning signs:** `SeederTest`/`PermissionsGroupedTest` failing after a folios-only change — this is the intended tripwire, fix by updating the test, not by working around it.

### Pitfall 8: `PaymentResource` doesn't extend `App\Base\BaseResource` (pre-existing inconsistency, not introduced by this phase)
**What goes wrong:** Every other resource in this codebase extends `App\Base\BaseResource` per the `tupcode-laravel-backend` skill's stated convention, but `PaymentResource` extends `Illuminate\Http\Resources\Json\JsonResource` directly. This is a pre-existing inconsistency, not something this phase caused, but since `PaymentResource::collection()` is being newly embedded inside `FolioResource` (D-02), the planner should not "fix" this as an unplanned refactor mid-phase (out of scope) but should be aware it's not a bug to chase — it currently works fine because `BaseResource`'s only meaningful behavior beyond `JsonResource` is not exercised by `PaymentResource`'s simple shape.
**Why it happens:** Predates this phase (P5/original folio implementation, before the `tupcode-laravel-backend` skill's convention was written down).
**How to avoid:** Leave `PaymentResource`'s base class alone; don't scope-creep into an unrelated convention fix during this phase.
**Warning signs:** A plan task titled "fix PaymentResource to extend BaseResource" — this is unrequested scope.

## Code Examples

Verified patterns from this codebase's own current source (not third-party docs — this phase extends existing code, so "current state" is the primary source):

### Current `GenerateFolioAction` (rebuild-by-delete — to be replaced by reconcile)
```php
// Source: backend/app/Actions/Folio/GenerateFolioAction.php (current, full file, read 2026-09-26)
public function handle(Reservation $reservation): array
{
    return DB::transaction(function () use ($reservation) {
        $folio = Folio::firstOrCreate(
            ['reservation_id' => $reservation->id],
            ['status' => FolioStatus::OPEN]
        );

        if ($folio->status === FolioStatus::SETTLED) {
            return ['data' => $folio->load('items'), 'code' => 200];
        }

        if ($reservation->status === ReservationStatus::CHECKED_OUT && ! $folio->wasRecentlyCreated) {
            return ['data' => $folio->load('items'), 'code' => 200];
        }

        // Regenerate line items fresh each call — folio reflects current charges, not accumulated history.
        $folio->items()->delete();   // <-- D-06: this line must go; manual/credit/disputed rows must survive

        $lines = [[
            'description' => __('custom.messages.folio_room_charge'),
            'amount_usd'  => (float) $reservation->total_usd,   // <-- D-06/D-03: must become a decimal string
            'source_type' => 'reservation',
            'source_id'   => $reservation->id,
        ]];
        // ... service_booking / service_request loops, each also (float)-casting amount_usd ...

        foreach ($lines as $line) {
            $folio->items()->create($line);   // <-- becomes updateOrCreate keyed by (source_type, source_id, source_line)
        }

        $total = array_sum(array_column($lines, 'amount_usd'));   // <-- D-07: must become recalculateTotals() DB SUM
        $folio->update(['subtotal_usd' => $total, 'total_usd' => $total]);

        return ['data' => $folio->fresh()->load('items'), 'code' => 200];
    });
}
```

### Current `CheckOutReservationAction`'s folio lock/generate call (the nested-lock interaction, Pitfall 2)
```php
// Source: backend/app/Actions/Booking/CheckOutReservationAction.php (current, lines ~66-73)
$folio = Folio::where('reservation_id', $locked->id)->lockForUpdate()->first();

if (! $folio || $folio->status === FolioStatus::OPEN) {
    // Still checked_in here, so GenerateFolioAction builds / refreshes items.
    $this->generateFolio->handle($locked);   // <-- D-06: GenerateFolioAction will now ALSO lockForUpdate the same row
    $folio = Folio::where('reservation_id', $locked->id)->lockForUpdate()->firstOrFail();
}
```

### Current `SettleFolioAction` (target for the D-14 payment-free close path and the `folio_settled` exception fix)
```php
// Source: backend/app/Actions/Folio/SettleFolioAction.php (current, full file)
public function handle(Folio $folio, string $method, float $amount, User $recorder, ?string $notes = null): array
{
    return DB::transaction(function () use ($folio, $method, $amount, $recorder, $notes) {
        $locked = Folio::where('id', $folio->id)->lockForUpdate()->firstOrFail();

        if ($locked->status === FolioStatus::SETTLED) {
            throw new ReservationStateException(__('custom.errors.reservation_state'));  // <-- Pitfall 3: becomes FolioSettledException
        }

        $this->recordCashPayment->handle($locked, $method, $amount, $recorder, $notes);  // <-- D-14: skip entirely when amount is null/balance already 0

        $locked->update(['status' => FolioStatus::SETTLED, 'settled_at' => now()]);

        return ['data' => $locked->fresh()->load(['items', 'payments']), 'code' => 200];
    });
}
```

### Current `RecordCashPaymentAction` (float parameter, to become decimal-string on the folio path per Pitfall 4)
```php
// Source: backend/app/Actions/Payment/RecordCashPaymentAction.php (current, full file)
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
            // 'idempotency_key' => ... // NEW: D-13 requires this set in the same create() call for the folio path
        ]);
        // ... pending → confirmed transition (unrelated to folio path) ...
        return ['data' => $payment, 'code' => 200];
    });
}
```

### Existing 404-for-foreign-guest-record precedent (Phase 4, to reuse for D-10's guest dispute route)
```php
// Pattern precedent: this codebase already throws NotFoundException (404 'not_found') for
// a guest accessing another guest's record — the same pattern applies to
// "item not on the guest's own folio". See app/Exceptions/NotFoundException.php:
class NotFoundException extends DomainException
{
    public function errorCode(): string { return 'not_found'; }
    public function statusCode(): int { return 404; }
}
```

### Existing `ReservationFilter::applyConditions()` override precedent (model for the new `has_open_disputes` filter)
```php
// Source: backend/app/Filters/ReservationFilter.php (current, full file's relevant method)
protected function applyConditions(Builder $query): void
{
    parent::applyConditions($query);

    if (! array_key_exists('folio_status', $this->params)) {
        return;
    }
    $value = $this->params['folio_status'];
    if ($this->isBlank($value)) {
        return;
    }
    if (! is_string($value) || ! in_array($value, FolioStatus::values(), true)) {
        throw $this->reject('folio_status', __('custom.validation.in', ['attribute' => 'folio_status']));
    }
    $query->whereHas('folio', fn (Builder $q) => $q->where('status', $value));
}
// D-12's has_open_disputes=1|0 filter follows the same shape: array_key_exists check,
// isBlank early-return, then $query->whereHas('folio.disputes', fn ($q) => $q->where('status', 'open'))
// for the =1 case, or whereDoesntHave for =0.
```

## State of the Art

| Old Approach (this codebase, today) | New Approach (this phase) | When Changed | Impact |
|--------------------------------------|----------------------------|---------------|--------|
| `GenerateFolioAction` deletes all items, recreates from scratch, no lock | Reconcile via `updateOrCreate` keyed by `(source_type,source_id,source_line)`, own `lockForUpdate` | This phase (D-06) | Manual/credit/disputed rows survive refresh; item uuids stable across refreshes; concurrent generate calls serialize |
| Float casts for all money (`(float) $x`) | `bcmath` string arithmetic (`bcmul`, `bcadd`, `bcsub`, `bccomp`) at scale 2 | This phase (D-03/D-07/D-13), folio path only | Eliminates float rounding drift on boundary amounts; legacy reservation-level settle path (`PaymentService::settleReservation`) is explicitly NOT required to change ("other settle behaviour unchanged" per D-14) |
| `SettleFolioAction` throws generic `ReservationStateException` for "already settled" | Dedicated `FolioSettledException` (`folio_settled`) used consistently across settle/post-item/post-payment | This phase (D-05/D-13/D-14) | `FolioTest::test_settling_already_settled_folio_returns_422` must be updated — a deliberate, documented contract change |
| No idempotency mechanism anywhere in this codebase | `Idempotency-Key` header + unique DB index + replay-on-conflict, via a shared `IdempotentWrite` helper | This phase (D-08), new infrastructure | First idempotency pattern in the codebase; likely to be reused by future money-touching phases (event deposits per `PITFALLS.md` Pitfall 11) |
| No dispute/complaint subsystem | `folio_item_disputes` table, `FolioItemDispute` model, `hasManyThrough` on `Folio`, `scopeBindings()` on a nested staff route | This phase (D-09/D-10/D-11), new infrastructure | First `hasManyThrough`/`scopeBindings()` usage in the codebase |

**Deprecated/outdated:**
- The current `GenerateFolioAction` rebuild-by-delete behavior is fully superseded by D-06's reconcile behavior; no code path should keep the old delete-then-recreate logic after this phase.

## Assumptions Log

| # | Claim | Section | Risk if Wrong |
|---|-------|---------|---------------|
| A1 | Laravel 11+ no longer requires `doctrine/dbal` for a simple `->change()` column-length alteration (used to narrow `folio_items.source_type` to `string(32)`) on both SQLite and MySQL grammars | Package Legitimacy Audit | If wrong, the migration throws on `php artisan migrate` in tests (SQLite) or in production (MySQL) without `doctrine/dbal` installed; low-severity since D-04's `source_type` narrowing is a nice-to-have, easily dropped from the migration without affecting any locked business rule |
| A2 | SQLite reports SQLSTATE `23000` with message text containing `UNIQUE constraint failed`, and MySQL reports SQLSTATE `23000` with driver error code `1062`, for unique-constraint violations caught via `QueryException` | Pattern 3 (Idempotency) | If the detection logic (`isUniqueViolation()`) doesn't match the actual exception shape, a genuine idempotency race falls through to an uncaught 500 instead of a clean replay-200; must be verified with a direct concurrency test before trusting it, exactly as D-19 calls for |
| A3 | Laravel's automatic child-scoping for implicit route-model binding with a custom route key (`uuid`) will correctly 404 `{item}` when it doesn't belong to `{folio}`, using the convention-guessed relationship name derived from the route parameter (`item` → `items`, matching `Folio::items()`) | Pattern 4 (scopeBindings) | If the convention guess doesn't match or the auto-scoping-with-custom-keys behavior differs from the docs summary, the staff dispute route could 200 on a cross-folio item instead of 404ing — mitigated by the recommended explicit fallback check in the controller/service, which the planner should implement regardless of whether `scopeBindings()` alone would have worked |
| A4 | Whether existing production folio data (if any exists outside this dev SQLite database) needs backward-compatible `source_type` string values is not addressed in CONTEXT.md, and this milestone's `STATE.md`/`ROADMAP.md` imply a still-in-development v1 backend with no live production data yet | Pitfall 6 | If wrong (production already has real folio rows), the new `FolioItemSource` enum's case values must exactly match today's literal strings (`'reservation'`, `'service_booking'`, `'service_request'`) — recommend setting them that way regardless as a zero-cost safety measure |

**If this table is empty:** N/A — see above.

## Open Questions

1. **Should `PaymentGatewayInterface::charge()`'s `$amount` parameter type change from `float` to a wider type, or should the folio path convert to float only at the interface boundary?**
   - What we know: D-13 explicitly says "`RecordCashPaymentAction`/`PaymentService` float parameters become decimal strings (no `(float)` casts on the folio path)," but the interface is shared with the untouched legacy reservation-settle path and its only test (`PaymentTest::test_interface_resolves_manual_driver`) calls `charge('cash', 100.0)` with a float literal.
   - What's unclear: Whether CONTEXT.md intends an interface signature change (breaking, ripples to `ManualDriver` and any future gateway) or only an internal-to-the-action change with a float cast retained solely at the `$gateway->charge()` call site.
   - Recommendation: Default to Pitfall 4's approach (b) — keep the interface's `float $amount` unchanged, do all folio-side math (credit floors, overpayment comparison, balance calculation) in bcmath on decimal strings, and only produce a `float` value at the single point `RecordCashPaymentAction` calls `$this->gateway->charge()`. This satisfies "no float casts" for every comparison/accumulation while leaving the shared interface and its existing test untouched. Confirm with the user/consultant if a stricter reading is intended.

2. **Does `FolioItemResource.posted_by` need a new `FolioItem::postedBy()` relation, and should it be eager-loaded by default or `whenLoaded()`-guarded?**
   - What we know: D-02 says `posted_by {uuid,name}|null` on the item resource, `whenLoaded`-guarded per the resource-level convention stated for the parent fields, but does not explicitly say whether `posted_by` itself is `whenLoaded`-guarded the same way.
   - What's unclear: Whether `FolioService::adminShow`'s eager-load graph should always include `items.postedBy` (adding to the D-03 query-budget target of ≤6 queries) or only when relevant.
   - Recommendation: Eager-load `items.postedBy` unconditionally in `adminShow` (it's a single extra join/query, well within the ≤6 budget) and still wrap the resource key in `whenLoaded()` per this codebase's blanket convention, matching how `FolioResource.reservation_uuid` is already done today.

3. **Exact wording/placeholder shape for the new `custom.validation.decimal` message key** (D-17 explicitly flags this as needing verification — `BaseRequest::messages()` currently has no `decimal` entry).
   - What we know: Laravel's default English validation message for the `decimal` rule is parameterized by a `:decimal` placeholder that Laravel fills with either a single number or a "between X and Y" phrase depending on whether `decimal:N` or `decimal:N,M` was used [ASSUMED — training-data recollection of Laravel's stock `lang/en/validation.php`, not verified against this project's vendor copy in this session].
   - What's unclear: The exact placeholder name and whether all five locales' `custom.validation.decimal` entries need the same placeholder or a hardcoded phrase.
   - Recommendation: During planning/execution, trigger the `decimal:0,2` rule with an invalid value in a throwaway tinker/test run and inspect the actual `$fail()` message Laravel's `ValidatesAttributes::validateDecimal()` produces before writing the five-locale keys, rather than guessing the placeholder name.

## Environment Availability

| Dependency | Required By | Available | Version | Fallback |
|------------|------------|-----------|---------|----------|
| PHP `bcmath` extension | All money math (D-03/D-05/D-07/D-13) | ✓ | bundled with the installed PHP 8.3 build [VERIFIED: `php -m`] | — |
| SQLite (test DB) | `phpunit.xml` `DB_DATABASE=:memory:` | ✓ | bundled with PHP's PDO SQLite driver (already the project's test DB per `TESTING.md`) | — |
| MySQL (production grammar) | D-07's "MySQL-grammar assertions" (query listener proving compiled SQL contains `for update`), since SQLite silently ignores `lockForUpdate()` | Not available in this dev/test environment for a real MySQL connection — no MySQL CI job exists (explicitly deferred per CONTEXT.md's Deferred Ideas: "a MySQL CI job") | — | The lock assertions must be done via `DB::listen()`/compiled-SQL inspection against the query builder's generated SQL string for the MySQL grammar, NOT by actually executing against a live MySQL server (matches this project's existing "MySQL-only backstops" test-docblock convention already used for Phase 3's row-lock tests) |
| `doctrine/dbal` | Only if a Laravel-version-specific fallback requires it for the `folio_items.source_type` column-length `->change()` (see Assumption A1) | ✗ (not installed) [VERIFIED: `composer.lock`/`vendor/doctrine` inspection] | — | Laravel 11+'s native migration grammar for common alterations (no dependency needed) per A1; if it fails, drop the `source_type` length-narrowing from the migration (non-essential per D-04) |

**Missing dependencies with no fallback:** None — MySQL's absence has a documented, already-precedented fallback (compiled-SQL grammar assertion via query listener, exactly as this project's "MySQL-only backstops" convention already handles for Phase 3).

**Missing dependencies with fallback:** `doctrine/dbal` (fallback: Laravel 11+ native alteration, or drop the non-essential length-narrowing).

## Validation Architecture

### Test Framework
| Property | Value |
|----------|-------|
| Framework | PHPUnit 12.5.12 [VERIFIED: `backend/composer.json`] |
| Config file | `backend/phpunit.xml` (SQLite `:memory:`, `APP_ENV=testing`) |
| Quick run command | `php artisan test --filter=Folio` (or `--filter=Payment`) |
| Full suite command | `php artisan test` (serial — `--parallel` unavailable, `paratest` not installed, per Phase 3's `SUMMARY.md`) |

### Phase Requirements → Test Map
| Req ID | Behavior | Test Type | Automated Command | File Exists? |
|--------|----------|-----------|-------------------|-------------|
| FOLIO-01 | `GET /cms/reservations/{r}/folio` returns line items/payments/totals; 404 `folio_missing` when absent | feature | `php artisan test --filter=FolioReadTest` | ❌ Wave 0 |
| FOLIO-01 | Query budget ≤6 on the read path | unit/feature (query-count assertion) | `php artisan test --filter=FolioReadTest::test_.*query_count` | ❌ Wave 0 |
| FOLIO-02 | Post charge/credit; totals update; `folio_settled` on settled folio; credit floors (`folio_credit_exceeds_item`/`folio_credit_exceeds_balance`) | feature | `php artisan test --filter=FolioLineItemTest` | ❌ Wave 0 |
| FOLIO-02 | Interleaved `post → generate → post → credit → generate` invariant (`total_usd == SUM(items)` after every step) | feature (concurrency-style, mirrors `ConcurrencyTest.php`) | `php artisan test --filter=FolioLineItemTest::test_interleaved` | ❌ Wave 0 |
| FOLIO-02 | `Idempotency-Key` replay returns original result; conflicting payload → 409 `idempotency_conflict` | feature | `php artisan test --filter=FolioLineItemTest::test_idempotency` | ❌ Wave 0 |
| FOLIO-02 | Reconcile survives manual/credit/disputed rows across refresh; cancelled sources drop unless referenced; uuids stable; MySQL-grammar lock assertion | unit | `php artisan test --filter=GenerateFolioReconcileTest` | ❌ Wave 0 |
| FOLIO-03 | Dispute matrix: guest own 200, foreign 404 `not_found`, not-checked-in 403 `no_active_reservation`, double-open 422 `folio_item_dispute_open`, staff raise/resolve/reject, non-open 422 `folio_dispute_state`, item outside folio 404, check-out never blocked | feature | `php artisan test --filter=FolioDisputeTest` | ❌ Wave 0 |
| FOLIO-04 | Payment recording; overpayment 422 `folio_overpayment`; settled 422 `folio_settled`; auto-settle at zero balance with `folio.auto_settled` log; prepaid close via settle route with `folio.settled_no_payment` log; then check-out gate passes | feature | `php artisan test --filter=FolioPaymentTest` | ❌ Wave 0 |
| FOLIO-04 | Boundary decimal precision (`10.00 − 9.99 − 0.01 == 0.00`, no drift) | unit/feature | `php artisan test --filter=FolioPaymentTest::test_boundary` | ❌ Wave 0 |
| FOLIO-04 | Legacy `POST /cms/reservations/{r}/settle` refused with `folio_settled` after the folio is settled | feature | `php artisan test --filter=PaymentTest::test_.*folio_settled` (new method in existing `PaymentTest.php`) | ❌ Wave 0 (new method in existing file) |
| XCUT-01 | `folios.post`/`folios.dispute` seeded, presets updated, permission counts correct | feature | `php artisan test --filter=SeederTest` / `--filter=PermissionsGroupedTest` | ✅ (existing files, need updated assertions) |
| Regression | `SettleFolioAction`'s exception code change doesn't silently break the existing test | feature | `php artisan test --filter=FolioTest::test_settling_already_settled_folio_returns_422` | ✅ (existing, needs updated assertion — Pitfall 3) |
| Regression | Full existing suite stays green (folio/payment/reservation/check-out paths untouched in behavior) | feature+unit | `php artisan test` | ✅ (existing 1237 tests per Phase 3 `SUMMARY.md`, count will grow with Phase 4/5 additions) |

### Sampling Rate
- **Per task commit:** `php artisan test --filter=Folio` (and `--filter=Payment` when touching the payment/settle path)
- **Per wave merge:** `php artisan test` (full suite)
- **Phase gate:** Full suite green before `/gsd-verify-work`, matching this project's stated non-negotiable rule (`PITFALLS.md` Pitfall 10, `CONVENTIONS.md`/`03-...SUMMARY.md` precedent)

### Wave 0 Gaps
- [ ] `tests/Feature/Folio/FolioReadTest.php` — covers FOLIO-01
- [ ] `tests/Feature/Folio/FolioLineItemTest.php` — covers FOLIO-02
- [ ] `tests/Feature/Folio/FolioPaymentTest.php` — covers FOLIO-04
- [ ] `tests/Feature/Folio/FolioDisputeTest.php` — covers FOLIO-03
- [ ] `tests/Unit/Folio/GenerateFolioReconcileTest.php` — covers the D-06 reconcile invariants and the D-07 MySQL-grammar lock assertion
- [ ] `database/factories/FolioItemDisputeFactory.php` — needed by `FolioDisputeTest`
- [ ] `FolioItemFactory` states `manual()`/`credit()` — needed by `FolioLineItemTest`
- [ ] Framework install: none needed — PHPUnit/Laravel test infra already fully present

## Security Domain

### Applicable ASVS Categories

| ASVS Category | Applies | Standard Control |
|---------------|---------|-----------------|
| V2 Authentication | No (new) | Reuses existing Sanctum `auth:users`/`auth:guests` guards, unchanged this phase |
| V3 Session Management | No (new) | Unchanged — existing token-based sessions |
| V4 Access Control | Yes | `permission:folios.post` / `permission:folios.dispute` middleware (new); `scopeBindings()` + explicit ownership check for nested staff dispute route; guest route compares `$item->folio->reservation->guest_id` directly against the authenticated guest (never trusts `GuestEntitlement::currentReservation()`'s known-buggy resolution, per Anti-Patterns) |
| V5 Input Validation | Yes | `PostFolioItemRequest`/`RecordFolioPaymentRequest`/`StaffFolioDisputeRequest`/`GuestFolioDisputeRequest` (Laravel `FormRequest` + `decimal:0,2`/`Rule::enum()`/`required_if` rules, matching existing `SettleFolioRequest` pattern) |
| V6 Cryptography | No | No new secrets/tokens introduced; `Idempotency-Key` is a client-supplied opaque string, not a cryptographic credential |

### Known Threat Patterns for this stack

| Pattern | STRIDE | Standard Mitigation |
|---------|--------|---------------------|
| Double-submit / retry causing duplicate charge or duplicate line item | Repudiation / Tampering | `Idempotency-Key` + unique DB index + replay-on-conflict (Pattern 3); this is the primary threat this phase's idempotency design defends against |
| TOCTOU race on folio balance (two concurrent payments both read a stale balance and both "succeed") | Tampering | `lockForUpdate()` on the folio row inside `DB::transaction()` before any balance read, exactly matching the already-fixed P8 pattern documented in `PITFALLS.md` Pitfall 2 |
| Cross-tenant/cross-guest data exposure (guest disputes another guest's line item by guessing/enumerating a UUID) | Information Disclosure / Elevation of Privilege | 404 `not_found` (not 403) for a foreign guest's item, per the established Phase 4 precedent, so the response doesn't confirm the item's existence to an unauthorized guest |
| Staff over-crediting a folio below zero, or crediting more than the original charge, to manufacture a refund/discount not authorized | Tampering | Credit-floor checks in `PostFolioItemAction` (`|credit| + Σ existing credits referencing it ≤ charge.amount_usd`, and whole-folio `balanceDueUsd() ≥ 0.00`), enforced server-side under the folio lock, never trusting client-computed totals |
| Permission sprawl / inconsistent naming for the two new permissions | Elevation of Privilege (indirect — over-broad grants) | `{domain}.{action}` naming already locked (`folios.post`, `folios.dispute`), granted only to the `reception` preset (the only current holder of `folios.settle`), matching `PITFALLS.md` Pitfall 9's prevention guidance |
| Activity-log tampering/omission hiding who posted a manual credit | Repudiation | `LogsActivity` on `FolioItem`/`Payment`/`FolioItemDispute` with causer resolution (staff user or guest), plus explicit `activity()` log entries for `folio.auto_settled`/`folio.settled_no_payment`, matching this project's existing money-audit convention |

## Sources

### Primary (HIGH confidence)
- Direct reads of this codebase's current source (2026-09-26): `app/Actions/Folio/{GenerateFolioAction,SettleFolioAction,ApproveFolioAction}.php`, `app/Actions/Booking/CheckOutReservationAction.php`, `app/Actions/Payment/RecordCashPaymentAction.php`, `app/Payments/ManualDriver.php`, `app/Contracts/PaymentGatewayInterface.php`, `app/Services/Folio/{FolioService,ReceiptService}.php`, `app/Services/Payment/PaymentService.php`, `app/Http/Controllers/{Admin,Api}/FolioController.php`, `app/Http/Controllers/Admin/PaymentController.php`, `app/Http/Resources/Folio/{FolioResource,FolioItemResource}.php`, `app/Http/Resources/Payment/PaymentResource.php`, `app/Http/Requests/Folio/SettleFolioRequest.php`, `app/Models/{Folio,FolioItem,Payment,Refund}.php`, `database/migrations/{2026_07_12_200000_create_folios_table,2026_07_12_200001_create_folio_items_table,2026_07_10_100005_create_payments_table,2026_07_10_100006_create_refunds_table}.php`, `database/factories/{FolioFactory,FolioItemFactory,PaymentFactory}.php`, `app/Enums/{FolioStatus,PaymentMethod}.php`, `app/Exceptions/{FolioUnsettledException,NotFoundException,ReservationStateException,NoActiveReservationException,DomainException}.php`, `app/Filters/ReservationFilter.php`, `app/Http/Middleware/EnsureIsCheckedIn.php`, `app/Support/GuestEntitlement.php`, `database/seeders/RolesAndPermissionsSeeder.php`, `app/Base/{BaseFilter,BaseRequest}.php`, `app/Traits/{HasUuid,LogsActivity}.php`, `config/activitylog.php`, `routes/api.php` (lines 560-710), `tests/Feature/Folio/FolioTest.php`, `tests/Feature/Payment/PaymentTest.php`, `tests/Feature/Reservations/ExpressCheckoutTest.php`, `tests/Feature/Booking/ConcurrencyTest.php`, `tests/Feature/SeederTest.php`, `tests/Feature/Staff/PermissionsGroupedTest.php`, `backend/composer.json`, `backend/composer.lock`
- Direct local shell verification (2026-09-26): `php -m` (confirms `bcmath` present), grep across `backend/app` and `backend/routes/api.php` (confirms no `bcmath`, no `Idempotency-Key`, no `scopeBindings()` precedent exists yet)
- Laravel framework source: `vendor/laravel/framework/src/Illuminate/Validation/Concerns/ValidatesAttributes.php` (confirms `decimal:N,M` rule semantics)
- Project planning documents: `.planning/phases/05-folio-extensions/05-CONTEXT.md`, `.planning/REQUIREMENTS.md`, `.planning/ROADMAP.md`, `.planning/STATE.md`, `.planning/research/PITFALLS.md`, `.planning/codebase/{TESTING.md,CONVENTIONS.md}`, `.planning/phases/03-reservations-front-desk-verbs/{SUMMARY.md,03-CONTEXT.md}`, `.planning/phases/04-guests-stay/04-CONTEXT.md`, `backend/.claude/skills/tupcode-laravel-backend/SKILL.md`
- `docs/carlton-tree.html`, `backend/docs/API_GUIDE_DASHBOARD.md`, `backend/docs/API_GUIDE_MOBILE.md`, `backend/docs/CHANGELOG_MOBILE_API.md`, `backend/docs/postman/carlton-api.postman_collection.json` (grepped for existing Folio sections/nodes/folders, 2026-09-26)

### Secondary (MEDIUM confidence)
- [Laravel 13.x Routing docs — Scoping Eloquent Relationships / `scopeBindings()`](https://laravel.com/docs/routing) — WebSearch-verified summary of automatic nested-route-model-binding scoping behavior with custom route keys; not independently re-derived from this exact framework version's source in this session

### Tertiary (LOW confidence)
- Training-data recollections flagged inline as `[ASSUMED]` throughout (Doctrine DBAL removal in Laravel 11+ migrations, `QueryException` SQLSTATE/driver-code shapes for SQLite vs MySQL unique violations, Laravel's default `decimal` validation message placeholder) — see Assumptions Log for the full list and recommended verification steps

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH — no new packages; `bcmath` presence directly verified via `php -m`; Laravel/PHPUnit versions directly read from `composer.json`
- Architecture: HIGH — every pattern is either a direct read of this codebase's existing, working code (reconcile target, ledger balance, filter override) or a documented Laravel framework feature (`scopeBindings()`) cross-checked via web search; the idempotency helper is genuinely new design work, flagged as such rather than presented as pre-existing
- Pitfalls: HIGH — all eight pitfalls are grounded in specific, quoted current-code behavior or specific existing test assertions that will need updating, not generic hotel-PMS pattern-matching

**Research date:** 2026-09-26
**Valid until:** 30 days (stable Laravel-version codebase; re-verify if `composer.json`'s `laravel/framework` constraint changes or if Phase 4's in-flight work changes `GuestEntitlement`/`EnsureIsCheckedIn` before Phase 5 planning begins)
