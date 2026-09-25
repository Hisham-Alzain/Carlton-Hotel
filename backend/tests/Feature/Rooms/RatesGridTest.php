<?php

namespace Tests\Feature\Rooms;

use App\Actions\Booking\QuoteReservationAction;
use App\Enums\ModifierType;
use App\Enums\PricingScope;
use App\Models\PricingRule;
use App\Models\RoomType;
use App\Models\User;
use App\Services\Operations\FrontDeskService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * GET /api/front-desk/rates-grid (Phase 2, ROOMS-04, D-10, D-11). Read-only,
 * and every cell equals QuoteReservationAction's one-night daily rate.
 */
class RatesGridTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->travelTo(Carbon::parse('2027-03-10 09:00:00'));
    }

    private function staffToken(string ...$permissions): string
    {
        $user = User::factory()->create();
        $user->givePermissionTo($permissions);
        return $user->createToken('t')->plainTextToken;
    }

    private function presetToken(string $role): string
    {
        $user = User::factory()->create();
        $user->assignRole($role);
        return $user->createToken('t')->plainTextToken;
    }

    private function grid(string $query = '', ?string $token = null)
    {
        return $this->withToken($token ?? $this->presetToken('reception'))
            ->getJson('/api/front-desk/rates-grid'.($query !== '' ? '?'.$query : ''));
    }

    /** @return array<string, array> room types keyed by uuid */
    private function types($response): array
    {
        return collect($response->json('data.room_types'))->keyBy('uuid')->all();
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

    private function quoted(RoomType $type, string $date): string
    {
        $next = Carbon::parse($date)->addDay()->toDateString();
        $rate = app(QuoteReservationAction::class)->handle($type, $date, $next)['daily_rate_usd'];

        return number_format($rate, 2, '.', '');
    }

    public function test_rates_grid_defaults_to_14_days_and_base_price_without_rules(): void
    {
        $type = RoomType::factory()->create(['base_price_usd' => 150.00, 'name' => ['en' => 'Suite', 'ar' => 'جناح']]);
        RoomType::factory()->inactive()->create();

        $response = $this->grid()
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.from', '2027-03-10')
            ->assertJsonPath('data.days', 14)
            ->assertJsonCount(1, 'data.room_types');

        $row = $this->types($response)[$type->uuid];
        $this->assertSame(['uuid', 'name', 'base_price_usd', 'cells'], array_keys($row));
        $this->assertSame(['en' => 'Suite', 'ar' => 'جناح'], $row['name']);
        $this->assertSame('150.00', $row['base_price_usd']);
        $this->assertCount(14, $row['cells']);
        $this->assertSame('2027-03-10', $row['cells'][0]['date']);
        $this->assertSame('2027-03-23', $row['cells'][13]['date']);
        foreach ($row['cells'] as $cell) {
            $this->assertSame(['date', 'rate_usd', 'rule_scope'], array_keys($cell));
            $this->assertSame('150.00', $cell['rate_usd']);
            $this->assertNull($cell['rule_scope']);
        }
    }

    public function test_rates_grid_matches_the_quote_for_every_cell(): void
    {
        $a = RoomType::factory()->create(['base_price_usd' => 100.00, 'sort_order' => 1]);
        $b = RoomType::factory()->create(['base_price_usd' => 100.00, 'sort_order' => 2]);
        $other = RoomType::factory()->create(['base_price_usd' => 80.00, 'sort_order' => 3]);

        // Overlapping rules, identical windows, both insertion orders (FA-03-3).
        $this->rule($a, PricingScope::SEASONAL, ModifierType::PERCENTAGE, 10, '2027-03-12', '2027-03-14');
        $this->rule($a, PricingScope::HOLIDAY, ModifierType::FLAT, 20, '2027-03-12', '2027-03-14');
        $this->rule($b, PricingScope::WEEKEND, ModifierType::FLAT, 20, '2027-03-12', '2027-03-14');
        $this->rule($b, PricingScope::SEASONAL, ModifierType::PERCENTAGE, 10, '2027-03-12', '2027-03-14');
        // Non-overlapping window on a different date range.
        $this->rule($a, PricingScope::WEEKEND, ModifierType::FLAT, 15, '2027-03-16', '2027-03-18');
        // Ignored: inactive, and a rule on another type.
        $this->rule($a, PricingScope::SEASONAL, ModifierType::PERCENTAGE, 50, '2027-03-10', '2027-03-23', active: false);
        $this->rule($other, PricingScope::HOLIDAY, ModifierType::FLAT, 33, '2027-03-11', '2027-03-13');

        $types = $this->types($this->grid()->assertOk());

        $checked = 0;
        foreach ([$a, $b, $other] as $type) {
            foreach ($types[$type->uuid]['cells'] as $cell) {
                $this->assertSame($this->quoted($type, $cell['date']), $cell['rate_usd'], "{$type->uuid} on {$cell['date']}");
                $checked++;
            }
        }
        $this->assertSame(42, $checked);

        $aCells = collect($types[$a->uuid]['cells'])->keyBy('date');
        $bCells = collect($types[$b->uuid]['cells'])->keyBy('date');
        $this->assertSame(['date' => '2027-03-13', 'rate_usd' => '130.00', 'rule_scope' => 'holiday'], $aCells['2027-03-13']);
        $this->assertSame(['date' => '2027-03-13', 'rate_usd' => '132.00', 'rule_scope' => 'seasonal'], $bCells['2027-03-13']);
        $this->assertSame(['date' => '2027-03-17', 'rate_usd' => '115.00', 'rule_scope' => 'weekend'], $aCells['2027-03-17']);
        $this->assertSame(['date' => '2027-03-10', 'rate_usd' => '100.00', 'rule_scope' => null], $aCells['2027-03-10']);
    }

    public function test_rule_window_is_inclusive_on_both_ends(): void
    {
        $type = RoomType::factory()->create(['base_price_usd' => 100.00]);
        $this->rule($type, PricingScope::SEASONAL, ModifierType::FLAT, 25, '2027-03-12', '2027-03-14');

        $cells = collect($this->types($this->grid('days=7')->assertOk())[$type->uuid]['cells'])
            ->pluck('rate_usd', 'date')->all();

        $this->assertSame([
            '2027-03-10' => '100.00', '2027-03-11' => '100.00', '2027-03-12' => '125.00',
            '2027-03-13' => '125.00', '2027-03-14' => '125.00', '2027-03-15' => '100.00',
            '2027-03-16' => '100.00',
        ], $cells);
    }

    public function test_rules_straddling_the_window_edges_still_apply(): void
    {
        $type = RoomType::factory()->create(['base_price_usd' => 100.00]);
        $this->rule($type, PricingScope::SEASONAL, ModifierType::FLAT, 5, '2027-03-01', '2027-03-10'); // ends on `from`
        $this->rule($type, PricingScope::HOLIDAY, ModifierType::FLAT, 7, '2027-03-12', '2027-04-30');  // starts inside, ends after

        $cells = collect($this->types($this->grid('days=3')->assertOk())[$type->uuid]['cells'])
            ->pluck('rate_usd', 'date')->all();

        $this->assertSame(['2027-03-10' => '105.00', '2027-03-11' => '100.00', '2027-03-12' => '107.00'], $cells);
    }

    public function test_rate_usd_is_a_two_decimal_string(): void
    {
        $type  = RoomType::factory()->create(['base_price_usd' => 99.99]);
        $plain = RoomType::factory()->create(['base_price_usd' => 120.5]);
        $this->rule($type, PricingScope::SEASONAL, ModifierType::PERCENTAGE, 7.5, '2027-03-12', '2027-03-14');

        $types = $this->types($this->grid()->assertOk());

        foreach ($types as $row) {
            foreach ($row['cells'] as $cell) {
                $this->assertIsString($cell['rate_usd']);
                $this->assertMatchesRegularExpression('/^\d+\.\d{2}$/', $cell['rate_usd']);
            }
        }
        $cells = collect($types[$type->uuid]['cells'])->keyBy('date');
        $this->assertSame('107.49', $cells['2027-03-13']['rate_usd']);
        $this->assertSame($this->quoted($type, '2027-03-13'), $cells['2027-03-13']['rate_usd']);
        $this->assertSame('99.99', $cells['2027-03-11']['rate_usd']);
        $this->assertSame('120.50', $types[$plain->uuid]['cells'][0]['rate_usd']);
    }

    public static function boundaries(): array
    {
        return [
            'days 0'               => ['days=0', 422, 'days', null],
            'days 32'              => ['days=32', 422, 'days', null],
            'days not a number'    => ['days=abc', 422, 'days', null],
            'days 1'               => ['days=1', 200, null, 1],
            'days 31'              => ['days=31', 200, null, 31],
            'wrong from format'    => ['from=2027/03/10', 422, 'from', null],
            'from today minus 366' => ['from=2026-03-09', 422, 'from', null],
            'from today minus 365' => ['from=2026-03-10', 200, null, 14],
        ];
    }

    #[DataProvider('boundaries')]
    public function test_rates_grid_validation_boundaries(string $query, int $status, ?string $field, ?int $cells): void
    {
        RoomType::factory()->create();

        $response = $this->grid($query)->assertStatus($status);

        if ($status === 422) {
            $response->assertJsonPath('error_code', 'validation_failed')->assertJsonValidationErrors([$field]);
            return;
        }

        $this->assertCount($cells, $response->json('data.room_types.0.cells'));
    }

    public function test_rates_grid_requires_reservations_view(): void
    {
        $this->grid('', $this->presetToken('housekeeping'))
            ->assertStatus(403)
            ->assertJsonPath('error_code', 'forbidden');

        $this->grid('', $this->staffToken('cms.edit'))
            ->assertStatus(403)
            ->assertJsonPath('error_code', 'forbidden');
    }

    public function test_rates_grid_requires_authentication(): void
    {
        $this->getJson('/api/front-desk/rates-grid')
            ->assertStatus(401)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error_code', 'unauthorized');
    }

    public function test_rates_grid_service_runs_exactly_two_read_queries(): void
    {
        foreach (RoomType::factory()->count(3)->create() as $type) {
            $this->rule($type, PricingScope::SEASONAL, ModifierType::PERCENTAGE, 10, '2027-03-12', '2027-03-20');
        }
        $service = app(FrontDeskService::class);

        $sql = [];
        DB::listen(function ($query) use (&$sql) {
            $sql[] = $query->sql;
        });

        $this->expectsDatabaseQueryCount(2);
        $result = $service->ratesGrid(['days' => 31]);

        $this->assertSame(200, $result['code']);
        $this->assertCount(3, $result['data']['room_types']);
        $this->assertCount(2, $sql);
        foreach ($sql as $statement) {
            $this->assertStringStartsWith('select', strtolower(ltrim($statement)));
        }
    }

    public function test_rates_grid_query_count_does_not_grow_with_days(): void
    {
        $count = 0;
        DB::listen(function () use (&$count) {
            $count++;
        });
        $token = $this->presetToken('reception');
        $this->grid('', $token)->assertOk(); // warm-up

        $type = RoomType::factory()->create();
        $this->rule($type, PricingScope::SEASONAL, ModifierType::PERCENTAGE, 10, '2027-03-12', '2027-03-20');

        $count = 0;
        $this->grid('days=1', $token)->assertOk();
        $small = $count;

        foreach (RoomType::factory()->count(3)->create() as $t) {
            $this->rule($t, PricingScope::HOLIDAY, ModifierType::FLAT, 10, '2027-03-15', '2027-04-05');
        }

        $count = 0;
        $this->grid('days=31', $token)->assertOk()->assertJsonCount(4, 'data.room_types');
        $large = $count;

        $this->assertSame($small, $large);
    }
}
