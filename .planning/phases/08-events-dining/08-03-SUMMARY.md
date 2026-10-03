---
phase: 08-events-dining
plan: 03
status: complete
---

# 08-03 Summary — Inquiry read shapes

## Built
- `EventInquiryService` (legacy, PR-6): `DETAIL_RELATIONS`; list `withCount(checklist_done_count)`; `show/updateStatus/assign` return the detail relations; `allowedTargets()` extracted; `InquiryStateException` now carries `{status, allowed}` (D-21); `updateStaffNotes()` added here (used by 08-04).
- `EventInquiryResource` (still `JsonResource`, PR-7): additive `staff_notes`, `deposit_status`, `deposit_paid_at`, `checklist_done_count` (stored ticks + derived deposit when paid, FA-8.03-1), `checklist_total` = 5.
- New `EventInquiryDetailResource extends EventInquiryResource`: `checklist[]` (enum order, derived deposit), `deposit{status, amount_usd, method, paid_at, received_by, payment_uuid}`, `assigned_user`, `guest`, `event_space`.
- `Admin\EventInquiryController`: show/status/assign respond with the detail resource.

## Tests
- `tests/Feature/Events/EventInquiryDetailTest.php` (12): shape, derived tick, unpaid object, notes split, list keys, `inquiry_state` context (`context.status` / `context.allowed` — the handler's envelope path), PATCH shapes, guest receipt has no staff keys, budgets (list **4**, show **8**, both under the D-30 caps of 6 / 9), 401/403.
- Full suite: 2061 green.

## Deviations
- **Public submit receipt guarded.** `POST /event-inquiries` (guest/anonymous) used the same `EventInquiryResource`; adding staff keys there would have exposed internal fields on a public route. `EventInquiryResource::forGuest()` drops the Phase 8 staff keys; the public controller uses it, so its response is byte-identical to before. Pinned by `test_public_submit_response_carries_no_staff_fields`.
- Show budget pinned at 8 without collapsing the two user loads (the eight relation queries are already under the cap of 9).
- The deposit object and the derived checklist tick only read the payment when `deposit_status = paid` (one source of truth for "done", D-04).
