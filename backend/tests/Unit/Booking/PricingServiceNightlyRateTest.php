<?php

namespace Tests\Unit\Booking;

use App\Actions\Booking\QuoteReservationAction;
use App\Enums\ModifierType;
use App\Enums\PricingScope;
use App\Models\PricingRule;
use App\Models\RoomType;
use App\Services\Booking\PricingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * PricingService::nightlyRate — the deliberate clone of QuoteReservationAction's
 * rule loop (Phase 2, D-10). For a one-night stay it must equal the quote.
 */
class PricingServiceNightlyRateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2027-03-10 09:00:00'));
    }

    private function rule(RoomType $type, PricingScope $scope, ModifierType $modifier, float $value, string $from, string $to, bool $active = true): PricingRule
    {
        return PricingRule::factory()->create([
            'room_type_id'   => $type->id,
            'scope'          => $scope,
            'modifier_type'  => $modifier,
            'modifier_value' => $value,
            'starts_on'      => $from,
            'ends_on'        => $to,
            'is_active'      => $active,
        ]);
    }

    private function rate(RoomType $type, string $date): array
    {
        return app(PricingService::class)->nightlyRate(
            $type,
            Carbon::parse($date),
            PricingRule::where('room_type_id', $type->id)->get(),
        );
    }

    private function quote(RoomType $type, string $date): float
    {
        $next = Carbon::parse($date)->addDay()->toDateString();

        return (float) app(QuoteReservationAction::class)->handle($type, $date, $next)['daily_rate_usd'];
    }

    public function test_no_rules_returns_base_price_and_null_scope(): void
    {
        $type = RoomType::factory()->create(['base_price_usd' => 150.00]);

        $this->assertSame(['rate_usd' => 150.0, 'rule_scope' => null], $this->rate($type, '2027-03-13'));
        $this->assertSame($this->quote($type, '2027-03-13'), $this->rate($type, '2027-03-13')['rate_usd']);
    }

    public function test_percentage_then_fixed_in_insertion_order_matches_the_quote(): void
    {
        $type = RoomType::factory()->create(['base_price_usd' => 100.00]);
        $this->rule($type, PricingScope::SEASONAL, ModifierType::PERCENTAGE, 10, '2027-03-12', '2027-03-14');
        $this->rule($type, PricingScope::HOLIDAY, ModifierType::FLAT, 20, '2027-03-12', '2027-03-14');

        $rate = $this->rate($type, '2027-03-13');

        $this->assertSame(130.0, $rate['rate_usd']);
        $this->assertSame('holiday', $rate['rule_scope']);
        $this->assertSame($this->quote($type, '2027-03-13'), $rate['rate_usd']);
    }

    public function test_fixed_then_percentage_in_insertion_order_matches_the_quote(): void
    {
        $type = RoomType::factory()->create(['base_price_usd' => 100.00]);
        $this->rule($type, PricingScope::WEEKEND, ModifierType::FLAT, 20, '2027-03-12', '2027-03-14');
        $this->rule($type, PricingScope::SEASONAL, ModifierType::PERCENTAGE, 10, '2027-03-12', '2027-03-14');

        $rate = $this->rate($type, '2027-03-13');

        $this->assertSame(132.0, $rate['rate_usd']);
        $this->assertSame('seasonal', $rate['rule_scope']);
        $this->assertSame($this->quote($type, '2027-03-13'), $rate['rate_usd']);
    }

    public function test_rule_applies_on_its_first_and_last_day_only(): void
    {
        $type = RoomType::factory()->create(['base_price_usd' => 100.00]);
        $this->rule($type, PricingScope::SEASONAL, ModifierType::PERCENTAGE, 10, '2027-03-12', '2027-03-14');

        $this->assertSame(['rate_usd' => 100.0, 'rule_scope' => null], $this->rate($type, '2027-03-11'));
        $this->assertSame(['rate_usd' => 110.0, 'rule_scope' => 'seasonal'], $this->rate($type, '2027-03-12'));
        $this->assertSame(['rate_usd' => 110.0, 'rule_scope' => 'seasonal'], $this->rate($type, '2027-03-13'));
        $this->assertSame(['rate_usd' => 110.0, 'rule_scope' => 'seasonal'], $this->rate($type, '2027-03-14'));
        $this->assertSame(['rate_usd' => 100.0, 'rule_scope' => null], $this->rate($type, '2027-03-15'));

        foreach (['2027-03-11', '2027-03-12', '2027-03-14', '2027-03-15'] as $date) {
            $this->assertSame($this->quote($type, $date), $this->rate($type, $date)['rate_usd'], $date);
        }
    }

    public function test_inactive_and_other_room_type_rules_are_ignored(): void
    {
        $type  = RoomType::factory()->create(['base_price_usd' => 100.00]);
        $other = RoomType::factory()->create(['base_price_usd' => 100.00]);
        $this->rule($type, PricingScope::SEASONAL, ModifierType::PERCENTAGE, 50, '2027-03-12', '2027-03-14', active: false);
        $this->rule($other, PricingScope::HOLIDAY, ModifierType::FLAT, 40, '2027-03-12', '2027-03-14');

        // Hand every rule in the database to the type: the method itself must filter.
        $rate = app(PricingService::class)->nightlyRate($type, Carbon::parse('2027-03-13'), PricingRule::all());

        $this->assertSame(['rate_usd' => 100.0, 'rule_scope' => null], $rate);
        $this->assertSame($this->quote($type, '2027-03-13'), $rate['rate_usd']);
    }

    public function test_rounding_matches_the_quote(): void
    {
        $type = RoomType::factory()->create(['base_price_usd' => 99.99]);
        $this->rule($type, PricingScope::SEASONAL, ModifierType::PERCENTAGE, 7.5, '2027-03-12', '2027-03-14');

        $rate = $this->rate($type, '2027-03-13');

        $this->assertSame(107.49, $rate['rate_usd']);
        $this->assertSame($this->quote($type, '2027-03-13'), $rate['rate_usd']);
    }
}
