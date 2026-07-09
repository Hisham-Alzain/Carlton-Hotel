<?php

namespace Tests\Feature\Booking;

use App\Models\PricingRule;
use App\Models\PromoCode;
use App\Models\ReservationRoom;
use App\Models\RoomType;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PricingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_base_price_returned_when_no_rules(): void
    {
        $rt = RoomType::factory()->create(['base_price_usd' => 100]);
        $this->getJson("/api/public/quote?room_type_uuid={$rt->uuid}&check_in=2027-01-01&check_out=2027-01-03")
            ->assertOk()
            ->assertJson(['data' => ['daily_rate_usd' => 100, 'nights' => 2, 'subtotal_usd' => 200, 'total_usd' => 200]]);
    }

    public function test_seasonal_pricing_rule_applied(): void
    {
        $rt = RoomType::factory()->create(['base_price_usd' => 100]);
        PricingRule::factory()->create([
            'room_type_id'   => $rt->id,
            'scope'          => PricingRule::SCOPE_SEASONAL,
            'starts_on'      => '2026-12-01',
            'ends_on'        => '2027-02-01',
            'modifier_type'  => PricingRule::TYPE_PERCENTAGE,
            'modifier_value' => 20, // +20%
        ]);

        $this->getJson("/api/public/quote?room_type_uuid={$rt->uuid}&check_in=2027-01-01&check_out=2027-01-03")
            ->assertOk()
            ->assertJson(['data' => ['daily_rate_usd' => 120, 'subtotal_usd' => 240, 'total_usd' => 240]]);
    }

    public function test_promo_code_reduces_total(): void
    {
        $rt    = RoomType::factory()->create(['base_price_usd' => 100]);
        $promo = PromoCode::factory()->create(['code' => 'SAVE10', 'type' => PromoCode::TYPE_PERCENTAGE, 'value' => 10]);

        $this->getJson("/api/public/quote?room_type_uuid={$rt->uuid}&check_in=2027-01-01&check_out=2027-01-03&promo_code=SAVE10")
            ->assertOk()
            ->assertJson(['data' => ['subtotal_usd' => 200, 'discount_usd' => 20, 'total_usd' => 180]]);
    }

    public function test_invalid_promo_returns_422(): void
    {
        $rt = RoomType::factory()->create(['base_price_usd' => 100]);
        $this->getJson("/api/public/quote?room_type_uuid={$rt->uuid}&check_in=2027-01-01&check_out=2027-01-03&promo_code=NOPE")
            ->assertStatus(422)->assertJsonPath('error_code', 'invalid_promo');
    }

    public function test_price_snapshot_unchanged_after_base_price_update(): void
    {
        $rt   = RoomType::factory()->create(['base_price_usd' => 100]);
        $room = \App\Models\Room::factory()->create(['room_type_id' => $rt->id, 'is_active' => true]);
        $res  = \App\Models\Reservation::factory()->create([
            'check_in' => '2027-01-01', 'check_out' => '2027-01-03',
        ]);
        ReservationRoom::factory()->create([
            'reservation_id' => $res->id,
            'room_type_id'   => $rt->id,
            'price_usd'      => 200.00, // snapshotted total
        ]);

        // Change base price — snapshot must not change
        $rt->update(['base_price_usd' => 999]);

        $this->assertEquals('200.00', ReservationRoom::first()->price_usd);
    }
}
