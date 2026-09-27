---
phase: 06-housekeeping-guest-services
plan: 02
subsystem: automatic housekeeping tasks
requires: [06-01]
provides: [reservations.check_out_mode, hotel.turnover_sla_minutes, CreateTurnoverTaskOnCheckOut, CreateRequestTaskOnServiceRequestPlaced]
key-files:
  created:
    - backend/database/migrations/2026_09_27_100200_add_check_out_mode_to_reservations_table.php
    - backend/app/Listeners/CreateTurnoverTaskOnCheckOut.php
    - backend/app/Listeners/CreateRequestTaskOnServiceRequestPlaced.php
    - backend/tests/Feature/Housekeeping/TurnoverOnCheckOutTest.php
    - backend/tests/Feature/Housekeeping/RequestTaskOnServiceRequestTest.php
  modified:
    - backend/app/Models/Reservation.php
    - backend/app/Actions/Booking/CheckOutReservationAction.php
    - backend/app/Http/Resources/Booking/ReservationResource.php
    - backend/config/hotel.php
    - backend/tests/Feature/Reservations/CheckOutTest.php
    - backend/tests/Feature/Reservations/ExpressCheckoutTest.php
completed: 2026-09-27
---

# 06-02 — Tasks appear by themselves

## Shipped
- Migration `reservations.check_out_mode` string(20) nullable (D-20); `Reservation` fillable + `CheckOutMode` cast; `CheckOutReservationAction` writes the effective mode in the same `update()` as `checked_out_at` (plain `none`, forced `staff_force`, settled+force `none`, guest `guest_express`).
- `ReservationResource.check_out_mode`: staff-only (same `User` condition as `notes`); guest responses never carry it.
- `config('hotel.turnover_sla_minutes')` = `(int) env('HOTEL_TURNOVER_SLA_MINUTES', 120)` with docblock.
- `CreateTurnoverTaskOnCheckOut` (sync, auto-discovered): one `ensureOpen(TURNOVER)` per distinct assigned room (ordered by id), priority high when a `confirmed` reservation with `check_in` = `HotelClock::today()` holds the room, due now + SLA, reason `check_out`, actor null; per-room `try { } catch (\Throwable) { report() }`.
- `CreateRequestTaskOnServiceRequestPlaced` (sync): only `Department::HOUSEKEEPING`; room = first reservation line (lowest id) with a room; none → activity `housekeeping_task_skipped` {reason: no_room}; else `ensureOpen(REQUEST, ['service_request' => $request, ...])`, due `created_at + (item expected_minutes ?? 60)`, priority/notes from the request, reason `service_request`; failures reported.

## Deviations from PLAN.md
- **`.env.example` NOT edited.** The Read/Edit tools are denied on `backend/.env.example` by the owner's permission settings, and I did not work around the deny rule. The owner must add `HOTEL_TURNOVER_SLA_MINUTES=120` under `HOTEL_CHECK_OUT_TIME` (line 13). Behaviour is unaffected (config default 120). The plan's verify line `grep -q HOTEL_TURNOVER_SLA_MINUTES=120 .env.example` therefore fails until then.
- Consultant override: request tasks link through `service_request_id` (attrs key `service_request`), asserted as `task.service_request_id === request.id` instead of `source_type/source_id`.
- Tests use real bearer tokens for guests (`$guest->createToken('guest')`) rather than `actingAs`.

## Flagged assumptions
- FA-6.02-1 confirmed (listener failures reported, check-out still 200; `test_a_failing_room_does_not_fail_the_check_out` with `Exceptions::assertReported(UniqueConstraintViolationException::class)`).
- FA-6.02-2 confirmed (arrival = confirmed + check_in hotel-local today, via a reservation line).

## Verification
- RED confirmed (9 failing) before implementation.
- Scratch sqlite `migrate:fresh --seed` / `rollback --step=1` / `migrate`: ok; dev DB sha1 unchanged.
- Targeted: `TurnoverOnCheckOutTest|RequestTaskOnServiceRequestTest|CheckOutTest|ExpressCheckoutTest|CheckOutReservationActionTest|ServiceCatalogTest|OperationsQueueTest|FolioTest|StayTest` 127 passed.
- Full suite: 1555 passed.
