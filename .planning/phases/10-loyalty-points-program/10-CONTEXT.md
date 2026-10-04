# Phase 10: Loyalty Points Program — Context

**Source:** user Q&A on 2026-10-04 (decisions are locked; planner must not re-litigate).

## Goal
Guests earn points on spend, points expire on a rolling basis, and guests redeem them for booking discounts, free nights and room upgrades. Staff configure and audit the program from the dashboard.

## Locked decisions

### Earning
- Sources: room stays (per USD), services/F&B charges, manual staff award/deduct (reason required, audited).
- Credited **once on folio settlement**. No pending balance.
- Points are integers, **round half up**.
- No tiers: a single flat balance.
- No backfill of historical folios; the program starts at launch.

### Reversals (full)
- Folio refund / reservation cancel: earned points are clawed back, spent points are refunded, and a used voucher is restored.

### Expiry
- Rolling, per earn batch, consumed FIFO. Expiry months configurable, default 24.
- Daily scheduled job expires batches.
- Guest notification N days before expiry (default 30, configurable) via the existing notifications system.

### Redemption
- Free-form points → USD discount on a reservation at booking time (DECIMAL USD, redeem rate configurable).
- Staff-managed rewards catalog (AR/EN names, points cost, type: discount voucher / free night / room upgrade).
- Redeeming a reward creates a **voucher** (code, status, expiry) that the guest applies at booking.
- Redeem is idempotent and transactional.

### Configurable via dashboard settings (no seeded rates)
Earn rate, redeem value, expiry months, expiry-warning days, minimum points to redeem, max % of a booking payable with points.

## API surface
- **Guest:** balance (available / expiring soon) + ledger; browse catalog; redeem reward → voucher; my vouchers; preview points earned / discount at booking.
- **Staff:** settings get/update; rewards CRUD (+ recycle bin if soft-deleted); view a guest's balance and ledger; manual adjust; reports (issued / redeemed / expired over a period).

## Constraints (project-wide)
TupCode conventions (Base* classes, services/actions returning `['data','code']`, domain exceptions, AR/EN keys, UUID public ids, `/api/v1`, routes grouped by role), additive migrations, 3NF, ledger with status/history (no boolean flags), every new route has happy/401/403/422 tests, full suite green, commit after the phase, never push.

## Resolved planning rulings (decided by Fable consultant, 2026-10-04; see 10-DISCUSSION-LOG.md and 10-RESEARCH.md)
These are LOCKED for the planner. Requirement IDs: LOY-01..LOY-22 (REQUIREMENTS.md).

- **Phase 9 dependency: SOFT.** No Phase 9 code symbol is used (no `MoneyAggregate`, no business date, no `reports.view`). Plan now, execute after the Phase 9 commit (shared permission catalogue, `routes/api.php`, 5 lang files, docs). Plans must say "re-read permission counts at execution". Migrations start at `2026_10_05_100000`.
- **Q1** Folio-refund reversal: tested `ReverseLoyaltyForFolioAction` seam, wired live only from reservation cancel (no refund endpoint).
- **Q2** Clawback of already-spent earned points: take from originating batch, then other active batches FIFO, floor 0, record `shortfall_points`, never block a cancel.
- **Q3** Refunded spent points: restore into original batch only if status `active|depleted` AND `expires_at > now()`; else a new `refund` batch with full term. `expired|reversed` batches never revive.
- **Q4** Three independent capabilities: `earning` iff `earn_rate > 0`; `points_discount` iff `redeem_value_usd > 0` AND `max_redeem_percent` set; `rewards` needs no settings. `LoyaltyProgram::maxDiscountPercent()` throws `LoyaltyProgramInactiveException` when null (never default to 100). `min_redeem_points` null = 1; `expiry_months` 24 and `expiry_warning_days` 30 are column defaults; account returns derived `program{earning, points_discount, rewards}`.
- **Q5** Cap base = post-promo quote total; min and cap apply to free-form points only; at most one voucher; voucher + points → 422 `loyalty_discount_conflict`; total floors at 0.00; over-cap 422 carries `max_points`.
- **Q6** Booking idempotency via `Idempotency-Key` required only when loyalty fields present; key stored on `loyalty_reservation_applications.idempotency_key` (unique with `guest_id`), not via the ledger; replay with same inputs → 200 same reservation, mismatch → `idempotency_conflict`.
- **Q7** Permissions `loyalty.view`, `loyalty.manage`, `loyalty.adjust`; no preset changes; reward bin uses `cms.restore|cms.purge`; reports under `loyalty.view`.
- **Q8** Earn is inline-atomic at all three settle sites (`SettleFolioAction` x2, `RecordFolioPaymentAction`); the action catches only `UniqueConstraintViolationException` (treat as already earned); any other failure rolls back settlement.
- **Q9** Earn on any reservation status EXCEPT `cancelled` (no-op + activity log `loyalty.earn_skipped_cancelled`).
- **Q10** One configured rate on net spend, half-up per bucket (`stay`, `service`); preview uses the same `LoyaltyMath`; points-discounted amounts do not earn.
- **Q11-Q15, Q17, Q18, Q20-Q25** accept research defaults (see 10-RESEARCH.md "Open Questions"); `config/loyalty.php` holds `restored_voucher_grace_days=30` and `max_adjust_points=1000000`. Q14 consumption order `expires_at, id`.
- **Q12** Free night = discount of one night at `daily_rate_usd` capped at total; room upgrade voucher is consumed with discount 0.00 and `upgrade_requested` shown on the reservation (staff upgrade via room assignment).
- **Q16 API contract strings:** ledger `type` ∈ `earn|redeem|expire|adjust|clawback|refund`; `source` ∈ `stay|service|manual|refund`; account: `available_points, expiring_soon_points, expiring_soon_window_days, next_expiry_at, lifetime_earned_points, lifetime_redeemed_points, program{…}, redeem_value_usd, min_redeem_points, max_redeem_percent`; no tier fields. Guest ledger hides `reason`/`performed_by`; staff shows both. `POST /reservations` adds `loyalty_points`, `voucher_code`; `ReservationResource.loyalty{points_redeemed, points_discount_usd, voucher{code,type}|null, voucher_discount_usd, upgrade_requested, status}`. Error codes: `loyalty_program_inactive, loyalty_insufficient_points, loyalty_below_minimum, loyalty_over_cap, loyalty_voucher_invalid, loyalty_reward_unavailable, loyalty_adjustment_invalid, loyalty_discount_conflict` + reused `idempotency_conflict`. New `NotificationType::LOYALTY_POINTS_EXPIRING`. Document in API_GUIDE_MOBILE + CHANGELOG_MOBILE_API with a Flutter note (dining/spa collapse to `service`).
- **Q19** `issued` = earn + positive adjust only; report also `redeemed, expired, refunded, clawed_back, adjusted_out, outstanding_points, liability_usd`.

### Mandatory must_haves / prohibitions for plans
- `LoyaltyProgram` getters throw on null rate/cap; no `?? 100`, `?? 1.0` anywhere.
- `EarnLoyaltyPointsAction` catches only `UniqueConstraintViolationException`.
- `CancelReservationAction` re-checks status after `lockForUpdate()` inside the transaction; return stays `['data'=>null,'code'=>204]`.
- `QuoteReservationAction` and `ReleaseExpiredHoldsAction` byte-unmodified; test that a hold release touches no loyalty table.
- `ApplyLoyaltyToReservationAction` refuses `status = pending_verification` or non-null `hold_expires_at`.
- Lock order `reservation → folio → guest → batches`, documented in each action and asserted with `RecordsRowLocks`.
- Discount is never accepted from the client (points/code only).
- Settings never stored in `site_settings`; no caching of rates.
- `loyalty_vouchers.reservation_id` unique and nulled on restore; `reverses_entry_id` unique; `(folio_id, source)` unique for earn.
- Lang keys appended, never reordered; permission/seeder count pins re-pinned once after Phase 9's commit.
