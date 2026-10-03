# Phase 9 discussion log

2026-10-03: user authorized continuing the resumed implementation through completion; original workflow delegates business decisions to consultant, implementation to engineering, independent review to QA. Current consultant is a Codex agent. No Claude model or external council was invoked.

- Reviewed backend/CLAUDE.md, the required TupCode backend SKILL.md and complete developer-guide source, project/requirements/roadmap, Phase8 context and summary, dashboard audit/report mocks and page usage, and relevant model/schema/time/financial patterns. Local code wins over stale `/api/v1` wording.
- Business-date decision: explicit first date and persisted progression, not today at every GET. Rejected automatic wall-clock close progression because reopening a date must be deterministic. Risk of choosing a wrong initial date is a documented setup obligation, not an unrequested reset feature.
- Concurrency: singleton unique key plus lock, unique audit date, check unique audit/type, blocker unique check; all mutations serialize through audit. Parent approved atomic first-open and close, with MySQL runtime limitation recorded.
- Snapshot decision: category snapshots with exact counts and bounded evidence; current source state at first-open is not historical reconstruction. Rejected unbounded per-source blockers. Parent required explicit evaluated_at and disclosure of current dirty-room/ticket/dispute state for old dates.
- Resolution decision: two explicit attestations (check and category blocker), both required to close. Parent required no ambiguous hidden auto-resolution. No underlying domain mutations or financial writes under reports.manage.
- Close endpoint: explicit POST is a necessary scope amendment to fulfill roadmap's close-business-date goal. Original AUDIT-01..03 list alone cannot advance last_closed_date. Parent agreed to explicit close and state model.
- Reporting decision: bounded SQL aggregates over maximum 31 dates. Booked room-nights handle multi-room stays, half-open dates, cancellation/unverified exclusions, current inventory denominator and zero rooms.
- Financial decision: parent confirmed signed folio lines by posting created_at for operational revenue, cash collections separately segmented by payable type. Neither treated as full accrual accounting; no floats, no joined-ledger double counts, refunds excluded explicitly. Event deposits never room revenue.
- Permissions: reports.view remains read plus lazy immutable initialization. reports.manage owns acknowledgments and close. Actual roles have no hotel management preset: grant per account, do not widen content_manager. Seed catalogue 30/12 after addition.
- Frontend: contract adaptation documented instead of returning invented mock numbers or aliases. Backend-only scope retained.
- Plan split communicated to parent before implementation: audit-owned files, reports-owned files, root-owned route/localization/permissions/docs. Plans are executable but unexecuted; no suite run by planner, no source DB changed.

Rejected alternatives: SQL sums cast straight to float; day-by-day report SELECT loops; serializing full source records in snapshots; automatic source ticket/room/folio resolution from blockers; closing via a checklist toggle; silent historical reconstruction; inventing a manager preset; adding unrelated analytics/export functionality.
