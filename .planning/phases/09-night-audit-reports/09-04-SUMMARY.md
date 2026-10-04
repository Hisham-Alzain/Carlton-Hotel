---
phase: 09-night-audit-reports
plan: 04
status: complete
---

# 09-04 Summary — lazy idempotent open, exceptions, lang

## Built
- `app/Actions/NightAudit/OpenNightAuditAction.php`: `handle(?string $date, bool $canInitialize, User $actor)`. In one transaction: lock state (or initialize: null date → `night_audit_not_initialized`; no right → `forbidden` `{reason}`; future → `night_audit_date_in_future`; else `insertOrIgnore` + locked re-select), look up audit for `date ?? current`, D-04 order (existing → return; ≠ current → `night_audit_date_mismatch`; > `HotelClock::today()` → audit null; else create). Create = `NightAudit::create` + evaluator + one bulk insert of 5 checks + one `INSERT … SELECT` for blockers (uuid per blocking type via a CASE, so 0/1/2 blockers cost the same statement). `UniqueConstraintViolationException` caught **outside** the failed transaction and recovered by one locked re-read; protected `findAudit()` is the test seam (FA-9.04-1).
- `app/Services/Operations/NightAuditService.php`: `lockState()`, `findAudit()` (`whereDate`), `payload(state, ?audit)`, `readiness(audit)` (no query). Relations `checks.actor`, `blockers.actor`, `blockers.check`, `opener`, `closer` are loaded with 3 fixed statements (checks, blockers, one users lookup) and `setRelation()`, so `whenLoaded` holds and the budget is volume- and actor-independent.
- 6 exceptions (`NightAudit{NotInitialized,DateMismatch,DateInFuture,Closed,ItemResolved,NotReady}Exception`, 422).
- Lang (Sonnet delegate, verified: parity 220 keys × 5 locales, `php -l`, LocaleFoundation/ValidationMessageLocalization green, spot-checked ar/fr): `errors.*` 6 codes, `messages.night_audit_check_updated|night_audit_blocker_resolved|night_audit_closed`, new `night_audit` section (`checks` 5, `check_statuses` 4, `blocker_statuses` 2, `statuses` 2) before `attributes`, and `validation.report_period_too_long`.

## Tests
- `tests/Feature/NightAudit/OpenNightAuditActionTest.php` (20): initialization ×5, targeting ×5, snapshot ×2, idempotency (source changed, checks byte-identical), unique recovery via seam, non-unique `QueryException` propagates with rollback, state lock before audit lookup, budgets, initializing budget, no source writes, locale resolution in 5 locales.

## Budgets (measured, pinned)
- First open **19** (≤ 24), identical with 0 issues and 25 issues/category: state lock, audit lookup, audit insert, Spatie's post-insert subject re-read of the audit, 10 evaluator, checks insert, blockers insert, checks, blockers, users.
- Initializing open **21** (+ insertOrIgnore + locked re-select). Re-read **5** (≤ 7).

## Deviations
- `validation.report_period_too_long` landed here with the other keys (one delegated locale pass) instead of in 09-08 (R-2). No behaviour impact.
- Relations are batch-loaded with `setRelation()` rather than `->load([...])`, to keep budgets constant (nested eager loads are skipped when there are no blockers, which would make counts volume-dependent).
