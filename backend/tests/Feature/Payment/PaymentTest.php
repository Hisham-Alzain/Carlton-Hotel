<?php

namespace Tests\Feature\Payment;

use App\Contracts\PaymentGatewayInterface;
use App\Enums\FolioStatus;
use App\Enums\ReservationStatus;
use App\Models\Folio;
use App\Models\Payment;
use App\Models\Reservation;
use App\Models\User;
use App\Payments\ManualDriver;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\RecordsRowLocks;
use Tests\TestCase;

/**
 * @group p5
 */
class PaymentTest extends TestCase
{
    use RefreshDatabase, RecordsRowLocks;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function makeReception(): User
    {
        $user = User::factory()->create();
        $user->givePermissionTo('folios.settle');
        return $user;
    }

    private function makeReservation(string $status = 'pending'): Reservation
    {
        return Reservation::factory()->create(['status' => $status]);
    }

    public function test_cash_settlement_creates_payment_and_records_actor(): void
    {
        $user        = $this->makeReception();
        $reservation = $this->makeReservation('pending');

        $response = $this->actingAs($user, 'users')
            ->postJson("/api/cms/reservations/{$reservation->uuid}/settle", [
                'method'     => 'cash',
                'amount_usd' => 150.00,
            ]);

        $response->assertOk()
                 ->assertJsonStructure(['data' => ['uuid', 'method', 'amount_usd', 'status', 'recorded_by', 'created_at']]);

        $this->assertDatabaseHas('payments', [
            'payable_type' => Reservation::class,
            'payable_id'   => $reservation->id,
            'method'       => 'cash',
            'status'       => 'completed',
            'recorded_by'  => $user->id,
        ]);
    }

    public function test_cash_settlement_transitions_pending_reservation_to_confirmed(): void
    {
        $user        = $this->makeReception();
        $reservation = $this->makeReservation('pending');

        $this->actingAs($user, 'users')
             ->postJson("/api/cms/reservations/{$reservation->uuid}/settle", [
                 'method'     => 'cash',
                 'amount_usd' => 100.00,
             ])
             ->assertOk();

        $this->assertEquals(ReservationStatus::CONFIRMED, $reservation->fresh()->status);
    }

    public function test_on_arrival_path_records_payment(): void
    {
        $user        = $this->makeReception();
        $reservation = $this->makeReservation('confirmed');

        $this->actingAs($user, 'users')
             ->postJson("/api/cms/reservations/{$reservation->uuid}/settle", [
                 'method'     => 'on_arrival',
                 'amount_usd' => 200.00,
                 'note'       => 'Guest paid on check-in',
             ])
             ->assertOk();

        $this->assertDatabaseHas('payments', [
            'method' => 'on_arrival',
            'note'   => 'Guest paid on check-in',
            'status' => 'completed',
        ]);
    }

    public function test_confirmed_reservation_is_not_downgraded_on_payment(): void
    {
        $user        = $this->makeReception();
        $reservation = $this->makeReservation('confirmed');

        $this->actingAs($user, 'users')
             ->postJson("/api/cms/reservations/{$reservation->uuid}/settle", [
                 'method'     => 'cash',
                 'amount_usd' => 100.00,
             ])
             ->assertOk();

        $this->assertEquals(ReservationStatus::CONFIRMED, $reservation->fresh()->status);
    }

    public function test_permission_gate_blocks_unpermitted_user(): void
    {
        $user        = User::factory()->create();
        $reservation = $this->makeReservation('pending');

        $this->actingAs($user, 'users')
             ->postJson("/api/cms/reservations/{$reservation->uuid}/settle", [
                 'method'     => 'cash',
                 'amount_usd' => 100.00,
             ])
             ->assertForbidden();
    }

    public function test_interface_resolves_manual_driver(): void
    {
        $driver = app(PaymentGatewayInterface::class);
        $this->assertInstanceOf(ManualDriver::class, $driver);

        $result = $driver->charge('cash', 100.0);
        $this->assertEquals('completed', $result['status']);
        $this->assertNull($result['reference']);
    }

    public function test_failed_gateway_response_throws_payment_failed_exception(): void
    {
        $this->app->bind(PaymentGatewayInterface::class, fn () => new class implements PaymentGatewayInterface {
            public function charge(string $method, float $amount, array $context = []): array
            {
                return ['reference' => null, 'status' => 'failed'];
            }
        });

        $user        = $this->makeReception();
        $reservation = $this->makeReservation('pending');

        $this->actingAs($user, 'users')
             ->postJson("/api/cms/reservations/{$reservation->uuid}/settle", [
                 'method'     => 'cash',
                 'amount_usd' => 100.00,
             ])
             ->assertStatus(422)
             ->assertJson(['success' => false, 'error_code' => 'payment_failed']);

        $this->assertDatabaseMissing('payments', ['payable_id' => $reservation->id]);
        $this->assertEquals(ReservationStatus::PENDING, $reservation->fresh()->status);
    }

    public function test_validation_rejects_invalid_method(): void
    {
        $user        = $this->makeReception();
        $reservation = $this->makeReservation('pending');

        $this->actingAs($user, 'users')
             ->postJson("/api/cms/reservations/{$reservation->uuid}/settle", [
                 'method'     => 'credit_card',
                 'amount_usd' => 100.00,
             ])
             ->assertStatus(422);
    }

    public function test_validation_rejects_zero_amount(): void
    {
        $user        = $this->makeReception();
        $reservation = $this->makeReservation('pending');

        $this->actingAs($user, 'users')
             ->postJson("/api/cms/reservations/{$reservation->uuid}/settle", [
                 'method'     => 'cash',
                 'amount_usd' => 0,
             ])
             ->assertStatus(422);
    }

    public function test_unauthenticated_request_is_rejected(): void
    {
        $reservation = $this->makeReservation('pending');

        $this->postJson("/api/cms/reservations/{$reservation->uuid}/settle", [
            'method'     => 'cash',
            'amount_usd' => 100.00,
        ])->assertUnauthorized();
    }

    // ── Phase 5 (D-13, D-14, D-15): legacy reservation settle ───────────

    public function test_amount_is_stored_as_an_exact_decimal(): void
    {
        $user        = $this->makeReception();
        $reservation = $this->makeReservation('confirmed');

        $this->actingAs($user, 'users')
             ->postJson("/api/cms/reservations/{$reservation->uuid}/settle", ['method' => 'cash', 'amount_usd' => 150.5])
             ->assertOk()
             ->assertJsonPath('data.amount_usd', '150.50');

        $this->assertSame('150.50', Payment::sole()->amount_usd);
    }

    public function test_reservation_settle_is_refused_once_the_folio_is_settled(): void
    {
        $user        = $this->makeReception();
        $reservation = $this->makeReservation('checked_in');
        $folio       = Folio::factory()->create([
            'reservation_id' => $reservation->id, 'status' => FolioStatus::SETTLED, 'settled_at' => now(),
        ]);

        $this->actingAs($user, 'users')
             ->postJson("/api/cms/reservations/{$reservation->uuid}/settle", ['method' => 'cash', 'amount_usd' => 10])
             ->assertStatus(422)
             ->assertJsonPath('success', false)
             ->assertJsonPath('error_code', 'folio_settled')
             ->assertJsonPath('context.folio_uuid', $folio->uuid);

        $this->assertSame(0, Payment::count());
    }

    public function test_reservation_settle_still_works_with_an_open_folio_or_none(): void
    {
        $user = $this->makeReception();
        $open = $this->makeReservation('checked_in');
        Folio::factory()->create(['reservation_id' => $open->id]);
        $none = $this->makeReservation('confirmed');

        $this->actingAs($user, 'users')
             ->postJson("/api/cms/reservations/{$open->uuid}/settle", ['method' => 'cash', 'amount_usd' => 10])
             ->assertOk();
        $this->actingAs($user, 'users')
             ->postJson("/api/cms/reservations/{$none->uuid}/settle", ['method' => 'cash', 'amount_usd' => 10])
             ->assertOk();

        $this->assertSame(2, Payment::count());
    }

    public function test_reservation_settle_locks_the_reservation_then_the_folio(): void
    {
        $user        = $this->makeReception();
        $reservation = $this->makeReservation('checked_in');
        Folio::factory()->create(['reservation_id' => $reservation->id]);

        $locked = $this->lockedSelects(fn () => $this->actingAs($user, 'users')
            ->postJson("/api/cms/reservations/{$reservation->uuid}/settle", ['method' => 'cash', 'amount_usd' => 10])
            ->assertOk());

        $tables = array_map(fn (string $sql) => preg_match('/from "(\w+)"/', $sql, $m) ? $m[1] : null, $locked);
        $this->assertSame(['reservations', 'folios'], array_values(array_unique($tables)));
    }

    /**
     * The legacy request still validates `numeric`, so any numeric the rule
     * accepts must be answered with a 200 or a 422, never a 500, and never
     * recorded as a silently truncated amount (Phase 5 moved this path to
     * bcmath; behaviour was to stay unchanged otherwise).
     */
    public function test_numeric_amounts_the_rule_accepts_never_crash_or_truncate(): void
    {
        $user        = $this->makeReception();
        $reservation = $this->makeReservation('confirmed');

        foreach (['1e3', '10.555'] as $amount) {
            $response = $this->actingAs($user, 'users')
                ->postJson("/api/cms/reservations/{$reservation->uuid}/settle", ['method' => 'cash', 'amount_usd' => $amount]);

            $this->assertContains($response->status(), [200, 422], "amount {$amount} answered {$response->status()}");
            if ($response->status() === 200) {
                $this->assertNotSame('10.55', $response->json('data.amount_usd'), '10.555 must not be truncated to 10.55');
            }
        }
    }
}
