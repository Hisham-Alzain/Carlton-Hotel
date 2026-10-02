<?php

namespace Tests\Unit\Tickets;

use App\Actions\Tickets\EscalateTicketAction;
use App\Enums\TicketActionType;
use App\Enums\TicketStatus;
use App\Events\TicketChanged;
use App\Exceptions\AssigneeNotEligibleException;
use App\Exceptions\TicketClosedException;
use App\Exceptions\TicketEscalationInvalidException;
use App\Exceptions\TicketEscalationLimitException;
use App\Models\Ticket;
use App\Models\TicketAction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\Concerns\RecordsRowLocks;
use Tests\TestCase;

/** D-18 / D-19: escalation to a named colleague, guarded and capped. */
class EscalateTicketActionTest extends TestCase
{
    use RecordsRowLocks, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Event::fake([TicketChanged::class]);
    }

    private function eligible(): User
    {
        return User::factory()->withPermissions('tickets.respond')->create();
    }

    private function escalate(Ticket $ticket, User $target, ?User $actor = null, string $reason = 'Guest is VIP'): array
    {
        return app(EscalateTicketAction::class)->handle($ticket, $target, $reason, $actor ?? User::factory()->create());
    }

    public function test_open_ticket_escalates_to_assigned(): void
    {
        $ticket = Ticket::factory()->create();
        $target = $this->eligible();
        $actor  = User::factory()->create();

        $result = $this->escalate($ticket, $target, $actor);

        $this->assertSame(200, $result['code']);
        $fresh = $ticket->fresh();
        $this->assertSame(1, $fresh->escalation_level);
        $this->assertSame(TicketStatus::ASSIGNED, $fresh->status);
        $this->assertSame($target->id, $fresh->assigned_user_id);

        $row = TicketAction::where('ticket_id', $ticket->id)->sole();
        $this->assertSame(TicketActionType::ESCALATION, $row->type);
        $this->assertSame($actor->id, $row->user_id);
        $this->assertSame($target->id, $row->target_user_id);
        $this->assertSame('Guest is VIP', $row->body);
        $this->assertSame(['open', 'assigned'], [$row->from_status, $row->to_status]);
        $this->assertSame(['level' => 1, 'previous_assignee_uuid' => null], $row->meta);
        Event::assertDispatchedTimes(TicketChanged::class, 1);
    }

    public function test_active_ticket_swaps_assignee_and_records_previous(): void
    {
        $a      = $this->eligible();
        $b      = $this->eligible();
        $ticket = Ticket::factory()->inProgress()->create(['assigned_user_id' => $a->id]);

        $this->escalate($ticket, $b);

        $fresh = $ticket->fresh();
        $this->assertSame(TicketStatus::IN_PROGRESS, $fresh->status);
        $this->assertSame($b->id, $fresh->assigned_user_id);

        $row = TicketAction::where('ticket_id', $ticket->id)->sole();
        $this->assertSame($a->uuid, $row->meta['previous_assignee_uuid']);
        $this->assertNull($row->from_status);
        $this->assertNull($row->to_status);
    }

    public function test_a_b_a_is_allowed_within_the_cap(): void
    {
        $a      = $this->eligible();
        $b      = $this->eligible();
        $c      = User::factory()->create();
        $ticket = Ticket::factory()->assignedTo($a)->create();

        $this->escalate($ticket, $b, $c);
        $this->escalate($ticket, $a, $c);

        $this->assertSame(2, $ticket->fresh()->escalation_level);
        $this->assertSame($a->id, $ticket->fresh()->assigned_user_id);
    }

    public function test_self_is_refused(): void
    {
        $actor  = $this->eligible();
        $ticket = Ticket::factory()->create();

        try {
            $this->escalate($ticket, $actor, $actor);
            $this->fail('self escalation must be refused');
        } catch (TicketEscalationInvalidException $e) {
            $this->assertSame('ticket_escalation_invalid', $e->errorCode());
            $this->assertSame(['reason' => 'self'], $e->context());
        }

        $this->assertSame(0, TicketAction::count());
        $this->assertSame(0, $ticket->fresh()->escalation_level);
    }

    public function test_same_assignee_is_refused(): void
    {
        $owner  = $this->eligible();
        $ticket = Ticket::factory()->assignedTo($owner)->create();

        try {
            $this->escalate($ticket, $owner);
            $this->fail('escalating to the current assignee must be refused');
        } catch (TicketEscalationInvalidException $e) {
            $this->assertSame(['reason' => 'same_assignee'], $e->context());
        }

        $this->assertSame(0, TicketAction::count());
        Event::assertNotDispatched(TicketChanged::class);
    }

    public function test_cap_is_enforced(): void
    {
        $ticket = Ticket::factory()->escalated(3)->create();

        try {
            $this->escalate($ticket, $this->eligible());
            $this->fail('the default cap is 3');
        } catch (TicketEscalationLimitException $e) {
            $this->assertSame('ticket_escalation_limit', $e->errorCode());
            $this->assertSame(['level' => 3, 'max' => 3], $e->context());
        }

        config(['hotel.ticket_max_escalation_level' => 1]);
        $other = Ticket::factory()->create();
        $this->escalate($other, $this->eligible());

        try {
            $this->escalate($other, $this->eligible());
            $this->fail('the configured cap is 1');
        } catch (TicketEscalationLimitException $e) {
            $this->assertSame(['level' => 1, 'max' => 1], $e->context());
        }

        $this->assertSame(1, $other->fresh()->escalation_level);
    }

    public function test_closed_or_resolved_is_refused_first(): void
    {
        foreach (['resolved', 'closed'] as $status) {
            $ticket = Ticket::factory()->create(['status' => $status]);

            try {
                $this->escalate($ticket, $this->eligible());
                $this->fail("{$status} must be refused");
            } catch (TicketClosedException $e) {
                $this->assertSame(['status' => $status], $e->context());
            }
        }

        $actor  = $this->eligible();
        $closed = Ticket::factory()->closed()->create();

        $this->expectException(TicketClosedException::class);
        $this->escalate($closed, $actor, $actor);
    }

    public function test_ineligible_target_is_refused(): void
    {
        $ticket = Ticket::factory()->create();

        try {
            $this->escalate($ticket, User::factory()->create());
            $this->fail('ineligible target must be refused');
        } catch (AssigneeNotEligibleException $e) {
            $this->assertSame('tickets.respond', $e->context()['required_permission']);
        }

        $this->assertSame(0, TicketAction::count());
    }

    /** Proves `for update` on the ticket row only; SQLite never serialises (council A9). */
    public function test_ticket_row_is_locked(): void
    {
        $ticket = Ticket::factory()->create();

        $this->assertLocksRow('tickets', fn () => $this->escalate($ticket, $this->eligible()));
    }
}
