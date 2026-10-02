<?php

namespace Tests\Unit\Tickets;

use App\Actions\Tickets\AssignTicketAction;
use App\Enums\TicketActionType;
use App\Enums\TicketStatus;
use App\Events\TicketChanged;
use App\Exceptions\AssigneeNotEligibleException;
use App\Exceptions\TicketClosedException;
use App\Models\Ticket;
use App\Models\TicketAction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Tests\Concerns\RecordsRowLocks;
use Tests\TestCase;

/** D-08 / D-09: the single writer of a ticket's assignee. */
class AssignTicketActionTest extends TestCase
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

    private function assign(Ticket $ticket, User $assignee, ?User $actor = null): array
    {
        return app(AssignTicketAction::class)->handle($ticket, $assignee, $actor ?? User::factory()->create());
    }

    public function test_open_ticket_becomes_assigned(): void
    {
        $ticket   = Ticket::factory()->create();
        $assignee = $this->eligible();
        $actor    = User::factory()->create();

        $result = $this->assign($ticket, $assignee, $actor);

        $this->assertSame(200, $result['code']);
        $fresh = $ticket->fresh();
        $this->assertSame(TicketStatus::ASSIGNED, $fresh->status);
        $this->assertSame($assignee->id, $fresh->assigned_user_id);

        $row = TicketAction::where('ticket_id', $ticket->id)->sole();
        $this->assertSame(TicketActionType::ASSIGNMENT, $row->type);
        $this->assertSame(['open', 'assigned'], [$row->from_status, $row->to_status]);
        $this->assertSame($assignee->id, $row->target_user_id);
        $this->assertSame($actor->id, $row->user_id);
        Event::assertDispatchedTimes(TicketChanged::class, 1);
    }

    public function test_active_tickets_swap_the_assignee_and_keep_the_status(): void
    {
        foreach (['assigned', 'in_progress', 'waiting_guest'] as $status) {
            $ticket = Ticket::factory()->create(['status' => $status, 'assigned_user_id' => $this->eligible()->id]);
            $next   = $this->eligible();

            $this->assign($ticket, $next);

            $fresh = $ticket->fresh();
            $this->assertSame($status, $fresh->status->value);
            $this->assertSame($next->id, $fresh->assigned_user_id);

            $row = TicketAction::where('ticket_id', $ticket->id)->sole();
            $this->assertSame(TicketActionType::ASSIGNMENT, $row->type);
            $this->assertNull($row->from_status);
            $this->assertNull($row->to_status);
            $this->assertSame($next->id, $row->target_user_id);
        }

        Event::assertDispatchedTimes(TicketChanged::class, 3);
    }

    public function test_finished_tickets_are_refused(): void
    {
        foreach (['resolved', 'closed'] as $status) {
            $ticket = Ticket::factory()->create(['status' => $status]);

            try {
                $this->assign($ticket, $this->eligible());
                $this->fail("{$status} must be refused");
            } catch (TicketClosedException $e) {
                $this->assertSame('ticket_closed', $e->errorCode());
                $this->assertSame(422, $e->statusCode());
                $this->assertSame(['status' => $status], $e->context());
            }

            $this->assertNull($ticket->fresh()->assigned_user_id);
        }

        $this->assertSame(0, TicketAction::count());
        Event::assertNotDispatched(TicketChanged::class);
    }

    public function test_current_assignee_is_a_no_op(): void
    {
        $owner  = $this->eligible();
        $ticket = Ticket::factory()->assignedTo($owner)->create();

        $result = $this->assign($ticket, $owner);

        $this->assertSame(200, $result['code']);
        $this->assertSame(0, TicketAction::count());
        Event::assertNotDispatched(TicketChanged::class);
    }

    public function test_ineligible_targets_are_refused(): void
    {
        $noPermission = User::factory()->create();
        $inactive     = User::factory()->withPermissions('tickets.respond')->create(['is_active' => false]);
        $otherType    = $this->eligible();
        DB::table('users')->where('id', $otherType->id)->update(['type' => 'integration']);

        foreach ([$noPermission, $inactive, $otherType->fresh()] as $target) {
            $ticket = Ticket::factory()->create();

            try {
                $this->assign($ticket, $target);
                $this->fail('ineligible target must be refused');
            } catch (AssigneeNotEligibleException $e) {
                $this->assertSame('tickets.respond', $e->context()['required_permission']);
                $this->assertSame($target->uuid, $e->context()['user_uuid']);
            }

            $this->assertSame(TicketStatus::OPEN, $ticket->fresh()->status);
        }

        $this->assertSame(0, TicketAction::count());
    }

    public function test_eligibility_runs_before_the_no_op(): void
    {
        $owner  = User::factory()->create();
        $ticket = Ticket::factory()->assignedTo($owner)->create();

        $this->expectException(AssigneeNotEligibleException::class);
        $this->assign($ticket, $owner);
    }

    public function test_super_admin_is_assignable(): void
    {
        $admin  = User::factory()->superAdmin()->create();
        $ticket = Ticket::factory()->create();

        $this->assign($ticket, $admin);

        $this->assertSame($admin->id, $ticket->fresh()->assigned_user_id);
    }

    /** Proves `for update` on the ticket row only; SQLite never serialises (council A9). */
    public function test_ticket_row_is_locked(): void
    {
        $ticket = Ticket::factory()->create();

        $this->assertLocksRow('tickets', fn () => $this->assign($ticket, $this->eligible()));
    }
}
