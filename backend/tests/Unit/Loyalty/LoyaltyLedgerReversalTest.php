<?php

namespace Tests\Unit\Loyalty;

use App\Enums\LoyaltyBatchSource;
use App\Enums\LoyaltyBatchStatus;
use App\Enums\LoyaltyEntryType;
use App\Models\Guest;
use App\Models\LoyaltyEarnBatch;
use App\Models\LoyaltyLedgerEntry;
use App\Models\LoyaltyVoucher;
use App\Models\Reservation;
use App\Support\LoyaltyLedger;
use App\Support\LoyaltyProgram;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Concerns\BuildsLoyaltyFixtures;
use Tests\Concerns\RecordsRowLocks;
use Tests\TestCase;

/**
 * Phase 10 (Q2, Q3, LOY-13, LOY-17, Pitfalls 3-4): refund of spent points,
 * clawback of earned points and the expiry write of LoyaltyLedger. Every
 * reversal is idempotent and no path can drive a balance below zero.
 */
class LoyaltyLedgerReversalTest extends TestCase
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

    /** A redeem entry the way the redeem action will write it: consume FIFO, then record. */
    private function redeem(Guest $guest, int $points, array $extra = []): LoyaltyLedgerEntry
    {
        return $this->atomically($guest, function () use ($guest, $points, $extra) {
            $allocations = $this->ledger()->consume($guest->id, $points);

            return $this->ledger()->record(array_merge([
                'guest_id' => $guest->id,
                'type' => LoyaltyEntryType::REDEEM,
                'points' => -$points,
                'idempotency_key' => 'redeem:test:'.Str::uuid(),
            ], $extra), $allocations);
        });
    }

    /** The earn entry grantPoints() wrote for a batch. */
    private function earnEntryOf(LoyaltyEarnBatch $batch): LoyaltyLedgerEntry
    {
        return LoyaltyLedgerEntry::query()->where('batch_id', $batch->id)->firstOrFail();
    }

    private function refund(Guest $guest, LoyaltyLedgerEntry $redeem): LoyaltyLedgerEntry
    {
        return $this->atomically($guest, fn () => $this->ledger()->refund($redeem));
    }

    private function clawback(Guest $guest, LoyaltyLedgerEntry $earn): LoyaltyLedgerEntry
    {
        return $this->atomically($guest, fn () => $this->ledger()->clawback($earn));
    }

    // ---- refund (Q3) ----------------------------------------------------

    public function test_refund_restores_points_into_the_still_valid_original_batch(): void
    {
        $guest = Guest::factory()->create();
        $batch = $this->grantPoints($guest, 200, now()->addDays(60));
        $redeem = $this->redeem($guest, 150);
        $this->assertSame(50, $batch->refresh()->points_remaining);

        $refund = $this->refund($guest, $redeem)->refresh();

        $batch->refresh();
        $this->assertSame(200, $batch->points_remaining);
        $this->assertSame(LoyaltyBatchStatus::ACTIVE, $batch->status);
        $this->assertSame(1, LoyaltyEarnBatch::query()->count());

        $this->assertSame(LoyaltyEntryType::REFUND, $refund->type);
        $this->assertSame(LoyaltyBatchSource::REFUND, $refund->source);
        $this->assertSame(150, $refund->points);
        $this->assertSame($redeem->id, $refund->reverses_entry_id);
        $this->assertSame('refund:'.$redeem->id, $refund->idempotency_key);
        $this->assertSame($guest->id, $refund->guest_id);
        $this->assertNull($refund->batch_id);
        $this->assertNotNull($refund->occurred_at);
        $this->assertSame([$batch->id => 150], $refund->allocations()->pluck('points', 'batch_id')->all());
    }

    public function test_refund_copies_reservation_and_voucher_from_the_redeem(): void
    {
        $guest = Guest::factory()->create();
        $this->grantPoints($guest, 200, now()->addDays(60));
        $reservation = Reservation::factory()->create();
        $voucher = LoyaltyVoucher::factory()->create(['guest_id' => $guest->id]);
        $redeem = $this->redeem($guest, 100, ['reservation_id' => $reservation->id, 'voucher_id' => $voucher->id]);

        $refund = $this->refund($guest, $redeem)->refresh();

        $this->assertSame($reservation->id, $refund->reservation_id);
        $this->assertSame($voucher->id, $refund->voucher_id);
    }

    public function test_refund_revives_a_depleted_batch_that_has_not_expired(): void
    {
        $guest = Guest::factory()->create();
        $batch = $this->grantPoints($guest, 150, now()->addDays(60));
        $redeem = $this->redeem($guest, 150);
        $this->assertSame(LoyaltyBatchStatus::DEPLETED, $batch->refresh()->status);

        $this->refund($guest, $redeem);

        $batch->refresh();
        $this->assertSame(150, $batch->points_remaining);
        $this->assertSame(LoyaltyBatchStatus::ACTIVE, $batch->status);
        $this->assertSame(150, $this->ledger()->available($guest->id));
    }

    public function test_refund_after_the_original_batch_expired_creates_one_new_full_term_batch(): void
    {
        $this->configureLoyalty();
        $guest = Guest::factory()->create();
        $old = $this->grantPoints($guest, 200, now()->addDays(30));
        $redeem = $this->redeem($guest, 150);

        $this->travelTo(now()->addDays(31));
        $refund = $this->refund($guest, $redeem)->refresh();

        $old->refresh();
        $this->assertSame(50, $old->points_remaining);
        $this->assertSame(LoyaltyBatchStatus::ACTIVE, $old->status);

        $created = LoyaltyEarnBatch::query()->where('id', '!=', $old->id)->get();
        $this->assertCount(1, $created);
        $new = $created->first();
        $this->assertSame(LoyaltyBatchSource::REFUND, $new->source);
        $this->assertSame(150, $new->points);
        $this->assertSame(150, $new->points_remaining);
        $this->assertSame(LoyaltyBatchStatus::ACTIVE, $new->status);
        $this->assertSame(
            LoyaltyProgram::current()->expiresAtFrom(now())->format('Y-m-d H:i:s'),
            $new->expires_at->format('Y-m-d H:i:s'),
        );

        $this->assertSame($new->id, $refund->batch_id);
        $this->assertSame(150, $refund->points);
        $this->assertSame([$new->id => 150], $refund->allocations()->pluck('points', 'batch_id')->all());
        $this->assertSame(150, $this->ledger()->available($guest->id));
    }

    public function test_refund_splits_between_an_unexpired_batch_and_a_new_batch_for_a_reversed_one(): void
    {
        $this->configureLoyalty();
        $guest = Guest::factory()->create();
        $first = $this->grantPoints($guest, 100, now()->addDays(10));
        $second = $this->grantPoints($guest, 200, now()->addDays(20));
        $redeem = $this->redeem($guest, 250);
        $first->refresh()->forceFill(['status' => LoyaltyBatchStatus::REVERSED])->save();

        $refund = $this->refund($guest, $redeem)->refresh();

        $this->assertSame(200, $second->refresh()->points_remaining);
        $this->assertSame(LoyaltyBatchStatus::ACTIVE, $second->status);
        $this->assertSame(0, $first->refresh()->points_remaining);
        $this->assertSame(LoyaltyBatchStatus::REVERSED, $first->status);

        $new = LoyaltyEarnBatch::query()->whereNotIn('id', [$first->id, $second->id])->sole();
        $this->assertSame(LoyaltyBatchSource::REFUND, $new->source);
        $this->assertSame(100, $new->points_remaining);

        $this->assertSame(250, $refund->points);
        $this->assertSame($new->id, $refund->batch_id);
        $this->assertEqualsCanonicalizing(
            [$second->id => 150, $new->id => 100],
            $refund->allocations()->pluck('points', 'batch_id')->all(),
        );
        $this->assertSame(300, $this->ledger()->available($guest->id));
    }

    public function test_refund_is_idempotent_and_the_second_call_writes_nothing(): void
    {
        $guest = Guest::factory()->create();
        $batch = $this->grantPoints($guest, 200, now()->addDays(60));
        $redeem = $this->redeem($guest, 150);

        $first = $this->refund($guest, $redeem);
        $entries = LoyaltyLedgerEntry::query()->count();
        $second = $this->refund($guest, $redeem->fresh());

        $this->assertSame($first->id, $second->id);
        $this->assertSame($entries, LoyaltyLedgerEntry::query()->count());
        $this->assertSame(200, $batch->refresh()->points_remaining);
    }

    public function test_the_unique_reverses_entry_id_index_is_the_backstop(): void
    {
        $guest = Guest::factory()->create();
        $this->grantPoints($guest, 200, now()->addDays(60));
        $redeem = $this->redeem($guest, 150);
        $this->refund($guest, $redeem);

        $this->expectException(UniqueConstraintViolationException::class);
        LoyaltyLedgerEntry::factory()->create([
            'guest_id' => $guest->id,
            'type' => LoyaltyEntryType::REFUND,
            'points' => 150,
            'reverses_entry_id' => $redeem->id,
        ]);
    }

    // ---- clawback (Q2) --------------------------------------------------

    public function test_clawback_of_untouched_points_takes_everything_and_reverses_the_origin(): void
    {
        $guest = Guest::factory()->create();
        $origin = $this->grantPoints($guest, 500, now()->addDays(60), LoyaltyBatchSource::STAY);
        $earn = $this->earnEntryOf($origin);

        $claw = $this->clawback($guest, $earn)->refresh();

        $this->assertSame(LoyaltyEntryType::CLAWBACK, $claw->type);
        $this->assertSame(LoyaltyBatchSource::STAY, $claw->source);
        $this->assertSame(-500, $claw->points);
        $this->assertSame(0, $claw->shortfall_points);
        $this->assertSame($earn->id, $claw->reverses_entry_id);
        $this->assertSame('clawback:'.$earn->id, $claw->idempotency_key);
        $this->assertSame([$origin->id => 500], $claw->allocations()->pluck('points', 'batch_id')->all());

        $origin->refresh();
        $this->assertSame(0, $origin->points_remaining);
        $this->assertSame(LoyaltyBatchStatus::REVERSED, $origin->status);
        $this->assertSame(0, $this->ledger()->available($guest->id));
    }

    public function test_clawback_of_partly_spent_points_takes_the_rest_from_other_batches_and_records_the_shortfall(): void
    {
        $guest = Guest::factory()->create();
        $origin = $this->grantPoints($guest, 500, now()->addDays(10), LoyaltyBatchSource::STAY);
        $other = $this->grantPoints($guest, 100, now()->addDays(20));
        $earn = $this->earnEntryOf($origin);
        $this->redeem($guest, 300);
        $this->assertSame(200, $origin->refresh()->points_remaining);

        $claw = $this->clawback($guest, $earn)->refresh();

        $this->assertSame(-300, $claw->points);
        $this->assertSame(200, $claw->shortfall_points);
        $this->assertEqualsCanonicalizing(
            [$origin->id => 200, $other->id => 100],
            $claw->allocations()->pluck('points', 'batch_id')->all(),
        );

        $origin->refresh();
        $other->refresh();
        $this->assertSame(0, $origin->points_remaining);
        $this->assertSame(LoyaltyBatchStatus::REVERSED, $origin->status);
        $this->assertSame(0, $other->points_remaining);
        $this->assertSame(LoyaltyBatchStatus::DEPLETED, $other->status);
        $this->assertSame(0, $this->ledger()->available($guest->id));
        $this->assertSame(0, LoyaltyEarnBatch::query()->where('points_remaining', '<', 0)->count());
    }

    public function test_clawback_of_fully_spent_points_with_nothing_else_writes_a_zero_entry_with_the_whole_shortfall(): void
    {
        $guest = Guest::factory()->create();
        $origin = $this->grantPoints($guest, 500, now()->addDays(60), LoyaltyBatchSource::STAY);
        $earn = $this->earnEntryOf($origin);
        $this->redeem($guest, 500);

        $claw = $this->clawback($guest, $earn)->refresh();

        $this->assertSame(LoyaltyEntryType::CLAWBACK, $claw->type);
        $this->assertSame(0, $claw->points);
        $this->assertSame(500, $claw->shortfall_points);
        $this->assertSame($earn->id, $claw->reverses_entry_id);
        $this->assertSame(0, $claw->allocations()->count());
        $this->assertSame(LoyaltyBatchStatus::REVERSED, $origin->refresh()->status);
        $this->assertSame(0, $this->ledger()->available($guest->id));
    }

    public function test_clawback_with_an_expired_origin_leaves_it_expired_and_takes_from_other_active_batches(): void
    {
        $guest = Guest::factory()->create();
        $origin = LoyaltyEarnBatch::factory()->expired()->create([
            'guest_id' => $guest->id,
            'source' => LoyaltyBatchSource::STAY,
            'points' => 500,
        ]);
        $earn = LoyaltyLedgerEntry::factory()->create([
            'guest_id' => $guest->id,
            'type' => LoyaltyEntryType::EARN,
            'source' => LoyaltyBatchSource::STAY,
            'points' => 500,
            'batch_id' => $origin->id,
        ]);
        $other = $this->grantPoints($guest, 700, now()->addDays(40));

        $claw = $this->clawback($guest, $earn)->refresh();

        $this->assertSame(-500, $claw->points);
        $this->assertSame(0, $claw->shortfall_points);
        $this->assertSame([$other->id => 500], $claw->allocations()->pluck('points', 'batch_id')->all());
        $this->assertSame(LoyaltyBatchStatus::EXPIRED, $origin->refresh()->status);
        $this->assertSame(200, $other->refresh()->points_remaining);
        $this->assertSame(LoyaltyBatchStatus::ACTIVE, $other->status);
    }

    public function test_clawback_never_takes_from_an_expired_unswept_other_batch(): void
    {
        $guest = Guest::factory()->create();
        $origin = $this->grantPoints($guest, 500, now()->addDays(60), LoyaltyBatchSource::STAY);
        $earn = $this->earnEntryOf($origin);
        $this->redeem($guest, 500);
        $stale = $this->grantPoints($guest, 400, now()->addDays(1));

        $this->travelTo(now()->addDays(2));
        $claw = $this->clawback($guest, $earn)->refresh();

        $this->assertSame(0, $claw->points);
        $this->assertSame(500, $claw->shortfall_points);
        $this->assertSame(400, $stale->refresh()->points_remaining);
    }

    public function test_clawback_copies_folio_and_reservation_from_the_earn_entry(): void
    {
        $guest = Guest::factory()->create();
        [$reservation, $folio] = $this->generatedStay();
        $origin = $this->grantPoints($guest, 300, now()->addDays(60), LoyaltyBatchSource::STAY);
        $earn = $this->earnEntryOf($origin);
        $earn->forceFill(['folio_id' => $folio->id, 'reservation_id' => $reservation->id])->save();

        $claw = $this->clawback($guest, $earn)->refresh();

        $this->assertSame($folio->id, $claw->folio_id);
        $this->assertSame($reservation->id, $claw->reservation_id);
    }

    public function test_clawback_is_idempotent_and_the_second_call_writes_nothing(): void
    {
        $guest = Guest::factory()->create();
        $origin = $this->grantPoints($guest, 500, now()->addDays(60), LoyaltyBatchSource::STAY);
        $other = $this->grantPoints($guest, 100, now()->addDays(70));
        $earn = $this->earnEntryOf($origin);

        $first = $this->clawback($guest, $earn);
        $entries = LoyaltyLedgerEntry::query()->count();
        $second = $this->clawback($guest, $earn->fresh());

        $this->assertSame($first->id, $second->id);
        $this->assertSame($entries, LoyaltyLedgerEntry::query()->count());
        $this->assertSame(100, $other->refresh()->points_remaining);
        $this->assertSame(0, $second->refresh()->shortfall_points);
    }

    public function test_a_second_reversal_row_for_the_same_earn_is_rejected_by_the_database(): void
    {
        $guest = Guest::factory()->create();
        $origin = $this->grantPoints($guest, 500, now()->addDays(60), LoyaltyBatchSource::STAY);
        $earn = $this->earnEntryOf($origin);
        $this->clawback($guest, $earn);

        $this->expectException(UniqueConstraintViolationException::class);
        LoyaltyLedgerEntry::factory()->create([
            'guest_id' => $guest->id,
            'type' => LoyaltyEntryType::CLAWBACK,
            'points' => -500,
            'reverses_entry_id' => $earn->id,
        ]);
    }

    // ---- expire ---------------------------------------------------------

    public function test_expire_writes_a_negative_entry_with_one_allocation_and_empties_the_batch(): void
    {
        $guest = Guest::factory()->create();
        $batch = $this->grantPoints($guest, 120, now()->subDay());

        $entry = $this->atomically($guest, fn () => $this->ledger()->expire($batch));

        $this->assertInstanceOf(LoyaltyLedgerEntry::class, $entry);
        $entry->refresh();
        $this->assertSame(LoyaltyEntryType::EXPIRE, $entry->type);
        $this->assertSame(-120, $entry->points);
        $this->assertSame($guest->id, $entry->guest_id);
        $this->assertSame($batch->id, $entry->batch_id);
        $this->assertSame('expire:batch:'.$batch->id, $entry->idempotency_key);
        $this->assertSame([$batch->id => 120], $entry->allocations()->pluck('points', 'batch_id')->all());

        $batch->refresh();
        $this->assertSame(0, $batch->points_remaining);
        $this->assertSame(LoyaltyBatchStatus::EXPIRED, $batch->status);
    }

    public function test_expire_is_a_no_op_for_a_batch_that_is_no_longer_active_or_is_empty(): void
    {
        $guest = Guest::factory()->create();
        $batch = $this->grantPoints($guest, 120, now()->subDay());
        $this->atomically($guest, fn () => $this->ledger()->expire($batch));
        $entries = LoyaltyLedgerEntry::query()->count();

        // The caller still holds the stale in-memory model (status active): it must be re-read under lock.
        $this->assertSame(LoyaltyBatchStatus::ACTIVE, $batch->status);
        $this->assertNull($this->atomically($guest, fn () => $this->ledger()->expire($batch)));

        $depleted = LoyaltyEarnBatch::factory()->depleted()->create(['guest_id' => $guest->id, 'expires_at' => now()->subDay()]);
        $emptyActive = LoyaltyEarnBatch::factory()->create([
            'guest_id' => $guest->id,
            'points_remaining' => 0,
            'expires_at' => now()->subDay(),
        ]);
        $this->assertNull($this->atomically($guest, fn () => $this->ledger()->expire($depleted)));
        $this->assertNull($this->atomically($guest, fn () => $this->ledger()->expire($emptyActive)));

        $this->assertSame($entries, LoyaltyLedgerEntry::query()->count());
    }

    // ---- locking --------------------------------------------------------

    public function test_refund_clawback_and_expire_lock_batch_rows_for_update(): void
    {
        $guest = Guest::factory()->create();
        $spendable = $this->grantPoints($guest, 300, now()->addDays(60), LoyaltyBatchSource::STAY);
        $earn = $this->earnEntryOf($spendable);
        $redeem = $this->redeem($guest, 100);
        $stale = $this->grantPoints($guest, 50, now()->subDay());

        $runs = [
            'refund' => fn () => $this->refund($guest, $redeem),
            'clawback' => fn () => $this->clawback($guest, $earn),
            'expire' => fn () => $this->atomically($guest, fn () => $this->ledger()->expire($stale)),
        ];

        foreach ($runs as $name => $run) {
            $locked = $this->lockedSelects($run);
            $this->assertNotEmpty(
                array_filter($locked, fn (string $sql) => str_contains($sql, 'loyalty_earn_batches')),
                "{$name}() must lock loyalty_earn_batches; locked statements:\n".implode("\n", $locked ?: ['(none)']),
            );
        }
    }
}
