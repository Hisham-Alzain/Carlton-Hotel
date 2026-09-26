<?php

namespace Tests\Unit\Booking;

use App\Actions\Booking\CancelReservationAction;
use App\Actions\Booking\SubmitOnlineCheckInAction;
use App\Enums\ReservationStatus;
use App\Exceptions\OnlineCheckInClosedException;
use App\Exceptions\ReservationStateException;
use App\Models\CheckInApproval;
use App\Models\Reservation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * App\Actions\Booking\SubmitOnlineCheckInAction (Phase 4, D-10).
 */
class SubmitOnlineCheckInActionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['hotel.timezone' => 'Asia/Damascus']);
        $this->travelTo(Carbon::parse('2027-03-10 09:00:00'));
    }

    private function action(): SubmitOnlineCheckInAction
    {
        return app(SubmitOnlineCheckInAction::class);
    }

    private function stay(array $attributes = []): Reservation
    {
        return Reservation::factory()->confirmed()->create(array_merge([
            'check_in' => '2027-03-12', 'check_out' => '2027-03-14',
        ], $attributes));
    }

    public function test_returns_data_and_code(): void
    {
        $stay   = $this->stay();
        $result = $this->action()->handle($stay, '18:30');

        $this->assertSame(['data', 'code'], array_keys($result));
        $this->assertSame(200, $result['code']);
        $this->assertInstanceOf(Reservation::class, $result['data']);
        $this->assertSame($stay->id, $result['data']->id);
        $this->assertSame('18:30', substr((string) $stay->refresh()->arrival_time, 0, 5));
        $this->assertNotNull($stay->online_check_in_submitted_at);
    }

    public function test_refusals_write_nothing(): void
    {
        $wrongState = $this->stay(['status' => ReservationStatus::CHECKED_IN]);
        try {
            $this->action()->handle($wrongState, '18:30');
            $this->fail('Expected ReservationStateException');
        } catch (ReservationStateException $e) {
            $this->assertSame(['status' => 'checked_in', 'allowed' => ['confirmed']], $e->context());
        }

        $closed = $this->stay(['check_in' => '2027-03-09']);
        try {
            $this->action()->handle($closed, '18:30');
            $this->fail('Expected OnlineCheckInClosedException');
        } catch (OnlineCheckInClosedException $e) {
            $this->assertSame(['check_in' => '2027-03-09', 'today' => '2027-03-10'], $e->context());
            $this->assertSame('online_check_in_closed', $e->errorCode());
            $this->assertSame(422, $e->statusCode());
        }

        foreach ([$wrongState, $closed] as $stay) {
            $stay->refresh();
            $this->assertNull($stay->arrival_time);
            $this->assertNull($stay->online_check_in_submitted_at);
        }
        $this->assertSame(0, CheckInApproval::count());
    }

    public function test_a_stale_model_cannot_check_in_after_cancellation(): void
    {
        $stay  = $this->stay();
        $stale = Reservation::findOrFail($stay->id);

        app(CancelReservationAction::class)->handle(Reservation::findOrFail($stay->id));

        $this->assertSame(ReservationStatus::CONFIRMED, $stale->status); // still stale in memory

        $this->expectException(ReservationStateException::class);
        try {
            $this->action()->handle($stale, '18:30');
        } finally {
            $this->assertNull($stay->refresh()->arrival_time);
            $this->assertSame(0, CheckInApproval::count());
        }
    }

    public function test_first_or_create_keeps_a_single_approval(): void
    {
        $stay = $this->stay();

        $this->action()->handle($stay, '18:30');
        $this->action()->handle($stay, '19:45');

        $this->assertSame(1, CheckInApproval::where('reservation_id', $stay->id)->count());
        $this->assertSame('19:45', substr((string) $stay->refresh()->arrival_time, 0, 5));
    }
}
