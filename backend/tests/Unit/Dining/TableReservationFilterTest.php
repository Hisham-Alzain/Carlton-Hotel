<?php

namespace Tests\Unit\Dining;

use App\Enums\BookableType;
use App\Filters\TableReservationFilter;
use App\Models\RestaurantTable;
use App\Models\ServiceBooking;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * The hotel-local window maths across a DST change (PR-5: Europe/London, the
 * October fall-back day is 25 hours long) and the 31-day range cap.
 */
class TableReservationFilterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['hotel.timezone' => 'Europe/London']);
    }

    private function sql(array $params): array
    {
        $query = ServiceBooking::query();
        (new TableReservationFilter($params))->apply($query);

        return $query->getBindings();
    }

    public function test_fall_back_day_is_a_25_hour_window(): void
    {
        $bindings = $this->sql(['date' => '2026-10-25']);

        $this->assertContains('2026-10-24 23:00:00', $bindings);
        $this->assertContains('2026-10-26 00:00:00', $bindings);
    }

    public function test_a_late_booking_on_the_fall_back_day_is_included(): void
    {
        $table   = RestaurantTable::factory()->create(['dining_venue_id' => \App\Models\DiningVenue::factory()->create()->id]);
        $booking = ServiceBooking::factory()->create([
            'bookable_type' => BookableType::RESTAURANT_TABLE->value,
            'bookable_id'   => $table->id,
            'scheduled_at'  => CarbonImmutable::parse('2026-10-25 23:30:00', 'UTC'), // 23:30 GMT local
        ]);

        $query = ServiceBooking::query();
        (new TableReservationFilter(['date' => '2026-10-25']))->apply($query);

        $this->assertSame([$booking->id], $query->pluck('id')->all());
    }

    public function test_31_days_accepted_32_rejected(): void
    {
        $bindings = $this->sql(['from' => '2026-10-01', 'to' => '2026-10-31']);
        $this->assertContains('2026-09-30 23:00:00', $bindings);
        $this->assertContains('2026-11-01 00:00:00', $bindings);

        $this->expectException(ValidationException::class);
        $this->sql(['from' => '2026-10-01', 'to' => '2026-11-01']);
    }

    public function test_no_window_param_defaults_to_the_hotel_today(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-07-01 23:30:00', 'UTC')); // 00:30 BST on 2 July

        $bindings = $this->sql([]);

        $this->assertContains('2026-07-01 23:00:00', $bindings);
        $this->assertContains('2026-07-02 23:00:00', $bindings);
    }
}
