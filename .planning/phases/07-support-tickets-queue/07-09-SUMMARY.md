---
phase: 07-support-tickets-queue
plan: 09
subsystem: queue claim
requires: [07-08]
provides: [PATCH /operations/queue/{type}/{uuid}/claim, ClaimGuard, queue_item_already_claimed 409, claim flag on the three assign writers]
key-files:
  created:
    - backend/app/Support/ClaimGuard.php
    - backend/app/Exceptions/QueueItemAlreadyClaimedException.php
    - backend/tests/Feature/Operations/QueueClaimTest.php
    - backend/tests/Feature/Operations/DeactivatedTokenClaimTest.php
    - backend/tests/Unit/Support/ClaimGuardTest.php
  modified:
    - backend/app/Actions/Tickets/AssignTicketAction.php
    - backend/app/Actions/Housekeeping/AssignHousekeepingTaskAction.php
    - backend/app/Actions/Operations/AssignRequestAction.php
    - backend/app/Services/Operations/OperationsQueueService.php
    - backend/app/Http/Controllers/Admin/OperationsQueueController.php
    - backend/routes/api.php
    - backend/lang/{en,ar,fr,tr,es}/custom.php
completed: 2026-10-02
---

# 07-09 — Queue claim (OPS-01)

## Shipped
- `PATCH /api/operations/queue/{type}/{uuid}/claim` (no body, `auth:users`) for `service-requests`, `tickets`, `housekeeping-tasks`. Gated in-service by the type's **work** permission (`service_requests.update | tickets.respond | housekeeping.update`) before the item is resolved (403 before 404, as on assign/status). An unknown type or uuid is 404 `not_found`.
- `App\Support\ClaimGuard::check($item, $actor)`: null assignee → false (assign), the actor → true (200 no-op), anyone else → `QueueItemAlreadyClaimedException` (409 `queue_item_already_claimed`, context `{assigned_user_uuid}`).
- `bool $claim = false` on `AssignTicketAction`, `AssignHousekeepingTaskAction` and `AssignRequestAction` (passed to all three arms). Under each writer's existing lock (ticket row; room → task; SR row) the order is closed check → ClaimGuard → assign. Claim skips `AssigneeEligibility`; non-claim paths are unchanged.
- Side effects: ticket open → assigned (in_progress etc. keep their status) + an `assignment` action with `meta {claim: true}`; HK pending → assigned + a history row with reason `claimed`; SR status unchanged. Ticket and HK claims mirror once through their writer's event; an SR claim mirrors once after commit. A no-op writes nothing and mirrors nothing.
- Writers return `'claimed' => bool`; the controller picks `custom.messages.queue_item_claimed` or `custom.messages.queue_item_already_yours`. The response is `OperationsQueueItemResource` with status 200.
- Terminal items answer with the type's own code: `ticket_closed`, `housekeeping_task_closed`, `service_request_closed` (422, context `{status}`), including when the caller already owns the item. `queue_item_closed` does not exist (council A1, checked with grep over app/lang/routes).
- Lang: `errors.queue_item_already_claimed`, `messages.queue_item_claimed`, `messages.queue_item_already_yours` in five locales.

## Tests
- `QueueClaimTest`: 25 methods (401, 404 type/uuid, 403 with no permission or with assign only, reception preset 403 on HK, kitchen preset happy path on SR, happy/no-op/409/closed per type, own closed ticket is 422, the per-type closed-code test, `assertLocksRow('tickets'|'service_requests')`, room locked before housekeeping_tasks). The class docblock states the A9 caveat: lock assertions prove only that `for update` is issued; serialisation is MySQL-only.
- `DeactivatedTokenClaimTest`: deactivation through `PATCH /api/staff/{uuid}/deactivate` leaves zero tokens; the old token gets 401 on claim; an active token claims (control).
- `ClaimGuardTest`: 9 data-provided cases (3 outcomes × 3 types).
- RED confirmed first (36 tests, 34 failing); then green.

## Deviations
- The 422 test case for the claim route is the per-type closed codes (FA-7.09-3; the route has no body, so there is no validation input).
- In `AssignHousekeepingTaskAction` and the SR arm, ClaimGuard compares against `$assignee`/`$user` (the claimer, equal to the actor on a claim). The ticket writer compares against `$actor`. The service always passes the actor as both.

## Verification
- Filtered: `QueueClaimTest|DeactivatedTokenClaimTest|ClaimGuardTest|AssignTicketActionTest|AssignHousekeepingTaskActionTest|QueueAssignEligibilityTest|LocaleFoundationTest`: 114 passed.
- Full suite: **1980 passed** (12645 assertions). The `database.sqlite` sha1 is still `babcdd27c9070101d4b7dc2bcfa42fdb8affd70d`.
