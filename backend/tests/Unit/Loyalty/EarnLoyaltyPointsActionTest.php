<?php

namespace Tests\Unit\Loyalty;

use App\Actions\Loyalty\EarnLoyaltyPointsAction;
use App\Enums\LoyaltyBatchSource;
use App\Enums\LoyaltyEntryType;
use App\Models\Folio;
use App\Models\FolioItem;
use App\Models\LoyaltyEarnBatch;
use App\Models\LoyaltyLedgerEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\BuildsLoyaltyFixtures;
use Tests\TestCase;

/**
 * Phase 10 (Q8, Q10, Q11): the bucketing and the once-only backstop of
 * EarnLoyaltyPointsAction, called the way the settle actions call it: inside a
 * transaction on a folio they already locked.
 */
class EarnLoyaltyPointsActionTest extends TestCase
{
    use BuildsLoyaltyFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->configureLoyalty(['earn_rate' => '1.0000']);
    }

    /** @return array{0: Folio, 1: array} [folio, the action's result] */
    private function earn(Folio $folio): array
    {
        $result = DB::transaction(function () use ($folio) {
            $locked = Folio::query()->whereKey($folio->id)->lockForUpdate()->firstOrFail();

            return app(EarnLoyaltyPointsAction::class)->handle($locked);
        });

        return [$folio, $result];
    }

    private function line(Folio $folio, string $sourceType, string $amount, ?FolioItem $reverses = null): FolioItem
    {
        return FolioItem::factory()->create([
            'folio_id' => $folio->id,
            'source_type' => $sourceType,
            'amount_usd' => $amount,
            'reverses_item_id' => $reverses?->id,
        ]);
    }

    /** @return array<string, int> points per bucket */
    private function pointsByBucket(Folio $folio): array
    {
        return LoyaltyLedgerEntry::query()
            ->where('folio_id', $folio->id)
            ->where('type', LoyaltyEntryType::EARN->value)
            ->get()
            ->mapWithKeys(fn (LoyaltyLedgerEntry $entry) => [$entry->source->value => $entry->points])
            ->all();
    }

    public function test_a_plain_stay_earns_into_the_stay_bucket_only(): void
    {
        [, $folio] = $this->generatedStay('300.00');

        [, $result] = $this->earn($folio);

        $this->assertSame(['stay' => 300], $this->pointsByBucket($folio));
        $this->assertSame(200, $result['code']);
    }

    public function test_service_booking_service_request_and_manual_lines_share_the_service_bucket_half_up(): void
    {
        [, $folio] = $this->generatedStay('300.00');
        $this->line($folio, 'service_booking', '45.50');
        $this->line($folio, 'service_request', '10.25');
        $this->line($folio, 'manual', '5.00');

        $this->earn($folio);

        $this->assertSame(['stay' => 300, 'service' => 61], $this->pointsByBucket($folio)); // 60.75 up
    }

    public function test_a_credit_reduces_the_bucket_of_the_line_it_reverses_and_rounds_after_summing(): void
    {
        [, $folio] = $this->generatedStay('300.00');
        $booking = $this->line($folio, 'service_booking', '45.50');
        $this->line($folio, 'service_request', '10.25');
        $this->line($folio, 'manual', '5.00');
        $this->line($folio, 'credit', '-45.50', $booking);

        $this->earn($folio);

        $this->assertSame(['stay' => 300, 'service' => 15], $this->pointsByBucket($folio)); // 15.25 down
    }

    public function test_a_credit_reversing_the_reservation_line_reduces_the_stay_bucket(): void
    {
        [, $folio] = $this->generatedStay('300.00');
        $room = FolioItem::query()->where('folio_id', $folio->id)->where('source_type', 'reservation')->sole();
        $this->line($folio, 'credit', '-100.00', $room);

        $this->earn($folio);

        $this->assertSame(['stay' => 200], $this->pointsByBucket($folio));
    }

    public function test_a_standalone_credit_reduces_the_service_bucket(): void
    {
        [, $folio] = $this->generatedStay('300.00');
        $this->line($folio, 'service_booking', '45.50');
        $this->line($folio, 'credit', '-20.00');

        $this->earn($folio);

        $this->assertSame(['stay' => 300, 'service' => 26], $this->pointsByBucket($folio)); // 25.50 up
    }

    public function test_a_bucket_driven_below_zero_earns_nothing_and_never_goes_negative(): void
    {
        [, $folio] = $this->generatedStay('300.00');
        $this->line($folio, 'service_booking', '10.00');
        $this->line($folio, 'credit', '-30.00');

        $this->earn($folio);

        $this->assertSame(['stay' => 300], $this->pointsByBucket($folio));
        $this->assertSame(0, LoyaltyLedgerEntry::query()->where('points', '<', 0)->count());
        $this->assertSame(1, LoyaltyEarnBatch::query()->count());
    }

    public function test_a_reservation_line_credited_to_zero_or_below_earns_no_stay_batch(): void
    {
        [, $folio] = $this->generatedStay('300.00');
        $room = FolioItem::query()->where('folio_id', $folio->id)->where('source_type', 'reservation')->sole();
        $this->line($folio, 'credit', '-400.00', $room);
        $this->line($folio, 'manual', '40.00');

        $this->earn($folio);

        $this->assertSame(['service' => 40], $this->pointsByBucket($folio));
    }

    public function test_every_batch_and_entry_carries_the_folio_reservation_and_guest(): void
    {
        [$reservation, $folio] = $this->generatedStay('300.00');
        $this->line($folio, 'manual', '10.00');

        $this->earn($folio);

        $this->assertSame(2, LoyaltyEarnBatch::query()->where('folio_id', $folio->id)->where('guest_id', $reservation->guest_id)->count());
        foreach (LoyaltyLedgerEntry::query()->where('folio_id', $folio->id)->get() as $entry) {
            $this->assertSame($reservation->id, $entry->reservation_id);
            $this->assertSame($reservation->guest_id, $entry->guest_id);
            $this->assertNotNull($entry->batch_id);
            $this->assertSame("earn:folio:{$folio->id}:{$entry->source->value}", $entry->idempotency_key);
        }
    }

    public function test_an_existing_earn_key_skips_that_bucket_without_error_and_leaves_one_entry(): void
    {
        [$reservation, $folio] = $this->generatedStay('300.00');
        $this->line($folio, 'manual', '40.00');
        LoyaltyLedgerEntry::factory()->create([
            'guest_id' => $reservation->guest_id,
            'type' => LoyaltyEntryType::EARN,
            'source' => LoyaltyBatchSource::STAY,
            'points' => 300,
            'folio_id' => $folio->id,
            'idempotency_key' => "earn:folio:{$folio->id}:stay",
        ]);

        [, $result] = $this->earn($folio);

        $this->assertSame(['stay' => 300, 'service' => 40], $this->pointsByBucket($folio));
        $this->assertSame(1, LoyaltyLedgerEntry::query()->where('idempotency_key', "earn:folio:{$folio->id}:stay")->count());
        $this->assertSame(0, LoyaltyEarnBatch::query()->where('folio_id', $folio->id)->where('source', 'stay')->count());
        $this->assertCount(1, $result['data']);
        $this->assertSame(LoyaltyBatchSource::SERVICE, $result['data'][0]->source);
    }

    public function test_the_return_value_lists_the_created_entries(): void
    {
        [, $folio] = $this->generatedStay('300.00');
        $this->line($folio, 'manual', '40.00');

        [, $result] = $this->earn($folio);

        $this->assertSame(200, $result['code']);
        $this->assertCount(2, $result['data']);
        $this->assertContainsOnlyInstancesOf(LoyaltyLedgerEntry::class, $result['data']);
        $this->assertSame([300, 40], array_map(fn (LoyaltyLedgerEntry $e) => $e->points, $result['data']));
    }

    public function test_an_inactive_program_returns_an_empty_result_and_writes_nothing(): void
    {
        $this->configureLoyalty(['earn_rate' => null]);
        [, $folio] = $this->generatedStay('300.00');

        [, $result] = $this->earn($folio);

        $this->assertSame(['data' => [], 'code' => 200], $result);
        $this->assertSame(0, LoyaltyLedgerEntry::query()->count());
    }
}
