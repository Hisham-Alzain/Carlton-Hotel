---
phase: 06-housekeeping-guest-services
status: complete
completed: 2026-09-27
requirements-completed: [HK-01, HK-02, HK-03, HK-04, HK-05, SVC-01, SVC-02, SVC-03, SVC-04, DOCS-01, XCUT-01]
---

# Phase 6 — Housekeeping & Guest Services: Summary

## Endpoints delivered

| Method | Path | Permission |
|---|---|---|
| GET, POST | `/api/v1/housekeeping/tasks` | housekeeping.view / housekeeping.assign |
| GET | `/api/v1/housekeeping/tasks/{task}` | housekeeping.view |
| PATCH | `/api/v1/housekeeping/tasks/{task}/assign` | housekeeping.assign |
| PATCH | `/api/v1/housekeeping/tasks/{task}/status` | housekeeping.update |
| PATCH | `/api/v1/operations/queue/housekeeping-tasks/{uuid}/assign\|status` | checked in service (housekeeping.assign / .update) |
| GET | `/api/v1/operations/queue` | gate widened to admit housekeeping.view |
| GET | `/api/v1/dashboard/summary` | gains `housekeeping_tasks` block |
| GET | `/api/v1/cms/service-requests`, `/{serviceRequest}` | service_requests.view |
| GET | `/api/v1/departure-services` | service_requests.view |
| PATCH | `/api/v1/departure-services/{uuid}/status` | service_requests.update |
| GET | `/api/v1/public/service-catalog` | public; gains `late_checkout` and `luggage` categories (no price) |

Housekeeping tasks are created automatically on check-out (turnover) and when a guest places a service request routed to housekeeping (request task, linked by `service_request_id` FK per the consultant override).

## Waves

06-01 task foundation · 06-02 auto-created tasks · 06-03 task writers · 06-04 task board · 06-05 queue registry (`OperationsQueueType`, third queue type, `room_number` + `allowed_statuses`, Firestore mirror listener) · 06-06 service request board · 06-07 departure catalogue + projection · 06-08 departure status writer (`UpdateServiceBookingStatusAction`) · 06-09 docs + gate. See each `06-NN-SUMMARY.md`.

## Permissions (XCUT-01)

| Permission | Presets | Routes |
|---|---|---|
| housekeeping.view | housekeeping, reception | task GETs, queue tasks, summary block |
| housekeeping.assign | housekeeping, reception | POST tasks, task assign, queue assign |
| housekeeping.update | housekeeping | task status, queue status |
| service_requests.update (existing) | + reception (post-build ruling) | SR queue status, departure status |

Catalogue: 23 → 26 permissions, 10 → 11 groups.

## Error codes added

`housekeeping_task_closed`, `housekeeping_task_transition_invalid`, `service_booking_transition_invalid`, `departure_service_readonly` (all 5 locales).

## Dashboard & App Path Changes (DOCS-01)

- App: GET `/public/service-catalog` — additive (two categories).
- Dashboard: staff reservation `check_out_mode` changed; all routes above are new; queue index/assign/status gain `room_number`, `allowed_statuses`, `reason`, a new `housekeeping-tasks` segment and new 422 codes; dashboard summary gains a block.

No existing path, field or error_code changed this phase; all changes are additive.

## Docs Updated (DOCS-01)

Dashboard guide (Housekeeping, Service Request Board, Departure Services, Operations Queue, permission catalogue, error codes); mobile guide quick-request chips (endpoint index unchanged); changelog §12; Postman folder 20 plus 2 queue examples in folder 16; `docs/carlton-tree.html` flips housekeeping, departures, staff request board, quick requests (api:true 78 → 82 of 94 nodes).

## Edge and prohibition coverage

18 edges = 15 authored + 3 flagged (HK-02 and HK-03 unclassified, SVC-04 concurrency). 2 MySQL-only lock backstops (SQLite `lockForUpdate` is a no-op). Prohibitions verified per plan summaries.

## Test counts

1522 (phase start) → 1626 (after 06-04) → 1702 (after 06-09) → 1704 (QA pass) → 1708 (after post-build fixes: reception ruling, QA minors 1 and 2), all green (`php artisan test`, 11200 assertions). ParaTest is not installed, so `--parallel` is unavailable; the suite runs serially (~3.7 min).

## QA verdict

**PASS.** 8/8 mutations caught. 3 minors:

1. **Fixed.** `UpdateRequestStatusAction` wrote the request status, the Firestore mirror and the D-11 cancel of the linked request task in separate transactions; a throwing cancel left the request closed, the task open, and a 500. Choice: one `DB::transaction(fn, 3)` that locks room(s) → task(s) → request — the same order as the task path (D-05: room → task → request), so no deadlock between the two paths. The cancel still goes through `UpdateHousekeepingTaskStatusAction` (a savepoint that re-locks the same rows). The mirror moved to `DB::afterCommit` so a rolled-back status is never published. Tickets keep the plain update. Test: `RequestTaskOnServiceRequestTest::test_a_failing_task_cancel_rolls_back_the_request_close` (cancel forced to throw → 500, request still `new`, task still `pending`, nothing mirrored).
2. **Fixed.** `housekeeping:reconcile` only scanned rows with `dedupe_key` set. It now also re-saves open turnover/stayover/inspection tasks stored with a NULL key so the model's saving hook re-derives it (`Restored N missing dedupe key(s).`); an open duplicate whose key is already held is reported by uuid (`Duplicate open tasks (close by hand): …`) and left keyless because the column is UNIQUE. Tests: 3 new cases in `ReconcileHousekeepingTasksTest`.
3. **Deferred** — any user is assignable; see carry-forwards.

## Deviations

- Consultant override: plain nullable unique `service_request_id` FK instead of a morph source; the existing `HasOne` covered 06-06's intended `MorphOne`.
- Two 06-03 unit assertions narrowed (no `service_request_{uuid}` mirror; tasks mirror themselves).
- `due_at[gte|lte]` filter values normalised to UTC; unparseable values → 422.
- Task `store` builds its response directly to keep distinct created / already-exists messages (D-08).
- `before_or_equal` validation message mapped in `BaseRequest` and translated in 5 locales.
- 06-04 tests were written after the code (no red-first run).

## Flagged carry-forwards

- Pre-existing: non-GET verbs on GET-only paths returned 500 instead of 405. **Consultant decision (conf 90):** fixed in a separate repair commit — `bootstrap/app.php` renders a 405 envelope with `error_code: method_not_allowed` (additive; note to Flutter/React teams, no client change required).
- **Consultant decision (conf 85), applied in the Phase 6 commit:** reception preset gains `service_requests.update`. Blast radius is exactly two routes: `PATCH /operations/queue/service-requests/{uuid}/status` and `PATCH /departure-services/{uuid}/status`. `service_requests.assign` stays withheld. Rationale: late checkout routes to the reception department. Pinned in `RolePresetsTest`, `SeederTest` and `DepartureServicesTest` (reception → 200; a view-only token still → 403).
- QA minor 3 — any staff user can be named as an assignee (no eligibility check on department/role): **deferred to Phase 7 D-09**, which introduces assignee eligibility across all queue types.
- Known gaps: no `POST` and no `GET /departure-services/{uuid}`.
- No-alias exception for task verbs (D-13).
- Queue cap 3 × 500.
- The literal `turnover` reason skips the room-board closer.

## Production deploy notes

- [BLOCKING] `php artisan migrate` (three additive migrations).
- [BLOCKING] `php artisan db:seed --class=RolesAndPermissionsSeeder` and `--class=GuestServiceCatalogSeeder`.
- [BLOCKING] set `HOTEL_TURNOVER_SLA_MINUTES` (default 120; missing from `.env.example`, which is permission-blocked for agents).
- [BLOCKING] run a queue worker for `MirrorHousekeepingTaskToFirestore`.
- Run `php artisan housekeeping:reconcile` after deploy.
