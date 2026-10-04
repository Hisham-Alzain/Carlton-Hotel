<?php

namespace Tests\Feature\Currency;

use App\Models\ExchangeRate;
use App\Models\Guest;
use App\Services\Currency\ExchangeRateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\CountsDomainQueries;
use Tests\TestCase;

/**
 * GET /api/public/exchange-rates (Phase 9.1, FX-01, D-15, D-16, D-18).
 */
class PublicExchangeRatesTest extends TestCase
{
    use CountsDomainQueries;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-10-04 08:00:00', 'UTC'));
    }

    private function board(array $headers = [])
    {
        return $this->withHeaders($headers)->getJson('/api/public/exchange-rates');
    }

    public function test_no_rates_yet_returns_null_stale_entries(): void
    {
        $response = $this->board()->assertOk();
        $response->assertExactJson([
            'success'    => true,
            'message'    => __('custom.messages.success', [], 'en'),
            'request_id' => $response->json('request_id'),
            'data'       => [
                'base' => 'USD', 'stale_after_hours' => 168,
                'rates' => [
                    ['currency' => 'SYP', 'rate' => null, 'display_decimals' => 0, 'updated_at' => null, 'is_stale' => true],
                    ['currency' => 'TRY', 'rate' => null, 'display_decimals' => 2, 'updated_at' => null, 'is_stale' => true],
                ],
            ],
        ]);
    }

    public function test_the_latest_row_of_each_currency_wins(): void
    {
        for ($i = 1; $i <= 30; $i++) {
            ExchangeRate::factory()->create(['currency' => 'SYP', 'rate' => (string) (13000 + $i), 'note' => 'secret note']);
        }
        for ($i = 1; $i <= 20; $i++) {
            ExchangeRate::factory()->create(['currency' => 'TRY', 'rate' => '41.'.str_pad((string) $i, 2, '0', STR_PAD_LEFT)]);
        }

        $data = $this->board()->assertOk()->json('data');

        $this->assertSame([
            'base' => 'USD', 'stale_after_hours' => 168,
            'rates' => [
                ['currency' => 'SYP', 'rate' => '13030.000000', 'display_decimals' => 0, 'updated_at' => '2026-10-04T08:00:00Z', 'is_stale' => false],
                ['currency' => 'TRY', 'rate' => '41.200000', 'display_decimals' => 2, 'updated_at' => '2026-10-04T08:00:00Z', 'is_stale' => false],
            ],
        ], $data);
    }

    public function test_staff_fields_never_leak(): void
    {
        ExchangeRate::factory()->create(['note' => 'internal']);

        $body = $this->board()->assertOk()->getContent();

        $this->assertStringNotContainsString('internal', $body);
        $this->assertStringNotContainsString('set_by', $body);
        $this->assertStringNotContainsString('uuid', $body);
    }

    public function test_staleness_boundary(): void
    {
        ExchangeRate::factory()->create();

        $this->travelTo(Carbon::parse('2026-10-11 08:00:00', 'UTC')); // +168h exactly
        $this->assertFalse($this->board()->json('data.rates.0.is_stale'));

        $this->travelTo(Carbon::parse('2026-10-11 08:00:01', 'UTC')); // +168h +1s
        $this->assertTrue($this->board()->json('data.rates.0.is_stale'));
    }

    public function test_stale_hours_follow_config(): void
    {
        config(['currency.stale_after_hours' => 24]);
        ExchangeRate::factory()->create();
        $this->travel(25)->hours();

        $this->board()->assertOk()
            ->assertJsonPath('data.stale_after_hours', 24)
            ->assertJsonPath('data.rates.0.is_stale', true);
    }

    public function test_cache_control_header(): void
    {
        $header = $this->board()->assertOk()->headers->get('Cache-Control');

        $this->assertStringContainsString('public', $header);
        $this->assertStringContainsString('max-age=300', $header);
    }

    public function test_works_with_a_guest_token(): void
    {
        $token = Guest::factory()->create()->createToken('app')->plainTextToken;

        $this->withToken($token)->getJson('/api/public/exchange-rates')->assertOk();
    }

    public function test_query_budget_is_two_regardless_of_history(): void
    {
        ExchangeRate::factory()->count(30)->create(['currency' => 'SYP']);
        ExchangeRate::factory()->count(20)->create(['currency' => 'TRY', 'rate' => '41']);

        $count = $this->countDomainQueries(fn () => app(ExchangeRateService::class)->board(false));

        $this->assertLessThanOrEqual(2, $count);
    }

    public function test_a_newly_configured_currency_appears_with_nulls(): void
    {
        config(['currency.currencies' => [
            'SYP' => ['display_decimals' => 0], 'TRY' => ['display_decimals' => 2], 'EUR' => ['display_decimals' => 2],
        ]]);

        $rates = $this->board()->assertOk()->json('data.rates');

        $this->assertSame(['SYP', 'TRY', 'EUR'], array_column($rates, 'currency'));
        $this->assertNull($rates[2]['rate']);
    }

    public function test_message_follows_accept_language(): void
    {
        $this->board(['Accept-Language' => 'ar'])->assertOk()
            ->assertJsonPath('message', __('custom.messages.success', [], 'ar'));
    }

    public function test_route_is_throttled_and_public(): void
    {
        $route = collect(app('router')->getRoutes()->getRoutes())
            ->first(fn ($r) => $r->uri() === 'api/public/exchange-rates');

        $this->assertNotNull($route);
        $this->assertContains('throttle:60,1', $route->gatherMiddleware());
        $this->assertEmpty(array_filter($route->gatherMiddleware(), fn ($m) => is_string($m) && str_starts_with($m, 'auth')));
    }
}
