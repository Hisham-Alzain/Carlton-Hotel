<?php

namespace Tests\Unit\Loyalty;

use App\Actions\Loyalty\ForfeitLoyaltyBalanceAction;
use App\Enums\LoyaltyBatchStatus;
use App\Enums\LoyaltyVoucherStatus;
use App\Models\Guest;
use App\Models\LoyaltyEarnBatch;
use App\Models\LoyaltySetting;
use App\Models\LoyaltyVoucher;
use App\Models\Reservation;
use App\Support\LoyaltyLedger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\BuildsLoyaltyFixtures;
use Tests\Concerns\RecordsRowLocks;
use Tests\TestCase;

/**
 * Phase 10 gap LOY-23: ForfeitLoyaltyBalanceAction forfeits the points through
 * the ledger and closes the guest's active vouchers (void while still valid,
 * expired at or past expires_at), for a guest row the caller has locked.
 *
 * No loyalty_settings row is created anywhere in this class on purpose.
 */
class ForfeitLoyaltyBalanceActionTest extends TestCase
{
    use BuildsLoyaltyFixtures;
    use RecordsRowLocks;
    use RefreshDatabase;

    private Guest $guest;

    protected function setUp(): void
    {
        parent::setUp();
        $this->freezeTime();
        $this->guest = Guest::factory()->create();
    }

    private function forfeit(?Guest $guest = null): array
    {
        $guest ??= $this->guest;

        return DB::transaction(function () use ($guest): array {
            $locked = Guest::query()->whereKey($guest->id)->lockForUpdate()->firstOrFail();

            return app(ForfeitLoyaltyBalanceAction::class)->handle($locked);
        });
    }

    private function voucher(array $overrides = [], ?Guest $guest = null): LoyaltyVoucher
    {
        return LoyaltyVoucher::factory()->create(array_merge(['guest_id' => ($guest ?? $this->guest)->id], $overrides));
    }

    /** @return array<string, mixed> the columns a close must never change */
    private function fixed(LoyaltyVoucher $voucher): array
    {
        $fresh = $voucher->fresh();

        return [
            'code' => $fresh->code,
            'reservation_id' => $fresh->reservation_id,
            'used_at' => $fresh->getRawOriginal('used_at'),
            'expires_at' => $fresh->getRawOriginal('expires_at'),
        ];
    }

    public function test_returns_counts_only_in_the_service_shape(): void
    {
        $this->grantPoints($this->guest, 60, now()->addDays(10));
        $this->grantPoints($this->guest, 40, now()->addDays(20));
        $this->voucher(['expires_at' => now()->addDay()]);

        $result = $this->forfeit();

        $this->assertSame([
            'data' => ['forfeited_points' => 100, 'expired_batches' => 2, 'closed_vouchers' => 1],
            'code' => 200,
        ], $result);
        $this->assertSame(0, app(LoyaltyLedger::class)->available($this->guest->id));
    }

    public function test_active_vouchers_close_as_void_or_expired_and_nothing_else_changes(): void
    {
        $valid = $this->voucher(['expires_at' => now()->addDay()]);
        $atNow = $this->voucher(['expires_at' => now()]);
        $past = $this->voucher(['expires_at' => now()->subDay()]);
        $used = LoyaltyVoucher::factory()->used(Reservation::factory()->create(['guest_id' => $this->guest->id]))
            ->create(['guest_id' => $this->guest->id]);
        $expired = LoyaltyVoucher::factory()->expired()->create(['guest_id' => $this->guest->id]);
        $void = $this->voucher(['status' => LoyaltyVoucherStatus::VOID]);
        $othersActive = $this->voucher([], Guest::factory()->create());

        $all = [$valid, $atNow, $past, $used, $expired, $void, $othersActive];
        $fixedBefore = array_map(fn (LoyaltyVoucher $v) => $this->fixed($v), $all);
        $untouched = array_map(fn (LoyaltyVoucher $v) => $v->fresh()->getAttributes(), [$used, $expired, $void, $othersActive]);

        $result = $this->forfeit();

        $this->assertSame(3, $result['data']['closed_vouchers']);
        $this->assertSame(LoyaltyVoucherStatus::VOID, $valid->fresh()->status);
        $this->assertSame(LoyaltyVoucherStatus::EXPIRED, $atNow->fresh()->status);
        $this->assertSame(LoyaltyVoucherStatus::EXPIRED, $past->fresh()->status);
        $this->assertSame(
            $untouched,
            array_map(fn (LoyaltyVoucher $v) => $v->fresh()->getAttributes(), [$used, $expired, $void, $othersActive]),
        );
        $this->assertSame($fixedBefore, array_map(fn (LoyaltyVoucher $v) => $this->fixed($v), $all));
    }

    public function test_batches_are_locked_before_vouchers(): void
    {
        $this->grantPoints($this->guest, 50);
        $this->voucher();

        $locked = $this->lockedSelects(fn () => $this->forfeit());

        $first = fn (string $table) => collect($locked)->search(fn (string $sql) => str_contains($sql, 'from "'.$table.'"'));
        $batches = $first('loyalty_earn_batches');
        $vouchers = $first('loyalty_vouchers');

        $this->assertNotFalse($batches, "no `for update` select on loyalty_earn_batches:\n".implode("\n", $locked));
        $this->assertNotFalse($vouchers, "no `for update` select on loyalty_vouchers:\n".implode("\n", $locked));
        $this->assertLessThan($vouchers, $batches, 'lock order must be batches -> vouchers');
    }

    public function test_a_second_call_returns_zeros_and_changes_nothing(): void
    {
        $this->grantPoints($this->guest, 50);
        $voucher = $this->voucher();
        $this->forfeit();
        $before = $this->loyaltyRowCounts();
        $voucherBefore = $voucher->fresh()->getAttributes();

        $this->assertSame(
            ['data' => ['forfeited_points' => 0, 'expired_batches' => 0, 'closed_vouchers' => 0], 'code' => 200],
            $this->forfeit(),
        );
        $this->assertSame($before, $this->loyaltyRowCounts());
        $this->assertSame($voucherBefore, $voucher->fresh()->getAttributes());
    }

    public function test_a_guest_without_loyalty_rows_and_without_settings_gets_zeros(): void
    {
        $this->assertSame(0, LoyaltySetting::count());
        $before = $this->loyaltyRowCounts();

        $this->assertSame(
            ['data' => ['forfeited_points' => 0, 'expired_batches' => 0, 'closed_vouchers' => 0], 'code' => 200],
            $this->forfeit(),
        );
        $this->assertSame($before, $this->loyaltyRowCounts());
    }

    public function test_no_settings_row_does_not_stop_a_real_forfeit(): void
    {
        $this->assertSame(0, LoyaltySetting::count());
        $batch = $this->grantPoints($this->guest, 75);
        $this->voucher();

        $result = $this->forfeit();

        $this->assertSame(['forfeited_points' => 75, 'expired_batches' => 1, 'closed_vouchers' => 1], $result['data']);
        $this->assertSame(LoyaltyBatchStatus::EXPIRED, $batch->fresh()->status);
        $this->assertSame(0, LoyaltyEarnBatch::where('guest_id', $this->guest->id)->where('points_remaining', '<', 0)->count());
    }
}
