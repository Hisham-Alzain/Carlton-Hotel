# Phase 9 discussion log

## 2026-10-03 — Codex draft (superseded in part)

A Codex planning agent wrote the first 09-CONTEXT (D-01..D-16), RESEARCH, PATTERNS, VALIDATION and four plans (09-01..09-04) under the owner's delegated workflow. No Claude model or council was involved. The files were committed with Phase 8 in `3886416`. Its main choices: persisted business-date singleton, explicit POST close, five-category snapshot, one blocker per non-empty check, new `reports.manage`, catalogue 30/12, driver-specific `julianday`/`DATEDIFF` occupancy SQL, budgets ≤12/≤16/≤6, AR/EN-centric wording in places.

## 2026-10-04 — Consultant decision set (binding)

Consultant: **Opus 5.5 standing in for Fable. No ai-council was convened.** The owner delegated every decision (memory: full auto, consultant decides), so nothing was escalated to the user. The consultant read the code read-only and did not run the suite (Phase 8 QA was running in the same tree). Output: D-01..D-25 in the scratchpad file `09-consultant-decisions.md`, now transcribed into `09-CONTEXT.md`.

Kept from the draft: persisted business date and explicit-date initialization (D-03), explicit close as scope completion (D-01/D-12), snapshot-at-first-open semantics (D-09), the five evaluator definitions (D-10), idempotent lazy creation (D-11), separate revenue vs collections measures (D-18), current-state open work (D-19), no fabricated metrics (D-20).

Superseded (Δ) with reasons:

| Δ | Draft | Final | Reason |
|---|---|---|---|
| Permission | `reports.manage`; GET `reports.view` only | `night_audit.manage`; GET `reports.view\|night_audit.manage` | Reports are read-only; a night auditor must attest/close without seeing revenue (least privilege) |
| Catalogue | 30 / 12 groups | 30 / **13** groups | a new prefix `night_audit` is a new group; the draft's 12 was wrong even for its own string |
| Blockers | one per non-empty check (≤5) | only `unsettled_departures` + `unassigned_arrivals` (≤2); no override; checks expose `blocking` | the draft doubled sign-off without information; advisory categories are current-state, disputes never block (FOLIO-03) |
| Initialization | any first reader | `night_audit.manage` only; view-only → 403; future initial date → 422 `night_audit_date_in_future` | picking the accounting start date is a setup act |
| Future current date | creates a snapshot | 200 with `audit:null` | closing D at 23:30 then reloading must not create a meaningless D+1 snapshot |
| Error code | `night_audit_date_required` | `night_audit_not_initialized` | names the state, not the field |
| Schema | — | `night_audits.opened_by` added; report-index migration (`folio_items.created_at`, `payments(status, created_at)`) | trail clarity; report predicates were full scans |
| Occupancy SQL | `julianday`/`DATEDIFF` branch | portable `GROUP BY (check_in, check_out)` fold in PHP | avoids the codebase's first driver-specific date SQL |
| Money | "integer cents or equivalent" | concrete `MoneyAggregate::centsExpression/fromCents`, unsupported driver throws | makes exactness and driver support explicit |
| Revenue | charges/credits/net | + `by_source` per `FolioItemSource` | real segmentation from the same query, replaces mock "sources" |
| Budgets | reports ≤12, first open ≤16, read ≤6 | reports ≤8, first open ≤24, read ≤7, mutation ≤12 | ≤16 is not achievable with eager-loaded actors; reports need only 7 |
| Locales | AR/EN wording | all 5 locales (en/ar/fr/tr/es) | the project has 5 locale files in parity |
| Dates | `check_out = D` | `whereDate()` everywhere | SQLite stores `Y-m-d 00:00:00` |

Dissents recorded: strict "no wall clock at all" (rejected, D-04); live blocker verification (deferred, D-06); single `reports.*` namespace (rejected, D-15).

## 2026-10-04 — Re-plan (GSD planning crew, Opus)

- Verified the consultant's code premises against the tree at `3886416`. All hold except one: `tickets` has only a `status` index, not `(status, priority)`. Ruling R-1: no new ticket index.
- Applied the roadmap/requirements wording fixes (`/api` not `/api/v1`, fifth check, `night_audit.manage`, new AUDIT-04, 5 locales, catalogue 30/13, Reuses list, Phase 8 complete at `3886416`).
- Rulings R-2..R-5 (lang keys land with first use; permission seeded with route 1; DB::listen budget counter excluding activity_log; baseline re-measured) recorded in 09-CONTEXT.
- Deleted draft plans 09-01..09-04 and wrote 09-01..09-10 as small sequential waves, each Task 1 QA RED then Task 2 engineer GREEN, in the Phase 8 plan format.
- Nothing committed; the owner commits planning docs.
