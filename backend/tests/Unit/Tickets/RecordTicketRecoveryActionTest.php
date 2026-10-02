<?php

namespace Tests\Unit\Tickets;

use App\Actions\Tickets\RecordTicketRecoveryAction;
use App\Enums\TicketActionType;
use App\Enums\TicketRecoveryType;
use App\Events\TicketChanged;
use App\Exceptions\TicketClosedException;
use App\Exceptions\TicketRecoveryFolioInvalidException;
use App\Models\Folio;
use App\Models\FolioItem;
use App\Models\Guest;
use App\Models\Payment;
use App\Models\Reservation;
use App\Models\Ticket;
use App\Models\TicketAction;
use App\Models\TicketRecovery;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\RecordsRowLocks;
use Tests\TestCase;

/** D-14 / D-15 / A7: record-only recoveries that link an existing folio credit. */
class RecordTicketRecoveryActionTest extends TestCase
{
    use RecordsRowLocks, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Event::fake([TicketChanged::class]);
    }

    /** A folio credit of -$amount on the given (or a new) reservation's folio. */
    private function credit(?Reservation $reservation = null, string $amount = '25.00'): FolioItem
    {
        $reservation ??= Reservation::factory()->create();
        $folio         = Folio::factory()->create(['reservation_id' => $reservation->id]);

        return FolioItem::factory()->credit()->create([
            'folio_id'       => $folio->id,
            'unit_price_usd' => $amount,
            'amount_usd'     => '-'.$amount,
        ]);
    }

    private function record(Ticket $ticket, array $data, ?User $actor = null): array
    {
        return app(RecordTicketRecoveryAction::class)->handle($ticket, $data + [
            'description' => 'Compensation for the broken AC',
            'amount_usd'  => null,
            'folio_item'  => null,
        ], $actor ?? User::factory()->create());
    }

    private function linkCredit(Ticket $ticket, FolioItem $item, ?string $amount = null): array
    {
        return $this->record($ticket, ['type' => TicketRecoveryType::FOLIO_CREDIT, 'folio_item' => $item, 'amount_usd' => $amount]);
    }

    private function assertInvalid(Ticket $ticket, FolioItem $item, string $reason): void
    {
        $before = TicketAction::where('ticket_id', $ticket->id)->count();

        try {
            $this->linkCredit($ticket, $item);
            $this->fail("expected {$reason}");
        } catch (TicketRecoveryFolioInvalidException $e) {
            $this->assertSame('ticket_recovery_folio_invalid', $e->errorCode());
            $this->assertSame(422, $e->statusCode());
            $this->assertSame(['folio_item_uuid' => $item->uuid, 'reason' => $reason], $e->context());
        }

        $this->assertSame($before, TicketAction::where('ticket_id', $ticket->id)->count());
    }

    public function test_folio_credit_is_linked_with_its_absolute_amount(): void
    {
        $reservation = Reservation::factory()->create();
        $item        = $this->credit($reservation);
        $ticket      = Ticket::factory()->withReservation($reservation)->create();
        $actor       = User::factory()->create();

        $result = $this->record($ticket, ['type' => TicketRecoveryType::FOLIO_CREDIT, 'folio_item' => $item], $actor);

        $this->assertSame(201, $result['code']);
        $action = TicketAction::where('ticket_id', $ticket->id)->sole();
        $this->assertSame(TicketActionType::RECOVERY, $action->type);
        $this->assertSame($actor->id, $action->user_id);
        $this->assertNull($action->body);

        $recovery = TicketRecovery::sole();
        $this->assertSame($action->id, $recovery->ticket_action_id);
        $this->assertSame(TicketRecoveryType::FOLIO_CREDIT, $recovery->type);
        $this->assertSame('25.00', (string) $recovery->amount_usd);
        $this->assertSame($item->id, $recovery->folio_item_id);
        Event::assertNotDispatched(TicketChanged::class);
    }

    public function test_guest_fallback_when_the_ticket_has_no_reservation(): void
    {
        $guest  = Guest::factory()->create();
        $item   = $this->credit(Reservation::factory()->create(['guest_id' => $guest->id]));
        $ticket = Ticket::factory()->create(['guest_id' => $guest->id]);

        $this->linkCredit($ticket, $item);

        $this->assertSame($item->id, TicketRecovery::sole()->folio_item_id);
    }

    public function test_no_stay_is_checked_first(): void
    {
        $charge = FolioItem::factory()->manual()->create();
        $ticket = Ticket::factory()->staff()->create();

        $this->assertInvalid($ticket, $charge, 'no_stay');
    }

    public function test_not_credit(): void
    {
        $reservation = Reservation::factory()->create();
        $folio       = Folio::factory()->create(['reservation_id' => $reservation->id]);
        $charge      = FolioItem::factory()->manual()->create(['folio_id' => $folio->id]);
        $ticket      = Ticket::factory()->withReservation($reservation)->create();

        $this->assertInvalid($ticket, $charge, 'not_credit');
    }

    public function test_other_stay_for_reservation_tickets(): void
    {
        $guest  = Guest::factory()->create();
        $mine   = Reservation::factory()->create(['guest_id' => $guest->id]);
        $other  = $this->credit(Reservation::factory()->create(['guest_id' => $guest->id]));
        $ticket = Ticket::factory()->withReservation($mine)->create();

        $this->assertInvalid($ticket, $other, 'other_stay');
    }

    public function test_other_stay_for_guest_only_tickets(): void
    {
        $item   = $this->credit();
        $ticket = Ticket::factory()->create(['guest_id' => Guest::factory()->create()->id]);

        $this->assertInvalid($ticket, $item, 'other_stay');
    }

    public function test_already_linked(): void
    {
        $reservation = Reservation::factory()->create();
        $item        = $this->credit($reservation);
        $first       = Ticket::factory()->withReservation($reservation)->create();
        $second      = Ticket::factory()->withReservation($reservation)->create();

        $this->linkCredit($first, $item);
        $this->assertInvalid($second, $item, 'already_linked');
        $this->assertSame(1, TicketRecovery::count());
    }

    public function test_unique_index_backstop(): void
    {
        $reservation = Reservation::factory()->create();
        $item        = $this->credit($reservation);
        $ticket      = Ticket::factory()->withReservation($reservation)->create();
        $competitor  = TicketAction::factory()->recovery()->create();
        $fired       = false;

        TicketRecovery::creating(function () use (&$fired, $item, $competitor) {
            if ($fired) {
                return;
            }
            $fired = true;

            DB::table('ticket_recoveries')->insert([
                'uuid'             => (string) Str::uuid(),
                'ticket_action_id' => $competitor->id,
                'type'             => 'folio_credit',
                'amount_usd'       => '25.00',
                'description'      => 'Won the race',
                'folio_item_id'    => $item->id,
                'created_at'       => now(),
            ]);
        });

        try {
            $this->linkCredit($ticket, $item);
            $this->fail('the lost race must be reported');
        } catch (TicketRecoveryFolioInvalidException $e) {
            $this->assertSame('already_linked', $e->context()['reason']);
        }

        $this->assertTrue($fired);
        $this->assertSame(0, TicketAction::where('ticket_id', $ticket->id)->count());
    }

    public function test_amount_must_match_the_credit(): void
    {
        $reservation = Reservation::factory()->create();
        $item        = $this->credit($reservation);
        $ticket      = Ticket::factory()->withReservation($reservation)->create();

        try {
            $this->linkCredit($ticket, $item, '20.00');
            $this->fail('a different amount must be refused');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('amount_usd', $e->errors());
        }

        $this->assertSame(0, TicketRecovery::count());

        $this->linkCredit($ticket, $item, '25');
        $this->assertSame('25.00', (string) TicketRecovery::sole()->amount_usd);
    }

    public function test_non_money_types_record_an_optional_amount(): void
    {
        $ticket = Ticket::factory()->create();

        $this->record($ticket, ['type' => TicketRecoveryType::APOLOGY]);
        $this->record($ticket, ['type' => TicketRecoveryType::ROOM_UPGRADE, 'amount_usd' => '40']);

        $rows = TicketRecovery::orderBy('id')->get();
        $this->assertNull($rows[0]->amount_usd);
        $this->assertSame('40.00', (string) $rows[1]->amount_usd);
        $this->assertNull($rows[0]->folio_item_id);
        $this->assertNull($rows[1]->folio_item_id);
    }

    public function test_closed_is_refused_and_resolved_is_allowed(): void
    {
        try {
            $this->record(Ticket::factory()->closed()->create(), ['type' => TicketRecoveryType::APOLOGY]);
            $this->fail('closed must be refused');
        } catch (TicketClosedException $e) {
            $this->assertSame(['status' => 'closed'], $e->context());
        }

        $this->assertSame(201, $this->record(Ticket::factory()->resolved()->create(), ['type' => TicketRecoveryType::APOLOGY])['code']);
    }

    /**
     * Leaf-lock invariant: only the ticket row is locked; the folio side is
     * read, never written or locked. `for update` only — SQLite never
     * serialises (council A9).
     */
    public function test_folio_is_never_written_or_locked(): void
    {
        $reservation = Reservation::factory()->create();
        $item        = $this->credit($reservation);
        $ticket      = Ticket::factory()->withReservation($reservation)->create();
        $items       = FolioItem::count();
        $payments    = Payment::count();

        $locked = $this->lockedSelects(fn () => $this->linkCredit($ticket, $item));

        $this->assertNotEmpty(array_filter($locked, fn ($sql) => str_contains($sql, 'from "tickets"')));
        $this->assertEmpty(array_filter($locked, fn ($sql) => preg_match('/from "(folios|folio_items|payments)"/', $sql)));
        $this->assertSame('-25.00', (string) $item->fresh()->amount_usd);
        $this->assertSame($items, FolioItem::count());
        $this->assertSame($payments, Payment::count());
    }
}
