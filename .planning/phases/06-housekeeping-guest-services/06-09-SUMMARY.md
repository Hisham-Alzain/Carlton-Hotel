---
phase: 06-housekeeping-guest-services
plan: 09
subsystem: docs, phase gate
requires: [06-08]
provides: [dashboard guide modules, mobile chip mapping, changelog §12, Postman folder 20, tree flips]
key-files:
  modified:
    - backend/docs/API_GUIDE_DASHBOARD.md
    - backend/docs/API_GUIDE_MOBILE.md
    - backend/docs/CHANGELOG_MOBILE_API.md
    - backend/docs/postman/carlton-api.postman_collection.json
    - docs/carlton-tree.html
completed: 2026-09-27
---

# 06-09 — Docs and phase gate

## Shipped
- Dashboard guide: permission catalogue 11 modules / 26 permissions, enforcement + presets, Reference Data count; new modules Housekeeping, Service Request Board, Departure Services (incl. PATCH resolution, booking table, known gaps, dashboard handoff, reception-lacks-`service_requests.update` note); Operations Queue rewritten for the third type; staff-only `check_out_mode`; four 422 error codes. "Genuinely inert" untouched.
- Mobile guide `### Quick-request chips (Phase 6)`; changelog `## 12 — Housekeeping & guest services (Phase 6)`; Postman folder `20 - Housekeeping & Departures (Admin)` (9 requests) + two queue examples in folder 16 (JSON parses); tree: housekeeping, departures, staff request board, quick requests → api:true (nodes 94 → 94, api:true 78 → 82).

## Phase gate
- Routes: 5 housekeeping, 2 board, 2 departures, queue gate widened — guards/permissions as in the endpoint table.
- Permissions 23 → 26 (base + 3).
- Scratch SQLite `migrate:fresh --seed`, `migrate:rollback --step=3`, `migrate`: ok. `backend/database/database.sqlite` sha1 `babcdd27c9070101d4b7dc2bcfa42fdb8affd70d` before and after.
- Folio files: `git diff --quiet 9791549 -- <folio paths>` clean, no working-tree changes.
- Dedupe gate: 0 writers of `dedupe_key` outside the model.
- Full suite: **1702 passed, 11143 assertions**.

## Deviations
- **Phase-level `SUMMARY.md` not written:** the Write tool refused the file name for this agent ("Subagents should return findings as text"). Its full content was returned to the orchestrator in the hand-back to be written by the parent session.
- `(mock)` acceptance grep: line count 6 → 4, occurrence count 15 → 10 (the ≥ 5 drop holds by occurrences; several mocks shared one line).
- PermissionGuideAccuracyTest / CmsAccessControlTest: 26 passed.
