<?php

namespace Tests\Unit\Booking;

use App\Actions\Booking\IssueDigitalKeyAction;
use App\Actions\Booking\RevokeDigitalKeyAction;
use App\Enums\ReservationStatus;
use App\Models\Reservation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;
use ValueError;

/**
 * App\Actions\Booking\RevokeDigitalKeyAction (Phase 4, D-11).
 */
class RevokeDigitalKeyActionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['hotel.timezone' => 'Asia/Damascus', 'hotel.check_out_time' => '12:00']);
        $this->travelTo(Carbon::parse('2027-03-10 09:00:00'));
    }

    private function action(): RevokeDigitalKeyAction
    {
        return app(RevokeDigitalKeyAction::class);
    }

    private function keyedStay(): Reservation
    {
        $stay = Reservation::factory()->create([
            'status' => ReservationStatus::CONFIRMED, 'check_in' => '2027-03-11', 'check_out' => '2027-03-12',
        ]);
        app(IssueDigitalKeyAction::class)->handle($stay);

        return $stay->refresh();
    }

    public function test_revocation_nulls_code_and_hash_and_records_the_reason(): void
    {
        $stay = $this->keyedStay();
        $this->assertNotNull($stay->digital_key_code);

        $this->action()->handle($stay, 'checked_out');

        $stay->refresh();
        $this->assertNull($stay->digital_key_code);
        $this->assertNull($stay->digital_key_hash);
        $this->assertSame('2027-03-10 09:00:00', $stay->digital_key_revoked_at->toDateTimeString());
        $this->assertSame('checked_out', $stay->digital_key_revoked_reason);
        $this->assertNotNull($stay->digital_key_issued_at);
        $this->assertFalse($stay->hasActiveDigitalKey());
    }

    public function test_noop_without_an_unrevoked_key(): void
    {
        $never = Reservation::factory()->confirmed()->create();
        $this->assertSame(200, $this->action()->handle($never, 'cancelled')['code']);
        $never->refresh();
        $this->assertNull($never->digital_key_revoked_at);
        $this->assertNull($never->digital_key_revoked_reason);

        $revoked = $this->keyedStay();
        $this->action()->handle($revoked, 'rejected');
        $at = $revoked->refresh()->digital_key_revoked_at;

        $this->travel(1)->hours();
        $this->action()->handle($revoked, 'cancelled');

        $revoked->refresh();
        $this->assertSame('rejected', $revoked->digital_key_revoked_reason);
        $this->assertTrue($at->equalTo($revoked->digital_key_revoked_at));
    }

    public function test_revokes_an_expired_unrevoked_key(): void
    {
        $stay = $this->keyedStay();
        $stay->forceFill(['digital_key_expires_at' => now()->subMinute()])->save();

        $this->action()->handle($stay, 'expired');

        $stay->refresh();
        $this->assertSame('expired', $stay->digital_key_revoked_reason);
        $this->assertNull($stay->digital_key_code);
    }

    public function test_unknown_reason_is_rejected(): void
    {
        $stay = $this->keyedStay();

        try {
            $this->action()->handle($stay, 'lost');
            $this->fail('Expected ValueError');
        } catch (ValueError) {
            $this->assertNotNull($stay->refresh()->digital_key_code);
            $this->assertNull($stay->digital_key_revoked_at);
        }
    }

    public function test_returns_data_and_code(): void
    {
        $stay   = $this->keyedStay();
        $result = $this->action()->handle($stay, 'cancelled');

        $this->assertSame(['data', 'code'], array_keys($result));
        $this->assertSame(200, $result['code']);
        $this->assertInstanceOf(Reservation::class, $result['data']);
        $this->assertSame($stay->id, $result['data']->id);
    }
}
