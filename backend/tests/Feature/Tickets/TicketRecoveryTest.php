<?php

namespace Tests\Feature\Tickets;

use App\Actions\Folio\GenerateFolioAction;
use App\Contracts\FirebaseServiceInterface;
use App\Models\Folio;
use App\Models\FolioItem;
use App\Models\Guest;
use App\Models\Reservation;
use App\Models\Ticket;
use App\Models\TicketRecovery;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\FakeFirebaseService;
use Tests\TestCase;

/** POST /api/support-tickets/{ticket}/recovery-actions (Phase 7, TICKET-06; D-14, D-15, A7). */
class TicketRecoveryTest extends TestCase
{
    use RefreshDatabase;

    private FakeFirebaseService $firebase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->firebase = new FakeFirebaseService();
        $this->app->instance(FirebaseServiceInterface::class, $this->firebase);
    }

    private function staff(string ...$permissions): User
    {
        $user = User::factory()->create();
        $user->givePermissionTo($permissions);

        return $user;
    }

    private function record(Ticket $ticket, array $body, ?User $actor = null)
    {
        $this->app['auth']->forgetGuards();
        $actor ??= $this->staff('tickets.respond');

        return $this->withToken($actor->createToken('t')->plainTextToken)
            ->withHeaders(['Accept-Language' => 'en'])
            ->postJson("/api/support-tickets/{$ticket->uuid}/recovery-actions", $body);
    }

    private function credit(Reservation $reservation, string $amount = '25.00'): FolioItem
    {
        $folio = Folio::factory()->create(['reservation_id' => $reservation->id]);

        return FolioItem::factory()->credit()->create(['folio_id' => $folio->id, 'unit_price_usd' => $amount, 'amount_usd' => '-'.$amount]);
    }

    private function body(array $overrides = []): array
    {
        return array_merge(['type' => 'apology', 'description' => 'Sincere apology from the duty manager'], $overrides);
    }

    public function test_requires_a_token(): void
    {
        $ticket = Ticket::factory()->create();

        $this->postJson("/api/support-tickets/{$ticket->uuid}/recovery-actions", $this->body())->assertStatus(401);
    }

    public function test_tickets_view_alone_is_forbidden(): void
    {
        $ticket = Ticket::factory()->create();

        $this->record($ticket, $this->body(), $this->staff('tickets.view'))->assertStatus(403);
        $this->assertSame(0, TicketRecovery::count());
    }

    /** @return array<string, array{array, string}> */
    public static function invalidBodies(): array
    {
        $uuid = '9b1deb4d-3b7d-4bad-9bdd-2b0d7b3dcb6d';

        return [
            'missing type'                   => [['type' => null], 'type'],
            'unknown type'                   => [['type' => 'transport_hold'], 'type'],
            'short description'              => [['description' => 'ab'], 'description'],
            'folio_credit without item'      => [['type' => 'folio_credit'], 'folio_item_uuid'],
            'apology with item'              => [['folio_item_uuid' => '__ITEM__'], 'folio_item_uuid'],
            'negative amount'                => [['amount_usd' => -1], 'amount_usd'],
            'amount too large'               => [['amount_usd' => 100000], 'amount_usd'],
            'three decimals'                 => [['amount_usd' => '1.234'], 'amount_usd'],
            'unknown folio item'             => [['type' => 'folio_credit', 'folio_item_uuid' => $uuid], 'folio_item_uuid'],
        ];
    }

    #[DataProvider('invalidBodies')]
    public function test_invalid_input_is_422(array $overrides, string $field): void
    {
        $reservation = Reservation::factory()->create();
        $item        = $this->credit($reservation);
        $ticket      = Ticket::factory()->withReservation($reservation)->create();

        $overrides = array_map(fn ($v) => $v === '__ITEM__' ? $item->uuid : $v, $overrides);
        $body      = array_filter($this->body($overrides), fn ($v) => $v !== null);

        $this->record($ticket, $body)->assertStatus(422)
            ->assertJsonPath('error_code', 'validation_failed')
            ->assertJsonValidationErrors([$field]);

        $this->assertSame(0, TicketRecovery::count());
    }

    public function test_no_stay(): void
    {
        $item   = FolioItem::factory()->credit()->create();
        $ticket = Ticket::factory()->staff()->create();

        $this->record($ticket, $this->body(['type' => 'folio_credit', 'folio_item_uuid' => $item->uuid]))->assertStatus(422)
            ->assertJsonPath('error_code', 'ticket_recovery_folio_invalid')
            ->assertJsonPath('context.folio_item_uuid', $item->uuid)
            ->assertJsonPath('context.reason', 'no_stay');
    }

    public function test_not_credit(): void
    {
        $reservation = Reservation::factory()->create();
        $folio       = Folio::factory()->create(['reservation_id' => $reservation->id]);
        $charge      = FolioItem::factory()->manual()->create(['folio_id' => $folio->id]);
        $ticket      = Ticket::factory()->withReservation($reservation)->create();

        $this->record($ticket, $this->body(['type' => 'folio_credit', 'folio_item_uuid' => $charge->uuid]))->assertStatus(422)
            ->assertJsonPath('error_code', 'ticket_recovery_folio_invalid')
            ->assertJsonPath('context.reason', 'not_credit');
    }

    public function test_other_stay(): void
    {
        $item   = $this->credit(Reservation::factory()->create());
        $ticket = Ticket::factory()->create(['guest_id' => Guest::factory()->create()->id]);

        $this->record($ticket, $this->body(['type' => 'folio_credit', 'folio_item_uuid' => $item->uuid]))->assertStatus(422)
            ->assertJsonPath('error_code', 'ticket_recovery_folio_invalid')
            ->assertJsonPath('context.folio_item_uuid', $item->uuid)
            ->assertJsonPath('context.reason', 'other_stay');
    }

    public function test_already_linked(): void
    {
        $reservation = Reservation::factory()->create();
        $item        = $this->credit($reservation);
        $body        = $this->body(['type' => 'folio_credit', 'folio_item_uuid' => $item->uuid]);

        $this->record(Ticket::factory()->withReservation($reservation)->create(), $body)->assertStatus(201);
        $this->record(Ticket::factory()->withReservation($reservation)->create(), $body)->assertStatus(422)
            ->assertJsonPath('error_code', 'ticket_recovery_folio_invalid')
            ->assertJsonPath('context.reason', 'already_linked');
    }

    public function test_closed_ticket_is_refused(): void
    {
        $this->record(Ticket::factory()->closed()->create(), $this->body())->assertStatus(422)
            ->assertJsonPath('error_code', 'ticket_closed');
    }

    public function test_two_step_credit_flow(): void
    {
        $reservation = Reservation::factory()->checkedIn()->create(['total_usd' => '300.00']);
        $folio       = app(GenerateFolioAction::class)->handle($reservation)['data'];
        $ticket      = Ticket::factory()->withReservation($reservation)->create();
        $poster      = $this->staff('folios.post');

        $posted = $this->withToken($poster->createToken('t')->plainTextToken)
            ->withHeaders(['Accept-Language' => 'en', 'Idempotency-Key' => 'TK-credit-1'])
            ->postJson("/api/cms/folios/{$folio->uuid}/line-items", [
                'kind' => 'credit', 'description' => 'Ticket goodwill', 'unit_price_usd' => '25.00', 'reason' => 'Broken AC',
            ])
            ->assertStatus(201);

        $itemUuid = collect($posted->json('data.items'))->firstWhere('source_type', 'credit')['uuid'];

        $this->record($ticket, [
            'type' => 'folio_credit', 'folio_item_uuid' => $itemUuid, 'description' => 'Goodwill credit for the broken AC',
        ])
            ->assertStatus(201)
            ->assertJsonPath('message', __('custom.messages.ticket_recovery_recorded'))
            ->assertJsonPath('data.actions.0.type', 'recovery')
            ->assertJsonPath('data.actions.0.recovery.type', 'folio_credit')
            ->assertJsonPath('data.actions.0.recovery.amount_usd', '25.00')
            ->assertJsonPath('data.actions.0.recovery.folio_item_uuid', $itemUuid)
            ->assertJsonPath('data.folio_credit_total_usd', '25.00');

        $this->assertSame('-25.00', (string) FolioItem::where('uuid', $itemUuid)->sole()->amount_usd);
    }

    public function test_totals_split_ledger_backed_from_recorded(): void
    {
        $reservation = Reservation::factory()->create();
        $item        = $this->credit($reservation);
        $ticket      = Ticket::factory()->withReservation($reservation)->create();

        $this->record($ticket, $this->body(['type' => 'folio_credit', 'folio_item_uuid' => $item->uuid]))->assertStatus(201);
        $this->record($ticket, $this->body(['type' => 'room_upgrade', 'amount_usd' => '40.00']))
            ->assertStatus(201)
            ->assertJsonPath('data.folio_credit_total_usd', '25.00')
            ->assertJsonPath('data.recorded_value_usd', '65.00');
    }

    public function test_no_delete_route_exists(): void
    {
        $ticket = Ticket::factory()->create();
        $this->record($ticket, $this->body())->assertStatus(201);
        $token  = $this->staff('tickets.respond', 'tickets.assign', 'tickets.view')->createToken('t')->plainTextToken;

        $this->withToken($token)->deleteJson("/api/support-tickets/{$ticket->uuid}")
            ->assertStatus(405)->assertJsonPath('error_code', 'method_not_allowed');
        $this->withToken($token)->deleteJson("/api/support-tickets/{$ticket->uuid}/recovery-actions")
            ->assertStatus(405)->assertJsonPath('error_code', 'method_not_allowed');

        $this->assertSame(1, TicketRecovery::count());
        $this->assertNotNull($ticket->fresh());
    }

    public function test_recovery_is_not_mirrored(): void
    {
        $ticket = Ticket::factory()->create();

        $this->record($ticket, $this->body())->assertStatus(201);

        $this->assertSame([], $this->firebase->mirrors);
    }
}
