# Technology Stack

**Project:** Carlton Hotel Backend — API Gap Closure (PMS operational features)
**Researched:** 2026-09-25
**Confidence:** HIGH (existing dependencies verified from composer.json; new-package claims verified via web search against 2026 Packagist/GitHub data)

## Summary

This milestone needs **zero new core packages**. Every target feature (room/housekeeping state, folio ledger, night audit, menu PDF export, report aggregation) is buildable with Laravel 13 primitives plus the packages already in `composer.json`. The one addition worth taking is a small, optional dev-time helper for report/list filtering consistency — and even that has a "don't bother" case argued below. The dominant risk in this milestone is not "missing library," it's over-engineering a state-machine/ledger package on top of a codebase that has already standardized on a simpler, proven pattern.

## Recommended Stack

### Core Framework — unchanged

| Technology | Version | Purpose | Why |
|------------|---------|---------|-----|
| laravel/framework | ^13.8 (existing) | Routing, ORM, queue, scheduler | Already the platform; no upgrade needed for any target feature |
| laravel/sanctum | ^4.0 (existing) | API auth | Unaffected by this milestone |
| spatie/laravel-permission | ^8.3 (existing) | RBAC | `night-audit.run`, `reports.view` (already seeded), `folio.dispute.resolve` etc. are new permission strings on the existing package — no version change |
| spatie/laravel-activitylog | ^5.0 (existing) | Audit trail | Folio disputes, night-audit runs, and ticket escalations should call `activity()->log()` like every other mutating action already does |

### State machines (room status, housekeeping task status, ticket status) — NO NEW PACKAGE

| Approach | Why Recommended |
|----------|------------------|
| Plain `status` enum column + append-only `*_status_history` table, written from the Action layer | This is the pattern the codebase already has half of: `UpdateRequestStatusAction::handle()` does `$item->update(['status' => $status])`. PROJECT.md constraint #4 explicitly requires "`status` + history over boolean flags." The only gap is that today's action does **not** write a history row — add that, don't add a package. |
| Transition validation as a small `match()`/array-map guard inside the Action (e.g. `RoomStatus::VALID_TRANSITIONS`), throwing the existing domain-exception pattern on an illegal move | Keeps validation colocated with the one place that mutates state, consistent with `Base*`/Action conventions; no new abstraction layer for staff to learn |

**Considered and rejected:** `spatie/laravel-model-states` (v2, actively maintained, Laravel 11/12/13 compatible) and `spatie/laravel-model-status` (polymorphic status-history package). Confidence: HIGH that both are solid, well-maintained packages — this is not a quality objection. Reasons to skip them here:
- The codebase has zero instances of either package today; introducing one for 2-3 state machines (room, housekeeping task, ticket) creates a second status pattern alongside the nine existing plain-column ones (reservation, booking, service request, event inquiry, etc.), which is a worse outcome than "slightly more boilerplate."
- `laravel-model-states` models each state as its own PHP class (`Clean extends RoomStatus`) with a config-bound state map — heavier than this project's Action-centric style, and its transition-validation feature duplicates what a 10-line guard array already gives you.
- `laravel-model-status` is closer to what's needed (append-only polymorphic history) but its `statuses` table has a generic shape (`name`, `reason`, `model_type`, `model_id`) that doesn't fit the project's convention of an explicit `room_status_history` table with FKs, `changed_by_staff_id`, and typed columns — the hand-rolled table is *more* convention-compliant, not less.

**If a 4th or 5th state machine appears later** (e.g., a genuinely branching workflow with entry/exit hooks), revisit `spatie/laravel-model-states` then — the threshold is "hooks and guards are duplicated across many models," not "a status column exists."

### Folio ledger (line items, payments, disputes) — NO NEW PACKAGE

| Approach | Why Recommended |
|----------|------------------|
| `folio_items` table: signed `DECIMAL(10,2)` amount (charges positive, payments/credits negative or a `type` enum), `DB::transaction()` wrapping every mutating action, running balance computed by `SUM(amount)` query (or a denormalized `balance` column updated inside the same transaction if the folio is read-heavy) | Matches the existing `RecordCashPaymentAction` / `GenerateFolioAction` / `SettleFolioAction`, all of which already use `DB::transaction()` and plain decimal columns. Extending, not replacing. |
| Disputes as a `folio_item_disputes` table (status: open/resolved, `raised_by` polymorphic guest/staff, `resolution_note`) rather than a status column on `folio_items` | A disputed line item needs its own audit trail (who raised it, staff resolution, timestamp) independent of the item's lifecycle — a second table, not an overloaded status enum on the item itself |

**Considered and rejected:** dedicated ledger/accounting packages (`usmanhalalit/eloquent-money`-style, or "double-entry" packages). Confidence: MEDIUM (ecosystem is thin and mostly unmaintained for Laravel 11+). None are needed for a single-currency (USD), single-property folio with no double-entry bookkeeping requirement — that's accounting-software complexity for a feature that's really "append rows, sum them, settle." Also rejected: `brick/money`/`moneyphp/money` value objects — the project's convention is raw `DECIMAL` cast with PHP arithmetic (see `developer-guide.md` §15, "Money is DECIMAL in USD... never store a float"); introducing a Money value object mid-milestone would create two money representations across old and new code for no correctness gain at single-currency scale.

### Night audit (checks, blockers, per business date) — NO NEW PACKAGE

| Approach | Why Recommended |
|----------|------------------|
| Artisan console command (`php artisan hotel:night-audit {date?}`) invoked by an authenticated API endpoint (wraps the command's logic in a service, not `Artisan::call()` from HTTP — keeps it testable and avoids the console-in-request anti-pattern) | Laravel's own primitives (Console commands + the Scheduler) are the standard 2025/2026 approach for batch/cron-shaped work in Laravel; nothing in the ecosystem beats "a command + a table" for a nightly batch job at this scale |
| `night_audit_runs` (one row per business date) + `night_audit_checks` (one row per check per run: name, passed/blocked, blocker detail) | Matches the "status + history" and "3NF, FK-indexed" constraints; makes "what blocked last Tuesday's audit" queryable without log-scraping |
| Each check is a small invokable class (`app/Actions/NightAudit/Checks/*`) run in sequence inside one `DB::transaction()`, aggregated into the run record | Keeps the Action-per-concern convention; new checks (e.g., a future "all folios settled" rule) are additive, not edits to a growing if/else block |

**Considered and rejected:** `spatie/laravel-schedule-monitor` (pings a dashboard when a scheduled task fails to run). Confidence: HIGH it's a good package for *unattended cron health monitoring*, but night audit here is triggered on-demand via an authenticated endpoint per the requirements ("night audit... per business date"), not a silent cron job — schedule-monitor solves a different problem (detecting a *missed* run) than this milestone's shape (staff-triggered run with visible check results). Revisit only if night audit becomes a required unattended midnight job with alerting.

### PDF export (venue menus) — REUSE EXISTING

| Technology | Version | Purpose | Why |
|------------|---------|---------|-----|
| mpdf/mpdf | ^8.3 (existing, verified current: 8.3.1 on Packagist, PHP 8.3-compatible) | Render venue menu HTML → PDF | Already a dependency (used for invoices/documents per INTEGRATIONS.md); menu PDF is the same rendering job (Blade view → HTML → mPDF), not a new capability |

**Considered and rejected:** `barryvdh/laravel-dompdf` or `spatie/laravel-pdf` (Browsershot/Chromium-based). Confidence: HIGH both are fine packages in general. Adding either here means maintaining two PDF engines for one feature that mPDF already handles (menus are simple styled HTML, not CSS-Grid-heavy layouts that would justify a headless-Chrome renderer). `spatie/laravel-pdf` in particular requires a Node/Chromium runtime dependency in production — unjustified operational weight for a menu export.

### Report aggregation (reports dashboard endpoint) — OPTIONAL LIGHT ADDITION

| Technology | Version | Purpose | When to Use |
|------------|---------|---------|-------------|
| Plain Eloquent aggregate queries (`selectRaw`, `withCount`, `groupBy`) inside a `ReportsService`, filtered via the existing `BaseFilter` subclass pattern | Default choice | The codebase's `BaseFilter` (query DSL: `?field[op]=value`, search, sort) already solves "filterable list/aggregate endpoint" — reuse it for date-range and category filters on the reports endpoint rather than adding a query-builder package |
| `spatie/laravel-query-builder` ^7.3 (verified current: 7.3.3, Laravel 12/13 + PHP 8.3 compatible) | Only if reports need ad-hoc, client-driven `?filter[x]=y&sort=-created_at&include=relation` shapes beyond what `BaseFilter` already expresses | Not recommended by default — `BaseFilter` already covers `eq/like/gte/lte/in`, search, and sort with project-specific error semantics (422 on bad operator, not silent ignore) that `laravel-query-builder` doesn't replicate out of the box. Introducing it would mean two competing filter DSLs in one API. Only reach for it if the reports endpoint needs relationship `include=` resolution that `BaseFilter` can't express — unlikely for a dashboard summary. |

## Alternatives Considered

| Category | Recommended | Alternative | Why Not |
|----------|-------------|-------------|---------|
| State machine | Status column + history table + Action-layer guard | spatie/laravel-model-states v2 | Introduces a second, heavier state pattern for only 2-3 machines; codebase has zero prior usage |
| State history | Status column + history table | spatie/laravel-model-status | Generic polymorphic `statuses` table shape doesn't fit project's typed, FK'd history-table convention |
| Money/ledger | Raw `DECIMAL` + `DB::transaction` | brick/money, moneyphp/money, double-entry ledger packages | Project convention is already raw-decimal single-currency; no double-entry requirement exists |
| PDF | mpdf/mpdf (existing) | spatie/laravel-pdf (Browsershot/Chromium) | Requires a Node/Chromium runtime for a simple HTML menu; unjustified ops weight |
| PDF | mpdf/mpdf (existing) | barryvdh/laravel-dompdf | Would run two PDF engines side by side for no functional gain |
| Filtering/reports | `BaseFilter` (existing) | spatie/laravel-query-builder | Would fork the API's filter DSL into two incompatible syntaxes |
| Night-audit scheduling | Artisan command + service, triggered by endpoint | spatie/laravel-schedule-monitor | Solves "detect a missed unattended cron," not "staff-triggered audit with visible checks" — different problem shape |

## What NOT to Use

| Avoid | Why | Use Instead |
|-------|-----|-------------|
| Any new state-machine package (`spatie/laravel-model-states`, `spatie/laravel-model-status`, `asantibanez/laravel-eloquent-state-machines`) | Codebase has an established plain-column + history-table convention (PROJECT.md constraint) with zero prior package usage; adding one now fragments the pattern across the app | Status enum column + `*_status_history` table + Action-layer transition guard, as already half-implemented in `UpdateRequestStatusAction` |
| Double-entry / ledger accounting packages | Single-currency, single-property folio with no bookkeeping requirement; these packages assume debit/credit account structures this domain doesn't have | `folio_items` table with signed decimals, summed for balance, inside `DB::transaction()` |
| Money value-object libraries (`brick/money`, `moneyphp/money`) | Existing convention is raw `DECIMAL` cast per developer-guide.md §15; introducing a value object mid-milestone creates two money representations | Continue casting `decimal:2` and doing PHP arithmetic on strings/floats-as-decimals as the existing Payment/Folio actions do |
| Browsershot/Chromium-based PDF (`spatie/laravel-pdf`) | Adds a Node/headless-Chrome runtime dependency to production for a feature mPDF already covers | mpdf/mpdf (already installed) |
| `spatie/laravel-query-builder` as the default reports filter | Would create a second filter DSL alongside `BaseFilter`, splitting error-handling and query semantics across the API surface | `BaseFilter` subclass per report endpoint |
| New queue/broadcast infrastructure (e.g., adding Redis/websockets for "live" room board) | PROJECT.md explicitly puts realtime/websockets out of scope for this milestone; polling is acceptable | Poll the existing `/operations/queue` and new front-desk endpoints on a client-side interval |

## Stack Patterns by Variant

**If a status needs staff-visible history (room, housekeeping task, ticket, event-inquiry checklist):**
- Add a `<entity>_status_history` migration (id, `<entity>_id` FK indexed, `from_status`, `to_status`, `changed_by_staff_id` FK, `note` nullable, timestamps)
- Write the history row inside the same Action that updates the parent's `status`, inside one `DB::transaction()`
- Because this is the pattern PROJECT.md's constraint already mandates, and it composes with the existing `activitylog` package for who/when at the model level without duplicating "what changed" detail

**If a batch/reporting job needs to run outside a request (night audit, future scheduled reports):**
- Use an Artisan command backed by a service class, callable both from `php artisan` and from an authenticated controller action
- Because Laravel's scheduler + command primitives are the 2025/2026 standard for this project size — no queue-of-jobs orchestration package is justified for one nightly job with a handful of checks

## Version Compatibility

| Package | Compatible With | Notes |
|---------|------------------|-------|
| mpdf/mpdf ^8.3 | PHP 8.3, Laravel 13 | Already installed; verified 8.3.1 is current on Packagist and requires PHP ~8.3.0+ — no action needed |
| spatie/laravel-permission ^8.3 | Laravel 13 | Already installed and working; new permission strings (`night-audit.run`, `folio.dispute.*`) are config/seed additions only |
| spatie/laravel-query-builder 7.3.3 (if ever adopted) | `illuminate/database ^12\|^13`, PHP ^8.3 | Confirmed Laravel 13-compatible as of the 7.x line if the "ad-hoc include=" case above ever materializes |
| spatie/laravel-model-states v2 (if ever adopted) | Laravel 11/12/13 | Confirmed actively maintained and version-compatible; not recommended for this milestone per rationale above, noted for future reference only |

## Sources

- `D:\TupCode\Carlton\backend\composer.json` — HIGH confidence, direct inspection of installed versions
- `D:\TupCode\Carlton\backend\app\Actions\Operations\UpdateRequestStatusAction.php` and `app/Base/BaseFilter.php` — HIGH confidence, direct inspection of existing conventions
- `D:\TupCode\Carlton\backend\.claude\skills\tupcode-laravel-backend\references\developer-guide.md` §15 — HIGH confidence, house convention for money/status/schema
- Packagist / GitHub via web search (2026 data): mpdf/mpdf 8.3.1, spatie/laravel-query-builder 7.3.3, spatie/laravel-model-states v2, spatie/laravel-model-status — MEDIUM-HIGH confidence, web search not a pinned registry snapshot but cross-checked release/changelog pages
- PROJECT.md constraints (money as DECIMAL, status+history, no new frameworks, no websockets this milestone) — HIGH confidence, explicit project decisions

---
*Stack research for: Carlton Hotel Backend PMS operational features*
*Researched: 2026-09-25*
