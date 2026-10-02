<?php

namespace Tests\Unit\Tickets;

use App\Enums\TicketActionType;
use App\Enums\TicketRecoveryType;
use App\Enums\TicketSource;
use App\Enums\TicketStatus;
use App\Models\FolioItem;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\Ticket;
use App\Models\TicketAction;
use App\Models\TicketRecovery;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use LogicException;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

/**
 * D-01, D-02, D-03 schema contracts plus council A6/A8 (append-only timeline,
 * restrictOnDelete actors, whitelisted meta, description kept out of the log).
 */
class TicketSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_tickets_gain_the_support_columns(): void
    {
        $this->assertTrue(Schema::hasColumns('tickets', [
            'description', 'reservation_id', 'room_id', 'created_by', 'resolved_at', 'closed_at', 'escalation_level',
        ]));

        foreach (['escalated_at', 'escalated_to_user_id', 'escalation_reason'] as $column) {
            $this->assertFalse(Schema::hasColumn('tickets', $column), $column);
        }

        $id = DB::table('tickets')->insertGetId([
            'uuid' => 'raw-uuid', 'subject' => 'x', 'category' => 'inquiry', 'department' => 'concierge',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $raw = DB::table('tickets')->find($id);
        $this->assertSame(0, (int) $raw->escalation_level);
        $this->assertSame('chatbot', $raw->source);

        $this->assertSame(0, Ticket::factory()->create()->fresh()->escalation_level);
    }

    public function test_ticket_actions_are_typed_and_have_no_updated_at(): void
    {
        $action = TicketAction::factory()->statusChange('open', 'in_progress', 'because')->create();

        $this->assertNotEmpty($action->uuid);
        $this->assertNotNull($action->fresh()->created_at);
        $this->assertFalse(Schema::hasColumn('ticket_actions', 'updated_at'));
        $this->assertSame(TicketActionType::STATUS_CHANGE, $action->fresh()->type);
        $this->assertSame('because', $action->fresh()->body);
    }

    public function test_meta_keys_are_whitelisted_per_type(): void
    {
        $ticket = Ticket::factory()->create();

        TicketAction::create(['ticket_id' => $ticket->id, 'type' => TicketActionType::ASSIGNMENT, 'meta' => ['claim' => true]]);
        TicketAction::create(['ticket_id' => $ticket->id, 'type' => TicketActionType::ESCALATION, 'meta' => ['level' => 1, 'previous_assignee_uuid' => null]]);

        foreach (TicketActionType::cases() as $type) {
            TicketAction::create(['ticket_id' => $ticket->id, 'type' => $type, 'meta' => null]);
        }

        $count = TicketAction::count();

        foreach ([
            [TicketActionType::ASSIGNMENT, ['level' => 2]],
            [TicketActionType::CREATED, ['claim' => true]],
            [TicketActionType::RECOVERY, ['amount_usd' => '10.00']],
        ] as [$type, $meta]) {
            try {
                TicketAction::create(['ticket_id' => $ticket->id, 'type' => $type, 'meta' => $meta]);
                $this->fail("{$type->value} accepted a foreign meta key");
            } catch (InvalidArgumentException) {
                $this->assertSame($count, TicketAction::count());
            }
        }
    }

    public function test_ticket_actions_are_append_only(): void
    {
        $action = TicketAction::factory()->reply('original')->create();

        try {
            $action->update(['body' => 'edited']);
            $this->fail('update allowed');
        } catch (LogicException) {
        }

        try {
            $action->delete();
            $this->fail('delete allowed');
        } catch (LogicException) {
        }

        $this->assertSame('original', DB::table('ticket_actions')->where('id', $action->id)->value('body'));
    }

    public function test_actor_and_target_users_cannot_be_deleted_while_referenced(): void
    {
        $actor  = User::factory()->create();
        $target = User::factory()->create();
        $ticket = Ticket::factory()->create();

        TicketAction::create(['ticket_id' => $ticket->id, 'type' => TicketActionType::REPLY, 'body' => 'x', 'user_id' => $actor->id]);
        TicketAction::create(['ticket_id' => $ticket->id, 'type' => TicketActionType::ASSIGNMENT, 'target_user_id' => $target->id]);

        foreach ([$actor, $target] as $user) {
            try {
                $user->delete();
                $this->fail('referenced user was deleted');
            } catch (QueryException) {
                $this->assertTrue(User::whereKey($user->id)->exists());
            }
        }
    }

    public function test_deleting_a_ticket_cascades_actions_and_recoveries(): void
    {
        $recovery = TicketRecovery::factory()->create();
        $ticketId = $recovery->action->ticket_id;

        DB::table('tickets')->where('id', $ticketId)->delete();

        $this->assertSame(0, DB::table('ticket_actions')->where('ticket_id', $ticketId)->count());
        $this->assertSame(0, DB::table('ticket_recoveries')->where('id', $recovery->id)->count());
    }

    public function test_folio_item_link_is_unique_and_nulls_on_delete(): void
    {
        $item  = FolioItem::factory()->credit()->create();
        $first = TicketRecovery::factory()->folioCredit($item)->create();
        $this->assertSame('10.00', $first->fresh()->amount_usd);

        try {
            TicketRecovery::factory()->folioCredit($item)->create();
            $this->fail('second link on the same folio item accepted');
        } catch (UniqueConstraintViolationException) {
        }

        try {
            TicketRecovery::create([
                'ticket_action_id' => $first->ticket_action_id,
                'type'             => TicketRecoveryType::APOLOGY,
                'description'      => 'dup',
            ]);
            $this->fail('second recovery on the same action accepted');
        } catch (UniqueConstraintViolationException) {
        }

        DB::table('folio_items')->where('id', $item->id)->delete();

        $this->assertNull($first->fresh()->folio_item_id);
    }

    public function test_description_is_excluded_from_the_activity_log(): void
    {
        $ticket = Ticket::factory()->create(['description' => 'The guest shouted at reception']);

        $ticket->update(['description' => 'Secret complaint text', 'status' => TicketStatus::RESOLVED]);

        $rows = Activity::where('subject_type', Ticket::class)->where('subject_id', $ticket->id)->get();
        $latest = $rows->last();

        $this->assertSame('updated', $latest->event);
        $this->assertArrayHasKey('status', $latest->attribute_changes['attributes'] ?? []);

        foreach ($rows as $row) {
            foreach (['attribute_changes', 'properties'] as $column) {
                $logged = json_encode($row->{$column});
                $this->assertStringNotContainsString('"description"', $logged);
                $this->assertStringNotContainsString('Secret complaint', $logged);
            }
        }

        $assignee = User::factory()->create();
        $ticket->update(['assigned_user_id' => $assignee->id]);
        $assign = Activity::where('subject_type', Ticket::class)->where('subject_id', $ticket->id)->get()->last();
        $this->assertArrayHasKey('assigned_user_id', $assign->attribute_changes['attributes'] ?? []);
    }

    public function test_recoveries_are_reached_through_actions_and_room_includes_trashed(): void
    {
        $recovery = TicketRecovery::factory()->create();
        $ticket   = $recovery->action->ticket;

        $this->assertSame([$recovery->id], $ticket->recoveries->pluck('id')->all());

        $room = Room::factory()->create();
        $withRoom = Ticket::factory()->create(['room_id' => $room->id]);
        $room->delete();

        $this->assertSame($room->number, $withRoom->fresh()->room->number);
    }

    public function test_factory_states(): void
    {
        $staff = Ticket::factory()->staff()->create();
        $this->assertSame(TicketSource::STAFF, $staff->source);
        $this->assertNull($staff->guest_id);

        $reservation = Reservation::factory()->create();
        $linked = Ticket::factory()->withReservation($reservation)->create();
        $this->assertSame($reservation->id, $linked->reservation_id);
        $this->assertSame($reservation->guest_id, $linked->guest_id);

        $user = User::factory()->create();
        $assigned = Ticket::factory()->assignedTo($user)->create();
        $this->assertSame(TicketStatus::ASSIGNED, $assigned->status);
        $this->assertSame($user->id, $assigned->assigned_user_id);

        $this->assertSame(TicketStatus::IN_PROGRESS, Ticket::factory()->inProgress()->create()->status);
        $this->assertSame(TicketStatus::WAITING_GUEST, Ticket::factory()->waitingGuest()->create()->status);

        $resolved = Ticket::factory()->resolved()->create();
        $this->assertSame(TicketStatus::RESOLVED, $resolved->status);
        $this->assertNotNull($resolved->resolved_at);

        $closed = Ticket::factory()->closed()->create();
        $this->assertSame(TicketStatus::CLOSED, $closed->status);
        $this->assertNotNull($closed->closed_at);

        $this->assertSame(2, Ticket::factory()->escalated(2)->create()->fresh()->escalation_level);
    }

    public function test_escalation_cap_config_defaults_to_three(): void
    {
        $this->assertSame(3, config('hotel.ticket_max_escalation_level'));
    }
}
