---
phase: 10-loyalty-points-program
plan: 06
subsystem: api
tags: [laravel, loyalty, ledger, account, staff-view, resources, filters, phpunit]

requires:
  - phase: 10-loyalty-points-program
    provides: "10-03 LoyaltyLedger::balances; 10-02 LoyaltyProgram; 10-04 cms/loyalty route block and loyalty.view; 10-05 earn entries; 10-01 models/factories and BuildsLoyaltyFixtures"
provides:
  - "GET /api/loyalty/account and GET /api/loyalty/ledger (auth:guests), caller-only, Q16 contract strings"
  - "GET /api/cms/loyalty/guests/{guest} and /ledger (permission:loyalty.view) with guest{uuid,name} and staff-only reason/performed_by"
  - "App\\Services\\Loyalty\\LoyaltyAccountService::account(Guest) and ledger(Guest, array, ?int)"
  - "App\\Filters\\LoyaltyLedgerFilter (type, source, occurred_at, points[int])"
  - "LoyaltyAccountResource, LoyaltyLedgerEntryResource; LoyaltyEntryType::label(), LoyaltyBatchSource::label()"
  - "custom.loyalty.entry_types.* (6) and custom.loyalty.sources.* (4) in all five locales"
affects: [10-07 adjust, 10-09 rewards/vouchers (guest block), 10-12 clawback, 10-14 reports, 10-15 docs]

tech-stack:
  added: []
  patterns:
    - "One service serves the guest route (caller) and the staff route (any guest); the controller picks the guest, the service never reads the request"
    - "Staff-only fields via $request->user() instanceof User, same idiom as ReservationResource"
    - "Column-limited eager loads in $with (batch:id,expires_at etc.) so a ledger page costs a fixed 7 queries"

key-files:
  created:
    - backend/app/Services/Loyalty/LoyaltyAccountService.php
    - backend/app/Filters/LoyaltyLedgerFilter.php
    - backend/app/Http/Resources/Loyalty/LoyaltyAccountResource.php
    - backend/app/Http/Resources/Loyalty/LoyaltyLedgerEntryResource.php
    - backend/app/Http/Controllers/Api/LoyaltyController.php
    - backend/app/Http/Controllers/Admin/LoyaltyGuestController.php
    - backend/tests/Feature/Loyalty/LoyaltyAccountTest.php
    - backend/tests/Feature/Loyalty/LoyaltyAccountStaffViewTest.php
  modified:
    - backend/app/Enums/LoyaltyEntryType.php
    - backend/app/Enums/LoyaltyBatchSource.php
    - backend/routes/api.php
    - backend/lang/en/custom.php
    - backend/lang/ar/custom.php
    - backend/lang/fr/custom.php
    - backend/lang/tr/custom.php
    - backend/lang/es/custom.php

key-decisions:
  - "Lifetime earned = earn + positive adjust - clawback; lifetime redeemed = redeem - refund; both floored at 0 and computed from one grouped query (FA-10.06-1); a negative adjust and an expiry count toward neither"
  - "The account endpoint runs exactly 3 queries (settings row, balances aggregate, grouped lifetime sums) and a ledger page exactly 7 (count, rows, 5 eager loads), both pinned in tests"
  - "Ledger takes the request query via the controller's indexParams() and the service receives a plain array, so the service never sees the Request"
  - "expires_at on a ledger row is the batch expiry only when the row credited points (points > 0); redeem/expire/clawback rows show null"
  - "The staff account response adds guest{uuid,name}; the guest response never echoes the caller's own identity"

patterns-established:
  - "Guest-facing loyalty routes live in one auth:guests prefix('loyalty') block next to reservations; later plans append rewards, vouchers and preview"
  - "Query-budget tests pin the count and also assert it is identical for 1 and 15 entries"

requirements-completed: [LOY-06, LOY-07]

coverage:
  - id: D1
    description: "Guest account: Q16 key set, no tier, available/expiring-soon from balances() with an expired-but-unswept batch excluded, warning window read at request time, program flags, unconfigured program answers 200 with nulls"
    requirement: "LOY-06"
    verification:
      - kind: feature
        ref: "tests/Feature/Loyalty/LoyaltyAccountTest.php::test_account_returns_the_contract_for_the_caller_only, test_the_warning_window_is_read_at_request_time, test_account_works_when_the_program_is_unconfigured"
        status: pass
    human_judgment: false
  - id: D2
    description: "Lifetime earned/redeemed are net of clawbacks and refunds, floored at 0; other guests' rows are ignored"
    requirement: "LOY-06"
    verification:
      - kind: feature
        ref: "tests/Feature/Loyalty/LoyaltyAccountTest.php::test_lifetime_figures_are_net_of_clawbacks_and_refunds, test_lifetime_figures_are_floored_at_zero, test_account_ignores_another_guests_batches_and_entries"
        status: pass
    human_judgment: false
  - id: D3
    description: "Guest ledger: paginated newest first with id tiebreak, every contract key, no reason/performed_by, localized labels, filters, ownership, spoofed ids ignored"
    requirement: "LOY-06"
    verification:
      - kind: feature
        ref: "tests/Feature/Loyalty/LoyaltyAccountTest.php::test_ledger_lists_newest_first_with_every_contract_key_and_no_staff_fields, test_ledger_labels_follow_the_request_locale, test_ledger_is_paginated_with_a_stable_newest_first_tiebreak, test_ledger_filters_by_type_source_date_and_points, test_ledger_never_shows_another_guests_rows"
        status: pass
    human_judgment: false
  - id: D4
    description: "Auth and validation: 401 without a token and with a staff token on both guest routes; 422 for a non-integer points filter"
    requirement: "LOY-06"
    verification:
      - kind: feature
        ref: "tests/Feature/Loyalty/LoyaltyAccountTest.php::test_account_requires_a_guest_token, test_ledger_requires_a_guest_token, test_a_non_integer_points_filter_answers_422"
        status: pass
    human_judgment: false
  - id: D5
    description: "Query budgets: account pinned at 3 queries; ledger query count identical for 1 and 15 entries (7)"
    requirement: "LOY-06"
    verification:
      - kind: feature
        ref: "tests/Feature/Loyalty/LoyaltyAccountTest.php::test_account_query_budget_is_fixed, test_ledger_query_count_does_not_grow_with_the_number_of_entries"
        status: pass
    human_judgment: false
  - id: D6
    description: "Staff view: loyalty.view reads any guest's account (+guest{uuid,name}) and ledger with reason and performed_by; 401 (no token, guest token), 403 (no permission, manage-only, adjust-only, reports.view-only, guests.view-only, every preset), 404 unknown uuid, 422 bad filter"
    requirement: "LOY-07"
    verification:
      - kind: feature
        ref: "tests/Feature/Loyalty/LoyaltyAccountStaffViewTest.php (10 tests)"
        status: pass
    human_judgment: false

duration: resumed session (partial work from a stopped executor reviewed and verified)
completed: 2026-10-05
status: complete
---

# Phase 10 Plan 06: Guest Account, Ledger and Staff Guest View Summary

**Guests read their own FIFO-expiring balance, expiring-soon points, lifetime figures and a paginated ledger tied to bookings; staff with `loyalty.view` read the same for any guest plus the staff-only reason and performer, all on fixed query budgets.**

## Performance

- **Tasks:** 2 (RED specs, GREEN implementation), done as one resumed run
- **Files created:** 8, **modified:** 8
- **Tests added:** 27 (17 guest, 10 staff)

## Accomplishments

- Four routes: `GET /api/loyalty/account` and `/ledger` in a new `auth:guests` block with no id in the URL; `GET /api/cms/loyalty/guests/{guest}` and `/ledger` inside the `cms/loyalty` block behind `permission:loyalty.view`.
- `LoyaltyAccountService` composes the Q16 account from `LoyaltyProgram`, `LoyaltyLedger::balances()` and one grouped ledger query, and builds the ledger list with column-limited eager loads and an `occurred_at desc, id desc` order.
- `LoyaltyLedgerEntryResource` exposes uuids and booking codes only and emits `reason` / `performed_by` solely for a staff `User`; `LoyaltyAccountResource` adds `guest{uuid,name}` for staff only.
- `LoyaltyEntryType::label()` and `LoyaltyBatchSource::label()` plus a trailing `loyalty` section in all five lang files.

## Task Commits

1. **Task 1 + Task 2 (one commit per project policy, full suite green first):** `0af458e` feat(10-06): guest loyalty account and ledger, staff guest view

**Plan metadata:** committed separately as `docs(10-06): complete guest account and ledger plan`.

## Resume note

This plan was resumed after a stopped executor. Reviewing the uncommitted tree against the plan task by task found all eight new files and all eight modified files complete and consistent with the plan: enum `label()` methods, the five-locale lang section (fr/tr/es/ar translated, same key set as en), the two route blocks, filter, service, resources, controllers and both test classes. Nothing needed fixing or completing; nothing was duplicated. `.planning/STATE.md` held only the foreign Phase 9.1 paragraph (no partial edit from the stopped executor), which was left unstaged.

## Verification

- `php artisan test --filter=LoyaltyAccount`: 27 tests, 202 assertions, pass.
- Plan verify filter `LoyaltyAccount|LoyaltyPermissions|LoyaltyLocaleTest|LocaleFoundationTest`: 90 tests, 581 assertions, pass.
- Full suite `php artisan test`: **2790 tests, 16991 assertions, exit 0** (325 s).
- `route:list --path=loyalty` lists the four new routes.
- Acceptance greps: `auth('guests')` appears 2 times in `Api\LoyaltyController`; no `'id' =>` key in either resource; contract-key and `assertJsonMissingPath` assertions present in `LoyaltyAccountTest`.

## Deviations from Plan

None - plan executed as written, with two small implementation choices inside its latitude:

- `ledger()` takes `array $params` (from `indexParams()`) as its second argument so the service never reads the request; the plan's `ledger(Guest, ?int)` signature carried no way to pass filters.
- The query budgets pinned are 3 (account) and 7 (ledger page).

## Drift from the plan (Phase 9.1)

- Full suite is now 2790 tests (10-05 recorded 2763; +27 from this plan). No count pin or permission baseline moved.
- `CmsAccessControlTest::$notYetBuilt` still contains `loyalty.adjust`; plan 10-07 removes it. Untouched here.
- Pint's `--test` over `app/Enums` reports many pre-existing files with `binary_operator_spaces` (the repo's aligned `=>` style); the two enum files touched here follow the same existing style, so no reformat was applied.

## Issues Encountered

None. `Carlton-hotel-s/`, `backend.zip` and the other session's `STATE.md` paragraph were not touched or staged.

## Known Stubs

None.

## Threat Flags

None beyond the plan's register. T-10-21 (IDOR), T-10-22 (staff notes), T-10-23 (staff view privilege) and T-10-24 (N+1) are mitigated and tested: no id in guest routes with spoofed `guest_id`/`guest` params ignored, staff-only fields absent from guest bodies, 403 for manage/adjust/reports/guests-only holders and every preset, constant query count.

## Requirements

- **LOY-06** and **LOY-07** are ticked: the guest and staff account and ledger endpoints are complete and tested.
- **LOY-08** and **LOY-21** were already ticked by earlier Phase 10 plans (FIFO expiry; lang/tests/route gates); this plan adds the endpoint-side evidence (expired-but-unswept batch excluded, five-locale labels, 401/403/422 tests) and left them as they were. Docs/Postman for these routes remain with 10-15.

## Next Phase Readiness

10-07 (manual adjust) writes entries that this ledger already renders with `reason` and `performed_by`. 10-09 appends rewards, vouchers and preview to the same `auth:guests` `loyalty` block. FA-10.06-2: a staff member who adjusts points must also hold `loyalty.view`; state this in the 10-15 guide.

## Self-Check: PASSED

- All eight created files exist; commit 0af458e exists.
