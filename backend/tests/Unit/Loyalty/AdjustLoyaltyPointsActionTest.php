<?php

namespace Tests\Unit\Loyalty;

use App\Actions\Loyalty\AdjustLoyaltyPointsAction;
use App\Enums\LoyaltyEntryType;
use App\Exceptions\IdempotencyConflictException;
use App\Exceptions\LoyaltyAdjustmentInvalidException;
use App\Exceptions\LoyaltyInsufficientPointsException;
use App\Models\Guest;
use App\Models\LoyaltyAllocation;
use App\Models\LoyaltyEarnBatch;
use App\Models\LoyaltyLedgerEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsLoyaltyFixtures;
use Tests\TestCase;

/**
 * Phase 10 LOY-05 (Q20): AdjustLoyaltyPointsAction called directly, the way
 * the service calls it.
 */
class AdjustLoyaltyPointsActionTest extends TestCase
{
    use BuildsLoyaltyFixtures;
    use RefreshDatabase;

    private function action(): AdjustLoyaltyPointsAction
    {
        return app(AdjustLoyaltyPointsAction::class);
    }

    public function test_a_positive_adjustment_returns_the_entry_with_201(): void
    {
        $guest = Guest::factory()->create();
        $actor = User::factory()->create();

        $result = $this->action()->handle($guest, 120, 'Goodwill', $actor, 'K-1');

        $this->assertSame(201, $result['code']);
        $this->assertInstanceOf(LoyaltyLedgerEntry::class, $result['data']);
        $this->assertSame(120, $result['data']->points);
        $this->assertSame(LoyaltyEntryType::ADJUST, $result['data']->type);
        $this->assertTrue($result['data']->relationLoaded('batch'));
        $this->assertTrue($result['data']->relationLoaded('performer'));
        $this->assertSame(120, LoyaltyEarnBatch::where('guest_id', $guest->id)->sole()->points_remaining);
    }

    public function test_a_negative_adjustment_consumes_and_returns_201(): void
    {
        $guest = Guest::factory()->create();
        $batch = $this->grantPoints($guest, 300);

        $result = $this->action()->handle($guest, -120, 'Correction', User::factory()->create(), 'K-1');

        $this->assertSame(201, $result['code']);
        $this->assertSame(-120, $result['data']->points);
        $this->assertSame(180, $batch->fresh()->points_remaining);
        $this->assertSame(120, LoyaltyAllocation::where('ledger_entry_id', $result['data']->id)->sum('points'));
    }

    public function test_a_replay_answers_200_with_the_same_entry_and_writes_nothing(): void
    {
        $guest = Guest::factory()->create();
        $actor = User::factory()->create();

        $first = $this->action()->handle($guest, 50, 'Once', $actor, 'K-1');
        $rows = $this->loyaltyRowCounts();
        $replay = $this->action()->handle($guest, 50, 'Once', $actor, 'K-1');

        $this->assertSame(200, $replay['code']);
        $this->assertSame($first['data']->uuid, $replay['data']->uuid);
        $this->assertSame($rows, $this->loyaltyRowCounts());
    }

    public function test_a_different_payload_under_the_same_key_conflicts(): void
    {
        $guest = Guest::factory()->create();
        $actor = User::factory()->create();
        $this->action()->handle($guest, 50, 'Once', $actor, 'K-1');

        $this->expectException(IdempotencyConflictException::class);
        $this->action()->handle($guest, 51, 'Once', $actor, 'K-1');
    }

    public function test_insufficient_points_leave_the_batches_untouched(): void
    {
        $guest = Guest::factory()->create();
        $first = $this->grantPoints($guest, 100, now()->addDays(5));
        $second = $this->grantPoints($guest, 100, now()->addDays(50));
        $rows = $this->loyaltyRowCounts();

        try {
            $this->action()->handle($guest, -250, 'Too much', User::factory()->create(), 'K-1');
            $this->fail('Expected LoyaltyInsufficientPointsException.');
        } catch (LoyaltyInsufficientPointsException $e) {
            $this->assertSame(['available_points' => 200, 'requested_points' => 250], $e->context());
        }

        $this->assertSame(100, $first->fresh()->points_remaining);
        $this->assertSame(100, $second->fresh()->points_remaining);
        $this->assertSame($rows, $this->loyaltyRowCounts());
    }

    public function test_zero_points_are_rejected_before_anything_is_written(): void
    {
        $guest = Guest::factory()->create();

        try {
            $this->action()->handle($guest, 0, 'Nothing', User::factory()->create(), 'K-1');
            $this->fail('Expected LoyaltyAdjustmentInvalidException.');
        } catch (LoyaltyAdjustmentInvalidException $e) {
            $this->assertSame(['max_adjust_points' => 1000000], $e->context());
        }

        $this->assertSame(0, LoyaltyLedgerEntry::count());
    }
}
