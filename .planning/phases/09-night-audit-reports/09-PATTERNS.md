# Phase 9 patterns — new files mapped to closest analogs

Load `backend/CLAUDE.md` and `backend/.claude/skills/tupcode-laravel-backend/SKILL.md` before backend work; its §17 checklist is the finishing gate. Plans are executed sequentially (waves 1–10), so there is no parallel file ownership; each plan lists the exact files it may touch.

## File map

| New / changed file | Closest analog | Notes |
|---|---|---|
| `database/migrations/2026_10_04_100000_create_night_audit_tables.php` | `2026_10_02_100100_create_event_inquiry_checklist_items_table.php`, `2026_09_26_130200_create_folio_item_disputes_table.php` | 4 tables (D-07); explicit `restrictOnDelete`; `down()` drops in reverse FK order |
| `database/migrations/2026_10_04_100100_add_report_indexes.php` | `2026_10_02_100300_add_scheduled_index_to_service_bookings_table.php` | named indexes; `down()` drops only them (D-08) |
| `app/Enums/NightAudit{Status,CheckType,CheckStatus,BlockerStatus}.php` | `app/Enums/EventChecklistItem.php`, `FolioDisputeStatus.php` | string-backed; `NightAuditCheckType::isBlocking()`, `label()`; `NightAuditCheckStatus::isTerminal()` |
| `app/Models/NightAudit{State,,Check,Blocker}.php` | `app/Models/EventInquiryChecklistItem.php` | `HasUuid` (not on State), `HasFactory`, `LogsActivity` with `logOnly` status/acted_by/closed_* (D-14); casts dates/enums/JSON; relations `checks`, `blockers`, `opener`, `closer`, `actor`, `check`, `audit` |
| `database/factories/NightAudit*Factory.php` | `EventInquiryChecklistItemFactory.php` | states `closed()`, `pending()`, `blocking()` at engineer discretion |
| `app/Support/MoneyAggregate.php` | `app/Support/FolioLedger.php`, `HotelClock.php` | static, driver switch on `DB::connection()->getDriverName()` (D-21) |
| `app/Actions/NightAudit/OpenNightAuditAction.php` | `app/Actions/Events/RecordEventDepositAction.php` (lock + replay), `app/Actions/Folio/*` | `handle(?string $date, bool $canInitialize, User $actor): array` → `['data' => ['state'=>NightAuditState,'audit'=>?NightAudit], 'code'=>200]` |
| `app/Support/NightAuditEvaluator.php` (or private methods) | `OperationsQueueService::summary` (grouped counts) | `evaluate(string $date): array<type, {count, evidence, truncated}>`; read-only |
| `app/Actions/NightAudit/ResolveNightAuditCheckAction.php`, `ResolveNightAuditBlockerAction.php`, `CloseNightAuditAction.php` | `app/Actions/Events/RecordEventDepositAction.php` | lock order state → audit → child; return the same payload as open |
| `app/Services/Operations/NightAuditService.php` | `app/Services/Operations/OperationsQueueService.php` | `payload(NightAuditState, ?NightAudit): array` eager-loads `checks.actor`, `blockers.actor`, `blockers.check`, `opener`, `closer` |
| `app/Services/Reports/ReportService.php` | `OperationsQueueService::summary` | `dashboard(string $from, string $to): array`; ≤ 8 queries |
| `app/Http/Controllers/Admin/NightAuditController.php`, `ReportController.php` | `Admin/FrontDeskController.php`, `Admin/OperationsStaffController.php` | extend `BaseController`, `respondFromService`; pass `$request->user()` and `->can('night_audit.manage')` to actions; never query |
| `app/Http/Requests/NightAudit/*Request.php`, `Requests/Reports/ReportDashboardRequest.php` | `Requests/Events/UpdateEventChecklistItemRequest.php` | extend `BaseRequest`; `prepareForValidation` trims `note`; strict `date_format:Y-m-d` + round-trip closure; 31-day rule message `custom.validation.report_period_too_long` |
| `app/Http/Resources/NightAudit/*Resource.php`, `Resources/Reports/ReportDashboardResource.php` | `Resources/Events/*` | `whenLoaded` only; UUIDs only; ISO-8601 Z via `->toIso8601ZuluString()` or house helper |
| `app/Exceptions/NightAudit{NotInitialized,DateMismatch,DateInFuture,Closed,ItemResolved,NotReady}Exception.php` | `EventDepositAlreadyRecordedException.php` | one-liners; 422; context passed by the thrower |
| `routes/api.php` (new block after `/operations/staff`, ~line 795) | the `operations/queue` and `dashboard/summary` blocks | `Route::middleware(['auth:users', 'permission:…'])` per D-01 |
| `database/seeders/RolesAndPermissionsSeeder.php` | Phase 8 `events.*` entry | append `'night_audit.manage'` with a Phase 9 comment; presets untouched |
| `lang/{en,ar,fr,tr,es}/custom.php` | Phase 8 `event_checklist` section | new `night_audit` section (check labels, statuses), keys in `errors`, `messages`, `validation`; same order everywhere |

## Contract shapes

Audit payload and report payload: exactly as `09-CONTEXT.md` D-13 and D-16..D-19. Money = 2dp strings; `occupancy_rate` = 4dp string; counts = ints; timestamps ISO-8601 Z; ids = UUIDs only.

## Test conventions

- Real Sanctum bearer tokens (`$user->createToken('t')->plainTextToken`), never `actingAs` (Phase 8 standing rule 7).
- Seed `RolesAndPermissionsSeeder` in permission tests; preset tokens via `User::factory()->create()->assignRole($role)`.
- Locks: `use RecordsRowLocks; $this->lockedSelects(fn () => …)`; assert order state → audit → child.
- Budgets (R-4): a small `CountsDomainQueries` helper (test concern) wrapping `DB::listen`, ignoring statements that touch `activity_log`, around the action/service call only.
- Time: `$this->travelTo(...)`, `config(['hotel.timezone' => 'Europe/London'])` for DST.
- SQLite date storage: seed `check_in/check_out` via `DB::table('reservations')->update(['check_out' => 'Y-m-d 00:00:00'])` to prove `whereDate`.
- Scratch migrations: temp SQLite file under the scratchpad; never `database/database.sqlite` or its `.bak-*`.
