<?php

namespace Tests\Unit\Booking;

use App\Models\Reservation;
use App\Support\HotelClock;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * The hotel-local business date (Phase 3, D-02) and the stay-window predicate.
 */
class HotelClockTest extends TestCase
{
    use RefreshDatabase;

    public function test_today_uses_the_hotel_timezone(): void
    {
        $this->travelTo(Carbon::parse('2027-03-09 16:30:00', 'UTC'));

        config(['hotel.timezone' => 'Asia/Tokyo']);
        $this->assertSame('Asia/Tokyo', HotelClock::timezone());
        $this->assertSame('2027-03-10', HotelClock::today()->toDateString());
        $this->assertSame('00:00:00', HotelClock::today()->format('H:i:s'));

        config(['hotel.timezone' => 'UTC']);
        $this->assertSame('2027-03-09', HotelClock::today()->toDateString());
    }

    public function test_config_and_env_example_name_the_timezone(): void
    {
        $this->assertContains(config('hotel.timezone'), timezone_identifiers_list());
        $this->assertStringContainsString(
            'HOTEL_TIMEZONE=Asia/Damascus',
            file_get_contents(base_path('.env.example')),
        );
        // Storage stays UTC; only business-date decisions use the hotel zone.
        $this->assertSame('UTC', config('app.timezone'));
    }

    public function test_stay_window_boundaries(): void
    {
        $reservation = Reservation::factory()->confirmed()->create([
            'check_in'  => '2027-03-10',
            'check_out' => '2027-03-12',
        ]);

        $at = fn (string $date) => $reservation->isWithinStayWindow(CarbonImmutable::parse($date));

        $this->assertFalse($at('2027-03-09'), 'day before arrival');
        $this->assertTrue($at('2027-03-10'), 'arrival day');
        $this->assertTrue($at('2027-03-11'), 'last night');
        $this->assertFalse($at('2027-03-12'), 'departure day');
        $this->assertFalse($at('2027-03-13'), 'after departure');
    }
}
