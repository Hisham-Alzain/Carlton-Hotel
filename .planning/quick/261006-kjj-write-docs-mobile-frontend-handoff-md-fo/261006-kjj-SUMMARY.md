---
quick_id: 261006-kjj
status: complete
---

# Quick task 261006-kjj: Mobile frontend handoff

Created `docs/MOBILE_FRONTEND_HANDOFF.md` (771 lines, §1-§7) for the Flutter guest-app developer, mirroring `docs/DASHBOARD_FRONTEND_HANDOFF.md`.

- Built from four read-only research notes (route inventory, Flutter audit, per-phase deltas + tree, loyalty contract) and code checks.
- Verified by script: all 82 anonymous/guest routes appear in the doc, 0 unknown paths, no staff route mentioned.
- Account-deletion loyalty forfeit is flagged as pending (LOY-23, plans 10-16/10-17).

## Findings worth acting on
- `docs/DASHBOARD_FRONTEND_HANDOFF.md` §6 still says Phase 10 is "planned, not built" (stale; not edited here).
- `backend/docs/API_GUIDE_MOBILE.md` omits 11 real public/guest routes and says `unauthenticated` where the API returns `unauthorized`.
- `mobile/CLAUDE.md` is stale ("no backend wired"); the Flutter loyalty screen is mock-only; no `Idempotency-Key` sent anywhere; push/Firebase commented out.
- No push payload carries a `type` key; the loyalty expiry push data is `points`, `expires_at`.
