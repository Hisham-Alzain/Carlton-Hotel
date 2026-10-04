<?php

namespace Tests\Unit\Currency;

use App\Exceptions\ExchangeRateLargeChangeException;
use App\Models\ExchangeRate;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Phase 9.1 (D-14, D-17, D-20): the append-only exchange_rates table.
 */
class ExchangeRateModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_rate_round_trips_as_an_exact_decimal_string(): void
    {
        $rate = ExchangeRate::factory()->create(['rate' => '13000.123456']);

        $fresh = $rate->fresh();
        $this->assertSame('13000.123456', $fresh->rate);
        $this->assertNotEmpty($fresh->uuid);
        $this->assertSame('SYP', $fresh->currency);
        $this->assertInstanceOf(User::class, $fresh->setBy);
    }

    public function test_latest_per_currency_returns_the_newest_row_of_each_configured_code(): void
    {
        ExchangeRate::factory()->create(['currency' => 'SYP', 'rate' => '1']);
        ExchangeRate::factory()->create(['currency' => 'SYP', 'rate' => '2']);
        $syp = ExchangeRate::factory()->create(['currency' => 'SYP', 'rate' => '3']);
        $try = ExchangeRate::factory()->create(['currency' => 'TRY', 'rate' => '40']);
        ExchangeRate::factory()->create(['currency' => 'EUR', 'rate' => '0.9']);

        $rows = ExchangeRate::latestPerCurrency(['SYP', 'TRY'])->get()->keyBy('currency');

        $this->assertSame(['SYP', 'TRY'], $rows->keys()->sort()->values()->all());
        $this->assertSame($syp->id, $rows['SYP']->id);
        $this->assertSame($try->id, $rows['TRY']->id);
    }

    public function test_indexes(): void
    {
        $indexes = collect(Schema::getIndexes('exchange_rates'));

        $this->assertTrue($indexes->contains(fn ($i) => $i['columns'] === ['currency', 'id']), 'missing (currency, id)');
        $this->assertTrue($indexes->contains(fn ($i) => $i['columns'] === ['set_by']), 'missing set_by index');
        $this->assertTrue($indexes->contains(fn ($i) => $i['columns'] === ['uuid'] && $i['unique']), 'missing unique uuid');
    }

    public function test_the_author_cannot_be_deleted(): void
    {
        $rate = ExchangeRate::factory()->create();

        try {
            DB::table('users')->where('id', $rate->set_by)->delete();
            $this->fail('restrictOnDelete did not fire');
        } catch (QueryException) {
            $this->assertDatabaseHas('users', ['id' => $rate->set_by]);
        }
    }

    public function test_the_model_has_no_soft_deletes(): void
    {
        $this->assertFalse(Schema::hasColumn('exchange_rates', 'deleted_at'));
    }

    /** @return array<string, array{string}> */
    public static function locales(): array
    {
        return ['en' => ['en'], 'ar' => ['ar'], 'fr' => ['fr'], 'tr' => ['tr'], 'es' => ['es']];
    }

    #[DataProvider('locales')]
    public function test_the_exception_renders_422_with_its_code(string $locale): void
    {
        Route::middleware('api')->get('/api/__test/fx-large-change', fn () => throw new ExchangeRateLargeChangeException('', [
            'currency' => 'SYP', 'current_rate' => '100.000000', 'proposed_rate' => '200.000000', 'change_percent' => '100.000000',
        ]));

        $this->withHeaders(['Accept-Language' => $locale])
            ->getJson('/api/__test/fx-large-change')
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'exchange_rate_large_change')
            ->assertJsonPath('message', __('custom.errors.exchange_rate_large_change', [], $locale))
            ->assertJsonPath('context.change_percent', '100.000000');
    }

    #[DataProvider('locales')]
    public function test_lang_keys_exist(string $locale): void
    {
        foreach (['custom.errors.exchange_rate_large_change', 'custom.messages.exchange_rate_recorded',
            'custom.attributes.currency', 'custom.attributes.rate', 'custom.attributes.confirm_large_change'] as $key) {
            $this->assertNotSame($key, __($key, [], $locale), "{$key} missing in {$locale}");
        }
    }
}
