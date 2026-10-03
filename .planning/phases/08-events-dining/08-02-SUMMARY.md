---
phase: 08-events-dining
plan: 02
status: complete
---

# 08-02 Summary — events.* permissions and re-gate

## Built
- `RolesAndPermissionsSeeder`: `events.view`, `events.manage`, `events.deposit` (29 permissions / 12 groups); `events` preset += the three; Phase 7 comment rewritten (event half of A4 resolved, conversations half remains).
- `routes/api.php`: `cms/event-inquiries` index/show → `permission:events.view`; status/assign → `permission:events.manage`.
- `OperationsQueueService::summary()`: `event_inquiries` key emitted on `events.view`, outside the tickets arm (PR-2).

## Tests
- New `tests/Feature/Events/EventInquiryPermissionsTest.php` (7 incl. data provider).
- Re-pinned: `EventInquiryTest` (actor `events.view/manage`; `tickets.view`-only → 403), `RolePresetsTest` (events preset diff; reception/concierge hold no `events.*`; blast-radius test now asserts 403 on event inquiries and no `event_inquiries` summary key; conversations/queue pins unchanged), `SeederTest` (26 → 29, new `test_only_the_events_preset_holds_events_permissions`), `PermissionsGroupedTest` (11 → 12, `events` group), `DashboardSummaryTest` (`test_tickets_view_unlocks_tickets_only`, `test_events_view_unlocks_event_inquiries`).
- Full suite: 2049 green.

## Deviations
- `events.deposit` is seeded here but enforced only from 08-05, and two guards flag inert permissions:
  - `PermissionGuideAccuracyTest` → guide "Genuinely inert" paragraph names `events.deposit` as seeded ahead of its route (FA-8.02-1 path).
  - `CmsAccessControlTest::test_every_seeded_permission_is_enforced_somewhere` → `events.deposit` added to `$notYetBuilt` with a TEMP comment.
  - Both are removed in 08-05.

## Contract tightening
- Reception and concierge lose all event-inquiry access (index/show/status/assign → 403) and the `event_inquiries` dashboard summary key.
