# Phase 9 implementation patterns and ownership

Load backend/CLAUDE.md and `.claude/skills/tupcode-laravel-backend/SKILL.md` before backend work; guide section 17 is the finishing gate. All Base classes are App\Base. Custom non-CRUD controllers use BaseController and respondFromService with Resources, not hand-written envelopes. Services/actions never request(), HTTP responses or HTTP exceptions. Domain errors receive stable error_code and translated strings. Requests validate; resources shape already-loaded data only. Controllers import concrete typed route-bound UUID models.

Audit public interfaces:

- `Actions/Reports/OpenNightAuditAction::handle(?string $date): array` returns ['data'=>NightAudit,'code'=>200]; transaction creates/loads state, snapshots once, returns eager-loaded audit.
- `Actions/Reports/UpdateNightAuditCheckAction::handle(NightAuditCheck $check,array $data,User $actor): array` returns full updated audit.
- `Actions/Reports/ResolveNightAuditBlockerAction::handle(NightAuditBlocker $blocker,array $data,User $actor): array` returns full updated audit.
- `Actions/Reports/CloseNightAuditAction::handle(NightAudit $audit,User $actor): array` returns full updated audit.
- `Services/Reports/NightAuditService::show(NightAudit $audit): array` loads checks/actors/blockers/actors/closedBy once. It may extend BaseService with NightAudit as model; never expose inherited CRUD routes.
- `Services/Reports/NightAuditEvaluator::evaluate(string $businessDate): array` returns five immutable category payloads with count, UUID samples and truncation; it does not persist or change source tables.

Report interface: `Services/Reports/ReportDashboardService::dashboard(string $startDate,string $endDate): array`, `Support/ReportAggregates` encapsulates supported SQLite/MySQL exact-cent and day-overlap expressions. Report Request normalizes optional dates in controller/service boundary; no request access in service. Resource consumes a bounded array.

Audit response core: uuid, business_date, status(open|closed), evaluated_at UTC, snapshot_basis, current_business_date, last_closed_date, checks[], blockers[], closed_at/by, readiness(checks_pending,blockers_open,can_close). Public actors only uuid/name via eager resource shape; notes never overwrite guest notes. Check: uuid/type/label/status/issue_count/evidence_uuids/evidence_truncated/note/acted_at/acted_by. Blocker: uuid/check_uuid/type/status/note/acted_at/acted_by; category count/evidence may use already-loaded check. No internal IDs.

Report response core: period{date_from,date_to,days,timezone}, generated_at, occupancy{basis,active_rooms,occupied_room_nights,available_room_nights,occupancy_rate}, arrivals, departures, revenue{basis,currency,charges_usd,credits_usd,net_usd}, collections{currency,stays_usd,event_deposits_usd,other_usd,total_usd,refunds_included:false}, open_work{basis,as_of,service_requests,tickets}. All *_usd strings; counts integers; only nonmoney occupancy ratio may be numeric floating point.

## Safe parallel ownership

| Owner | Files | Must not edit |
|---|---|---|
| Audit engineer, plan 09-01 | New NightAudit* models/enums/factories; Actions/Reports/*NightAudit*; Services/Reports/NightAudit*; Requests/Reports/*NightAudit*; Resources/Reports/NightAudit*; Controllers/Staff/NightAuditController; new `2026_10_03_100000_create_night_audit_tables.php`; own test files | routes, lang, permissions, report classes, shared docs/state |
| Reports engineer, plan 09-02 | ReportDashboardService; ReportAggregates; DashboardReportRequest/Resource; Staff/ReportDashboardController; `2026_10_03_100100_add_reporting_indexes.php`; own report tests | audit files, routes, lang, permission seeder, shared docs/state |
| Root integrator, plans 09-03/04 | routes/api.php, five lang/custom.php, RolesAndPermissionsSeeder + count/preset tests, guides/changelog/Postman/tree, Phase9 summaries/shared planning status | Do not overwrite engineer-owned files while their work is active |

New exceptions may be audit-owned by `NightAudit*Exception` naming; reports use ordinary request validation. Audit engineer sends translation key/message/context inventory to root. Root seeds/re-pins before engineer feature tests require middleware; engineers can run service/unit tests first. Shared route registration is root responsibility and happens once concrete classes exist. No author edits another's tests to weaken failures.

Reference existing `tests/Concerns/RecordsRowLocks.php` for lock intent and Phase8 `EventDepositTest` for auth/envelope/UUID isolation; do not assert SQLite is a concurrent row-lock engine. Full suite root only to avoid overlapping resource-heavy processes. Test fixture database always in-memory/scratch.
