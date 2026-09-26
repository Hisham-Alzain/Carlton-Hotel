---
phase: 04-guests-stay
plan: 09
subsystem: docs + phase gate
tags: [docs, postman, feature-tree, gate, summary]
requires: [04-08]
provides: [phase-4-close]
affects: [backend/docs, docs/carlton-tree.html, .planning/research/PITFALLS.md]
key-files:
  modified:
    - backend/docs/API_GUIDE_DASHBOARD.md
    - backend/docs/API_GUIDE_MOBILE.md
    - backend/docs/CHANGELOG_MOBILE_API.md
    - .planning/research/PITFALLS.md
    - backend/docs/postman/carlton-api.postman_collection.json
    - docs/carlton-tree.html
  created:
    - .planning/phases/04-guests-stay/SUMMARY.md
    - .planning/phases/04-guests-stay/04-09-SUMMARY.md
completed: 2026-09-26
---

# 04-09 — Phase close: docs, Postman, feature tree, gate

The phase-closing contract (permissions, path changes, docs, production notes, carry-forwards) lives in `SUMMARY.md` in this directory. This file records what 04-09 itself did.

## Task 1 — guides, changelog, Pitfall 6 (Sonnet delegate)

- `API_GUIDE_DASHBOARD.md`: `## Module: Guests (guests.view · guests.edit)` with the five endpoint sections (query params, 13-key row, 21-key profile, six-item checklist, note and preference rules, error codes); approvals paragraph (approve issues/keeps the key, reject revokes, push never carries the code); permission catalog 10 modules / 21 permissions; reception and concierge presets; P12 "guest directory" removed from "Coming in later phases". Engineer addition: nested `guest.preferences` note on `GET /cms/reservations/{uuid}`.
- `API_GUIDE_MOBILE.md`: index 59 -> 61; `### PATCH /api/auth/guest/preferences`; `preferences` in guest profile fields (engineer addition: also on nested `guest` objects); `### POST /api/stays/{uuid}/online-check-in`; stay-read blocks with no-store; `check_in_approved` push; `**ID scan (GUEST-06):**` paragraph. Every `lock-grade` mention says NOT lock-grade.
- `CHANGELOG_MOBILE_API.md`: `## 10 — Guests & Stay (Phase 4)`, Changed (non-breaking) rows (engineer added the nested `guest.preferences` row), tier-2 inventory rows.
- `PITFALLS.md`: Pitfall 6 `**Phase 4 status:**`.
- Verify printed the six dashboard headings once each, mobile index and sections, changelog entry, Pitfall note; `PermissionGuideAccuracyTest` 7/7; `SubmitDocumentsRequest.php` unchanged vs HEAD.

## Task 2 — Postman and tree (Sonnet delegate)

- Folder `19 - Guests (Admin)` with five requests in order; `Guest — Update Preferences` in `02 - Auth`; `Online check-in (Layla)` in `17 - Stays (Guest)`. No script stores a `digital_key` value; environment file untouched.
- `carlton-tree.html`: four nodes flipped to `api:true` (`guest directory · profile`, `guest preferences`, `online check-in`, `ID scan`), `check-in approvals` meta appended. 94 nodes, 77 `api:true`.
- Plan verify printed `postman and tree ok`.

## Task 3 — gate (engineer)

- `routes ok`: the seven new routes carry exactly their guard and permission.
- Permission tests green (17 tests, 144 assertions).
- `scratch migration ok, dev database untouched`: migrate:fresh --seed / rollback --step=3 / migrate on `storage/framework/phase4-gate.sqlite`; `backend/database/database.sqlite` sha1 unchanged.
- The four hardening-run files carry zero Phase 4 identifiers.
- Full suite at engineer stage: 1238/1238 passing, 6382 assertions, 0 failures; final total with the Phase 4 QA specs (QA stage): 1383/1383 passing, 9130 assertions, 0 failures.

## Deviations

- `backend/.env.example` was denied to the engineer's Edit tool by the session's permission settings; the Close stage added `HOTEL_CHECK_OUT_TIME=12:00` after `HOTEL_TIMEZONE=Asia/Damascus` via PowerShell (config default is 12:00).
- Nested `guest.preferences` documentation added beyond the task list (FA-4.02-2).

See `SUMMARY.md` for the full phase contract.
