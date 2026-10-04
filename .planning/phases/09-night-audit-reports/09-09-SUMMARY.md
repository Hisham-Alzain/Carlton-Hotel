---
phase: 09-night-audit-reports
plan: 09
status: complete
---

# 09-09 Summary — revenue, collections, open work, budget

## Built (`app/Services/Reports/ReportService.php`)
- Window: `[HotelClock::dayWindow(from)[0], HotelClock::dayWindow(to)[1])` (UTC, DST-safe).
- `revenue {basis: posted_folio_lines, currency: USD, charges_usd, credits_usd, net_usd, by_source{reservation, service_booking, service_request, manual, credit}}` — one `GROUP BY source_type` with `MoneyAggregate` cents expressions, folded with bcmath; every key present.
- `collections {basis: completed_payments, refunds_included: false, stays_usd, event_deposits_usd, other_usd, total_usd}` — one `GROUP BY payable_type` over `status = completed`; Reservation + Folio morph classes → stays, EventInquiry → event deposits, else other. Never joined to folio items; morph map untouched.
- `open_work {basis: current_state, as_of = generated_at, service_requests{new, in_progress, total}, tickets{open, assigned, in_progress, waiting_guest, total}}` — two grouped status counts.
- Arrivals/departures moved from two counts (09-08) into **one conditional aggregate** with half-open bounds on the stored value (no date SQL), to meet the expected 7 statements.
- Report shaping stays a service array (Claude's discretion; no `ReportDashboardResource` file).

## Tests
- `ReportRevenueTest` (4): mixed sources/signs + 10 × 0.10, local 23:59:59 / 00:00:00 edges under Asia/Damascus, empty all `"0.00"` with 5 keys, strings only.
- `ReportCollectionsTest` (6): split by payable type with pending/failed/out-of-window excluded, deposit absent from revenue, EventInquiry/Reservation/Folio FQCN + unmapped, folio payment once despite 3 items, factory payment = stays, empty zeros.
- `ReportOpenWorkTest` (3): active counts with history excluded and period independence, empty zeros, exact top-level keys + forbidden metric keys absent.
- `ReportQueryBudgetTest` (1): **7 statements** for empty 1-day and seeded 31-day (30 reservations, 60 lines, 200 items, 50 payments, 40 requests, 40 tickets).
- `NightAuditPermissionsTest` matrix += `reports` (`reports.view` only; `night_audit.manage` alone → 403).
- `--filter='Report|NightAuditPermissionsTest|MoneyAggregate'`: 120 green.

## Deviations
- The acceptance grep `refunds` matches the contract key `refunds_included` (required by D-18); no refund logic exists.
