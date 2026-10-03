---
phase: 08-events-dining
plan: 04
status: complete
---

# 08-04 Summary — Checklist and staff notes

## Built
- `App\Actions\Events\ToggleEventChecklistItemAction`: transaction (3 attempts) → inquiry `lockForUpdate` → cancelled guard (`inquiry_state` `{status, allowed: new,in_review,quoted,confirmed}`) → derived guard (`event_checklist_item_derived` `{item}`) → explicit-state no-op → lazy row create in a savepoint with the unique-index backstop (lost race re-reads the winner) → stamp/clear `completed_at` + `completed_by` → returns the locked row with `DETAIL_RELATIONS` loaded.
- `EventChecklistItemDerivedException` (422).
- `UpdateEventChecklistItemRequest` (`done` required|boolean), `UpdateEventStaffNotesRequest` (`staff_notes` present|nullable|string|max:5000).
- `EventInquiryService::updateStaffNotes()` (no lock, last write wins, D-20).
- Controller `updateChecklistItem`, `updateNotes`; routes `PATCH /cms/event-inquiries/{inquiry}/checklist/{item}` (`whereIn` enum values) and `/notes`, both `events.manage`.

## Tests
- `tests/Feature/Events/EventChecklistTest.php` (19 incl. providers): tick/untick, no-op (no write, no activity), untouched untick creates no row, existing row reused, **deterministic unique-backstop race** (a `DB::listen` hook inserts the competing row between the action's read and insert), derived 422, unknown 404, cancelled 422 + context, every open status, `done` validation, `assertLocksRow`, read budget ≤ 9, 401, 403.
- `tests/Feature/Events/EventNotesTest.php` (12): set/clear, guest notes untouched, 422 × 3, 5000 accepted, cancelled allowed, activity log, budget, 401, 403.

## Deviations
- The action returns `$locked->load(DETAIL_RELATIONS)` instead of `fresh()`: `fresh()` cost a tenth read and broke the D-30 cap of 9 (no inquiry column is changed by this action, so the locked row is current).
- Spatie activitylog v5 stores model diffs in `attribute_changes`, not `properties` — the activity assertion reads that column.
