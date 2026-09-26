# Phase 5: Folio Extensions - Context

**Gathered:** 2026-09-26
**Status:** Ready for planning (build after Phase 4 has landed; touches `GenerateFolioAction`, `ReceiptService`, `FolioResource`, `SettleFolioAction`, `PaymentService`)
**Decided by:** Fable 5.1 consultant (owner delegated all decisions); seven decisions were deliberated by two ai-councils (`wf_681ece81-b59` ledger design, `wf_ab8560d3-cb5` money semantics) whose amendments are folded in. Full consultant text: `05-DISCUSSION-LOG.md`.

<domain>
## Phase Boundary

Staff read a reservation's folio, post charges and credits, record manual payments, and raise/resolve line-item disputes; guests dispute their own items. Routes: `GET /cms/reservations/{reservation}/folio`, `POST /cms/folios/{folio}/line-items`, `POST /cms/folios/{folio}/payments`, `PATCH /cms/folios/{folio}/line-items/{item}/dispute`, `PATCH /folio/items/{item}/dispute`; plus a payment-free close path on the existing settle shortcut and a guard on the legacy reservation-level settle. New permissions `folios.post`, `folios.dispute`. Three additive migrations (folio_items ledger columns, `folio_item_disputes`, `payments.idempotency_key`). The ledger is append-only: no PATCH/DELETE of money rows, corrections are credit rows. Out of scope: refunds, card/online payments, taxes, localized item descriptions, per-item categories, reopening settled folios, receivables/write-off.

</domain>

<decisions>
## Implementation Decisions

### Folio read (FOLIO-01)
- **D-01:** `GET /cms/reservations/{reservation}/folio` inside the `cms/reservations` group under `permission:folios.view`; `AdminFolioController::showForReservation`, `FolioService::adminShow`. Pure read for any status; no auto-generate. Missing folio → new `FolioMissingException` (`folio_missing`, 404, context `{reservation_uuid, reservation_status}`); the dashboard then calls the existing generate route.
- **D-02:** One shape: `FolioResource` gains (all `whenLoaded`-guarded) `payments` (`PaymentResource::collection`), `paid_usd`, `balance_due_usd` (signed strings, 2dp, computed from the loaded relation, no queries in `toArray`), `open_disputes_count`. `FolioItemResource` gains `quantity`, `unit_price_usd`, `posted_by {uuid,name}|null`, `posted_at`, `reason`, `reverses_item_uuid`, `dispute` (latest: `{uuid, status, reason, raised_by: guest|staff, raised_at, resolved_at, resolution_note}|null`). Guest `GET /folio` shows dispute status per item for free.
- **D-03 (council):** `balance_due_usd = total_usd − Σ completed payments whose payable is the folio OR its reservation` (reservation-level deposits count). Implemented once: `Folio::ledgerPayments(): Builder`, `paidUsd()`, `balanceDueUsd()` using `bcadd`/`bcsub` at scale 2 on decimal strings (never float; signed, never clamped). `ReceiptService::paymentsFor` and `FolioResource` call these; the check-out gate stays status-based. Refunds are not subtracted (pinned by a test comment so the refund phase must revisit). Read path bound: `expectsDatabaseQueryCount(≤ 6)`.

### Manual line items (FOLIO-02)
- **D-04 (council):** Additive migration on `folio_items`: `quantity` unsignedSmallInteger default 1; `unit_price_usd` decimal(10,2) nullable (null on generated rows); `posted_by` FK users nullOnDelete; `reason` string(255) nullable; `reverses_item_id` self FK **restrictOnDelete**; `idempotency_key` string(64) nullable; `source_line` unsignedSmallInteger default 0; narrow `source_type` to string(32); unique `(folio_id, idempotency_key)`; unique `(folio_id, source_type, source_id, source_line)`. Enum `FolioItemSource { RESERVATION, SERVICE_BOOKING, SERVICE_REQUEST, MANUAL, CREDIT }`; `amount_usd` stays the signed line total (`manual > 0`, `credit < 0`); posted rows have `source_id` null. No `voided_at`, no tax, no category; `subtotal_usd == total_usd`.
- **D-05 (council):** `POST /cms/folios/{folio}/line-items`, permission `folios.post` (new; every preset holding `folios.settle`, today `reception`, also gets it). `PostFolioItemRequest`: `{ kind: charge|credit (default charge), description: required|string|max:255, quantity: integer|min:1|max:999 (default 1), unit_price_usd: required|decimal:0,2|min:0.01|max:99999.99, reason: required_if:kind,credit|string|max:255, reverses_item_uuid: uuid|nullable (credit only, same folio) }`; optional `Idempotency-Key` header (D-08). `PostFolioItemAction::handle(Folio, User $poster, array $data)`: `DB::transaction` → `lockForUpdate` folio → **replay check first** (D-08) → status `open` else new `FolioSettledException` (`folio_settled`, 422, `{folio_uuid, settled_at}`); reservation status is not a guard; `amount = bcmul(quantity, unit_price, 2)` negated for credit; a credit referencing a charge must satisfy `|credit| + Σ existing credits referencing it ≤ charge.amount_usd` else 422 `folio_credit_exceeds_item`; whole-folio floor: `balanceDueUsd()` after the credit must be `≥ 0.00` else 422 `folio_credit_exceeds_balance`; insert (`source_type` manual|credit, `posted_by`), `Folio::recalculateTotals()` (D-07); 201 with the full `FolioResource`. No PATCH/DELETE routes for items, ever.
- **D-06 (council):** `GenerateFolioAction` reconciles instead of rebuilding: it takes its **own** `lockForUpdate` on the folio row on every path (guest `GET /folio`, admin generate, check-out; nested lock inside check-out's transaction is fine), then for each computed line `(source_type, source_id, source_line)` → `updateOrCreate` (touching `description`, `amount_usd` only, DECIMAL strings, no float casts); generated rows whose source is no longer billable are deleted **unless referenced by any `reverses_item_id` or any dispute**, in which case they are frozen (neither deleted nor repriced); `manual`/`credit` rows are never touched; totals via `recalculateTotals()`. Existing settled/checked-out guards unchanged. Item uuids are stable across refreshes; `ExpressCheckoutTest` exactly-once tests assert uuid stability across two refreshes.
- **D-07:** `Folio::recalculateTotals()` = one `SELECT COALESCE(SUM(amount_usd),0)` under the held lock, written to `subtotal_usd` and `total_usd`, formatted to 2dp (never a PHP sum of a stale collection). Concurrency test: interleaved `post → generate → post → credit → generate` from two staff users asserting `total_usd == SUM(items)` after every step; MySQL-grammar assertions (query listener proving the compiled SQL contains `for update`) for `GenerateFolioAction`, `PostFolioItemAction`, `RecordFolioPaymentAction` since SQLite ignores `lockForUpdate`.
- **D-08 (council):** `Idempotency-Key` header (≤ 64 chars, merged into the request as `idempotency_key`), optional on line items, **required** on payments (422 `idempotency_key_required`), stored per row with the unique indexes above. Replay with an identical full validated payload (items: kind, description, quantity, unit_price_usd, reason, reverses_item_uuid; payments: method, amount_usd, note, **recorded_by**) → 200 with the current `FolioResource`, no write; any difference → new `IdempotencyConflictException` (`idempotency_conflict`, 409, `{idempotency_key}`). Checked first under the lock; a unique-violation `QueryException` is re-read and returned as replay. No TTL. One shared `IdempotentWrite` helper used by both actions. Document that replay returns current folio state, not a byte-identical original body. Settle routes stay without the header this phase (deferred).

### Disputes (FOLIO-03)
- **D-09 (council):** Table `folio_item_disputes`: `id`, `uuid`, `folio_item_id` FK cascadeOnDelete, `status` (`FolioDisputeStatus { OPEN, RESOLVED, REJECTED }`), `reason` string(500), `guest_id` FK nullable nullOnDelete, `user_id` FK nullable nullOnDelete (exactly one set, app-enforced), `resolved_by` FK users nullable nullOnDelete, `resolved_at`, `resolution_note` string(1000) nullable, timestamps; indexes `(folio_item_id, status)`, `status`. Model `FolioItemDispute` (`HasUuid`, `LogsActivity`); `FolioItem::disputes()` + `latestDispute()`; `Folio::disputes()` hasManyThrough; `FolioItemDispute::scopeOpen()`. One open dispute per item enforced under the folio lock (no partial index).
- **D-10:** Guest `PATCH /folio/items/{item}/dispute` in the existing `auth:guests` + `is_checked_in` group, body `{ reason: required|string|max:500 }`; item not on the guest's own folio → 404 `not_found` (Phase 4 precedent); allowed on open and settled folios; an open dispute already present → new `FolioItemDisputeOpenException` (`folio_item_dispute_open`, 422, `{item_uuid, dispute_uuid}`); re-dispute allowed after resolved/rejected. `RaiseFolioDisputeAction::handle(FolioItem, Guest|User, string)` locks the folio row only. 200 with `FolioItemResource`.
- **D-11:** Staff `PATCH /cms/folios/{folio}/line-items/{item}/dispute` with `scopeBindings()` (item outside folio → 404), permission `folios.dispute` (new; same presets as `folios.post`). Body `{ action: raise|resolve|reject, reason: required_if:action,raise|max:500, note: required_if:action,resolve,reject|max:1000 }`. `ResolveFolioDisputeAction` requires an open dispute else new `FolioDisputeStateException` (`folio_dispute_state`, 422, `{item_uuid, status}`); stamps resolver/at/note/status. Resolution never moves money; a refund-worthy dispute is settled by posting a credit line with `reverses_item_uuid` (documented in the guide). 200 with `FolioItemResource`.
- **D-12:** Flags, never a gate: `Folio::scopeWithOpenDisputes()`, `openDisputesCount()`, `open_disputes_count` in `FolioResource` and in the check-out response's reservation `folio` summary; `ReservationFilter` gains `has_open_disputes=1|0`. `CheckOutReservationAction` untouched; a test proves a settled folio with an open dispute checks out 200 and an open folio with a dispute is refused with `folio_unsettled` only.

### Payments (FOLIO-04)
- **D-13 (council):** `POST /cms/folios/{folio}/payments`, permission `folios.settle`. `RecordFolioPaymentRequest`: `{ method: enum PaymentMethod, amount_usd: required|decimal:0,2|min:0.01|max:99999.99, note: nullable|max:1000 }`, `Idempotency-Key` required. `RecordFolioPaymentAction::handle(Folio, User $recorder, array $data, string $key)`: transaction → lock folio → replay check first (D-08) → `settled` → 422 `folio_settled` → `bccomp(amount, balanceDueUsd(), 2) === 1` → new `FolioOverpaymentException` (`folio_overpayment`, 422, `{balance_due_usd, amount_usd}`) (a zero-balance folio therefore rejects payments) → `RecordCashPaymentAction::handle($folio, ...)` with `idempotency_key` set in the same `Payment::create` (payable = Folio) → if `bccomp(balanceDueUsd(), '0.00', 2) <= 0` set `status = settled`, `settled_at = now()`, log `folio.auto_settled` `{folio_uuid, paid_usd}`. 201 with the full `FolioResource`; replay → 200 same shape. Migration: `payments.idempotency_key` string(64) nullable, unique `(payable_type, payable_id, idempotency_key)`. `RecordCashPaymentAction`/`PaymentService` float parameters become decimal strings (no `(float)` casts on the folio path).
- **D-14 (council):** Payment-free close path: `SettleFolioAction` (behind the existing `POST /cms/folios/{folio}/settle`) settles without a `Payment` row when `balanceDueUsd() <= 0.00`, logging `folio.settled_no_payment`; `SettleFolioRequest.amount_usd` becomes nullable (`decimal:0,2|min:0.01` when present). The legacy `POST /cms/reservations/{reservation}/settle` gains one guard: reservation's folio exists and is settled → 422 `folio_settled`. Other settle behaviour unchanged (tightening deferred). Documented: pre-departure money is taken through the reservation-level deposit route; the folio payments route is for the departure desk (auto-settle closes the folio; there is no reopen).
- **D-15:** Lock discipline: every Phase 5 writer locks only the folio row (reservation → folio order preserved for callers that also lock the reservation). `LogsActivity` on `FolioItem`, `Payment`, `FolioItemDispute` records causer (staff user or guest); explicit `activity()` entries `folio.auto_settled`, `folio.settled_no_payment`.

### Night-audit hook (Phase 9 reads only)
- **D-16:** Ship and document: `Folio::scopeUnsettled()`, `scopeWithOpenDisputes()`, `FolioItemDispute::scopeOpen()`, `ledgerPayments()/paidUsd()/balanceDueUsd()`, `FolioResource.open_disputes_count`, `has_open_disputes` filter. Suggested Phase 9 queries recorded in the summary (unsettled departures; open disputes on in-house/checked-out stays).

### Contract, docs, tests
- **D-17:** Actions `app/Actions/Folio/{PostFolioItemAction, RecordFolioPaymentAction, RaiseFolioDisputeAction, ResolveFolioDisputeAction}.php`; `FolioService` extended (`adminShow`, `adminPostItem`, `adminRecordPayment`, `adminDispute`, `guestDispute`); requests `app/Http/Requests/Folio/{PostFolioItemRequest, RecordFolioPaymentRequest, StaffFolioDisputeRequest, GuestFolioDisputeRequest}.php`; enums `FolioItemSource`, `FolioDisputeStatus`; exceptions (one per code) `FolioMissing` 404, `FolioSettled`, `FolioCreditExceedsItem`, `FolioCreditExceedsBalance`, `FolioOverpayment`, `FolioItemDisputeOpen`, `FolioDisputeState` (422), `IdempotencyConflict` 409, plus `idempotency_key_required` as a validation error; `App\Support\IdempotentWrite`; migrations `add_ledger_columns_to_folio_items_table`, `create_folio_item_disputes_table`, `add_idempotency_key_to_payments_table`; factories `FolioItemDisputeFactory`, `FolioItemFactory` states `manual()`/`credit()`. Lang keys in all five locales: `messages.{folio_item_posted, folio_payment_recorded, folio_dispute_raised, folio_dispute_resolved, folio_dispute_rejected, folio_settled_no_payment}`, `errors.{folio_missing, folio_settled, folio_credit_exceeds_item, folio_credit_exceeds_balance, folio_overpayment, folio_item_dispute_open, folio_dispute_state, idempotency_conflict, idempotency_key_required}`, attribute names, `BaseRequest::messages()` for `decimal`/`required_if` if missing.
- **D-18:** `docs/carlton-tree.html` node "folio line items · payments" → `api:true`, `ep`: `GET /cms/reservations/{r}/folio`, `POST /cms/folios/{f}/line-items`, `POST /cms/folios/{f}/payments`, `PATCH /cms/folios/{f}/line-items/{i}/dispute`, `PATCH /folio/items/{i}/dispute`; meta "charges · credits · disputes · payments". `API_GUIDE_DASHBOARD.md` Folios module retitled `(folios.view, folios.post, folios.settle, folios.dispute)` with the four staff routes, the `Idempotency-Key` contract, extended shapes, "corrections are credit rows, never edits", the settle close path and reservation-settle guard, error rows, `has_open_disputes` filter; `API_GUIDE_MOBILE.md` + `CHANGELOG_MOBILE_API.md`: additive item fields, new guest dispute route, non-breaking `GET /folio` changes (stable uuids, `paid_usd`, `balance_due_usd`, `open_disputes_count`). Postman: four staff + one guest request with an `Idempotency-Key` pre-request `{{$guid}}`. Summary lists `folios.post`, `folios.dispute`, preset changes, production notes (`[BLOCKING] php artisan migrate`, seeder re-run).
- **D-19:** Tests: `tests/Feature/Folio/{FolioReadTest, FolioLineItemTest, FolioPaymentTest, FolioDisputeTest}.php` (happy / 401 / 403 / 422 / 404, envelope), the D-07 interleaved invariant test, replay + 409 tests, replay-before-settled ordering, credit floors, frozen-row survival, overpayment, exact-balance auto-settle with log entry, boundary `10.00 − 9.99 − 0.01` without drift, prepaid folio closes without a payment then checks out 200, reservation-level settle refused after folio settled, disputes matrix (own 200, foreign 404, not checked-in 403 `no_active_reservation`, double-open 422, staff raise/resolve/reject, non-open 422, item outside folio 404, check-out not blocked); `tests/Unit/Folio/GenerateFolioReconcileTest` (manual/credit rows and disputes survive refresh, cancelled sources drop unless referenced, uuids stable, MySQL-grammar lock assertion); receipt balance equals `FolioResource.balance_due_usd`; `SeederTest`/`PermissionsGroupedTest` updated for the two permissions.

### Claude's Discretion
- Class/method names above are defaults; `ledgerPayments()` may live on a small `App\Support\FolioLedger` helper shared by receipt and resource.
- `recalculateTotals()` via DB `SUM` or `bcadd` over a freshly loaded collection (under the lock either way).
- Whether the close path lives in `SettleFolioAction` or a `MarkFolioSettledAction` shared with auto-settle; how `Idempotency-Key` reaches the FormRequest (prepareForValidation vs middleware).
- `dispute` on the item resource via `latestOfMany` or the last of the loaded collection; five-locale wording; Postman ordering; test method names.

</decisions>

<canonical_refs>
## Canonical References

**Downstream agents MUST read these before planning or implementing.**

### Conventions (hard gate)
- `backend/.claude/skills/tupcode-laravel-backend/SKILL.md` + `references/developer-guide.md` (§15 money rules: DECIMAL, immutable ledger, transactions), `.claude/skills/{laravel-conventions,module-slice,test-discipline,naive-reviewer}/SKILL.md`
- `.planning/codebase/CONVENTIONS.md` (Phase Summary Contract), `.planning/phases/03-reservations-front-desk-verbs/03-CONTEXT.md` + `SUMMARY.md` (check-out gate, lock order reservation → folio, `GenerateFolioAction` guards, `CheckOutMode`, `folio_status` filter), `.planning/phases/04-guests-stay/04-CONTEXT.md` (404 precedent for foreign guest records)
- `.planning/research/PITFALLS.md` (money handling, TOCTOU, idempotency), `.planning/research/ARCHITECTURE.md` (folio rows; the "dispute columns" suggestion is overridden by D-09)

### Existing code this phase extends
- `backend/app/Models/{Folio,FolioItem,Payment,Refund,Reservation}.php` and migrations; `backend/app/Enums/{FolioStatus,PaymentMethod}.php`
- `backend/app/Actions/Folio/{GenerateFolioAction,SettleFolioAction,ApproveFolioAction}.php`, `backend/app/Actions/Booking/CheckOutReservationAction.php` (read-only here), `backend/app/Actions/Payment/RecordCashPaymentAction.php`, `backend/app/Payments/ManualDriver.php`
- `backend/app/Services/Folio/{FolioService,ReceiptService,ReceiptPdfRenderer}.php`, `backend/app/Services/Payment/PaymentService.php`, `backend/app/Http/Controllers/Admin/{FolioController,PaymentController}.php`, `backend/app/Http/Controllers/Api/FolioController.php`, `backend/app/Http/Resources/Folio/*`, `backend/app/Http/Requests/{Folio,Payment}/*`, `backend/app/Filters/ReservationFilter.php` (Phase 3)
- `backend/routes/api.php` (`cms/folios`, `cms/reservations/{r}/settle`, guest `/folio`), `backend/database/seeders/RolesAndPermissionsSeeder.php`, `backend/app/Http/Middleware/EnsureIsCheckedIn.php`, `backend/app/Support/GuestEntitlement.php`
- Existing tests `tests/Feature/Folio/*`, `tests/Feature/Payment/*`, `tests/Feature/Reservations/ExpressCheckoutTest.php`, `ConcurrencyTest`

### API contract & docs
- `backend/docs/API_GUIDE_DASHBOARD.md` (Folios & Express Checkout, Reservations `folio` summary), `backend/docs/API_GUIDE_MOBILE.md` (Folio/receipt), `backend/docs/CHANGELOG_MOBILE_API.md`, `backend/docs/postman/carlton-api.postman_collection.json`, `docs/carlton-tree.html`

### Planning artifacts
- `.planning/REQUIREMENTS.md` FOLIO-01..04, DOCS-01, XCUT-01; `.planning/ROADMAP.md` Phase 5 (criteria reworded per the consultant: 404 for foreign guest disputes, `folio_settled`/credit floors, overpayment refusal, auto-settle, `Idempotency-Key`, reconcile-not-rebuild, `folios.post` + `folios.dispute`)

</canonical_refs>

<code_context>
## Existing Code Insights

### Reusable Assets
- `SettleFolioAction` lock-then-check shape; `RecordCashPaymentAction` (payable morph); `GenerateFolioAction` line computation (to be reconciled, not rebuilt); `ReceiptService::paymentsFor` (to be replaced by `ledgerPayments()`); `ReservationFilter` `apply()` override; Phase 3 domain-exception style with context

### Established Patterns
- Money only through ledger rows; transactions + `lockForUpdate`; domain exceptions → `error_code`; five-locale keys; real-bearer-token tests; foreign guest records → 404
- Events after commit; `LogsActivity` on money models with causer

### Integration Points
- `routes/api.php`: `cms/reservations` (folio read), `cms/folios` (line items, payments, staff dispute), guest `auth:guests + is_checked_in` group (guest dispute)
- `GenerateFolioAction` callers: guest `GET /folio`, admin generate, `CheckOutReservationAction`
- Seeder presets `reception` (+ any holder of `folios.settle`)

</code_context>

<specifics>
## Specific Ideas

- Treat the folio as an append-only ledger: every correction is a new signed row, every money write is idempotent, every total is a DB `SUM` under a lock.
- Keep the receipt, the folio resource and the check-out gate reading the same balance helper so they can never disagree.

</specifics>

<deferred>
## Deferred Ideas

- Tightening `POST /cms/folios/{folio}/settle` to the exact balance; `Idempotency-Key` on both settle routes
- Refunds (table exists), change-making/overpayment credit, online payments; reopening settled folios; receivables/write-off closing path
- Localized `folio_items.description`; taxes/service charges; per-item categories; guest withdrawing a dispute; `under_review` state; dispute notifications; dispute re-raise cap
- Freezing all generated rows after any payment activity (Skeptic); per-night room postings (the `source_line` column is ready for it); a MySQL CI job

</deferred>

---

*Phase: 05-folio-extensions*
*Context gathered: 2026-09-26*
