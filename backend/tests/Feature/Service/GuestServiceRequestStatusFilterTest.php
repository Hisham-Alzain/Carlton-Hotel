<?php

namespace Tests\Feature\Service;

use App\Enums\ServiceRequestStatus;
use App\Models\Guest;
use App\Models\Reservation;
use App\Models\ServiceRequest;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Status filter on the guest's own request list (IT6-05).
 *
 * @group p7
 */
class GuestServiceRequestStatusFilterTest extends TestCase
{
    use RefreshDatabase;

    private Guest $guest;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->guest = Guest::factory()->create();
        $reservation = Reservation::factory()->checkedIn()->create(['guest_id' => $this->guest->id]);

        foreach (ServiceRequestStatus::cases() as $status) {
            ServiceRequest::factory()->create([
                'guest_id' => $this->guest->id,
                'reservation_id' => $reservation->id,
                'status' => $status,
            ]);
        }
    }

    private function statuses(string $query): array
    {
        $items = $this->actingAs($this->guest, 'guests')
            ->getJson('/api/service-requests'.$query)
            ->assertOk()
            ->json('data.items');

        $statuses = array_column($items, 'status');
        sort($statuses);

        return $statuses;
    }

    public function test_in_operator_returns_only_listed_statuses(): void
    {
        $this->assertSame(['in_progress', 'new'], $this->statuses('?status[in]=new,in_progress'));
    }

    public function test_repeated_array_param_is_an_in_filter(): void
    {
        $this->assertSame(['completed', 'new'], $this->statuses('?status[]=new&status[]=completed'));
    }

    public function test_shorthand_is_eq(): void
    {
        $this->assertSame(['completed'], $this->statuses('?status=completed'));
    }

    public function test_blank_status_means_no_filter(): void
    {
        $this->assertCount(4, $this->statuses('?status='));
        $this->assertCount(4, $this->statuses(''));
    }

    public function test_unknown_status_is_422_keyed_on_the_param(): void
    {
        $this->actingAs($this->guest, 'guests')
            ->getJson('/api/service-requests?status[in]=bogus')
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'validation_failed')
            ->assertJsonValidationErrors(['status.in'], 'errors');

        $this->actingAs($this->guest, 'guests')
            ->getJson('/api/service-requests?status=bogus')
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'validation_failed')
            ->assertJsonValidationErrors(['status'], 'errors');

        $this->actingAs($this->guest, 'guests')
            ->getJson('/api/service-requests?status[in]=new,bogus')
            ->assertStatus(422);
    }

    public function test_staff_board_params_are_ignored_on_the_guest_route(): void
    {
        $this->assertCount(4, $this->statuses('?assignee=not-a-uuid'));
        $this->assertCount(4, $this->statuses('?department=kitchen&room=1&guest=x&sort=created_at'));
    }

    public function test_other_guests_requests_never_appear(): void
    {
        $other = Guest::factory()->create();
        ServiceRequest::factory()->create([
            'guest_id' => $other->id,
            'reservation_id' => Reservation::factory()->checkedIn()->create(['guest_id' => $other->id])->id,
            'status' => ServiceRequestStatus::NEW,
        ]);

        $this->assertSame(['new'], $this->statuses('?status=new'));
        $this->assertCount(4, $this->statuses(''));
    }

    public function test_no_token_is_401(): void
    {
        $this->app['auth']->forgetGuards();
        $this->getJson('/api/service-requests?status=new')->assertStatus(401);
    }

    public function test_guest_not_checked_in_is_403(): void
    {
        $guest = Guest::factory()->create();

        $this->actingAs($guest, 'guests')
            ->getJson('/api/service-requests?status=new')
            ->assertStatus(403)
            ->assertJsonPath('error_code', 'no_active_reservation');
    }

    public function test_staff_board_behaviour_is_unchanged(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('service_requests.view');

        $this->actingAs($user, 'users')
            ->getJson('/api/cms/service-requests?status[in]=bogus')
            ->assertOk()
            ->assertJsonCount(0, 'data.items');
    }
}
