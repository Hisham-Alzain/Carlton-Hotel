<?php

namespace Tests\Feature\Payment;

use App\Contracts\PaymentGatewayInterface;
use App\Models\Reservation;
use App\Models\User;
use App\Payments\ManualDriver;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * @group p5
 */
class PaymentTest extends TestCase
{
    use RefreshDatabase;

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

        $this->assertEquals(Reservation::STATUS_CONFIRMED, $reservation->fresh()->status);
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

        $this->assertEquals(Reservation::STATUS_CONFIRMED, $reservation->fresh()->status);
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
        $this->assertEquals('pending', $reservation->fresh()->status);
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
}
