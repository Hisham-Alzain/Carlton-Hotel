---
phase: 07-support-tickets-queue
plan: 11
subsystem: docs + phase gate
requires: [07-10]
completed: 2026-10-02
---

# 07-11 — Docs, gate, phase summary

## Shipped
- **Dashboard guide (Sonnet delegate):**
  - Added a `## Module: Support Tickets` section that ends in `### Dashboard handoff (Phase 7)`.
  - Operations Queue now covers assignee eligibility (with the un-assignable table), claim (per-type closed codes, MySQL-only lock caveat) and `GET /api/operations/staff`.
  - Updated the permission catalogue enforcement and presets (26 / 11 kept; Genuinely inert list unchanged), the Event Inquiries and Chat role lines, 8 error codes, and Coming in later phases.
- **Postman (Sonnet delegate):** new folder `21 - Support Tickets & Queue (Admin)` with 14 requests.
- **Tree (Sonnet delegate):** `support tickets` and `live queue` are now api:true, taking the count from 82 to 84 of 94.

## Verification
- **Guide:** the token loop is clean, `queue_item_closed` appears 0 times, the Genuinely inert diff is 0, and `PermissionGuideAccuracyTest|CmsAccessControlTest` passes 26.
- **Postman/tree:** the Task 2 verify printed `postman tree ok`, and the Operations section is still partial (1 match).
- **Gate:**
  - (a) route guards and permissions match; there is no DELETE route.
  - (b) 26 permissions are seeded.
  - (c) the scratch fresh → seed → rollback 3 → migrate cycle exits rc 0, and the dev DB is unchanged.
  - (d) folio files are unchanged since the base.
  - (e) the mobile guide and changelog are unchanged.
  - (f) no `queue_item_closed` in app, lang or routes.
  - (g) the PROJECT.md debt entry is present.
  - (h) full suite **2023 passed** (12790 assertions).
- `database.sqlite` sha1 is `babcdd27c9070101d4b7dc2bcfa42fdb8affd70d`, unchanged.

## Deviations
- The phase-level `SUMMARY.md` write was blocked by a hook ("subagents should return findings as text"). Its full content was handed back to the coordinator in the final report so it can be written there.
- The guide delegate notes:
  - `assign` and `escalate` return `ticket_closed` for resolved and closed tickets, while `reply` and `recovery` return it only for closed ones; the guide documents this.
  - A status PATCH on a closed ticket returns `ticket_transition_invalid`.
  - The `/support-tickets` per_page default and cap are not stated in the guide.
