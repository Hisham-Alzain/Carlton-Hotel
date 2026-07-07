# Carlton Backend — Testing Report

> Living document. The coding agent (Sonnet 5) and the test sub-agent append one section per phase after each module's done-condition is evaluated. Never overwrite prior sections — append only. Reference phase IDs from `PLAN.md` (P0–P12).

## How to append
After finishing a module, add a section using the template below. Mark the done-condition PASS only when every listed check is green and `php artisan test` is green.

---

## Template (copy per phase)

### P<id> — <module name> — <PASS | FAIL | IN PROGRESS>
- **Date:**
- **Tests added:** <feature tests, unit tests — list files>
- **Result:** `php artisan test` → <n passed, n failed>
- **Mandatory checks:**
  - [ ] happy-path endpoint tests
  - [ ] auth/permission-failure tests
  - [ ] validation-failure tests
  - [ ] unit tests for non-trivial Actions
  - [ ] (P4 only) deterministic last-room concurrency test GREEN
  - [ ] (P4 only) pricing-snapshot immutability test GREEN
- **Coverage notes:**
- **Known gaps / follow-ups:**
- **Done-condition (P<id>-DONE):** <met / not met>

---

### P0 — Foundation & base layer — PASS
- **Date:** 2026-07-07
- **Tests added:**
  - `tests/Feature/HealthEndpointTest.php` (2 tests)
  - `tests/Feature/ExceptionEnvelopeTest.php` (2 tests)
  - `tests/Feature/SuperAdminBypassTest.php` (2 tests)
  - `tests/Feature/GuardsResolveTest.php` (4 tests)
  - `tests/Feature/SeederTest.php` (3 tests)
  - `tests/Unit/BaseFilterTest.php` (6 tests)
- **Result:** `php artisan test` → **21 passed, 0 failed** | 58 assertions | ~1.8s
- **Mandatory checks:**
  - [x] happy-path endpoint tests — `/health` envelope shape + `data.status:'ok'`
  - [x] auth/permission-failure tests — no-token → 401 `unauthorized`; wrong-guard token → 401
  - [x] validation-failure tests — envelope shape verified via exception handler test
  - [x] unit tests for non-trivial Actions — `BaseFilterTest` covers all 5 operators + whitelist enforcement
  - [ ] (P4 only) deterministic last-room concurrency test — N/A
  - [ ] (P4 only) pricing-snapshot immutability test — N/A
- **Coverage notes:**
  - Envelope contract (success, error, request_id) verified end-to-end via health + probe routes
  - `X-Request-Id` header confirmed to match body `request_id`
  - `Gate::before` super-admin bypass verified: super_admin passes ungranted gate; plain staff denied
  - Both Sanctum guards (`users`, `guests`) verified: correct token → 200; wrong-guard token → 401
  - Seeder idempotency verified: running twice produces exactly 16 permissions + 5 roles, no duplicates
  - `BaseFilter` all 5 operators (`eq`, `like`, `gte`, `lte`, `in`) verified; non-whitelisted field silently ignored
- **Known gaps / follow-ups:**
  - `ExceptionEnvelopeTest` does not exercise a live `ValidationException` via a real endpoint (no validating route in P0). Will be covered naturally from P1 onward when FormRequest-backed routes exist.
  - No test for `server_error` (500) — intentional; would require mocking internals not meaningful in P0.
- **Done-condition (P0-DONE):** **MET**
  - [x] `migrate:fresh --seed` green; 16 permissions + 5 presets seeded; idempotent
  - [x] `/api/health` → success envelope + `request_id` + `X-Request-Id` header matches body
  - [x] `/api/probe/domain-exception` → error envelope `error_code:'not_found'` + `request_id`
  - [x] Staff token → `/probe/staff` 200; guest token → `/probe/guest` 200; wrong guard → 401 `unauthorized`
  - [x] super_admin bypasses ungranted gate; plain staff denied
  - [x] `php artisan test` all green (21/21)
  - [x] Base layer contains zero domain logic

---

## Global exit checks (fill at P12)
- [ ] `migrate:fresh --seed` green from empty DB
- [ ] full `php artisan test` green
- [ ] BMS concurrency test green and deterministic
- [ ] every phase done-condition signed off
- [ ] error_code inventory exported
