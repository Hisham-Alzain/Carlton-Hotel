<?php

namespace Tests\Unit\Loyalty;

use App\Enums\LoyaltyBatchSource;
use App\Enums\LoyaltyBatchStatus;
use App\Enums\LoyaltyEntryType;
use App\Exceptions\LoyaltyInsufficientPointsException;
use App\Models\Guest;
use App\Models\LoyaltyAllocation;
use App\Models\LoyaltyEarnBatch;
use App\Models\LoyaltyLedgerEntry;
use App\Models\User;
use App\Support\LoyaltyLedger;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use LogicException;
use Tests\Concerns\BuildsLoyaltyFixtures;
use Tests\Concerns\RecordsRowLocks;
use Tests\TestCase;

/**
 * Phase 10 (Q14, LOY-08, Pitfall 5): the balance queries and FIFO consumption
 * of LoyaltyLedger. Mutating calls run the way real callers will: inside a
 * transaction with the guest row already locked.
 */
class LoyaltyLedgerFifoTest extends TestCase
{
    use BuildsLoyaltyFixtures;
    use RecordsRowLocks;
    use RefreshDatabase;

    private function ledger(): LoyaltyLedger
    {
        return app(LoyaltyLedger::class);
    }

    private function atomically(Guest $guest, callable $run): mixed
    {
        return DB::transaction(function () use ($guest, $run) {
            Guest::query()->whereKey($guest->id)->lockForUpdate()->firstOrFail();

            return $run();
        });
    }

    public function test_consume_drains_the_earliest_expiry_first_even_when_created_out_of_order(): void
    {
        $guest = Guest::factory()->create();
        $third = $this->grantPoints($guest, 300, now()->addDays(30));
        $first = $this->grantPoints($guest, 100, now()->addDays(10));
        $second = $this->grantPoints($guest, 200, now()->addDays(20));

        $allocations = $this->atomically($guest, fn () => $this->ledger()->consume($guest->id, 250));

        $this->assertSame([
            ['batch_id' => $first->id, 'points' => 100],
            ['batch_id' => $second->id, 'points' => 150],
        ], $allocations);
        $this->assertSame(250, array_sum(array_column($allocations, 'points')));

        $first->refresh();
        $this->assertSame(0, $first->points_remaining);
        $this->assertSame(LoyaltyBatchStatus::DEPLETED, $first->status);

        $second->refresh();
        $this->assertSame(50, $second->points_remaining);
        $this->assertSame(LoyaltyBatchStatus::ACTIVE, $second->status);

        $third->refresh();
        $this->assertSame(300, $third->points_remaining);
        $this->assertSame(LoyaltyBatchStatus::ACTIVE, $third->status);
    }

    public function test_equal_expiry_is_consumed_in_id_order(): void
    {
        $guest = Guest::factory()->create();
        $at = now()->addDays(5);
        $older = $this->grantPoints($guest, 100, $at);
        $newer = $this->grantPoints($guest, 100, $at);

        $allocations = $this->atomically($guest, fn () => $this->ledger()->consume($guest->id, 130));

        $this->assertSame([
            ['batch_id' => $older->id, 'points' => 100],
            ['batch_id' => $newer->id, 'points' => 30],
        ], $allocations);
    }

    public function test_consume_exactly_the_balance_depletes_everything(): void
    {
        $guest = Guest::factory()->create();
        $a = $this->grantPoints($guest, 100, now()->addDays(10));
        $b = $this->grantPoints($guest, 200, now()->addDays(20));

        $this->atomically($guest, fn () => $this->ledger()->consume($guest->id, 300));

        $this->assertSame(LoyaltyBatchStatus::DEPLETED, $a->refresh()->status);
        $this->assertSame(LoyaltyBatchStatus::DEPLETED, $b->refresh()->status);
        $this->assertSame(0, $this->ledger()->available($guest->id));
    }

    public function test_insufficient_points_throws_with_context_and_changes_nothing(): void
    {
        $guest = Guest::factory()->create();
        $this->grantPoints($guest, 100, now()->addDays(10));
        $this->grantPoints($guest, 200, now()->addDays(20));
        $this->grantPoints($guest, 300, now()->addDays(30));
        $before = LoyaltyEarnBatch::query()->orderBy('id')->pluck('points_remaining', 'id')->all();

        try {
            $this->atomically($guest, fn () => $this->ledger()->consume($guest->id, 700));
            $this->fail('Expected LoyaltyInsufficientPointsException');
        } catch (LoyaltyInsufficientPointsException $e) {
            $this->assertSame('loyalty_insufficient_points', $e->errorCode());
            $this->assertSame(422, $e->statusCode());
            $this->assertSame(['available_points' => 600, 'requested_points' => 700], $e->context());
        }

        $this->assertSame($before, LoyaltyEarnBatch::query()->orderBy('id')->pluck('points_remaining', 'id')->all());
        $this->assertSame(0, LoyaltyAllocation::query()->count());
    }

    public function test_a_batch_is_gone_at_its_expiry_instant_even_while_its_status_is_still_active(): void
    {
        $guest = Guest::factory()->create();
        $expiresAt = CarbonImmutable::parse('2027-03-01 12:00:00');
        $batch = $this->grantPoints($guest, 500, $expiresAt);

        $this->travelTo($expiresAt->subSecond());
        $this->assertSame(500, $this->ledger()->available($guest->id));
        $this->assertSame(500, $this->ledger()->balances($guest->id, 30)['available']);

        $this->travelTo($expiresAt);
        $this->assertSame(0, $this->ledger()->available($guest->id));
        $this->assertSame(0, $this->ledger()->balances($guest->id, 30)['available']);
        $this->assertNull($this->ledger()->balances($guest->id, 30)['next_expiry_at']);

        try {
            $this->atomically($guest, fn () => $this->ledger()->consume($guest->id, 1));
            $this->fail('An expired-but-unswept batch must not be consumable');
        } catch (LoyaltyInsufficientPointsException $e) {
            $this->assertSame(['available_points' => 0, 'requested_points' => 1], $e->context());
        }

        $this->travelTo($expiresAt->addSecond());
        $this->assertSame(0, $this->ledger()->available($guest->id));

        $batch->refresh();
        $this->assertSame(LoyaltyBatchStatus::ACTIVE, $batch->status);
        $this->assertSame(500, $batch->points_remaining);
    }

    public function test_consume_skips_an_expired_unswept_batch_and_uses_the_next_one(): void
    {
        $guest = Guest::factory()->create();
        $stale = $this->grantPoints($guest, 400, now()->addDays(1));
        $live = $this->grantPoints($guest, 400, now()->addDays(40));

        $this->travelTo(now()->addDays(2));
        $allocations = $this->atomically($guest, fn () => $this->ledger()->consume($guest->id, 150));

        $this->assertSame([['batch_id' => $live->id, 'points' => 150]], $allocations);
        $this->assertSame(400, $stale->refresh()->points_remaining);
    }

    public function test_other_guests_and_inactive_batches_are_never_counted_or_touched(): void
    {
        $guest = Guest::factory()->create();
        $stranger = Guest::factory()->create();
        $mine = $this->grantPoints($guest, 100, now()->addDays(10));
        $theirs = $this->grantPoints($stranger, 900, now()->addDays(5));
        $depleted = LoyaltyEarnBatch::factory()->depleted()->create(['guest_id' => $guest->id, 'expires_at' => now()->addDays(2)]);
        $expired = LoyaltyEarnBatch::factory()->expired()->create(['guest_id' => $guest->id]);
        $reversed = LoyaltyEarnBatch::factory()->create([
            'guest_id' => $guest->id,
            'points' => 700,
            'points_remaining' => 700,
            'status' => LoyaltyBatchStatus::REVERSED,
            'expires_at' => now()->addDays(3),
        ]);

        $this->assertSame(100, $this->ledger()->available($guest->id));
        $this->assertSame(900, $this->ledger()->available($stranger->id));

        $this->atomically($guest, fn () => $this->ledger()->consume($guest->id, 100));

        $this->assertSame(0, $mine->refresh()->points_remaining);
        $this->assertSame(900, $theirs->refresh()->points_remaining);
        $this->assertSame(700, $reversed->refresh()->points_remaining);
        $this->assertSame(LoyaltyBatchStatus::DEPLETED, $depleted->refresh()->status);
        $this->assertSame(LoyaltyBatchStatus::EXPIRED, $expired->refresh()->status);
    }

    public function test_balances_returns_available_expiring_soon_and_next_expiry_in_one_query(): void
    {
        $guest = Guest::factory()->create();
        $soon = $this->grantPoints($guest, 100, now()->addDays(10));
        $this->grantPoints($guest, 200, now()->addDays(20));
        $this->grantPoints($guest, 300, now()->addDays(40));
        $this->grantPoints(Guest::factory()->create(), 999, now()->addDays(1));
        LoyaltyEarnBatch::factory()->expired()->create(['guest_id' => $guest->id]);
        $soon->refresh();

        DB::flushQueryLog();
        DB::enableQueryLog();
        $balances = $this->ledger()->balances($guest->id, 30);
        $queries = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertSame(1, $queries);
        $this->assertSame(600, $balances['available']);
        $this->assertSame(300, $balances['expiring_soon']);
        $this->assertTrue($balances['next_expiry_at']->equalTo($soon->expires_at));
        $this->assertInstanceOf(CarbonImmutable::class, $balances['next_expiry_at']);
    }

    public function test_balances_for_a_guest_with_no_points_is_all_zero(): void
    {
        $guest = Guest::factory()->create();

        $this->assertSame(
            ['available' => 0, 'expiring_soon' => 0, 'next_expiry_at' => null],
            $this->ledger()->balances($guest->id, 30),
        );
        $this->assertSame(0, $this->ledger()->available($guest->id));
    }

    public function test_credit_creates_an_active_batch_with_the_given_attributes(): void
    {
        $guest = Guest::factory()->create();
        $staff = User::factory()->create();
        [, $folio] = $this->generatedStay();
        $expiresAt = CarbonImmutable::parse('2028-01-15 23:59:59');

        $batch = $this->ledger()->credit($guest->id, 400, LoyaltyBatchSource::STAY, $expiresAt, [
            'folio_id' => $folio->id,
            'awarded_by' => $staff->id,
            'reason' => 'Goodwill',
            'status' => 'reversed',
        ]);

        $batch->refresh();
        $this->assertSame($guest->id, $batch->guest_id);
        $this->assertSame(400, $batch->points);
        $this->assertSame(400, $batch->points_remaining);
        $this->assertSame(LoyaltyBatchSource::STAY, $batch->source);
        $this->assertSame(LoyaltyBatchStatus::ACTIVE, $batch->status);
        $this->assertTrue($expiresAt->equalTo($batch->expires_at));
        $this->assertSame($folio->id, $batch->folio_id);
        $this->assertSame($staff->id, $batch->awarded_by);
        $this->assertSame('Goodwill', $batch->reason);
        $this->assertNotNull($batch->earned_at);
    }

    public function test_credit_and_consume_reject_non_positive_points(): void
    {
        $guest = Guest::factory()->create();

        foreach ([0, -5] as $points) {
            try {
                $this->ledger()->credit($guest->id, $points, LoyaltyBatchSource::MANUAL, CarbonImmutable::now()->addDay());
                $this->fail('credit accepted '.$points);
            } catch (LogicException) {
                $this->assertSame(0, LoyaltyEarnBatch::query()->count());
            }

            try {
                $this->atomically($guest, fn () => $this->ledger()->consume($guest->id, $points));
                $this->fail('consume accepted '.$points);
            } catch (LogicException) {
                $this->assertTrue(true);
            }
        }
    }

    public function test_record_writes_the_entry_with_occurred_at_and_one_allocation_per_batch(): void
    {
        $guest = Guest::factory()->create();
        $a = $this->grantPoints($guest, 100, now()->addDays(10));
        $b = $this->grantPoints($guest, 200, now()->addDays(20));
        $this->travelTo(CarbonImmutable::parse('2027-05-05 09:30:00'));

        $entry = $this->ledger()->record([
            'guest_id' => $guest->id,
            'type' => LoyaltyEntryType::REDEEM,
            'points' => -150,
            'idempotency_key' => 'redeem:test:1',
        ], [
            ['batch_id' => $a->id, 'points' => 100],
            ['batch_id' => $b->id, 'points' => 50],
        ]);

        $entry->refresh();
        $this->assertSame(LoyaltyEntryType::REDEEM, $entry->type);
        $this->assertSame(-150, $entry->points);
        $this->assertSame('2027-05-05 09:30:00', $entry->occurred_at->format('Y-m-d H:i:s'));
        $this->assertSame(
            [$a->id => 100, $b->id => 50],
            $entry->allocations()->orderBy('batch_id')->pluck('points', 'batch_id')->all(),
        );
    }

    public function test_record_rejects_allocations_that_do_not_sum_to_the_entry_and_writes_nothing(): void
    {
        $guest = Guest::factory()->create();
        $batch = $this->grantPoints($guest, 200, now()->addDays(10));
        $entriesBefore = LoyaltyLedgerEntry::query()->count();

        try {
            $this->ledger()->record([
                'guest_id' => $guest->id,
                'type' => LoyaltyEntryType::REDEEM,
                'points' => -150,
                'idempotency_key' => 'redeem:test:bad',
            ], [['batch_id' => $batch->id, 'points' => 100]]);
            $this->fail('Expected LogicException');
        } catch (LogicException) {
            $this->assertSame($entriesBefore, LoyaltyLedgerEntry::query()->count());
            $this->assertSame(0, LoyaltyAllocation::query()->count());
        }
    }

    public function test_consume_locks_the_guests_batches_for_update(): void
    {
        $guest = Guest::factory()->create();
        $this->grantPoints($guest, 100, now()->addDays(10));

        $locked = $this->lockedSelects(fn () => $this->atomically($guest, fn () => $this->ledger()->consume($guest->id, 40)));

        $this->assertNotEmpty(
            array_filter($locked, fn (string $sql) => str_contains($sql, 'loyalty_earn_batches')),
            "consume() must lock loyalty_earn_batches; locked statements:\n".implode("\n", $locked ?: ['(none)']),
        );
    }

    public function test_consume_records_nothing_in_the_ledger_by_itself(): void
    {
        $guest = Guest::factory()->create();
        $this->grantPoints($guest, 100, now()->addDays(10));
        $entries = LoyaltyLedgerEntry::query()->count();

        $this->atomically($guest, fn () => $this->ledger()->consume($guest->id, 40));

        $this->assertSame($entries, LoyaltyLedgerEntry::query()->count());
        $this->assertSame(0, LoyaltyAllocation::query()->count());
    }
}
