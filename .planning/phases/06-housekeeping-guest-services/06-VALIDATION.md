---
phase: 6
slug: housekeeping-guest-services
# status lifecycle: draft (seeded by plan-phase) → validated (set by validate-phase §6)
status: draft
nyquist_compliant: true
wave_0_complete: false
created: 2026-09-27
---

# Phase 6 — Validation Strategy

> Per-phase validation contract for feedback sampling during execution.

## Test Infrastructure

| Property | Value |
|----------|-------|
| **Framework** | PHPUnit 12.5 (Laravel 13), SQLite `:memory:` via `backend/phpunit.xml` (lock order asserted via query listener) |
| **Config file** | `backend/phpunit.xml` |
| **Quick run command** | `cd backend && php artisan test --filter=Housekeeping` |
| **Full suite command** | `cd backend && php artisan test` |
| **Estimated runtime** | ~7 minutes (full suite) |

## Sampling Rate

- **After every task commit:** `cd backend && php artisan test --filter=Housekeeping` (or `--filter=DepartureServices` / `--filter=ServiceRequestBoard`)
- **After every plan wave:** full suite
- **Before `/gsd-verify-work`:** full suite green
- **Max feedback latency:** 420 seconds

## Per-Task Verification Map

| Task ID | Plan | Wave | Requirement | Threat Ref | Secure Behavior | Test Type | Automated Command | File Exists | Status |
|---------|------|------|-------------|------------|-----------------|-----------|-------------------|-------------|--------|
| 06-01-01 | 01 | 1 | HK-04 | T-06-01 | Specs: one open task per room/type, lost race → 200, stale key rethrown, room locked first (RED) | unit + feature | `cd backend && ! php artisan test --filter='HousekeepingTaskStatusTest\|CreateHousekeepingTaskActionTest\|ReconcileHousekeepingTasksTest'` | ❌ W0 (this task) | ⬜ pending |
| 06-01-02 | 01 | 1 | HK-04 | T-06-01, T-06-03 | Tables, model-derived dedupe key, ensureOpen; scratch migrate cycle; no other writer of the key | unit | `cd backend && php artisan test --filter='HousekeepingTaskStatusTest\|CreateHousekeepingTaskActionTest'` | ✅ after 06-01-01 | ⬜ pending |
| 06-01-03 | 01 | 1 | HK-04 | T-06-01 | `housekeeping:reconcile` repairs stale keys, lists dirty rooms without a turnover | feature | `cd backend && php artisan test --filter=ReconcileHousekeepingTasksTest` | ✅ after 06-01-01 | ⬜ pending |
| 06-02-01 | 02 | 2 | HK-04 | T-06-01 | Specs: turnover per room on check-out, request task, check_out_mode (RED) | feature | `cd backend && ! php artisan test --filter='TurnoverOnCheckOutTest\|RequestTaskOnServiceRequestTest'` | ❌ W0 (this task) | ⬜ pending |
| 06-02-02 | 02 | 2 | HK-04 | T-06-01, T-06-03, T-06-04 | Listeners dedupe via ensureOpen; failures reported not thrown; check_out_mode staff-only | feature | `cd backend && php artisan test --filter='TurnoverOnCheckOutTest\|RequestTaskOnServiceRequestTest\|CheckOutTest\|ExpressCheckoutTest'` | ✅ after 06-02-01 | ⬜ pending |
| 06-03-01 | 03 | 3 | HK-03, HK-04 | T-06-03, T-06-05 | Specs: transitions, room hook, lock order, room-board closer, request link (RED) | unit + feature | `cd backend && ! php artisan test --filter='UpdateHousekeepingTaskStatusActionTest\|AssignHousekeepingTaskActionTest\|RoomBoardClosesTurnoverTest'` | ❌ W0 (this task) | ⬜ pending |
| 06-03-02 | 03 | 3 | HK-03 | — | Six housekeeping keys in five locales; two exceptions | grep + feature | `cd backend && php artisan test --filter='LocaleFoundationTest\|ValidationMessageLocalizationTest'` | ✅ | ⬜ pending |
| 06-03-03 | 03 | 3 | HK-03, HK-04 | T-06-02, T-06-03, T-06-05 | Single writers room→task; only UpdateRoomStatusAction writes rooms.status; cancel never touches the room | unit + feature | `cd backend && php artisan test --filter='UpdateHousekeepingTaskStatusActionTest\|AssignHousekeepingTaskActionTest\|RoomBoardClosesTurnoverTest\|RequestTaskOnServiceRequestTest'` | ✅ after 06-03-01 | ⬜ pending |
| 06-04-01 | 04 | 4 | HK-01, HK-02, HK-03, HK-04 | T-06-02, T-06-05 | Specs: list/filters/edges, create 201/200, assign, status, 401/403/422 (RED) | feature | `cd backend && ! php artisan test --filter='Tests\\Feature\\Housekeeping\\(IndexTest\|CreateTest\|AssignTest\|StatusTest)'` | ❌ W0 (this task) | ⬜ pending |
| 06-04-02 | 04 | 4 | HK-01 | T-06-04, T-06-05 | Hotel-day window, filter rejects malformed input, resource without guest data | php -l + script | `cd backend && php -l app/Filters/HousekeepingTaskFilter.php` (+ dayWindow check in plan) | ✅ | ⬜ pending |
| 06-04-03 | 04 | 4 | HK-01, HK-02, HK-03 | T-06-02, T-06-08 | Five routes behind housekeeping.*; seeded count N+3, 11 groups; presets exact | feature | `cd backend && php artisan test --filter='Tests\\Feature\\Housekeeping\|SeederTest\|PermissionsGroupedTest\|RolePresetsTest\|CmsAccessControlTest\|PermissionGuideAccuracyTest'` | ✅ after 06-04-01 | ⬜ pending |
| 06-05-01 | 05 | 5 | HK-05 | T-06-02, T-06-05 | Specs: third type, permissions, shape, ordering, mirror once per write (RED) | feature | `cd backend && ! php artisan test --filter='OperationsQueueHousekeepingTest'` | ❌ W0 (this task) | ⬜ pending |
| 06-05-02 | 05 | 5 | HK-05 | T-06-04 | Advisory allowedTargets, withRoomNumber scope, widened index gate | script + feature | `cd backend && php artisan test --filter='ServiceCatalogTest\|OperationsQueueTest::test_queue'` | ✅ | ⬜ pending |
| 06-05-03 | 05 | 5 | HK-05 | T-06-04, T-06-05 | Registry-driven resolve/permission/index/summary/validation; room_number, allowed_statuses | feature | `cd backend && php artisan test --filter=OperationsQueueTest` | ✅ | ⬜ pending |
| 06-05-04 | 05 | 5 | HK-05 | T-06-02, T-06-06 | Task arm delegates to writers; queued Firestore mirror, outage-safe | feature | `cd backend && php artisan test --filter='OperationsQueueHousekeepingTest\|OperationsQueueTest'` | ✅ after 06-05-01 | ⬜ pending |
| 06-06-01 | 06 | 6 | SVC-01 | T-06-04 | Specs: board shape, filters, ≤7 queries, read-only, queue writes (RED) | feature | `cd backend && ! php artisan test --filter=ServiceRequestBoardTest` | ❌ W0 (this task) | ⬜ pending |
| 06-06-02 | 06 | 6 | SVC-01 | T-06-02, T-06-04, T-06-05 | GET-only board behind service_requests.view; guest limited to uuid + name | feature | `cd backend && php artisan test --filter='ServiceRequestBoardTest\|CmsAccessControlTest\|PermissionGuideAccuracyTest'` | ✅ after 06-06-01 | ⬜ pending |
| 06-07-01 | 07 | 7 | SVC-02, SVC-04 | T-06-04, T-06-07 | Specs: four kinds, hotel-day window, arrival exclusion, truncation, ≤7 queries, catalogue (RED) | feature | `cd backend && ! php artisan test --filter='DepartureServicesTest\|ServiceCatalogTest'` | ❌ W0 (this task) | ⬜ pending |
| 06-07-02 | 07 | 7 | SVC-02, SVC-04 | T-06-07 | Unpriced late_checkout / luggage categories; Department routing; booking transition table | feature | `cd backend && php artisan test --filter=ServiceCatalogTest` | ✅ after 06-07-01 | ⬜ pending |
| 06-07-03 | 07 | 7 | SVC-02 | T-06-03, T-06-04, T-06-05 | Bounded projection behind service_requests.view; validated filters | feature | `cd backend && php artisan test --filter='DepartureServicesTest\|ServiceCatalogTest\|FolioTest'` | ✅ after 06-07-01 | ⬜ pending |
| 06-08-01 | 08 | 8 | SVC-03 | T-06-05 | Specs: PATCH resolution, family check, read-only express, booking table, lock (RED) | unit + feature | `cd backend && ! php artisan test --filter='UpdateServiceBookingStatusActionTest\|DepartureServicesTest'` | ❌ W0 (this task) | ⬜ pending |
| 06-08-02 | 08 | 8 | SVC-03 | — | Three departure keys in five locales; two exceptions | grep + feature | `cd backend && php artisan test --filter='LocaleFoundationTest\|ValidationMessageLocalizationTest'` | ✅ | ⬜ pending |
| 06-08-03 | 08 | 8 | SVC-03 | T-06-02, T-06-03, T-06-05 | Booking writer under row lock; express rows never written; service_requests.update gate | unit + feature | `cd backend && php artisan test --filter='UpdateServiceBookingStatusActionTest\|DepartureServicesTest'` | ✅ after 06-08-01 | ⬜ pending |
| 06-09-01 | 09 | 9 | DOCS-01, XCUT-01 | T-06-08 | Dashboard guide accurate; inert list unchanged | grep + feature | `cd backend && php artisan test --filter=PermissionGuideAccuracyTest` | ✅ | ⬜ pending |
| 06-09-02 | 09 | 9 | SVC-04, DOCS-01 | — | Chip mapping, changelog, Postman JSON valid, tree +4 api:true | script | node tree/Postman checks in the plan's verify | ✅ | ⬜ pending |
| 06-09-03 | 09 | 9 | XCUT-01 | T-06-09 | Gate: routes, permissions, scratch migrations, folio files untouched, full suite; SUMMARY contract | full suite | `cd backend && php artisan test` | ✅ | ⬜ pending |

Every task has an `<automated>` verify; RED tasks assert failure and are followed in the same plan by the GREEN task that runs the same specs, so no three consecutive tasks lack a passing automated check.

## Wave 0 Requirements

Wave 0 is folded into the first (QA, RED) task of each plan, which creates the spec files before any implementation in that plan:

- [ ] 06-01-01: `tests/Unit/Housekeeping/HousekeepingTaskStatusTest.php`, `tests/Unit/Housekeeping/CreateHousekeepingTaskActionTest.php`, `tests/Feature/Housekeeping/ReconcileHousekeepingTasksTest.php`; `database/factories/HousekeepingTaskFactory.php` arrives in 06-01-02
- [ ] 06-02-01: `tests/Feature/Housekeeping/{TurnoverOnCheckOut,RequestTaskOnServiceRequest}Test.php`; `CheckOutTest` / `ExpressCheckoutTest` check_out_mode methods
- [ ] 06-03-01: `tests/Unit/Housekeeping/{UpdateHousekeepingTaskStatusAction,AssignHousekeepingTaskAction}Test.php`, `tests/Feature/Housekeeping/RoomBoardClosesTurnoverTest.php`; request-link methods
- [ ] 06-04-01: `tests/Feature/Housekeeping/{Index,Create,Assign,Status}Test.php`; re-pins of `SeederTest`, `PermissionsGroupedTest`, `RolePresetsTest` in 06-04-03 (count = seeder on disk + 3, never hard-coded); `PermissionGuideAccuracyTest` and `CmsAccessControlTest` run unchanged
- [ ] 06-05-01: `tests/Feature/Operations/OperationsQueueHousekeepingTest.php`; `OperationsQueueTest` room_number / allowed_statuses assertions
- [ ] 06-06-01: `tests/Feature/Operations/ServiceRequestBoardTest.php`
- [ ] 06-07-01: `tests/Feature/Operations/DepartureServicesTest.php`; `ServiceCatalogTest` ten-category re-pin; `ServiceBookingFactory::transfer()` arrives in 06-07-02
- [ ] 06-08-01: `tests/Unit/Service/UpdateServiceBookingStatusActionTest.php`; PATCH methods in `DepartureServicesTest`
- [ ] Existing infrastructure reused: `tests/Concerns/RecordsRowLocks.php`, `tests/Support/FakeFirebaseService.php`, PHPUnit / RefreshDatabase (no framework install)

## Manual-Only Verifications

| Behavior | Requirement | Why Manual | Test Instructions |
|----------|-------------|------------|-------------------|
| Guides, changelog, Postman, tree nodes (housekeeping, departures, staff request board, quick requests) | SVC-04, DOCS-01 | Documentation content | Open `docs/carlton-tree.html`; the four nodes are api:true with real `ep`s |
| Summary lists housekeeping.* presets, [BLOCKING] notes, queue worker, no-alias exception, known gaps | XCUT-01 | Summary format | Read the phase SUMMARY.md |

## Validation Sign-Off

- [ ] All tasks have `<automated>` verify or Wave 0 dependencies
- [ ] Sampling continuity: no 3 consecutive tasks without automated verify
- [ ] Wave 0 covers all MISSING references
- [ ] No watch-mode flags
- [ ] Feedback latency < 420s
- [ ] `nyquist_compliant: true` set in frontmatter

**Approval:** pending
