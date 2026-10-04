---
phase: 09-night-audit-reports
plan: 05
status: complete
---

# 09-05 Summary — GET night audit + night_audit.manage (30/13)

## Built
- Seeder: `night_audit.manage` appended with the Phase 9 comment. Catalogue **30 permissions / 13 groups**. `$presets` untouched.
- `GET /api/operations/night-audit` — `auth:users` + `permission:reports.view|night_audit.manage` ("P9 — Night audit" block after `/operations/staff`).
- `NightAuditController::show` (extends `BaseController`) passes `validated('date')`, `$user->can('night_audit.manage')` and the user to `OpenNightAuditAction`; private `payload()` wraps `NightAuditPayloadResource` + `respondFromService`.
- `ShowNightAuditRequest`: `date` `sometimes|date_format:Y-m-d` (Laravel's `date_format` already round-trips, so `2026-02-30` fails; no extra closure needed).
- Resources `NightAuditPayloadResource` (`state` + `audit|null`), `NightAuditResource` (D-13; `readiness` from `NightAudit::readiness()`, actors `{uuid,name}`), `NightAuditCheckResource` (`label`, `blocker_uuid`), `NightAuditBlockerResource` (`check_uuid`, `type`). Instants `toIso8601ZuluString()`; dates `Y-m-d`; no internal ids.
- `NightAuditService` now also sets `checks.blocker` from the already-loaded blockers (no extra statement); readiness logic moved to `NightAudit::readiness()` (pure over loaded relations), service delegates.

## Tests
- New `NightAuditShowTest` (13): D-13 structure + values, Accept-Language label, same audit on re-GET, 422 not_initialized, viewer 403 `{reason}` then 200 after a manager initializes, viewer lazily creates the current audit once initialized, 422 date_in_future / date_mismatch, null audit ahead of hotel today, invalid dates → `validation_failed`, closed history readable, no PII, re-read budget 5.
- New `NightAuditPermissionsTest` (data-provider matrix, later plans append rows): 401, 7 presets 403, unrelated permission 403, granting permissions alone, super admin.
- Re-pinned: `SeederTest` (`test_all_30_permissions_seeded`, 30 ×2, `night_audit.manage` added to the deliberately role-less list in `test_every_seeded_permission_is_reachable_through_some_role`), `PermissionsGroupedTest` (13, `night_audit` = `['night_audit.manage']`), `RolePresetsTest` (`test_phase9_leaves_every_preset_unchanged`, 7 exact arrays), `CmsAccessControlTest` (`$notYetBuilt = ['pricing.edit']`).
- `docs/API_GUIDE_DASHBOARD.md` "Genuinely inert" paragraph now names only `pricing.edit` (FA-9.05-2 path: `PermissionGuideAccuracyTest` pins the inert list, so it moved into this plan).
- Full suite at this point: 2264/2265 — the one failure was that guide paragraph, fixed and re-run green (`PermissionGuideAccuracyTest` 7/7).

## Deviations
- `SeederTest::test_every_seeded_permission_is_reachable_through_some_role` needed `night_audit.manage` in its role-less allow-list (not anticipated by the plan; D-15 makes it per-account).
- Guide paragraph edit pulled forward from 09-10 (FA-9.05-2).
