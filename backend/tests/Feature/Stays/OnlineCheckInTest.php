<?php

namespace Tests\Feature\Stays;

use App\Enums\CheckInApprovalStatus;
use App\Models\CheckInApproval;
use App\Models\Guest;
use App\Models\Reservation;
use App\Models\ReservationRoom;
use App\Models\RoomType;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * POST /api/stays/{reservation}/online-check-in (Phase 4, GUEST-05, D-10, D-11).
 */
class OnlineCheckInTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        config(['hotel.timezone' => 'Asia/Damascus']);
        $this->travelTo(Carbon::parse('2027-03-10 09:00:00'));
    }

    private function stay(?Guest $guest = null, array $attributes = [], string $state = 'confirmed'): Reservation
    {
        $type        = RoomType::factory()->create();
        $reservation = Reservation::factory()->{$state}()->create(array_merge([
            'guest_id'  => ($guest ?? Guest::factory()->create())->id,
            'check_in'  => '2027-03-12',
            'check_out' => '2027-03-14',
        ], $attributes));
        ReservationRoom::factory()->create(['reservation_id' => $reservation->id, 'room_type_id' => $type->id]);

        return $reservation;
    }

    private function submit(Reservation $reservation, array $body = ['arrival_time' => '18:30'], ?string $token = null)
    {
        $token ??= Guest::findOrFail($reservation->guest_id)->createToken('t')->plainTextToken;

        return $this->withToken($token)
            ->withHeaders(['Accept-Language' => 'en'])
            ->postJson("/api/stays/{$reservation->uuid}/online-check-in", $body);
    }

    public function test_owner_submits_an_arrival_time(): void
    {
        $stay = $this->stay();

        $res = $this->submit($stay)
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Online check-in submitted.')
            ->assertJsonPath('data.uuid', $stay->uuid)
            ->assertJsonPath('data.online_check_in.arrival_time', '18:30')
            ->assertJsonPath('data.online_check_in.submitted_at', '2027-03-10T09:00:00+00:00')
            ->assertJsonPath('data.online_check_in.approval_status', 'pending');

        $item = collect($res->json('data.pre_arrival_checklist.items'))->firstWhere('key', 'arrival_time_set');
        $this->assertTrue($item['done']);
        $this->assertSame('18:30', $item['arrival_time']);
        $this->assertStringContainsString('no-store', (string) $res->headers->get('Cache-Control'));

        $this->assertSame(1, CheckInApproval::where('reservation_id', $stay->id)->count());
        $this->assertSame(CheckInApprovalStatus::PENDING, CheckInApproval::first()->status);
    }

    public function test_resubmission_overwrites(): void
    {
        $stay = $this->stay();
        $this->submit($stay)->assertOk();

        $this->travel(1)->hours();
        $this->submit($stay, ['arrival_time' => '21:15'])
            ->assertOk()
            ->assertJsonPath('data.online_check_in.arrival_time', '21:15')
            ->assertJsonPath('data.online_check_in.submitted_at', '2027-03-10T10:00:00+00:00');

        $this->assertSame('2027-03-10 10:00:00', $stay->refresh()->online_check_in_submitted_at->toDateTimeString());
        $this->assertSame(1, CheckInApproval::count());
    }

    public static function decisions(): array
    {
        return ['approved' => ['approved'], 'rejected' => ['rejected'], 'pending' => ['pending']];
    }

    #[DataProvider('decisions')]
    public function test_existing_decisions_are_never_downgraded(string $status): void
    {
        $stay     = $this->stay();
        $staff    = User::factory()->create();
        $approval = CheckInApproval::factory()->create([
            'reservation_id' => $stay->id,
            'status'         => $status,
            'approved_by'    => $staff->id,
            'notes'          => 'Docs ok',
        ]);

        $this->submit($stay)->assertOk()->assertJsonPath('data.online_check_in.approval_status', $status);

        $approval->refresh();
        $this->assertSame($status, $approval->status->value);
        $this->assertSame($staff->id, $approval->approved_by);
        $this->assertSame('Docs ok', $approval->notes);
        $this->assertSame(1, CheckInApproval::count());
    }

    public function test_online_check_in_never_issues_a_key(): void
    {
        $stay = $this->stay();
        CheckInApproval::factory()->create(['reservation_id' => $stay->id, 'status' => CheckInApprovalStatus::APPROVED]);

        $this->submit($stay)->assertOk()->assertJsonPath('data.digital_key', null);

        $this->assertNull($stay->refresh()->digital_key_issued_at);
    }

    public function test_another_guests_reservation_is_forbidden(): void
    {
        $stay  = $this->stay();
        $other = Guest::factory()->create();

        $this->submit($stay, token: $other->createToken('t')->plainTextToken)
            ->assertStatus(403)
            ->assertJsonPath('error_code', 'forbidden');

        $this->assertNull($stay->refresh()->arrival_time);
        $this->assertSame(0, CheckInApproval::count());
    }

    public function test_unknown_reservation_is_404(): void
    {
        $guest = Guest::factory()->create();

        $this->withToken($guest->createToken('t')->plainTextToken)
            ->postJson('/api/stays/' . Str::uuid() . '/online-check-in', ['arrival_time' => '18:30'])
            ->assertStatus(404)
            ->assertJsonPath('error_code', 'not_found');
    }

    public function test_requires_a_guest_token(): void
    {
        $stay = $this->stay();

        $this->postJson("/api/stays/{$stay->uuid}/online-check-in", ['arrival_time' => '18:30'])
            ->assertStatus(401)->assertJsonPath('error_code', 'unauthorized');

        $staff = User::factory()->create();
        $staff->givePermissionTo('reservations.create');
        $this->submit($stay, token: $staff->createToken('t')->plainTextToken)->assertStatus(401);

        $this->assertNull($stay->refresh()->arrival_time);
    }

    public static function nonConfirmed(): array
    {
        return [
            'pending'              => ['pending'],
            'pending_verification' => ['pending_verification'],
            'checked_in'           => ['checked_in'],
            'checked_out'          => ['checked_out'],
            'cancelled'            => ['cancelled'],
        ];
    }

    #[DataProvider('nonConfirmed')]
    public function test_only_confirmed_reservations(string $value): void
    {
        $stay = $this->stay(null, ['status' => $value]);

        $this->submit($stay)
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'reservation_state')
            ->assertJsonPath('context.status', $value)
            ->assertJsonPath('context.allowed', ['confirmed']);

        $stay->refresh();
        $this->assertNull($stay->arrival_time);
        $this->assertNull($stay->online_check_in_submitted_at);
        $this->assertSame(0, CheckInApproval::count());
    }

    public function test_window_closes_after_the_check_in_date(): void
    {
        $guest = Guest::factory()->create();

        $this->submit($this->stay($guest, ['check_in' => '2027-03-10', 'check_out' => '2027-03-12']))->assertOk();

        $late = $this->stay($guest, ['check_in' => '2027-03-09', 'check_out' => '2027-03-12']);
        $this->submit($late)
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'online_check_in_closed')
            ->assertJsonPath('context.check_in', '2027-03-09')
            ->assertJsonPath('context.today', '2027-03-10');

        $this->assertNull($late->refresh()->arrival_time);
    }

    public function test_window_uses_the_hotel_local_date(): void
    {
        $this->travelTo(Carbon::parse('2027-03-09 16:30:00'));

        // Tokyo is already on 2027-03-10: a 2027-03-09 arrival is closed.
        config(['hotel.timezone' => 'Asia/Tokyo']);
        $this->submit($this->stay(null, ['check_in' => '2027-03-09', 'check_out' => '2027-03-11']))
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'online_check_in_closed')
            ->assertJsonPath('context.today', '2027-03-10');

        config(['hotel.timezone' => 'UTC']);
        $this->submit($this->stay(null, ['check_in' => '2027-03-09', 'check_out' => '2027-03-11']))->assertOk();
    }

    public function test_arrival_time_validation(): void
    {
        $stay = $this->stay();

        foreach ([[], ['arrival_time' => '6pm'], ['arrival_time' => '25:00'], ['arrival_time' => '18:30:00'], ['arrival_time' => '7:30']] as $body) {
            $this->submit($stay, $body)
                ->assertStatus(422)
                ->assertJsonPath('error_code', 'validation_failed')
                ->assertJsonValidationErrors('arrival_time');
        }
        $this->assertNull($stay->refresh()->arrival_time);

        $this->submit($stay, ['arrival_time' => '00:00'])->assertOk()->assertJsonPath('data.online_check_in.arrival_time', '00:00');
        $this->submit($stay, ['arrival_time' => '23:59'])->assertOk()->assertJsonPath('data.online_check_in.arrival_time', '23:59');
    }
}
