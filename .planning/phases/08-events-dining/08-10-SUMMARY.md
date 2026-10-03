---
phase: 08-events-dining
plan: 10
status: complete
---

# 08-10 Summary — Docs, Postman, tree, demo data, gate

## Built (mechanical parts delegated to Sonnet agents against a written contract brief, then verified)
- `backend/docs/API_GUIDE_DASHBOARD.md`: Events module (routes + gates, checklist, notes vs staff_notes, deposit money rules, shapes, error codes, dashboard handoff), Dining → Table reservations, menu-file CMS routes, permission catalogue 12 modules / 29 permissions with the reception/concierge tightening and the `event_inquiries` summary key. "Genuinely inert" still names only `pricing.edit`, `reports.view`.
- `backend/docs/API_GUIDE_MOBILE.md` (menu download 200/204/404; table-reservation hotel-local `date`/`time`, UTC `scheduled_at`) and `CHANGELOG_MOBILE_API.md` (2026-10-03 entry: Added route; Fixed `scheduled_at`).
- Postman: folder "Events & Dining (Phase 8)" (7 requests; deposit pre-request sets `{{idempotency_key}}` like the folio payment request); env vars `event_inquiry_quoted_uuid`, `idempotency_key`; `RefreshPostmanEnvironment` seeds `event_inquiry_quoted_uuid`. Both JSON files parse.
- `docs/carlton-tree.html`: the five nodes flipped per D-32 (diff reviewed).
- `DemoShowcaseSeeder::eventsAndDining()` (mine): through the real actions — checklist ticks + staff notes on 3 quoted/confirmed inquiries, one paid deposit (`RecordEventDepositAction`), tonight's table bookings (`ReserveTableAction`, hotel-local), a sample menu PDF (`ReplaceVenueMenuFileAction`). Verified with `migrate:fresh --seed` on a scratch SQLite file: 5 checklist rows, 1 paid inquiry with 1 FQCN payment, 1 menu media row. (The scratch run's menu PDF in gitignored `storage/app/public` was deleted afterwards.)
- `.planning/PROJECT.md`: A4 debt row updated (event half resolved, conversations half pending); Key Decision rows for D-15 and D-07. ROADMAP/REQUIREMENTS wording fixes were already applied at planning time.

## Gate
See `SUMMARY.md` § Gate.
