<?php

namespace Tests\Feature\Loyalty;

use App\Actions\Loyalty\ExpireLoyaltyBatchesAction;
use App\Enums\LoyaltyBatchStatus;
use App\Enums\LoyaltyEntryType;
use App\Enums\LoyaltyVoucherStatus;
use App\Models\Guest;
use App\Models\LoyaltyEarnBatch;
use App\Models\LoyaltyLedgerEntry;
use App\Models\LoyaltyVoucher;
use App\Support\LoyaltyProgram;
use Carbon\CarbonImmutable;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;
use Tests\Concerns\BuildsLoyaltyFixtures;
use Tests\Concerns\RecordsRowLocks;
use Tests\TestCase;

/**
 * Phase 10 LOY-08/LOY-09 (Q15, Pitfall 5): the daily sweep expires batches and
 * vouchers with ledger entries, idempotently, and is bookkeeping only because
 * availability filters `expires_at > now()` itself.
 */
class LoyaltyExpiryTest extends TestCase
{
    use BuildsLoyaltyFixtures;
    use RecordsRowLocks;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-10-04 12:00:00', 'UTC'));
    }

    private function expireEntries(): int
    {
        return LoyaltyLedgerEntry::query()->where('type', LoyaltyEntryType::EXPIRE->value)->count();
    }

    // ------------------------------------------------------------------- sweep

    public function test_the_sweep_expires_due_batches_with_a_ledger_entry_and_allocation_each(): void
    {
        $guest = Guest::factory()->create();
        $other = Guest::factory()->create();

        $a = $this->grantPoints($guest, 120, now()->subHour());
        $b = $this->grantPoints($guest, 80, now()->addDay());
        $c = LoyaltyEarnBatch::factory()->depleted()->create([
            'guest_id' => $guest->id,
            'expires_at' => now()->subDay(),
        ]);
        $d = $this->grantPoints($other, 40, now()->subHours(2));

        $this->artisan('loyalty:expire-points')
            ->expectsOutputToContain('Expired 2 loyalty batch(es) and 0 voucher(s).')
            ->assertSuccessful();

        foreach ([[$a, 120], [$d, 40]] as [$batch, $points]) {
            $batch->refresh();
            $this->assertSame(LoyaltyBatchStatus::EXPIRED, $batch->status);
            $this->assertSame(0, $batch->points_remaining);

            $entry = LoyaltyLedgerEntry::query()
                ->where('type', LoyaltyEntryType::EXPIRE->value)
                ->where('batch_id', $batch->id)
                ->sole();
            $this->assertSame(-$points, $entry->points);
            $this->assertSame($batch->guest_id, $entry->guest_id);
            $this->assertSame('expire:batch:'.$batch->id, $entry->idempotency_key);
            $this->assertCount(1, $entry->allocations);
            $this->assertSame($points, $entry->allocations->first()->points);
        }

        $this->assertSame(2, $this->expireEntries());

        $b->refresh();
        $this->assertSame(LoyaltyBatchStatus::ACTIVE, $b->status);
        $this->assertSame(80, $b->points_remaining);

        $c->refresh();
        $this->assertSame(LoyaltyBatchStatus::DEPLETED, $c->status);
        $this->assertSame(0, $c->points_remaining);
    }

    public function test_a_rerun_writes_and_changes_nothing(): void
    {
        $guest = Guest::factory()->create();
        $this->grantPoints($guest, 120, now()->subHour());

        $this->artisan('loyalty:expire-points')->assertSuccessful();

        $entries = LoyaltyLedgerEntry::query()->count();
        $snapshot = LoyaltyEarnBatch::query()->orderBy('id')->get(['id', 'status', 'points_remaining', 'updated_at'])->toArray();

        $this->artisan('loyalty:expire-points')
            ->expectsOutputToContain('Expired 0 loyalty batch(es) and 0 voucher(s).')
            ->assertSuccessful();

        $this->assertSame($entries, LoyaltyLedgerEntry::query()->count());
        $this->assertSame(1, $this->expireEntries());
        $this->assertSame($snapshot, LoyaltyEarnBatch::query()->orderBy('id')->get(['id', 'status', 'points_remaining', 'updated_at'])->toArray());
    }

    public function test_a_batch_is_swept_at_its_expiry_instant_and_not_a_second_before(): void
    {
        $guest = Guest::factory()->create();
        $expiresAt = CarbonImmutable::parse('2026-10-04 15:00:00', 'UTC');
        $batch = $this->grantPoints($guest, 100, $expiresAt);

        $this->travelTo($expiresAt->subSecond());
        $this->artisan('loyalty:expire-points')->assertSuccessful();
        $this->assertSame(LoyaltyBatchStatus::ACTIVE, $batch->refresh()->status);
        $this->assertSame(100, $batch->points_remaining);

        $this->travelTo($expiresAt);
        $this->artisan('loyalty:expire-points')->assertSuccessful();
        $this->assertSame(LoyaltyBatchStatus::EXPIRED, $batch->refresh()->status);
        $this->assertSame(0, $batch->points_remaining);
    }

    public function test_an_expired_batch_is_already_unavailable_before_the_sweep_runs(): void
    {
        $this->configureLoyalty();
        $guest = Guest::factory()->create();
        $expiresAt = CarbonImmutable::parse('2026-10-04 15:00:00', 'UTC');
        $this->grantPoints($guest, 100, $expiresAt);
        $this->grantPoints($guest, 25, now()->addYear());

        $this->travelTo($expiresAt->subSecond());
        $this->app['auth']->forgetGuards();
        $this->withToken($this->guestToken($guest))->getJson('/api/loyalty/account')
            ->assertOk()->assertJsonPath('data.available_points', 125);

        $this->travelTo($expiresAt->addSecond());
        $this->app['auth']->forgetGuards();
        $this->withToken($this->guestToken($guest))->getJson('/api/loyalty/account')
            ->assertOk()->assertJsonPath('data.available_points', 25);

        // The sweep has not run: the row is still "active" yet no longer spendable.
        $this->assertSame(1, LoyaltyEarnBatch::query()->where('status', LoyaltyBatchStatus::ACTIVE->value)->where('points_remaining', 100)->count());
    }

    // ---------------------------------------------------------------- vouchers

    public function test_due_active_vouchers_expire_and_other_statuses_are_untouched(): void
    {
        $due = LoyaltyVoucher::factory()->create(['expires_at' => now()->subMinute()]);
        $future = LoyaltyVoucher::factory()->create(['expires_at' => now()->addDay()]);
        $used = LoyaltyVoucher::factory()->create([
            'status' => LoyaltyVoucherStatus::USED,
            'expires_at' => now()->subDay(),
            'used_at' => now()->subDays(2),
        ]);
        $void = LoyaltyVoucher::factory()->create([
            'status' => LoyaltyVoucherStatus::VOID,
            'expires_at' => now()->subDay(),
        ]);

        $this->artisan('loyalty:expire-points')
            ->expectsOutputToContain('Expired 0 loyalty batch(es) and 1 voucher(s).')
            ->assertSuccessful();

        $this->assertSame(LoyaltyVoucherStatus::EXPIRED, $due->refresh()->status);
        $this->assertSame(LoyaltyVoucherStatus::ACTIVE, $future->refresh()->status);
        $this->assertSame(LoyaltyVoucherStatus::USED, $used->refresh()->status);
        $this->assertSame(LoyaltyVoucherStatus::VOID, $void->refresh()->status);

        $logged = Activity::query()
            ->where('subject_type', LoyaltyVoucher::class)
            ->where('subject_id', $due->id)
            ->where('description', 'updated')
            ->get();
        $this->assertCount(1, $logged);
        $this->assertSame('expired', $logged->first()->attribute_changes['attributes']['status'] ?? null);

        $this->assertSame(0, Activity::query()
            ->where('subject_type', LoyaltyVoucher::class)
            ->whereIn('subject_id', [$future->id, $used->id, $void->id])
            ->where('description', 'updated')
            ->count());
    }

    // ------------------------------------------------------ month-end and DST

    public function test_a_month_end_award_expires_at_the_end_of_the_clamped_london_day(): void
    {
        config(['hotel.timezone' => 'Europe/London']);
        $this->configureLoyalty(['expiry_months' => 1]);
        $this->travelTo(CarbonImmutable::parse('2027-01-31 10:00:00', 'UTC'));

        $guest = Guest::factory()->create();
        $batch = $this->grantPoints($guest, 100, LoyaltyProgram::current()->expiresAtFrom(now()));

        $this->assertSame(
            '2027-02-28 23:59:59',
            $batch->refresh()->expires_at->copy()->setTimezone('Europe/London')->toDateTimeString(),
        );

        $this->travelTo(CarbonImmutable::parse('2027-02-28 23:59:58', 'Europe/London'));
        $this->artisan('loyalty:expire-points')->assertSuccessful();
        $this->assertSame(LoyaltyBatchStatus::ACTIVE, $batch->refresh()->status);

        $this->travelTo(CarbonImmutable::parse('2027-03-01 00:00:00', 'Europe/London'));
        $this->artisan('loyalty:expire-points')->assertSuccessful();
        $this->assertSame(LoyaltyBatchStatus::EXPIRED, $batch->refresh()->status);
    }

    public function test_changing_the_expiry_months_does_not_move_an_existing_batch(): void
    {
        $this->configureLoyalty(['expiry_months' => 24]);
        $guest = Guest::factory()->create();
        $batch = $this->grantPoints($guest, 100, LoyaltyProgram::current()->expiresAtFrom(now()));
        $original = $batch->refresh()->expires_at->toDateTimeString();

        $this->configureLoyalty(['expiry_months' => 1]);
        $this->travelTo(now()->addMonths(2));
        $this->artisan('loyalty:expire-points')->assertSuccessful();

        $batch->refresh();
        $this->assertSame($original, $batch->expires_at->toDateTimeString());
        $this->assertSame(LoyaltyBatchStatus::ACTIVE, $batch->status);
        $this->assertSame(100, $batch->points_remaining);
    }

    // ---------------------------------------------------------- schedule, locks

    public function test_the_sweep_is_scheduled_daily_at_one_in_the_hotel_timezone_without_overlap(): void
    {
        $events = collect(app(Schedule::class)->events())
            ->filter(fn ($event) => str_contains((string) $event->command, 'loyalty:expire-points'))
            ->values();

        $this->assertCount(1, $events);
        $this->assertSame('0 1 * * *', $events[0]->expression);
        $this->assertSame(config('hotel.timezone'), (string) $events[0]->timezone);
        $this->assertTrue($events[0]->withoutOverlapping);
    }

    public function test_the_sweep_locks_the_guest_before_its_batches(): void
    {
        $guest = Guest::factory()->create();
        $this->grantPoints($guest, 100, now()->subHour());

        $locked = $this->lockedSelects(fn () => app(ExpireLoyaltyBatchesAction::class)->handle());

        $guestAt = $batchAt = null;
        foreach ($locked as $i => $sql) {
            $guestAt ??= str_contains($sql, 'from "guests"') ? $i : null;
            $batchAt ??= str_contains($sql, 'from "loyalty_earn_batches"') ? $i : null;
        }

        $this->assertNotNull($guestAt, 'The guest row must be locked: '.implode("\n", $locked));
        $this->assertNotNull($batchAt, 'The batch row must be locked: '.implode("\n", $locked));
        $this->assertLessThan($batchAt, $guestAt);
    }
}
