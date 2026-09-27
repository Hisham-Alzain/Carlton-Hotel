<?php

namespace Tests\Unit\Service;

use App\Actions\Service\UpdateServiceBookingStatusAction;
use App\Enums\ServiceBookingStatus;
use App\Exceptions\ServiceBookingTransitionException;
use App\Models\ServiceBooking;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\Models\Activity;
use Tests\Concerns\RecordsRowLocks;
use Tests\TestCase;

/** D-22: the staff writer of service-booking status and its transition table. */
class UpdateServiceBookingStatusActionTest extends TestCase
{
    use RecordsRowLocks, RefreshDatabase;

    private function move(ServiceBooking $booking, ServiceBookingStatus $to, ?User $actor = null, ?string $reason = null): array
    {
        return app(UpdateServiceBookingStatusAction::class)->handle($booking, $to, $actor, $reason);
    }

    private function booking(ServiceBookingStatus $status): ServiceBooking
    {
        return ServiceBooking::factory()->transfer()->create(['status' => $status]);
    }

    public static function allowed(): array
    {
        return [
            'pending → confirmed'   => [ServiceBookingStatus::PENDING, ServiceBookingStatus::CONFIRMED],
            'pending → cancelled'   => [ServiceBookingStatus::PENDING, ServiceBookingStatus::CANCELLED],
            'confirmed → completed' => [ServiceBookingStatus::CONFIRMED, ServiceBookingStatus::COMPLETED],
            'confirmed → cancelled' => [ServiceBookingStatus::CONFIRMED, ServiceBookingStatus::CANCELLED],
        ];
    }

    public static function disallowed(): array
    {
        return [
            'completed → confirmed' => [ServiceBookingStatus::COMPLETED, ServiceBookingStatus::CONFIRMED],
            'cancelled → pending'   => [ServiceBookingStatus::CANCELLED, ServiceBookingStatus::PENDING],
            'pending → completed'   => [ServiceBookingStatus::PENDING, ServiceBookingStatus::COMPLETED],
            'confirmed → confirmed' => [ServiceBookingStatus::CONFIRMED, ServiceBookingStatus::CONFIRMED],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('allowed')]
    public function test_allowed_transition_updates_and_logs(ServiceBookingStatus $from, ServiceBookingStatus $to): void
    {
        $booking = $this->booking($from);
        $actor   = User::factory()->create();

        $result = $this->move($booking, $to, $actor, 'guest called');

        $this->assertSame(200, $result['code']);
        $this->assertSame($to, $result['data']->status);
        $this->assertSame($to, $booking->fresh()->status);

        $entries = Activity::where('description', 'service_booking.status_changed')->get();
        $this->assertCount(1, $entries);
        $this->assertSame($booking->id, $entries[0]->subject_id);
        $this->assertSame($actor->id, $entries[0]->causer_id);
        $this->assertSame(
            ['from' => $from->value, 'to' => $to->value, 'reason' => 'guest called'],
            $entries[0]->properties->only(['from', 'to', 'reason'])->all(),
        );
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('disallowed')]
    public function test_disallowed_transition_throws_and_changes_nothing(ServiceBookingStatus $from, ServiceBookingStatus $to): void
    {
        $booking = $this->booking($from);

        try {
            $this->move($booking, $to);
            $this->fail('Expected ServiceBookingTransitionException');
        } catch (ServiceBookingTransitionException $e) {
            $this->assertSame('service_booking_transition_invalid', $e->errorCode());
            $this->assertSame(422, $e->statusCode());
            $this->assertSame([
                'from'    => $from->value,
                'to'      => $to->value,
                'allowed' => array_map(fn ($s) => $s->value, $from->allowedTargets()),
            ], $e->context());
        }

        $this->assertSame($from, $booking->fresh()->status);
        $this->assertSame(0, Activity::where('description', 'service_booking.status_changed')->count());
    }

    public function test_locks_the_booking_row(): void
    {
        $booking = $this->booking(ServiceBookingStatus::PENDING);

        $this->assertLocksRow('service_bookings', fn () => $this->move($booking, ServiceBookingStatus::CONFIRMED));
    }

    public function test_system_change_has_no_causer(): void
    {
        $booking = $this->booking(ServiceBookingStatus::PENDING);

        $this->move($booking, ServiceBookingStatus::CANCELLED);

        $this->assertNull(Activity::where('description', 'service_booking.status_changed')->value('causer_id'));
    }

    public function test_is_transactional(): void
    {
        $booking = $this->booking(ServiceBookingStatus::PENDING);

        DB::beginTransaction();
        $this->move($booking, ServiceBookingStatus::CONFIRMED);
        DB::rollBack();

        $this->assertSame(ServiceBookingStatus::PENDING, $booking->fresh()->status);
    }
}
