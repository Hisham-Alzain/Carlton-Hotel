<?php

namespace Tests\Unit\Tickets;

use App\Actions\Tickets\CreateTicketAction;
use App\Enums\Department;
use App\Enums\ServiceRequestPriority;
use App\Enums\TicketActionType;
use App\Enums\TicketCategory;
use App\Enums\TicketSource;
use App\Enums\TicketStatus;
use App\Events\TicketChanged;
use App\Models\Guest;
use App\Models\Reservation;
use App\Models\Ticket;
use App\Models\TicketAction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/** The single ticket creator (Phase 7, D-04, D-05, D-11, D-12, D-21). */
class CreateTicketActionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Event::fake([TicketChanged::class]);
    }

    private function create(array $data, ?User $actor = null): array
    {
        return app(CreateTicketAction::class)->handle(
            $data + ['subject' => 'Broken AC', 'category' => TicketCategory::COMPLAINT],
            $actor ?? User::factory()->create(),
        );
    }

    public function test_creates_a_staff_ticket_with_one_created_action(): void
    {
        $actor  = User::factory()->create();
        $result = $this->create(['description' => 'Loud'], $actor);

        $this->assertSame(201, $result['code']);
        $ticket = $result['data'];
        $this->assertInstanceOf(Ticket::class, $ticket);
        $this->assertSame(TicketSource::STAFF, $ticket->source);
        $this->assertSame(TicketStatus::OPEN, $ticket->status);
        $this->assertSame($actor->id, $ticket->created_by);
        $this->assertNull($ticket->conversation_id);
        $this->assertSame(2, (int) $ticket->priority);

        $action = TicketAction::sole();
        $this->assertSame(TicketActionType::CREATED, $action->type);
        $this->assertNull($action->from_status);
        $this->assertSame('open', $action->to_status);
        $this->assertSame($actor->id, $action->user_id);
        $this->assertNull($action->body);
        $this->assertNull($action->meta);

        Event::assertDispatchedTimes(TicketChanged::class, 1);
    }

    public function test_department_fallback(): void
    {
        $this->assertSame(
            TicketCategory::MAINTENANCE->department(),
            $this->create(['category' => TicketCategory::MAINTENANCE])['data']->department,
        );
        $this->assertSame(Department::CONCIERGE, $this->create(['category' => TicketCategory::INQUIRY])['data']->department);
        $this->assertSame(
            Department::EVENTS,
            $this->create(['category' => TicketCategory::MAINTENANCE, 'department' => Department::EVENTS])['data']->department,
        );
    }

    public function test_priority_is_stored_on_the_ticket_scale(): void
    {
        $this->assertSame(3, (int) $this->create(['priority' => ServiceRequestPriority::HIGH])['data']->priority);
        $this->assertSame(1, (int) $this->create(['priority' => ServiceRequestPriority::LOW])['data']->priority);
    }

    public function test_guest_is_derived_from_the_reservation(): void
    {
        $reservation = Reservation::factory()->create();

        $ticket = $this->create(['reservation' => $reservation])['data'];

        $this->assertSame($reservation->id, $ticket->reservation_id);
        $this->assertSame($reservation->guest_id, $ticket->guest_id);
    }

    public function test_guest_mismatch_throws_and_writes_nothing(): void
    {
        $reservation = Reservation::factory()->create();

        try {
            $this->create(['reservation' => $reservation, 'guest' => Guest::factory()->create()]);
            $this->fail('mismatch accepted');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('guest_uuid', $e->errors());
        }

        $this->assertSame(0, Ticket::count());
        $this->assertSame(0, TicketAction::count());
        Event::assertNotDispatched(TicketChanged::class);
    }

    public function test_ticket_and_action_roll_back_together(): void
    {
        Schema::drop('ticket_actions');

        try {
            $this->create([]);
            $this->fail('create succeeded without a timeline table');
        } catch (\Throwable) {
        }

        $this->assertSame(0, Ticket::count());
        Event::assertNotDispatched(TicketChanged::class);
    }
}
