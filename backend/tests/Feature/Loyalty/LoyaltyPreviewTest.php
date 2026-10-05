<?php

namespace Tests\Feature\Loyalty;

use App\Actions\Booking\QuoteReservationAction;
use App\Enums\LoyaltyRewardType;
use App\Enums\ModifierType;
use App\Models\Guest;
use App\Models\LoyaltyVoucher;
use App\Models\PromoCode;
use App\Models\Reservation;
use App\Models\RoomType;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\BuildsLoyaltyFixtures;
use Tests\TestCase;

/**
 * Phase 10 LOY-15 / LOY-16 / LOY-22 (Q5, Q10, Q12, Q25, M-7): the guest
 * previews what a booking costs with points or a voucher, without writing
 * anything.
 */
class LoyaltyPreviewTest extends TestCase
{
    use BuildsLoyaltyFixtures;
    use RefreshDatabase;

    private const KEYS = [
        'quote' => ['nights', 'daily_rate_usd', 'subtotal_usd', 'promo_discount_usd', 'total_usd'],
        'loyalty' => ['points_redeemed', 'points_discount_usd', 'voucher', 'voucher_discount_usd', 'upgrade_requested'],
    ];

    private Guest $guest;

    private RoomType $roomType;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->configureLoyalty([
            'redeem_value_usd' => '0.0100',
            'max_redeem_percent' => '50.00',
            'min_redeem_points' => 100,
            'earn_rate' => '1.0000',
        ]);
        $this->guest = Guest::factory()->create();
        $this->roomType = RoomType::factory()->create(['base_price_usd' => 100]);
    }

    private function preview(array $extra = [], ?string $token = null): TestResponse
    {
        $query = array_merge([
            'room_type_uuid' => $this->roomType->uuid,
            'check_in' => now()->addDays(10)->toDateString(),
            'check_out' => now()->addDays(13)->toDateString(),
        ], $extra);

        $this->app['auth']->forgetGuards();

        return $this->withToken($token ?? $this->guestToken($this->guest))
            ->getJson('/api/loyalty/preview?'.http_build_query($query));
    }

    public function test_a_guest_previews_a_points_redemption_with_every_contract_key(): void
    {
        $this->grantPoints($this->guest, 20000);

        $response = $this->preview(['loyalty_points' => 10000])->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => [
                'quote' => self::KEYS['quote'],
                'loyalty' => self::KEYS['loyalty'],
                'net_total_usd', 'available_points', 'max_points', 'points_earnable_estimate', 'program',
            ]]);

        $quote = app(QuoteReservationAction::class)->handle(
            $this->roomType,
            now()->addDays(10)->toDateString(),
            now()->addDays(13)->toDateString(),
        );

        $response
            ->assertJsonPath('data.quote.nights', 3)
            ->assertJsonPath('data.quote.daily_rate_usd', '100.00')
            ->assertJsonPath('data.quote.subtotal_usd', '300.00')
            ->assertJsonPath('data.quote.promo_discount_usd', '0.00')
            ->assertJsonPath('data.quote.total_usd', number_format($quote['total_usd'], 2, '.', ''))
            ->assertJsonPath('data.loyalty.points_redeemed', 10000)
            ->assertJsonPath('data.loyalty.points_discount_usd', '100.00')
            ->assertJsonPath('data.loyalty.voucher', null)
            ->assertJsonPath('data.loyalty.voucher_discount_usd', '0.00')
            ->assertJsonPath('data.loyalty.upgrade_requested', false)
            ->assertJsonPath('data.net_total_usd', '200.00')
            ->assertJsonPath('data.available_points', 20000)
            ->assertJsonPath('data.max_points', 15000)
            ->assertJsonPath('data.points_earnable_estimate', 200)
            ->assertJsonPath('data.program.points_discount', true);
    }

    public function test_a_guest_previews_a_voucher_without_leaking_ids(): void
    {
        $voucher = LoyaltyVoucher::factory()->create(['guest_id' => $this->guest->id, 'value_usd' => '40.00']);

        $response = $this->preview(['voucher_code' => $voucher->code])->assertOk()
            ->assertJsonPath('data.loyalty.voucher', ['code' => $voucher->code, 'type' => 'discount_voucher'])
            ->assertJsonPath('data.loyalty.voucher_discount_usd', '40.00')
            ->assertJsonPath('data.loyalty.points_redeemed', 0)
            ->assertJsonPath('data.net_total_usd', '260.00');

        $this->assertStringNotContainsString($voucher->uuid, $response->getContent());
    }

    public function test_a_room_upgrade_voucher_is_previewed_as_an_upgrade_request(): void
    {
        $voucher = LoyaltyVoucher::factory()->create([
            'guest_id' => $this->guest->id,
            'type' => LoyaltyRewardType::ROOM_UPGRADE,
            'value_usd' => null,
        ]);

        $this->preview(['voucher_code' => $voucher->code])->assertOk()
            ->assertJsonPath('data.loyalty.upgrade_requested', true)
            ->assertJsonPath('data.loyalty.voucher_discount_usd', '0.00')
            ->assertJsonPath('data.net_total_usd', '300.00');
    }

    public function test_a_preview_with_neither_still_reports_the_usable_points(): void
    {
        $this->grantPoints($this->guest, 4000);

        $this->preview()->assertOk()
            ->assertJsonPath('data.loyalty.points_redeemed', 0)
            ->assertJsonPath('data.loyalty.points_discount_usd', '0.00')
            ->assertJsonPath('data.net_total_usd', '300.00')
            ->assertJsonPath('data.max_points', 4000)
            ->assertJsonPath('data.available_points', 4000)
            ->assertJsonPath('data.points_earnable_estimate', 300);
    }

    public function test_the_cap_is_computed_on_the_post_promo_total(): void
    {
        $this->grantPoints($this->guest, 50000);
        $promo = PromoCode::factory()->create(['type' => ModifierType::PERCENTAGE, 'value' => 10]);

        // 300.00 less 10% = 270.00; the 50% cap is 135.00 = 13500 points.
        $this->preview(['promo_code' => $promo->code, 'loyalty_points' => 13500])->assertOk()
            ->assertJsonPath('data.quote.promo_discount_usd', '30.00')
            ->assertJsonPath('data.quote.total_usd', '270.00')
            ->assertJsonPath('data.loyalty.points_discount_usd', '135.00')
            ->assertJsonPath('data.net_total_usd', '135.00')
            ->assertJsonPath('data.max_points', 13500);

        $this->preview(['promo_code' => $promo->code, 'loyalty_points' => 13501])
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'loyalty_over_cap')
            ->assertJsonPath('context.max_points', 13500);
    }

    public function test_previewing_writes_nothing(): void
    {
        $this->grantPoints($this->guest, 20000);
        $voucher = LoyaltyVoucher::factory()->create(['guest_id' => $this->guest->id]);
        $promo = PromoCode::factory()->create(['max_uses' => 5]);
        $loyalty = $this->loyaltyRowCounts();
        $reservations = Reservation::count();

        $this->preview(['promo_code' => $promo->code, 'loyalty_points' => 1000])->assertOk();
        $this->preview(['promo_code' => $promo->code, 'voucher_code' => $voucher->code])->assertOk();
        $this->preview(['loyalty_points' => 1000])->assertOk();
        $this->preview(['voucher_code' => $voucher->code])->assertOk();
        $this->preview()->assertOk();

        $this->assertSame($loyalty, $this->loyaltyRowCounts());
        $this->assertSame($reservations, Reservation::count());
        $this->assertSame(0, $promo->fresh()->used_count);
        $this->assertSame('active', $voucher->fresh()->status->value);
    }

    public function test_the_preview_needs_a_guest_token(): void
    {
        $this->app['auth']->forgetGuards();
        $this->getJson('/api/loyalty/preview?'.http_build_query([
            'room_type_uuid' => $this->roomType->uuid,
            'check_in' => now()->addDays(10)->toDateString(),
            'check_out' => now()->addDays(13)->toDateString(),
        ]))->assertStatus(401);

        $this->preview([], $this->staffToken('cms.view'))->assertStatus(401);
    }

    public function test_the_request_is_validated(): void
    {
        $this->preview(['room_type_uuid' => null])->assertStatus(422)->assertJsonValidationErrors('room_type_uuid');
        $this->preview(['room_type_uuid' => 'not-a-room-type'])->assertStatus(422)->assertJsonValidationErrors('room_type_uuid');
        $this->preview([
            'check_in' => now()->addDays(10)->toDateString(),
            'check_out' => now()->addDays(8)->toDateString(),
        ])->assertStatus(422)->assertJsonValidationErrors('check_out');
        $this->preview(['loyalty_points' => 0])->assertStatus(422)->assertJsonValidationErrors('loyalty_points');
        $this->preview(['loyalty_points' => 'many'])->assertStatus(422)->assertJsonValidationErrors('loyalty_points');
    }

    public function test_the_client_cannot_supply_a_discount_or_a_total(): void
    {
        $this->preview(['discount_usd' => '999.00', 'total_usd' => '1.00', 'points_discount_usd' => '999.00'])
            ->assertOk()
            ->assertJsonPath('data.net_total_usd', '300.00')
            ->assertJsonPath('data.loyalty.points_discount_usd', '0.00');
    }

    public function test_domain_refusals_surface_their_error_code(): void
    {
        $this->grantPoints($this->guest, 500);
        $voucher = LoyaltyVoucher::factory()->create(['guest_id' => $this->guest->id]);

        $this->preview(['loyalty_points' => 20000])->assertStatus(422)
            ->assertJsonPath('error_code', 'loyalty_over_cap')
            ->assertJsonPath('context.max_points', 15000);

        $this->preview(['loyalty_points' => 600])->assertStatus(422)
            ->assertJsonPath('error_code', 'loyalty_insufficient_points')
            ->assertJsonPath('context.available_points', 500)
            ->assertJsonPath('context.requested_points', 600);

        $this->preview(['loyalty_points' => 50])->assertStatus(422)
            ->assertJsonPath('error_code', 'loyalty_below_minimum')
            ->assertJsonPath('context.min_redeem_points', 100);

        $this->preview(['loyalty_points' => 200, 'voucher_code' => $voucher->code])->assertStatus(422)
            ->assertJsonPath('error_code', 'loyalty_discount_conflict');

        $this->preview(['voucher_code' => 'LOY-NOSUCHCODE'])->assertStatus(422)
            ->assertJsonPath('error_code', 'loyalty_voucher_invalid')
            ->assertJsonPath('context', null);

        $this->preview(['promo_code' => 'NOPE'])->assertStatus(422)
            ->assertJsonPath('error_code', 'invalid_promo');
    }

    public function test_points_are_refused_while_the_points_discount_is_off(): void
    {
        $this->configureLoyalty(['max_redeem_percent' => null]);
        $this->grantPoints($this->guest, 500);

        $this->preview(['loyalty_points' => 200])->assertStatus(422)
            ->assertJsonPath('error_code', 'loyalty_program_inactive');

        $this->preview()->assertOk()
            ->assertJsonPath('data.max_points', null)
            ->assertJsonPath('data.program.points_discount', false);
    }

    public function test_another_guests_voucher_is_indistinguishable_from_an_unknown_one(): void
    {
        $other = LoyaltyVoucher::factory()->create();

        $foreign = $this->preview(['voucher_code' => $other->code])->assertStatus(422);
        $unknown = $this->preview(['voucher_code' => 'LOY-ZZZZZZZZ'])->assertStatus(422);

        $this->assertSame($unknown->json('error_code'), $foreign->json('error_code'));
        $this->assertSame($unknown->json('message'), $foreign->json('message'));
        $this->assertSame($unknown->json('context'), $foreign->json('context'));
    }

    public function test_the_preview_route_is_throttled_and_guest_only(): void
    {
        $route = collect(Route::getRoutes()->getRoutes())
            ->first(fn ($r) => $r->uri() === 'api/loyalty/preview' && in_array('GET', $r->methods(), true));

        $this->assertNotNull($route);
        $this->assertContains('throttle:30,1', $route->gatherMiddleware());
        $this->assertContains('auth:guests', $route->gatherMiddleware());
    }
}
