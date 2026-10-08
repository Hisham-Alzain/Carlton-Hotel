<?php

namespace Tests\Unit\Loyalty;

use App\Actions\Loyalty\ExpireLoyaltyBatchesAction;
use App\Enums\LoyaltyBatchStatus;
use App\Enums\LoyaltyEntryType;
use App\Models\Guest;
use App\Models\LoyaltyEarnBatch;
use App\Models\LoyaltyLedgerEntry;
use App\Models\LoyaltySetting;
use App\Support\LoyaltyLedger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\BuildsLoyaltyFixtures;
use Tests\Concerns\RecordsRowLocks;
use Tests\TestCase;

/**
 * Phase 10 gap LOY-23: LoyaltyLedger::forfeit() empties every active batch of
 * one guest through expire() when the account is deleted, FIFO, idempotently,
 * never negative and without reading a program setting. Calls run the way the
 * real callers do: inside a transaction with the guest row already locked.
 *
 * No loyalty_settings row is created anywhere in this class on purpose.
 */
class LoyaltyLedgerForfeitTest extends TestCase
{
    use BuildsLoyaltyFixtures;
    use RecordsRowLocks;
    use RefreshDatabase;

    private function ledger(): LoyaltyLedger
    {
        return app(LoyaltyLedger::class);
    }

    /** @return array{points: int, batches: int} */
    private function forfeit(Guest $guest): array
    {
        return DB::transaction(function () use ($guest): array {
            Guest::query()->whereKey($guest->id)->lockForUpdate()->firstOrFail();

            return $this->ledger()->forfeit($guest->id);
        });
    }

    private function expireEntryOf(LoyaltyEarnBatch $batch): LoyaltyLedgerEntry
    {
        return LoyaltyLedgerEntry::query()
            ->where('type', LoyaltyEntryType::EXPIRE->value)
            ->where('batch_id', $batch->id)
            ->sole();
    }

    /** @return array{0: Guest, 1: LoyaltyEarnBatch, 2: LoyaltyEarnBatch, 3: LoyaltyEarnBatch} */
    private function guestWithThreeActiveBatches(): array
    {
        $this->freezeTime();
        $guest = Guest::factory()->create();
        $b1 = $this->grantPoints($guest, 40, now()->addDays(10));
        $b2 = $this->grantPoints($guest, 25, now()->addDays(3));
        $b3 = $this->grantPoints($guest, 15, now()->addDays(3));

        return [$guest, $b1, $b2, $b3];
    }

    public function test_forfeit_expires_every_active_batch_of_the_guest_and_nothing_else(): void
    {
        [$guest, $b1, $b2, $b3] = $this->guestWithThreeActiveBatches();
        $depleted = LoyaltyEarnBatch::factory()->depleted()->create(['guest_id' => $guest->id]);
        $expired = LoyaltyEarnBatch::factory()->expired()->create(['guest_id' => $guest->id]);
        $reversed = LoyaltyEarnBatch::factory()->create([
            'guest_id' => $guest->id, 'status' => LoyaltyBatchStatus::REVERSED, 'points_remaining' => 0,
        ]);
        $other = Guest::factory()->create();
        $othersBatch = $this->grantPoints($other, 70, now()->addDays(5));
        $untouched = collect([$depleted, $expired, $reversed, $othersBatch])
            ->mapWithKeys(fn (LoyaltyEarnBatch $b) => [$b->id => $b->fresh()->getAttributes()]);

        $result = $this->forfeit($guest);

        $this->assertSame(['points' => 80, 'batches' => 3], $result);

        foreach ([$b1, $b2, $b3] as $batch) {
            $remaining = $batch->points_remaining;
            $entry = $this->expireEntryOf($batch);
            $this->assertSame(-$remaining, $entry->points);
            $this->assertSame($guest->id, (int) $entry->guest_id);
            $this->assertSame('expire:batch:'.$batch->id, $entry->idempotency_key);
            $this->assertCount(1, $entry->allocations);
            $this->assertSame($batch->id, (int) $entry->allocations->first()->batch_id);
            $this->assertSame($remaining, (int) $entry->allocations->first()->points);

            $fresh = $batch->fresh();
            $this->assertSame(LoyaltyBatchStatus::EXPIRED, $fresh->status);
            $this->assertSame(0, $fresh->points_remaining);
        }

        foreach ($untouched as $id => $attributes) {
            $this->assertSame($attributes, LoyaltyEarnBatch::findOrFail($id)->getAttributes(), "batch {$id} must be untouched");
        }
        $this->assertSame(0, LoyaltyLedgerEntry::where('type', LoyaltyEntryType::EXPIRE->value)->where('guest_id', $other->id)->count());
    }

    public function test_expire_entries_are_written_in_fifo_order_expires_at_then_id(): void
    {
        [$guest, $b1, $b2, $b3] = $this->guestWithThreeActiveBatches();

        $this->forfeit($guest);

        $ids = [$this->expireEntryOf($b2)->id, $this->expireEntryOf($b3)->id, $this->expireEntryOf($b1)->id];
        $sorted = $ids;
        sort($sorted);
        $this->assertSame($sorted, $ids, 'expire entries must ascend in the order B2, B3, B1');
    }

    public function test_an_expired_but_unswept_batch_is_forfeited_and_the_sweep_writes_nothing_afterwards(): void
    {
        $this->freezeTime();
        $guest = Guest::factory()->create();
        $atNow = $this->grantPoints($guest, 30, now());
        $justPast = $this->grantPoints($guest, 20, now()->subSecond());

        $this->assertSame(['points' => 50, 'batches' => 2], $this->forfeit($guest));
        $this->assertSame('expire:batch:'.$atNow->id, $this->expireEntryOf($atNow)->idempotency_key);
        $this->assertSame('expire:batch:'.$justPast->id, $this->expireEntryOf($justPast)->idempotency_key);

        $ledgerRows = LoyaltyLedgerEntry::count();
        $sweep = app(ExpireLoyaltyBatchesAction::class)->handle();

        $this->assertSame(0, $sweep['data']['batches_expired']);
        $this->assertSame($ledgerRows, LoyaltyLedgerEntry::count());
    }

    public function test_a_second_forfeit_writes_nothing(): void
    {
        [$guest] = $this->guestWithThreeActiveBatches();
        $this->forfeit($guest);
        $ledgerRows = LoyaltyLedgerEntry::count();

        $this->assertSame(['points' => 0, 'batches' => 0], $this->forfeit($guest));
        $this->assertSame($ledgerRows, LoyaltyLedgerEntry::count());
    }

    public function test_a_guest_without_batches_forfeits_nothing(): void
    {
        $guest = Guest::factory()->create();
        $before = $this->loyaltyRowCounts();

        $this->assertSame(['points' => 0, 'batches' => 0], $this->forfeit($guest));
        $this->assertSame($before, $this->loyaltyRowCounts());
    }

    public function test_an_active_batch_with_nothing_left_is_neither_counted_nor_given_an_entry(): void
    {
        $guest = Guest::factory()->create();
        $empty = LoyaltyEarnBatch::factory()->create([
            'guest_id' => $guest->id, 'status' => LoyaltyBatchStatus::ACTIVE, 'points_remaining' => 0,
        ]);
        $before = $this->loyaltyRowCounts();

        $this->assertSame(['points' => 0, 'batches' => 0], $this->forfeit($guest));
        $this->assertSame($before, $this->loyaltyRowCounts());
        $this->assertSame(0, LoyaltyLedgerEntry::where('batch_id', $empty->id)->where('type', LoyaltyEntryType::EXPIRE->value)->count());
    }

    public function test_no_batch_goes_negative_and_the_balance_ends_at_zero(): void
    {
        [$guest] = $this->guestWithThreeActiveBatches();
        LoyaltyEarnBatch::factory()->depleted()->create(['guest_id' => $guest->id]);

        $this->forfeit($guest);

        $this->assertSame(0, LoyaltyEarnBatch::where('guest_id', $guest->id)->where('points_remaining', '<', 0)->count());
        $this->assertSame(0, $this->ledger()->available($guest->id));
        $this->assertSame(0, LoyaltyEarnBatch::where('guest_id', $guest->id)->where('status', LoyaltyBatchStatus::ACTIVE->value)->count());
    }

    public function test_forfeit_needs_no_program_settings(): void
    {
        $this->assertSame(0, LoyaltySetting::count());
        $guest = Guest::factory()->create();
        $this->grantPoints($guest, 120);

        $this->assertSame(['points' => 120, 'batches' => 1], $this->forfeit($guest));
        $this->assertSame(0, $this->ledger()->available($guest->id));
    }

    public function test_forfeit_locks_the_batches_it_reads(): void
    {
        [$guest] = $this->guestWithThreeActiveBatches();

        $this->assertLocksRow('loyalty_earn_batches', fn () => $this->forfeit($guest));
    }
}
