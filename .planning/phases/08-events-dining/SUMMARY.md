---
phase: 08-events-dining
status: complete
completed: 2026-10-03
---

# Phase 8 — Events & Dining: Summary

**Base:** 0961153; tree at ae387c9 (owner merge) plus the merge repair. Opus 5.5 stood in for the Fable consultant; no council ran (see 08-DISCUSSION-LOG.md).

## Deploy notes
- **[BLOCKING]** `php artisan migrate` (4 additive migrations `2026_10_02_100000..100300`) and `php artisan db:seed --class=RolesAndPermissionsSeeder`. Without the seed every event-inquiry route returns 403 except for super admin.
- No data migration; existing table-reservation instants are not backfilled (D-22).
- Notify the Flutter team (changelog entry) and the dashboard team (handoff below).

## Endpoints

| Verb + path | Gate |
|---|---|
| GET /api/cms/event-inquiries | events.view (was tickets.view); list gains `staff_notes, deposit_status, deposit_paid_at, checklist_done_count, checklist_total` |
| GET /api/cms/event-inquiries/{uuid} | events.view; detail adds `checklist[]`, `deposit{}`, `assigned_user`, `guest`, `event_space` |
| PATCH …/{uuid}/status | events.manage (was tickets.assign); `inquiry_state` context `{status, allowed}` |
| PATCH …/{uuid}/assign | events.manage (was tickets.assign) |
| PATCH …/{uuid}/checklist/{item} | events.manage; new; explicit `{done}` |
| PATCH …/{uuid}/notes | events.manage; new; `{staff_notes}` |
| PATCH …/{uuid}/deposit | events.deposit; new; `{amount_usd, method?:cash, note?}` + required `Idempotency-Key` |
| GET /api/cms/table-reservations | service_requests.view; new; hotel-local `date` or `from`+`to` (≤31 days), default today; venue/table/status filters; per_page 50 (max 100) |
| POST, DELETE /api/cms/dining-venues/{uuid}/menu-file | cms.edit; new; pdf or image ≤10 MB; upload replaces; DELETE without file → 404 |
| GET /api/public/dining-venues/{uuid}/menu/download | public; new; 200 `{url,file_name,mime_type,size,updated_at}` / 204 / 404 |

## Permissions: 29 / 12 (was 26 / 11)
New group `events`: `events.view`, `events.manage`, `events.deposit`. Only the `events` preset changes (gains all three).

## Contract tightenings
1. Reception and concierge lose all event-inquiry access (403).
2. The `event_inquiries` dashboard summary key now follows `events.view` (PR-2).
3. Additive `inquiry_state` context `{status, allowed}`.
4. D-22: table reservations store the true UTC instant of the hotel-local slot (19:00 Damascus → 16:00Z); "today" is the hotel's day.

## Error codes
New: `event_checklist_item_derived` (422 `{item}`), `event_deposit_already_recorded` (422 `{payment_uuid, paid_at}`). Existing reused: `inquiry_state`, `idempotency_conflict` (409), `validation_failed`, `not_found`.

## Dashboard handoff
- `/events/{id}` → `/cms/event-inquiries/{uuid}`; no aliases. Nav gate `events.view`.
- Notes body `{staff_notes}`; `notes` is the client brief, read-only.
- Checklist body `{done}` required; unknown item → `not_found`; `deposit` cannot be toggled.
- Deposit body `{amount_usd, method?, note?}` + `Idempotency-Key`; `deposit{}` replaces `deposit_paid`, `deposit_amount`, `deposit_received_by`.
- Menu files never appear in venue images or `/cms/media`.

## For Phase 9
Segment payments by `payable_type` (event deposit is not room revenue, D-18). `EventInquiry` must never join the morph map (PR-8, pinned).

## Known gaps
- PR-9: generic `POST /service-bookings` can still create table bookings with a client time.
- PR-6: event assign has no eligibility check.
- Row-lock serialisation is MySQL-only verified; manual check: two concurrent deposits with different keys → one 200, one 422.

## Deviations
1. Merge repair of owner's ae387c9: duplicate `StayService::__construct` merged, duplicate guest logout route removed, self check-in now calls `CheckInReservationAction` with a nullable actor (baseline 2028).
2. `ofMany(['id'=>'max'], constraint)` for `depositPayment()` and `menuFile()` (`latestOfMany` + `where` bug).
3. `EventInquiryResource::forGuest()` keeps staff fields off the public receipt.
4. `load()` instead of `fresh()` to meet the read cap.
5. `events.deposit` briefly inert between 08-02 and 08-05, reverted.
6. `ReadsIdempotencyKey` trait shared with the folio payment request.
7. Lang key `validation.prohibits`.
8. Menu upload uses `success()` so its own message shows.
9. Alias `AdminTableReservationController`.
10. Query budgets: list 4 (cap 6), show 8 (cap 9), PATCHes ≤9, table reservations 6 (cap 6), download 2 (cap 2).

## Test counts
Baseline 2028 → 2049 → 2061 → 2145 → final 2165 (13,461 assertions), green.

## Gate
Scratch SQLite migrate:fresh / rollback --step=4 / migrate clean; seeded demo clean; route:list gates match on all 11 routes; no diff vs 0961153 on Payment actions, IdempotentWrite, PaymentMethod, CreateServiceBookingAction, AppServiceProvider, tests/Feature/Cms, UploadMediaRequest; database.sqlite hash unchanged.

## Decision coverage
D-01..D-32 and PR-1..PR-9 are each covered by the plan summaries 08-01..08-10 and the named tests (EventsDiningSchemaTest, EventInquiryDetailTest, EventNotesTest, EventChecklistTest, EventDepositTest, RecordEventDepositActionTest, TableReservationIndexTest, TableReservationTimezoneTest, VenueMenuFileTest, MenuDownloadTest, RolePresetsTest, SeederTest, DashboardSummaryTest, LocaleFoundationTest).
