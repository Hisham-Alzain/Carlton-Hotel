<?php

namespace Tests\Feature\Loyalty;

use App\Actions\Loyalty\ForfeitDeletedGuestBalancesAction;
use App\Enums\LoyaltyBatchStatus;
use App\Enums\LoyaltyEntryType;
use App\Enums\LoyaltyVoucherStatus;
use App\Models\Guest;
use App\Models\LoyaltyEarnBatch;
use App\Models\LoyaltyLedgerEntry;
use App\Models\LoyaltyVoucher;
use App\Services\Loyalty\LoyaltyReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsLoyaltyFixtures;
use Tests\Concerns\RecordsRowLocks;
use Tests\TestCase;

/**
 * Phase 10 gap LOY-23 (review item 7): `php artisan loyalty:forfeit-deleted`
 * forfeits the points and closes the vouchers still held by accounts deleted
 * before the deletion forfeit shipped (10-16). One-off, idempotent, and it
 * never touches an account that is not deleted.
 *
 * Residue is built with factories (G-10): a deleted account holding live
 * batches or vouchers can only be data written before 10-16.
 */
class LoyaltyForfeitDeletedCommandTest extends TestCase
{
    use BuildsLoyaltyFixtures;
    use RecordsRowLocks;
    use RefreshDatabase;

    private const ZERO_LINE = 'Forfeited 0 point(s) from 0 batch(es) and closed 0 voucher(s) on 0 deleted account(s).';

    /**
     * D1 (deleted): 300 points expiring in 30 days, 200 points already past
     * expiry and unswept, one active voucher. D2 (deleted): nothing. A (active):
     * 400 points and an active voucher.
     *
     * @return array<string, mixed>
     */
    private function leftovers(): array
    {
        $deleted = Guest::factory()->deleted()->create();
        $empty = Guest::factory()->deleted()->create();
        $active = Guest::factory()->create();

        $live = $this->grantPoints($deleted, 300, now()->addDays(30));
        $unswept = $this->grantPoints($deleted, 200, now()->subDay());
        $voucher = LoyaltyVoucher::factory()->create(['guest_id' => $deleted->id, 'expires_at' => now()->addDays(10)]);

        $activeBatch = $this->grantPoints($active, 400, now()->addDays(60));
        $activeVoucher = LoyaltyVoucher::factory()->create(['guest_id' => $active->id]);

        return compact('deleted', 'empty', 'active', 'live', 'unswept', 'voucher', 'activeBatch', 'activeVoucher');
    }

    public function test_it_forfeits_leftovers_on_deleted_accounts_and_leaves_active_guests_alone(): void
    {
        $f = $this->leftovers();
        $activeEntries = LoyaltyLedgerEntry::where('guest_id', $f['active']->id)->orderBy('id')->get()->toArray();

        $this->artisan('loyalty:forfeit-deleted')
            ->expectsOutputToContain('Forfeited 500 point(s) from 2 batch(es) and closed 1 voucher(s) on 1 deleted account(s).')
            ->assertSuccessful();

        foreach ([$f['live'], $f['unswept']] as $batch) {
            $fresh = $batch->fresh();
            $this->assertSame(LoyaltyBatchStatus::EXPIRED, $fresh->status);
            $this->assertSame(0, $fresh->points_remaining);

            $expire = LoyaltyLedgerEntry::where('type', LoyaltyEntryType::EXPIRE->value)
                ->where('batch_id', $batch->id)
                ->sole();
            $this->assertSame('expire:batch:'.$batch->id, $expire->idempotency_key);
            $this->assertSame(-$batch->points, $expire->points);
        }

        $this->assertSame(LoyaltyVoucherStatus::VOID, $f['voucher']->fresh()->status);

        $this->assertSame(LoyaltyBatchStatus::ACTIVE, $f['activeBatch']->fresh()->status);
        $this->assertSame(400, $f['activeBatch']->fresh()->points_remaining);
        $this->assertSame(LoyaltyVoucherStatus::ACTIVE, $f['activeVoucher']->fresh()->status);
        $this->assertSame(
            $activeEntries,
            LoyaltyLedgerEntry::where('guest_id', $f['active']->id)->orderBy('id')->get()->toArray(),
        );
        $this->assertSame(0, LoyaltyLedgerEntry::where('guest_id', $f['empty']->id)->count());
    }

    public function test_a_second_run_changes_nothing_and_prints_zeros(): void
    {
        $this->leftovers();

        $this->artisan('loyalty:forfeit-deleted')->assertSuccessful();
        $counts = $this->loyaltyRowCounts();

        $this->artisan('loyalty:forfeit-deleted')
            ->expectsOutputToContain(self::ZERO_LINE)
            ->assertSuccessful();

        $this->assertSame($counts, $this->loyaltyRowCounts());
    }

    public function test_an_empty_install_succeeds_with_zeros(): void
    {
        $this->assertDatabaseCount('loyalty_settings', 0);

        $this->artisan('loyalty:forfeit-deleted')
            ->expectsOutputToContain(self::ZERO_LINE)
            ->assertSuccessful();

        $this->assertSame(0, array_sum($this->loyaltyRowCounts()));
    }

    public function test_each_guest_is_locked_before_its_batches_and_vouchers(): void
    {
        $this->leftovers();

        $locked = $this->lockedSelects(fn () => app(ForfeitDeletedGuestBalancesAction::class)->handle());

        $first = function (string $table) use ($locked): int|false {
            foreach ($locked as $i => $sql) {
                if (str_contains($sql, 'from "'.$table.'"')) {
                    return $i;
                }
            }

            return false;
        };

        $guests = $first('guests');
        $batches = $first('loyalty_earn_batches');
        $vouchers = $first('loyalty_vouchers');
        $trace = implode("\n", $locked);

        $this->assertNotFalse($guests, $trace);
        $this->assertNotFalse($batches, $trace);
        $this->assertNotFalse($vouchers, $trace);
        $this->assertLessThan($batches, $guests, $trace);
        $this->assertLessThan($vouchers, $batches, $trace);
    }

    public function test_the_report_moves_the_leftovers_into_expired_points(): void
    {
        $this->configureLoyalty(['redeem_value_usd' => '0.0100']);
        $this->leftovers();

        $this->artisan('loyalty:forfeit-deleted')->assertSuccessful();

        $data = app(LoyaltyReportService::class)->report()['data'];
        $this->assertSame(500, $data['expired_points']);
        $this->assertSame(400, $data['outstanding_points']);
        $this->assertSame('4.00', $data['liability_usd']);
    }

    public function test_the_action_returns_the_counts(): void
    {
        $f = $this->leftovers();

        $this->assertSame(
            ['data' => ['accounts' => 1, 'forfeited_points' => 500, 'expired_batches' => 2, 'closed_vouchers' => 1], 'code' => 200],
            app(ForfeitDeletedGuestBalancesAction::class)->handle(),
        );
        $this->assertSame(0, LoyaltyEarnBatch::where('guest_id', $f['deleted']->id)
            ->where('status', LoyaltyBatchStatus::ACTIVE->value)
            ->count());
    }
}
