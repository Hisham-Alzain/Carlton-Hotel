<?php

namespace Tests\Feature\Stays;

use App\Actions\Booking\CancelReservationAction;
use App\Actions\Booking\RevokeDigitalKeyAction;
use App\Contracts\FirebaseServiceInterface;
use App\Enums\ReservationStatus;
use App\Events\ReservationCheckedOut;
use App\Listeners\RevokeDigitalKeyOnCheckOut;
use App\Models\CheckInApproval;
use App\Models\Guest;
use App\Models\Reservation;
use App\Models\ReservationRoom;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use RuntimeException;
use Tests\Support\FakeFirebaseService;
use Tests\TestCase;

/**
 * The end of the digital key's life: check-out, cancellation, the expiry sweep
 * (Phase 4, GUEST-05, D-11, D-14).
 */
class DigitalKeyLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private int $roomNumber = 500;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        config(['hotel.timezone' => 'Asia/Damascus', 'hotel.check_out_time' => '12:00']);
        $this->travelTo(Carbon::parse('2027-03-10 09:00:00'));
        $this->app->instance(FirebaseServiceInterface::class, new FakeFirebaseService);
    }

    private function staffToken(string ...$permissions): string
    {
        $user = User::factory()->create();
        $user->givePermissionTo($permissions);
        return $user->createToken('t')->plainTextToken;
    }

    private function stay(string $status = 'confirmed', array $attributes = []): Reservation
    {
        $type        = RoomType::factory()->create();
        $reservation = Reservation::factory()->create(array_merge([
            'status'    => $status,
            'check_in'  => '2027-03-11',
            'check_out' => '2027-03-13',
        ], $attributes));
        ReservationRoom::factory()->create([
            'reservation_id' => $reservation->id,
            'room_type_id'   => $type->id,
            'room_id'        => Room::factory()->create(['room_type_id' => $type->id, 'number' => (string) ++$this->roomNumber])->id,
        ]);
        CheckInApproval::factory()->create(['reservation_id' => $reservation->id]);

        return $reservation;
    }

    /** Issue through the approve endpoint; returns the plaintext code. */
    private function issue(Reservation $reservation): ?string
    {
        $this->withToken($this->staffToken('reservations.create'))
            ->patchJson("/api/cms/check-in-approvals/{$reservation->uuid}/approve", ['status' => 'approved'])
            ->assertOk();

        return Reservation::findOrFail($reservation->id)->digital_key_code;
    }

    private function guestToken(Reservation $reservation): string
    {
        return Guest::findOrFail($reservation->guest_id)->createToken('t')->plainTextToken;
    }

    public function test_check_out_revokes_the_key(): void
    {
        $stay = $this->stay('checked_in', ['check_in' => '2027-03-09', 'checked_in_at' => '2027-03-09 12:00:00']);
        $code = $this->issue($stay);
        $this->assertNotNull($code);

        $this->withToken($this->staffToken('reservations.create', 'folios.settle'))
            ->postJson("/api/cms/reservations/{$stay->uuid}/check-out", ['force' => true, 'reason' => 'Guest left early'])
            ->assertOk();

        $stay->refresh();
        $this->assertSame(ReservationStatus::CHECKED_OUT, $stay->status);
        $this->assertSame('checked_out', $stay->digital_key_revoked_reason);
        $this->assertNull($stay->digital_key_code);
        $this->assertNull($stay->digital_key_hash);

        $body = $this->withToken($this->guestToken($stay))->getJson('/api/stays/status')->assertOk()->getContent();
        $this->assertStringNotContainsString($code, $body);
    }

    public function test_revocation_listener_is_registered_and_synchronous(): void
    {
        Event::fake();

        Event::assertListening(ReservationCheckedOut::class, RevokeDigitalKeyOnCheckOut::class);
        $this->assertNotInstanceOf(ShouldQueue::class, app(RevokeDigitalKeyOnCheckOut::class));
    }

    public function test_guest_cancellation_revokes_the_key(): void
    {
        $stay = $this->stay();
        $this->assertNotNull($this->issue($stay));

        $this->withToken($this->guestToken($stay))->deleteJson("/api/reservations/{$stay->uuid}")->assertNoContent();

        $stay->refresh();
        $this->assertSame(ReservationStatus::CANCELLED, $stay->status);
        $this->assertSame('cancelled', $stay->digital_key_revoked_reason);
        $this->assertNull($stay->digital_key_code);
        $this->assertNull($stay->digital_key_hash);
    }

    public function test_staff_cancellation_revokes_the_key(): void
    {
        $stay = $this->stay();
        $this->assertNotNull($this->issue($stay));

        $this->withToken($this->staffToken('reservations.cancel'))
            ->deleteJson("/api/cms/reservations/{$stay->uuid}")
            ->assertNoContent();

        $stay->refresh();
        $this->assertSame(ReservationStatus::CANCELLED, $stay->status);
        $this->assertSame('cancelled', $stay->digital_key_revoked_reason);
        $this->assertNull($stay->digital_key_code);
    }

    public function test_cancellation_and_revocation_are_atomic(): void
    {
        $stay = $this->stay();
        $this->assertNotNull($this->issue($stay));

        $this->mock(RevokeDigitalKeyAction::class, fn ($m) => $m->shouldReceive('handle')->andThrow(new RuntimeException('revoke failed')));

        try {
            app(CancelReservationAction::class)->handle(Reservation::findOrFail($stay->id));
            $this->fail('Expected the revocation failure to surface');
        } catch (RuntimeException $e) {
            $this->assertSame('revoke failed', $e->getMessage());
        }

        $stay->refresh();
        $this->assertSame(ReservationStatus::CONFIRMED, $stay->status);
        $this->assertNotNull($stay->digital_key_code);
        $this->assertNull($stay->digital_key_revoked_at);
    }

    public function test_cancelling_without_a_key_writes_no_revocation(): void
    {
        $stay = $this->stay();

        $this->withToken($this->guestToken($stay))->deleteJson("/api/reservations/{$stay->uuid}")->assertNoContent();

        $stay->refresh();
        $this->assertSame(ReservationStatus::CANCELLED, $stay->status);
        $this->assertNull($stay->digital_key_revoked_at);
        $this->assertNull($stay->digital_key_revoked_reason);
        $this->assertNull($stay->digital_key_issued_at);
    }

    public function test_sweep_revokes_only_expired_unrevoked_keys(): void
    {
        $expired = $this->stay();
        $active  = $this->stay();
        $revoked = $this->stay();
        foreach ([$expired, $active, $revoked] as $stay) {
            $this->assertNotNull($this->issue($stay));
        }
        $expired->forceFill(['digital_key_expires_at' => now()->subMinute()])->save();
        app(RevokeDigitalKeyAction::class)->handle($revoked, 'rejected');
        $activeCode = Reservation::findOrFail($active->id)->digital_key_code;

        $this->artisan('stays:expire-digital-keys')
            ->expectsOutputToContain('Revoked 1 expired digital key(s).')
            ->assertSuccessful();

        $expired->refresh();
        $this->assertSame('expired', $expired->digital_key_revoked_reason);
        $this->assertNull($expired->digital_key_code);

        $active->refresh();
        $this->assertNull($active->digital_key_revoked_at);
        $this->assertSame($activeCode, $active->digital_key_code);

        $this->assertSame('rejected', $revoked->refresh()->digital_key_revoked_reason);
    }

    public function test_sweep_is_scheduled_every_fifteen_minutes(): void
    {
        $events = collect(app(Schedule::class)->events())
            ->filter(fn ($event) => str_contains((string) $event->command, 'stays:expire-digital-keys'))
            ->values();

        $this->assertCount(1, $events);
        $this->assertSame('*/15 * * * *', $events[0]->expression);
        $this->assertTrue($events[0]->withoutOverlapping);
    }

    public function test_a_released_hold_never_held_a_key(): void
    {
        $hold = $this->stay('pending_verification', ['hold_expires_at' => now()->subMinute()]);

        $this->assertNull($this->issue($hold));

        $this->artisan('booking:release-holds')->assertSuccessful();

        $hold->refresh();
        $this->assertSame(ReservationStatus::CANCELLED, $hold->status);
        foreach (['digital_key_code', 'digital_key_hash', 'digital_key_issued_at', 'digital_key_expires_at', 'digital_key_revoked_at', 'digital_key_revoked_reason'] as $column) {
            $this->assertNull($hold->{$column}, $column);
        }
    }
}
