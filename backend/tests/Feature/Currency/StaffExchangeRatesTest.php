<?php

namespace Tests\Feature\Currency;

use App\Models\ExchangeRate;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * GET|POST /api/cms/exchange-rates, GET /api/cms/exchange-rates/history
 * (Phase 9.1, FX-02, D-15..D-19). Gated by the existing `pricing.edit`.
 */
class StaffExchangeRatesTest extends TestCase
{
    use RefreshDatabase;

    private const PRESETS = ['reception', 'kitchen', 'housekeeping', 'concierge', 'events', 'content_editor', 'content_manager'];

    private User $editor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->editor = User::factory()->create(['name' => 'Rana Finance']);
        $this->editor->givePermissionTo('pricing.edit');
    }

    private function as(User $user)
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($user->createToken('t')->plainTextToken);
    }

    private function postRate(array $body, ?User $user = null, string $locale = 'en')
    {
        return $this->as($user ?? $this->editor)->withHeaders(['Accept-Language' => $locale])
            ->postJson('/api/cms/exchange-rates', $body);
    }

    /** @return array<string, array{string, string}> */
    public static function routes(): array
    {
        return [
            'board'   => ['GET', '/api/cms/exchange-rates'],
            'history' => ['GET', '/api/cms/exchange-rates/history'],
            'store'   => ['POST', '/api/cms/exchange-rates'],
        ];
    }

    #[DataProvider('routes')]
    public function test_401_without_a_token(string $method, string $uri): void
    {
        $this->json($method, $uri, ['currency' => 'SYP', 'rate' => '1'])->assertStatus(401);
    }

    #[DataProvider('routes')]
    public function test_403_without_the_permission(string $method, string $uri): void
    {
        $this->as(User::factory()->create())->json($method, $uri, ['currency' => 'SYP', 'rate' => '1'])
            ->assertStatus(403)->assertJsonPath('success', false);
    }

    /** @return array<string, array{string, string, string}> */
    public static function presetRoutes(): array
    {
        $out = [];
        foreach (self::PRESETS as $preset) {
            foreach (self::routes() as $name => [$method, $uri]) {
                $out["{$preset} {$name}"] = [$preset, $method, $uri];
            }
        }

        return $out;
    }

    #[DataProvider('presetRoutes')]
    public function test_every_preset_is_403(string $preset, string $method, string $uri): void
    {
        $user = User::factory()->create();
        $user->assignRole($preset);

        $this->as($user)->json($method, $uri, ['currency' => 'SYP', 'rate' => '1'])->assertStatus(403);
        $this->assertSame(0, ExchangeRate::count());
    }

    public function test_store_appends_and_returns_the_entry(): void
    {
        $response = $this->postRate(['currency' => 'syp', 'rate' => '13000', 'note' => 'CBS bulletin'])
            ->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', __('custom.messages.exchange_rate_recorded', [], 'en'));

        $data = $response->json('data');
        $this->assertSame(['uuid', 'currency', 'rate', 'note', 'set_by', 'created_at'], array_keys($data));
        $this->assertSame('SYP', $data['currency']);
        $this->assertSame('13000.000000', $data['rate']);
        $this->assertSame('CBS bulletin', $data['note']);
        $this->assertSame(['uuid' => $this->editor->uuid, 'name' => 'Rana Finance'], $data['set_by']);
        $this->assertTrue(Str::isUuid($data['uuid']));

        $first = ExchangeRate::first()->getAttributes();
        $this->postRate(['currency' => 'SYP', 'rate' => '13100'])->assertStatus(201);

        $this->assertSame(2, ExchangeRate::count());
        $this->assertSame($first, ExchangeRate::orderBy('id')->first()->getAttributes());
    }

    public function test_store_writes_an_activity_row_caused_by_the_staff_member(): void
    {
        $this->postRate(['currency' => 'TRY', 'rate' => '41.5'])->assertStatus(201);

        $rate = ExchangeRate::first();
        $this->assertTrue(DB::table('activity_log')
            ->where('subject_type', ExchangeRate::class)->where('subject_id', $rate->id)
            ->where('causer_type', User::class)->where('causer_id', $this->editor->id)
            ->exists());
    }

    public function test_super_admin_passes(): void
    {
        $admin = User::factory()->superAdmin()->create();

        $this->postRate(['currency' => 'SYP', 'rate' => '13000'], $admin)->assertStatus(201);
        $this->as($admin)->getJson('/api/cms/exchange-rates')->assertOk();
        $this->as($admin)->getJson('/api/cms/exchange-rates/history')->assertOk();
    }

    /** @return array<string, array{array, string}> */
    public static function invalidBodies(): array
    {
        return [
            'usd'               => [['currency' => 'USD', 'rate' => '1'], 'currency'],
            'unconfigured'      => [['currency' => 'EUR', 'rate' => '1'], 'currency'],
            'missing currency'  => [['rate' => '1'], 'currency'],
            'missing rate'      => [['currency' => 'SYP'], 'rate'],
            'zero'              => [['currency' => 'SYP', 'rate' => '0'], 'rate'],
            'zero decimals'     => [['currency' => 'SYP', 'rate' => '0.000000'], 'rate'],
            'negative'          => [['currency' => 'SYP', 'rate' => '-1'], 'rate'],
            'seven decimals'    => [['currency' => 'SYP', 'rate' => '1.1234567'], 'rate'],
            'fifteen digits'    => [['currency' => 'SYP', 'rate' => '123456789012345'], 'rate'],
            'non numeric'       => [['currency' => 'SYP', 'rate' => 'abc'], 'rate'],
            'note too long'     => [['currency' => 'SYP', 'rate' => '1', 'note' => str_repeat('x', 256)], 'note'],
            'confirm not bool'  => [['currency' => 'SYP', 'rate' => '1', 'confirm_large_change' => 'maybe'], 'confirm_large_change'],
        ];
    }

    #[DataProvider('invalidBodies')]
    public function test_validation(array $body, string $field): void
    {
        $this->postRate($body)
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'validation_failed')
            ->assertJsonValidationErrors([$field]);

        $this->assertSame(0, ExchangeRate::count());
    }

    public function test_a_numeric_rate_is_accepted(): void
    {
        $this->postRate(['currency' => 'TRY', 'rate' => 41.25])->assertStatus(201)->assertJsonPath('data.rate', '41.250000');
    }

    /** @return array<string, array{string}> */
    public static function locales(): array
    {
        return ['en' => ['en'], 'ar' => ['ar'], 'fr' => ['fr'], 'tr' => ['tr'], 'es' => ['es']];
    }

    #[DataProvider('locales')]
    public function test_large_change_needs_confirmation(string $locale): void
    {
        ExchangeRate::factory()->create(['currency' => 'SYP', 'rate' => '13000']);

        $this->postRate(['currency' => 'SYP', 'rate' => '130'], null, $locale)
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'exchange_rate_large_change')
            ->assertJsonPath('message', __('custom.errors.exchange_rate_large_change', [], $locale))
            ->assertJsonPath('context.currency', 'SYP')
            ->assertJsonPath('context.current_rate', '13000.000000')
            ->assertJsonPath('context.proposed_rate', '130.000000')
            ->assertJsonPath('context.change_percent', '-99.000000');
        $this->assertSame(1, ExchangeRate::count());

        $this->postRate(['currency' => 'SYP', 'rate' => '130', 'confirm_large_change' => true], null, $locale)->assertStatus(201);
        $this->assertSame(2, ExchangeRate::count());
    }

    public function test_board_lists_configured_currencies_in_order_with_staff_fields(): void
    {
        ExchangeRate::factory()->create(['currency' => 'SYP', 'rate' => '12000', 'set_by' => $this->editor->id]);
        ExchangeRate::factory()->create(['currency' => 'SYP', 'rate' => '13000', 'note' => 'board', 'set_by' => $this->editor->id]);

        $data = $this->as($this->editor)->getJson('/api/cms/exchange-rates')->assertOk()->json('data');

        $this->assertSame('USD', $data['base']);
        $this->assertSame(168, $data['stale_after_hours']);
        $this->assertSame(['SYP', 'TRY'], array_column($data['rates'], 'currency'));

        [$syp, $try] = $data['rates'];
        $this->assertSame('13000.000000', $syp['rate']);
        $this->assertSame(0, $syp['display_decimals']);
        $this->assertFalse($syp['is_stale']);
        $this->assertSame('board', $syp['note']);
        $this->assertSame(['uuid' => $this->editor->uuid, 'name' => 'Rana Finance'], $syp['set_by']);

        $this->assertSame(['currency' => 'TRY', 'rate' => null, 'display_decimals' => 2, 'updated_at' => null,
            'is_stale' => true, 'note' => null, 'set_by' => null], $try);
    }

    public function test_history_is_newest_first_and_filterable(): void
    {
        $a = ExchangeRate::factory()->create(['currency' => 'SYP', 'rate' => '1']);
        $b = ExchangeRate::factory()->create(['currency' => 'TRY', 'rate' => '2']);
        $c = ExchangeRate::factory()->create(['currency' => 'SYP', 'rate' => '3']);

        $all = $this->as($this->editor)->getJson('/api/cms/exchange-rates/history')->assertOk();
        $this->assertSame([$c->uuid, $b->uuid, $a->uuid], array_column($all->json('data.items'), 'uuid'));
        $this->assertSame(3, $all->json('data.meta.total'));
        $this->assertSame(['uuid', 'currency', 'rate', 'note', 'set_by', 'created_at'], array_keys($all->json('data.items.0')));

        $syp = $this->as($this->editor)->getJson('/api/cms/exchange-rates/history?currency=SYP')->assertOk();
        $this->assertSame([$c->uuid, $a->uuid], array_column($syp->json('data.items'), 'uuid'));

        $page = $this->as($this->editor)->getJson('/api/cms/exchange-rates/history?per_page=1')->assertOk();
        $this->assertSame(1, $page->json('data.meta.per_page'));
        $this->assertSame(3, $page->json('data.meta.last_page'));
    }
}
