<?php

namespace Tests\Unit\Currency;

use App\Actions\Currency\RecordExchangeRateAction;
use App\Exceptions\ExchangeRateLargeChangeException;
use App\Models\ExchangeRate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\RecordsRowLocks;
use Tests\TestCase;

/**
 * Phase 9.1 (D-17): the 50% fat-finger guard, in bcmath at scale 6.
 */
class RecordExchangeRateActionTest extends TestCase
{
    use RecordsRowLocks;
    use RefreshDatabase;

    private User $actor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actor = User::factory()->create();
    }

    private function record(string $rate, bool $confirm = false, string $currency = 'SYP'): array
    {
        return app(RecordExchangeRateAction::class)->handle(
            ['currency' => $currency, 'rate' => $rate, 'note' => null, 'confirm_large_change' => $confirm],
            $this->actor,
        );
    }

    public function test_the_first_rate_needs_no_confirmation(): void
    {
        $result = $this->record('13000');

        $this->assertSame(201, $result['code']);
        $this->assertInstanceOf(ExchangeRate::class, $result['data']);
        $this->assertSame('13000.000000', $result['data']->fresh()->rate);
        $this->assertSame($this->actor->id, $result['data']->set_by);
    }

    /** @return array<string, array{string}> */
    public static function withinGuard(): array
    {
        return ['+50% exactly' => ['150'], '-50% exactly' => ['50'], 'small move' => ['101.5']];
    }

    #[DataProvider('withinGuard')]
    public function test_changes_up_to_fifty_percent_pass(string $rate): void
    {
        ExchangeRate::factory()->create(['rate' => '100']);

        $this->assertSame(201, $this->record($rate)['code']);
        $this->assertSame(2, ExchangeRate::count());
    }

    /** @return array<string, array{string, string}> */
    public static function beyondGuard(): array
    {
        return [
            'just over +50%' => ['150.000001', '50.000001'],
            'just under -50%' => ['49.999999', '-50.000001'],
        ];
    }

    #[DataProvider('beyondGuard')]
    public function test_changes_beyond_fifty_percent_need_confirmation(string $rate, string $percent): void
    {
        ExchangeRate::factory()->create(['rate' => '100']);

        try {
            $this->record($rate);
            $this->fail('Expected the large-change guard to fire.');
        } catch (ExchangeRateLargeChangeException $e) {
            $this->assertSame([
                'currency'       => 'SYP',
                'current_rate'   => '100.000000',
                'proposed_rate'  => bcadd($rate, '0', 6),
                'change_percent' => $percent,
            ], $e->context());
        }

        $this->assertSame(1, ExchangeRate::count());
    }

    public function test_a_redenomination_slip_is_caught_and_passes_when_confirmed(): void
    {
        ExchangeRate::factory()->create(['rate' => '13000']);

        $this->expectException(ExchangeRateLargeChangeException::class);
        try {
            $this->record('130');
        } finally {
            $this->assertSame(201, $this->record('130', true)['code']);
            $this->assertSame('130.000000', ExchangeRate::orderByDesc('id')->first()->rate);
        }
    }

    public function test_the_guard_compares_against_the_same_currency_only(): void
    {
        ExchangeRate::factory()->create(['currency' => 'TRY', 'rate' => '40']);

        $this->assertSame(201, $this->record('13000')['code']);
    }

    public function test_the_latest_row_is_locked(): void
    {
        ExchangeRate::factory()->create(['rate' => '100']);

        $this->assertLocksRow('exchange_rates', fn () => $this->record('101'));
    }
}
