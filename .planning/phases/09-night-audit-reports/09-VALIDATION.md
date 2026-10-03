# Phase 9 validation strategy

Status: planned, no tests run by planner. Every decision D-01..D-16 must be covered by implementation review and the tests below. Root records actual commands/results, not anticipated totals.

## Audit gate

- Schema on scratch database: migrate, rollback only two new migrations, migrate again; UUID/date/check/blocker uniqueness, actor FKs, indexes, factories. Hash developer database before/after; never migrate:fresh it.
- First GET without date -> 422; strict impossible date -> 422; explicit date initializes. Freeze/advance Carbon months, omit date and retain state. Different uncreated date -> mismatch. Same date opens once, creates five checks and at most five blockers, returns same UUIDs/evaluated_at after source changes. Closed historical audit can still be read.
- Evaluator fixture: missing/open/settled folio departures; cancelled/unverified/pending exclusions; multi-room partially-unassigned/no-room reservations; inactive/deleted dirty rooms; all ticket active states, priority 2 vs 3; open disputes on settled and checked-out folios. Exact counts, deterministic samples capped 20, correct truncated flag for 21+ issues, absence of source PII.
- Check pending -> resolved/overridden requires note; blank/whitespace/missing/too-long note, invalid status, body actor forgery; second action -> item_resolved; passed checks already terminal. Blocker independent resolution and repetition. Wrong/random UUID ->404.
- Readiness starts clear only when all checks passed; check acknowledgment alone leaves linked blocker open; blocker resolution alone leaves check pending. Close refuses either remainder. With both clear, close changes date exactly one day, stores actor/time; repeated close returns original closed result and never advances again. Any other closed-audit mutation fails closed error.
- Lock tests cover singleton initialization/open/close and audit-before-child write order. If MySQL available: concurrent first open ->one audit/five checks; concurrent close ->one date advance; close vs resolution cannot slip readiness gate. Otherwise explicitly unverified.
- 401 all five staff routes; reports.view-only read success/write 403; reports.manage-only write auth tests with view absent; unauthorized staff/guest 403; body clients cannot claim another actor. Translations resolve in all project-supported locales.

## Reports gate

- Hotel timezone boundaries: selected date begins local midnight, prior UTC day; inclusive end becomes exclusive next-local-midnight; exact end excluded; DST zone fixture dayWindow rather than 24-hour arithmetic. Date pair/order/real-date/max31 validation; no parameters defaults hotel today.
- Multi-room stays spanning both edges; departure day excluded from occupied nights; zero-night/nonoverlap zero; cancelled/unverified/pending excluded; checked-out historical stays included; unassigned eligible room line counts. Current active/live denominator excludes inactive/deleted but includes maintenance; zero rooms rate zero; overbooking not clamped.
- Arrivals/departures count reservations once, independent of room-line multiplicity; include chosen boundary dates.
- Revenue fixture: positive and negative folio lines, dates just outside both bounds, repeated 0.10, large values, empty DB, mutable source limitations. Expected exact decimal strings. Report performs no ledger writes/generation.
- Collections: completed Reservation and Folio payments both counted once even for same stay; pending/failed excluded; EventInquiry isolated; unknown type in other; several folio items cannot multiply payments; total equals three segments; explicit refunds_included false.
- Current open work includes old active requests/tickets outside period, excludes completed/cancelled/resolved/closed, and exposes current_state basis/as_of.
- Query-count tests compare small/large source populations and 1/31-day periods; <=12 report domain SELECTs, no per-day/per-source loops; first audit <=16, read <=6 with authentication bookkeeping excluded. SQL must be portable SQLite/MySQL. Exact integer-cent/decimal aggregate tests mandatory.

## Integration/phase gate

- Full PHPUnit suite once after integrated changes; targeted tests while developing; independent QA reviews actual code against D-01..16 and re-runs relevant failures after fixes.
- API route inventory proves auth:users and correct permission on five routes. Seed catalogue 30/12, all existing presets unchanged; docs explain per-account reports.manage + reports.view.
- Guide endpoint/body/error/shape/basis examples, Postman five requests, API tree coverage, changelog, summary and requirement mapping. Warn dashboard mocks need adaptation, not a claim of completed frontend integration.
- Additive migrations and developer DB hash checked; backend formatting/style check using existing repository commands. No push, no unrelated file staging, commit only after passing gate.

Explicit acceptance limits: MySQL runtime concurrency may be unavailable; source snapshots are current state at opening; posted-line reports are operational and mutable for open folios; occupancy uses current capacity/status. These limitations must survive into summary and guide.
