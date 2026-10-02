<?php

namespace Tests\Unit\Tickets;

use App\Actions\Tickets\UpdateTicketStatusAction;
use App\Enums\TicketActionType;
use App\Enums\TicketStatus;
use App\Events\TicketChanged;
use App\Exceptions\TicketTransitionException;
use App\Models\Ticket;
use App\Models\TicketAction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\RecordsRowLocks;
use Tests\TestCase;

/** D-06 / D-07: the single writer of a ticket's status. */
class UpdateTicketStatusActionTest extends TestCase
{
    use RecordsRowLocks, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Event::fake([TicketChanged::class]);
    }

    private function move(Ticket $ticket, TicketStatus $to, ?string $reason = null, ?User $actor = null): array
    {
        return app(UpdateTicketStatusAction::class)->handle($ticket, $to, $reason, $actor ?? User::factory()->create());
    }

    /** @return array<string, array{string, string, bool}> */
    public static function pairs(): array
    {
        $allowed = [
            'open'          => ['in_progress', 'resolved', 'closed'],
            'assigned'      => ['in_progress', 'waiting_guest', 'resolved', 'closed'],
            'in_progress'   => ['waiting_guest', 'resolved', 'closed'],
            'waiting_guest' => ['in_progress', 'resolved', 'closed'],
            'resolved'      => ['closed', 'in_progress'],
            'closed'        => [],
        ];

        $pairs = [];

        foreach (TicketStatus::values() as $from) {
            foreach (TicketStatus::values() as $to) {
                $pairs["{$from} -> {$to}"] = [$from, $to, in_array($to, $allowed[$from], true)];
            }
        }

        return $pairs;
    }

    #[DataProvider('pairs')]
    public function test_transition_matrix(string $from, string $to, bool $ok): void
    {
        $this->assertCount(36, self::pairs());

        $ticket = Ticket::factory()->create(['status' => $from]);

        if ($ok) {
            $result = $this->move($ticket, TicketStatus::from($to), 'because');

            $this->assertSame(200, $result['code']);
            $this->assertSame($to, $ticket->fresh()->status->value);
            $this->assertSame(1, TicketAction::where('ticket_id', $ticket->id)->count());

            return;
        }

        try {
            $this->move($ticket, TicketStatus::from($to), 'because');
            $this->fail("{$from} -> {$to} must be refused");
        } catch (TicketTransitionException $e) {
            $this->assertSame('ticket_transition_invalid', $e->errorCode());
            $this->assertSame(422, $e->statusCode());
            $this->assertSame([
                'from'    => $from,
                'to'      => $to,
                'allowed' => array_map(fn (TicketStatus $s) => $s->value, TicketStatus::from($from)->allowedTargets()),
            ], $e->context());
        }

        $this->assertSame($from, $ticket->fresh()->status->value);
        $this->assertSame(0, TicketAction::count());
        Event::assertNotDispatched(TicketChanged::class);
    }

    public function test_reason_rules(): void
    {
        $needsReason = [
            ['open', TicketStatus::CLOSED],
            ['in_progress', TicketStatus::CLOSED],
            ['waiting_guest', TicketStatus::CLOSED],
            ['resolved', TicketStatus::IN_PROGRESS],
        ];

        foreach ($needsReason as [$from, $to]) {
            $ticket = Ticket::factory()->create(['status' => $from]);

            try {
                $this->move($ticket, $to, '  ');
                $this->fail("{$from} -> {$to->value} needs a reason");
            } catch (ValidationException $e) {
                $this->assertArrayHasKey('reason', $e->errors());
            }

            $this->assertSame($from, $ticket->fresh()->status->value);

            $this->move($ticket, $to, 'Duplicate of another ticket');
            $this->assertSame('Duplicate of another ticket', TicketAction::where('ticket_id', $ticket->id)->latest('id')->first()->body);
        }

        $resolved = Ticket::factory()->resolved()->create();
        $this->move($resolved, TicketStatus::CLOSED);
        $this->assertSame(TicketStatus::CLOSED, $resolved->fresh()->status);
        $this->assertNull(TicketAction::where('ticket_id', $resolved->id)->first()->body);
    }

    public function test_stamps(): void
    {
        $ticket = Ticket::factory()->inProgress()->create();

        $this->move($ticket, TicketStatus::RESOLVED);
        $this->assertNotNull($ticket->fresh()->resolved_at);

        $this->move($ticket, TicketStatus::IN_PROGRESS, 'Guest came back');
        $this->assertNull($ticket->fresh()->resolved_at);

        $this->move($ticket, TicketStatus::CLOSED, 'Handled offline');
        $this->assertNotNull($ticket->fresh()->closed_at);
    }

    public function test_in_progress_self_assigns_an_unassigned_ticket(): void
    {
        $actor  = User::factory()->create();
        $ticket = Ticket::factory()->create();

        $this->move($ticket, TicketStatus::IN_PROGRESS, null, $actor);

        $this->assertSame($actor->id, $ticket->fresh()->assigned_user_id);
        $rows = TicketAction::where('ticket_id', $ticket->id)->get();
        $this->assertCount(1, $rows);
        $this->assertSame(TicketActionType::STATUS_CHANGE, $rows[0]->type);
        $this->assertSame($actor->id, $rows[0]->target_user_id);

        $owner    = User::factory()->create();
        $assigned = Ticket::factory()->assignedTo($owner)->create();

        $this->move($assigned, TicketStatus::IN_PROGRESS, null, $actor);

        $this->assertSame($owner->id, $assigned->fresh()->assigned_user_id);
        $this->assertNull(TicketAction::where('ticket_id', $assigned->id)->first()->target_user_id);
    }

    public function test_one_timeline_row_and_one_event_per_success(): void
    {
        $actor  = User::factory()->create();
        $ticket = Ticket::factory()->create();

        $this->move($ticket, TicketStatus::RESOLVED, null, $actor);

        $rows = TicketAction::where('ticket_id', $ticket->id)->get();
        $this->assertCount(1, $rows);
        $this->assertSame($actor->id, $rows[0]->user_id);
        $this->assertSame(['open', 'resolved'], [$rows[0]->from_status, $rows[0]->to_status]);
        Event::assertDispatchedTimes(TicketChanged::class, 1);

        try {
            $this->move($ticket, TicketStatus::WAITING_GUEST);
        } catch (TicketTransitionException) {
        }

        $this->assertSame(1, TicketAction::count());
        Event::assertDispatchedTimes(TicketChanged::class, 1);
    }

    public function test_status_is_read_under_the_lock(): void
    {
        $ticket = Ticket::factory()->create();
        Ticket::whereKey($ticket->id)->update(['status' => TicketStatus::CLOSED->value]);

        $this->assertSame(TicketStatus::OPEN, $ticket->status);

        try {
            $this->move($ticket, TicketStatus::IN_PROGRESS);
            $this->fail('the stale in-memory status must not be trusted');
        } catch (TicketTransitionException $e) {
            $this->assertSame('closed', $e->context()['from']);
            $this->assertSame([], $e->context()['allowed']);
        }
    }

    /**
     * Proves the writer asks for `for update` on the ticket row. SQLite never
     * serialises (council A9): real blocking is a MySQL-only guarantee.
     */
    public function test_ticket_row_is_locked(): void
    {
        $ticket = Ticket::factory()->create();

        $this->assertLocksRow('tickets', fn () => $this->move($ticket, TicketStatus::IN_PROGRESS));
    }
}
