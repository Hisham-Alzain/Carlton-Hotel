# Phase 9 local research

Status: complete planning research; implementation/test claims remain unverified. Source labels below are file groups, not context-mode knowledge-base entries (context-mode tools were unavailable).

| Source label | Evidence | Consequence |
|---|---|---|
| phase9-requirements | `.planning/PROJECT.md`, REQUIREMENTS AUDIT-01..03/REPORT-01, ROADMAP Phase9 | Five checks including Phase5 dispute hook; bounded reports; no existing business-date state |
| phase9-audit-ui | `dashboard/src/mocks/data/nightAudit.js`, pages/NightAudit.jsx, services/nightAuditService.js, store/nightAuditStore.js | Mock has property_day, checks, blockers, readiness, closed_at/by; source mock also contains close/reopen functions. Actual page/service has no close call. Its FOLIOS_VIEW gate and mock fields need adaptation |
| phase9-reports-ui | `dashboard/src/mocks/data/reports.js`, pages/Reports.jsx, services/reportsService.js | Static synthetic ADR/RevPAR/MTD/YTD/room-type/source values; no reliable contract for real accounting semantics |
| phase9-time | `backend/app/Support/HotelClock.php`, config/hotel.php | Hotel timezone default Asia/Damascus; dayWindow strict date and DST-safe half-open UTC; app timestamp storage UTC |
| phase9-ledger | Models/Folio.php, FolioItem.php, Payment.php; Support/FolioLedger.php; migrations 2026_09_26_130000..130200 | Folio signed lines, exact decimal/bcmath; SQLite SUM may return binary REAL; openDisputes and unsettled hooks; payments include reservation and folio sources; refunds not subtracted yet |
| phase9-events | Phase8 SUMMARY.md and PROJECT key decisions | Event deposits are completed payments for EventInquiry FQCN; do not add model to morph map; separate from stay/room revenue |
| phase9-stays | Models/Reservation.php, Enums/ReservationStatus.php, reservation/reservation_rooms migrations | Date stays, many room lines, nullable assignment, price snapshots; no no-show enum; holdings scope includes live unverified holds but reporting deliberately excludes them |
| phase9-work | Models/Ticket.php, ServiceRequest.php; Enums/TicketStatus.php, ServiceRequestStatus.php, ServiceRequestPriority.php | High ticket priority is integer 3; active ticket states four; active request states new/in_progress |
| phase9-access | RolesAndPermissionsSeeder.php | reports.view exists; 29 permissions/12 groups after Phase8; no hotel manager preset; reports.manage is per-account with unchanged existing presets |

## Query and index design

Audit evaluator: one count and capped UUID query per category (10 SELECTs), no nested relationship iteration. Distinct parent reservation queries use EXISTS/NOT EXISTS for folio/room-line conditions. Dirty rooms exclude deleted_at and inactive. Samples ordered by id so equal snapshots are deterministic. A category blocker's evidence is read through its check, not independently duplicated.

Report shape: one capacity count; one room-night overlap SUM over eligible reservation room lines; one conditional aggregate for arrivals/departures; one signed folio-line aggregate; one payment aggregate grouped/conditional by morph type; one active requests count; one active tickets count. <=12 SELECTs allows resource/setup overhead but never per-record SQL. Response contains aggregates only, no pagination bypass disguised as a report.

Existing indexes: reservations check_in/check_out/status each indexed; reservation_rooms reservation_id/room_id indexed; rooms status/is_active and soft-delete live uniqueness; folios reservation_id unique/status; disputes status and folio_item_id/status; tickets status/priority and created_at; requests status; payments payable_type/payable_id morph index but no period index; folio_items financial source indexes from Phase5 but no report period index.

Add period indexes on folio_items.created_at and payments(status,created_at,payable_type). Add reservations(status,check_in,check_out) for eligible overlap/arrivals; check_out/status is useful for departures/audit if EXPLAIN or schema checks justify the extra composite. Avoid redundant ticket status/priority and dispute status indexes. New audit tables index each actor FK plus audit/status/unique identifiers. Do not drop existing indexes. Migration down removes only new named indexes.

Monetary aggregates: SQLite per-row integer cents then integer SUM avoids carrying SQLite binary aggregate error into bcadd. MySQL DECIMAL SUM is exact; helper must return a normalized two-decimal string with no PHP float arithmetic. Test 0.10 repeated, negative credits, large totals and empty aggregate. If using rounded integer cents, overflow bounds should be within signed 64-bit; DECIMAL(10,2) per row and max 31-day operational report is practical, but overflow must fail rather than silently coerce.

Room-night overlap: MAX(0, MIN(check_out,to+1)-MAX(check_in,from)), evaluated by supported driver SQL date operations over DATE columns. Do not turn a timestamp day into DATE in server timezone. Bind all user dates. Denominator uses current live active room capacity; historical unavailable inventory is not reconstructable from current data alone.

## Explicit uncertainties and risks

- SQLite can verify unique constraints and lock clauses via RecordsRowLocks, not InnoDB race semantics; actual MySQL simultaneous first-open/close remains an environment-dependent QA check.
- First-open snapshots are not a cross-domain point-in-time lock. Source categories can change during/after capture; disclose and require human attestation.
- Mutable generated folio items can change earlier posting totals. `posted_folio_lines` is the honest basis; closing audit does not freeze all source ledgers.
- Current reservation status and present room inventory make historical occupancy an operational reconstruction, not a formal historical ledger.
- Reports UI's synthetic ADR/RevPAR/MTD/YTD is outside the required real aggregate contract. No unsupported figures should be invented to preserve visual mock parity.
- The complete skill guide contains a stale general API-version bullet contradicting its routing section and actual code; use actual `/api` routes.

No external web research was needed: all decisions depend on local supported code/schema, not a claim of external financial policy.
