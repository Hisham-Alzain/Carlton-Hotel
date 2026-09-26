<?php

namespace Tests\Unit\Booking;

use App\Actions\Booking\IssueDigitalKeyAction;
use App\Enums\ReservationStatus;
use App\Models\Reservation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * App\Actions\Booking\IssueDigitalKeyAction (Phase 4, D-11).
 */
class IssueDigitalKeyActionTest extends TestCase
{
    use RefreshDatabase;

    private const KEY_PATTERN = '/^[ABCDEFGHJKLMNPQRSTUVWXYZ23456789]{4}-[ABCDEFGHJKLMNPQRSTUVWXYZ23456789]{4}-[ABCDEFGHJKLMNPQRSTUVWXYZ23456789]{4}$/';

    protected function setUp(): void
    {
        parent::setUp();
        config(['hotel.timezone' => 'Asia/Damascus', 'hotel.check_out_time' => '12:00']);
        $this->travelTo(Carbon::parse('2027-03-10 09:00:00'));
    }

    private function action(): IssueDigitalKeyAction
    {
        return app(IssueDigitalKeyAction::class);
    }

    private function stay(ReservationStatus $status = ReservationStatus::CONFIRMED): Reservation
    {
        return Reservation::factory()->create([
            'status' => $status, 'check_in' => '2027-03-11', 'check_out' => '2027-03-12',
        ]);
    }

    public function test_generated_codes_use_the_alphabet_and_are_unique(): void
    {
        $codes = [];
        for ($i = 0; $i < 500; $i++) {
            $codes[] = $code = $this->action()->generateCode();
            $this->assertSame(14, strlen($code));
            $this->assertMatchesRegularExpression(self::KEY_PATTERN, $code);
            // D-11's locked alphabet drops the look-alikes 0/O and 1/I (it keeps L).
            $this->assertDoesNotMatchRegularExpression('/[0O1I]/', $code);
        }

        $this->assertCount(500, array_unique($codes));
        $this->assertSame('ABCDEFGHJKLMNPQRSTUVWXYZ23456789', IssueDigitalKeyAction::ALPHABET);
        $this->assertSame(32, strlen(count_chars(IssueDigitalKeyAction::ALPHABET, 3)));
    }

    public function test_mints_with_201_and_keeps_with_200(): void
    {
        $stay = $this->stay();

        $first = $this->action()->handle($stay);
        $this->assertSame(['data', 'code'], array_keys($first));
        $this->assertSame(201, $first['code']);
        $code = $first['data']->digital_key_code;
        $this->assertMatchesRegularExpression(self::KEY_PATTERN, $code);
        $this->assertSame('2027-03-12T09:00:00+00:00', $first['data']->digital_key_expires_at->toIso8601String());

        $second = $this->action()->handle($stay->refresh());
        $this->assertSame(200, $second['code']);
        $this->assertSame($code, $second['data']->digital_key_code);
    }

    public function test_does_nothing_for_non_holding_states(): void
    {
        foreach ([ReservationStatus::CANCELLED, ReservationStatus::CHECKED_OUT, ReservationStatus::PENDING, ReservationStatus::PENDING_VERIFICATION] as $status) {
            $stay   = $this->stay($status);
            $result = $this->action()->handle($stay);

            $this->assertSame(200, $result['code'], $status->value);
            $this->assertNull($stay->refresh()->digital_key_issued_at, $status->value);
            $this->assertNull($stay->digital_key_code, $status->value);
        }
    }

    public function test_replaces_an_expired_unrevoked_key(): void
    {
        $stay = $this->stay();
        $old  = $this->action()->handle($stay)['data']->digital_key_code;
        $stay->forceFill(['digital_key_expires_at' => now()->subMinute()])->save();

        $result = $this->action()->handle($stay->refresh());

        $this->assertSame(201, $result['code']);
        $this->assertNotSame($old, $result['data']->digital_key_code);
        $this->assertTrue($stay->refresh()->hasActiveDigitalKey());
    }

    public function test_stale_model_does_not_mint_twice(): void
    {
        $stay  = $this->stay();
        $stale = Reservation::findOrFail($stay->id);

        $minted = $this->action()->handle(Reservation::findOrFail($stay->id));
        $this->assertSame(201, $minted['code']);
        $this->assertNull($stale->digital_key_issued_at); // stale in memory

        $again = $this->action()->handle($stale);
        $this->assertSame(200, $again['code']);
        $this->assertSame($minted['data']->digital_key_code, $again['data']->digital_key_code);
        $this->assertSame($minted['data']->digital_key_code, $stay->refresh()->digital_key_code);
    }
}
