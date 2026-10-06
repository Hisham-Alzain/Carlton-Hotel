---
quick_id: 261006-kjj
type: docs
autonomous: true
files_modified:
  - docs/MOBILE_FRONTEND_HANDOFF.md
---

# Quick task 261006-kjj: Mobile frontend handoff

## Objective
Write `docs/MOBILE_FRONTEND_HANDOFF.md` for the Flutter guest-app developer, mirroring `docs/DASHBOARD_FRONTEND_HANDOFF.md` (header, §1 quick start, §2 mock-to-real contract changes, §3 modules with error tables and flows, §4 tree checklist, §5 deferred, §6 Phase 10 loyalty, §7 gotchas). Scope: guest/public API of Phases 1-10 + 9.1.

## Tasks
1. Research (read-only, notes in scratchpad): route inventory from `php artisan route:list`; Flutter app audit; per-phase guest deltas + tree; as-built loyalty guest contract.
2. Write the doc from the notes; every path/gate verified against route:list; money as strings; base `/api` (no `/v1`).
3. Verify with a script: every `METHOD /path` in the doc exists in route:list as guest/public; fix mismatches.

## Constraints
- Doc only; no code/route changes; do not edit the dashboard handoff.
- Account-deletion loyalty forfeit is PENDING (plans 10-16/10-17, LOY-23): flag it as not yet shipped.
- Commit locally, never push.

## Acceptance
- File exists with sections §1-§7; script check reports 0 unknown routes.
