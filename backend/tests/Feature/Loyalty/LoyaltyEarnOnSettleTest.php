<?php

namespace Tests\Feature\Loyalty;

use App\Actions\Folio\GenerateFolioAction;
use App\Enums\FolioStatus;
use App\Enums\LoyaltyBatchSource;
use App\Enums\LoyaltyEntryType;
use App\Models\Folio;
use App\Models\FolioItem;
use App\Models\LoyaltyEarnBatch;
use App\Models\LoyaltyLedgerEntry;
use App\Models\Payment;
use App\Models\Reservation;
use App\Support\LoyaltyProgram;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use RuntimeException;
use Spatie\Activitylog\Models\Activity;
use Tests\Concerns\BuildsLoyaltyFixtures;
use Tests\Concerns\RecordsRowLocks;
use Tests\TestCase;

/**
 * Phase 10 LOY-02/03/04 (Q8, Q9, Q10, Q11, M-2, M-6): points are credited once,
 * inline under the folio lock, at all three settlement statements:
 *  - POST /cms/folios/{folio}/settle, nothing due;
 *  - POST /cms/folios/{folio}/settle, cash payment recorded then closed;
 *  - POST /cms/folios/{folio}/payments, the payment that auto-settles.
 */
class LoyaltyEarnOnSettleTest extends TestCase
{
    use BuildsLoyaltyFixtures;
    use RecordsRowLocks;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function settle(Folio $folio, array $body = []): TestResponse
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($this->staffToken('folios.settle'))
            ->postJson("/api/cms/folios/{$folio->uuid}/settle", $body, ['Accept-Language' => 'en']);
    }

    private ?string $desk = null;

    /** The same desk records every payment of a test: an Idempotency-Key replay from another desk is a conflict. */
    private function pay(Folio $folio, string $amount, string $key = 'K-1'): TestResponse
    {
        $this->app['auth']->forgetGuards();
        $this->desk ??= $this->staffToken('folios.settle');

        return $this->withToken($this->desk)
            ->postJson(
                "/api/cms/folios/{$folio->uuid}/payments",
                ['method' => 'cash', 'amount_usd' => $amount],
                ['Accept-Language' => 'en', 'Idempotency-Key' => $key],
            );
    }

    private function deposit(Reservation $reservation, string $amount): void
    {
        Payment::factory()->create([
            'payable_type' => Reservation::class,
            'payable_id' => $reservation->id,
            'amount_usd' => $amount,
        ]);
    }

    /** A positive service-side line, with the folio totals refreshed so the balance includes it. */
    private function addLine(Folio $folio, string $sourceType, string $amount): FolioItem
    {
        $item = FolioItem::factory()->create([
            'folio_id' => $folio->id,
            'source_type' => $sourceType,
            'amount_usd' => $amount,
            'description' => 'Line '.$sourceType,
        ]);
        $folio->refresh()->recalculateTotals();

        return $item;
    }

    private function earnEntries(Folio $folio): Collection
    {
        return LoyaltyLedgerEntry::query()
            ->where('folio_id', $folio->id)
            ->where('type', LoyaltyEntryType::EARN->value)
            ->orderBy('id')
            ->get();
    }

    // -- Site 1: nothing due ------------------------------------------------

    public function test_settling_a_prepaid_folio_earns_half_up_points_into_a_stay_batch(): void
    {
        $this->configureLoyalty(['earn_rate' => '1.0000']);
        [$reservation, $folio] = $this->generatedStay('300.00');
        $this->deposit($reservation, '300.00');
        $this->freezeSecond();

        $this->settle($folio)->assertOk()->assertJsonPath('data.status', 'settled');

        $batch = LoyaltyEarnBatch::query()->where('folio_id', $folio->id)->sole();
        $this->assertSame(LoyaltyBatchSource::STAY, $batch->source);
        $this->assertSame($reservation->guest_id, $batch->guest_id);
        $this->assertSame(300, $batch->points);
        $this->assertSame(300, $batch->points_remaining);
        $this->assertSame(
            LoyaltyProgram::current()->expiresAtFrom(now())->utc()->toDateTimeString(),
            $batch->expires_at->utc()->toDateTimeString(),
        );

        $entry = $this->earnEntries($folio)->sole();
        $this->assertSame(300, $entry->points);
        $this->assertSame(LoyaltyBatchSource::STAY, $entry->source);
        $this->assertSame($batch->id, $entry->batch_id);
        $this->assertSame($reservation->id, $entry->reservation_id);
        $this->assertSame($reservation->guest_id, $entry->guest_id);
        $this->assertSame("earn:folio:{$folio->id}:stay", $entry->idempotency_key);
    }

    // -- Site 2: settle with a cash payment ----------------------------------

    public function test_settling_with_cash_earns_a_stay_batch_and_a_service_batch(): void
    {
        $this->configureLoyalty(['earn_rate' => '1.0000']);
        [, $folio] = $this->generatedStay('300.00');
        $this->addLine($folio, 'service_booking', '45.50');
        $this->addLine($folio, 'manual', '14.50');

        $this->settle($folio, ['method' => 'cash', 'amount_usd' => '360.00'])
            ->assertOk()
            ->assertJsonPath('data.status', 'settled');

        $this->assertSame(
            ['stay' => 300, 'service' => 60],
            $this->earnEntries($folio)->mapWithKeys(fn ($e) => [$e->source->value => $e->points])->all(),
        );
        $this->assertSame(2, LoyaltyEarnBatch::query()->where('folio_id', $folio->id)->count());
        $this->assertDatabaseHas('loyalty_ledger_entries', ['idempotency_key' => "earn:folio:{$folio->id}:service"]);
    }

    public function test_settling_with_cash_and_no_service_lines_earns_only_a_stay_batch(): void
    {
        $this->configureLoyalty(['earn_rate' => '1.0000']);
        [, $folio] = $this->generatedStay('120.00');

        $this->settle($folio, ['method' => 'cash', 'amount_usd' => '120.00'])->assertOk();

        $this->assertSame(1, LoyaltyEarnBatch::query()->where('folio_id', $folio->id)->count());
        $this->assertSame(120, $this->earnEntries($folio)->sole()->points);
    }

    // -- Site 3: auto-settling payment ----------------------------------------

    public function test_the_payment_that_auto_settles_earns_and_a_partial_payment_does_not(): void
    {
        $this->configureLoyalty(['earn_rate' => '1.0000']);
        [, $folio] = $this->generatedStay('300.00');

        $this->pay($folio, '100.00', 'K-1')->assertCreated()->assertJsonPath('data.status', 'open');
        $this->assertSame(0, $this->earnEntries($folio)->count());
        $this->assertSame(0, LoyaltyEarnBatch::query()->count());

        $this->pay($folio, '200.00', 'K-2')->assertCreated()->assertJsonPath('data.status', 'settled');

        $this->assertSame(300, $this->earnEntries($folio)->sole()->points);
        $this->assertSame(300, LoyaltyEarnBatch::query()->where('folio_id', $folio->id)->sole()->points);
    }

    // -- Rounding ----------------------------------------------------------------

    public function test_points_round_half_up_per_bucket_and_a_sub_half_point_bucket_earns_nothing(): void
    {
        $this->configureLoyalty(['earn_rate' => '0.0500']);
        [, $folio] = $this->generatedStay('30.00');
        $this->addLine($folio, 'service_booking', '9.90');

        $this->settle($folio, ['method' => 'cash', 'amount_usd' => '39.90'])->assertOk();

        $entry = $this->earnEntries($folio)->sole();
        $this->assertSame(LoyaltyBatchSource::STAY, $entry->source);
        $this->assertSame(2, $entry->points); // 30.00 x 0.05 = 1.5, half-up
        $this->assertSame(1, LoyaltyEarnBatch::query()->where('folio_id', $folio->id)->count());
    }

    // -- Once only ----------------------------------------------------------------

    public function test_a_second_settle_answers_folio_settled_and_earns_nothing_more(): void
    {
        $this->configureLoyalty(['earn_rate' => '1.0000']);
        [, $folio] = $this->generatedStay('300.00');
        $this->addLine($folio, 'service_request', '20.00');

        $this->settle($folio, ['method' => 'cash', 'amount_usd' => '320.00'])->assertOk();
        $this->assertSame(2, $this->earnEntries($folio)->count());

        $this->settle($folio, ['method' => 'cash', 'amount_usd' => '320.00'])
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'folio_settled');

        $this->assertSame(2, $this->earnEntries($folio)->count());
        $this->assertSame(2, LoyaltyEarnBatch::query()->where('folio_id', $folio->id)->count());
    }

    public function test_replaying_the_settling_payment_with_the_same_key_earns_nothing_new(): void
    {
        $this->configureLoyalty(['earn_rate' => '1.0000']);
        [, $folio] = $this->generatedStay('300.00');

        $this->pay($folio, '300.00', 'K-1')->assertCreated();
        $this->pay($folio, '300.00', 'K-1')->assertOk();

        $this->assertSame(1, $this->earnEntries($folio)->count());
        $this->assertSame(1, LoyaltyEarnBatch::query()->where('folio_id', $folio->id)->count());
    }

    // -- No-ops ---------------------------------------------------------------------

    public function test_settling_with_no_program_row_writes_no_loyalty_rows(): void
    {
        [$reservation, $folio] = $this->generatedStay('300.00');
        $this->deposit($reservation, '300.00');
        $before = $this->loyaltyRowCounts();

        $this->settle($folio)->assertOk()->assertJsonPath('data.status', 'settled');

        $this->assertSame($before, $this->loyaltyRowCounts());
    }

    public function test_settling_with_a_zero_earn_rate_writes_no_loyalty_rows(): void
    {
        $this->configureLoyalty(['earn_rate' => '0']);
        [$reservation, $folio] = $this->generatedStay('300.00');
        $this->deposit($reservation, '300.00');
        $before = $this->loyaltyRowCounts();

        $this->settle($folio)->assertOk()->assertJsonPath('data.status', 'settled');

        $this->assertSame($before, $this->loyaltyRowCounts());
    }

    public function test_settling_with_earning_switched_off_by_a_null_rate_writes_no_loyalty_rows(): void
    {
        $this->configureLoyalty(['earn_rate' => null]);
        [, $folio] = $this->generatedStay('300.00');
        $before = $this->loyaltyRowCounts();

        $this->pay($folio, '300.00')->assertCreated()->assertJsonPath('data.status', 'settled');

        $this->assertSame($before, $this->loyaltyRowCounts());
    }

    public function test_a_cancelled_reservation_settles_without_earning_and_logs_the_skip(): void
    {
        $this->configureLoyalty(['earn_rate' => '1.0000']);
        [$reservation, $folio] = $this->generatedStay('300.00', 'cancelled');
        $this->deposit($reservation, '300.00');
        $before = $this->loyaltyRowCounts();

        $this->settle($folio)->assertOk()->assertJsonPath('data.status', 'settled');

        $this->assertSame($before, $this->loyaltyRowCounts());

        $log = Activity::query()->where('description', 'loyalty.earn_skipped_cancelled')->sole();
        $this->assertSame($folio->id, (int) $log->subject_id);
        $this->assertSame($folio->uuid, $log->properties['folio_uuid']);
        $this->assertSame($reservation->uuid, $log->properties['reservation_uuid']);
    }

    public function test_a_reservation_without_a_guest_settles_without_earning(): void
    {
        $this->configureLoyalty(['earn_rate' => '1.0000']);
        $reservation = Reservation::factory()->checkedIn()->create(['total_usd' => '300.00', 'guest_id' => null]);
        $folio = app(GenerateFolioAction::class)->handle($reservation)['data'];
        $this->deposit($reservation, '300.00');
        $before = $this->loyaltyRowCounts();

        $this->settle($folio)->assertOk()->assertJsonPath('data.status', 'settled');

        $this->assertSame($before, $this->loyaltyRowCounts());
        $this->assertSame(0, Activity::query()->where('description', 'loyalty.earn_skipped_cancelled')->count());
    }

    // -- Atomicity (M-2) -----------------------------------------------------------

    public function test_a_failure_while_earning_rolls_the_whole_settlement_back(): void
    {
        $this->configureLoyalty(['earn_rate' => '1.0000']);
        [, $folio] = $this->generatedStay('300.00');
        $token = $this->staffToken('folios.settle');

        // LoyaltyLedger is final and cannot be doubled; a creating listener makes
        // credit() throw a non-unique error from inside the real call.
        LoyaltyEarnBatch::creating(function (): void {
            throw new RuntimeException('loyalty storage down');
        });

        try {
            $this->app['auth']->forgetGuards();
            $this->withToken($token)
                ->postJson("/api/cms/folios/{$folio->uuid}/settle", ['method' => 'cash', 'amount_usd' => '300.00'])
                ->assertStatus(500);
        } finally {
            LoyaltyEarnBatch::flushEventListeners();
        }

        $fresh = $folio->fresh();
        $this->assertSame(FolioStatus::OPEN, $fresh->status);
        $this->assertNull($fresh->settled_at);
        $this->assertSame(0, Payment::query()->where('payable_type', Folio::class)->where('payable_id', $folio->id)->count());
        $this->assertSame(0, LoyaltyLedgerEntry::query()->count());
    }

    // -- Locks (M-6) -----------------------------------------------------------------

    public function test_a_settle_locks_the_folio_and_takes_no_guest_or_batch_lock(): void
    {
        $this->configureLoyalty(['earn_rate' => '1.0000']);
        [$reservation, $folio] = $this->generatedStay('300.00');
        $this->deposit($reservation, '300.00');
        $token = $this->staffToken('folios.settle');
        $this->app['auth']->forgetGuards();

        $locked = $this->lockedSelects(
            fn () => $this->withToken($token)->postJson("/api/cms/folios/{$folio->uuid}/settle")->assertOk(),
        );

        $this->assertNotEmpty(array_filter($locked, fn (string $sql) => str_contains($sql, 'from "folios"')));
        $this->assertSame([], array_values(array_filter(
            $locked,
            fn (string $sql) => str_contains($sql, 'from "guests"') || str_contains($sql, 'from "loyalty_earn_batches"'),
        )));
        $this->assertSame(1, $this->earnEntries($folio)->count());
    }
}
