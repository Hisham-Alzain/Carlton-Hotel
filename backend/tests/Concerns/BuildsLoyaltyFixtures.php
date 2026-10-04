<?php

namespace Tests\Concerns;

use App\Actions\Folio\GenerateFolioAction;
use App\Enums\LoyaltyBatchSource;
use App\Enums\LoyaltyEntryType;
use App\Models\Folio;
use App\Models\Guest;
use App\Models\LoyaltyEarnBatch;
use App\Models\LoyaltyLedgerEntry;
use App\Models\LoyaltySetting;
use App\Models\Reservation;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Phase 10 shared fixtures: every loyalty test builds its program settings,
 * point balances, tokens and stays through this trait so the ledger and the
 * batches always agree.
 *
 * `staffToken()` / `presetToken()` need the roles and permissions seeded
 * (`RolesAndPermissionsSeeder`), exactly like the per-class copies in the
 * Folio tests they were taken from.
 */
trait BuildsLoyaltyFixtures
{
    /** The seven loyalty tables, in creation order. */
    private const LOYALTY_TABLES = [
        'loyalty_settings',
        'loyalty_rewards',
        'loyalty_earn_batches',
        'loyalty_vouchers',
        'loyalty_ledger_entries',
        'loyalty_allocations',
        'loyalty_reservation_applications',
    ];

    /**
     * Create or update the singleton settings row: the fully configured
     * factory defaults merged with `$overrides` (pass null to switch a value
     * off).
     */
    protected function configureLoyalty(array $overrides = []): LoyaltySetting
    {
        $setting = LoyaltySetting::query()->firstOrNew(['singleton' => 1]);
        $setting->fill(array_merge(LoyaltySetting::factory()->raw(), $overrides))->save();

        return $setting->refresh();
    }

    /**
     * Credit a guest with a batch of points and the matching +points ledger
     * entry (adjust for manual, refund for refund, earn for stay/service).
     */
    protected function grantPoints(
        Guest $guest,
        int $points,
        ?CarbonInterface $expiresAt = null,
        LoyaltyBatchSource $source = LoyaltyBatchSource::MANUAL,
    ): LoyaltyEarnBatch {
        $batch = LoyaltyEarnBatch::factory()->create([
            'guest_id' => $guest->id,
            'source' => $source,
            'points' => $points,
            'points_remaining' => $points,
            'earned_at' => now(),
            'expires_at' => $expiresAt ?? now()->addMonths(24),
        ]);

        LoyaltyLedgerEntry::factory()->create([
            'guest_id' => $guest->id,
            'type' => match ($source) {
                LoyaltyBatchSource::MANUAL => LoyaltyEntryType::ADJUST,
                LoyaltyBatchSource::REFUND => LoyaltyEntryType::REFUND,
                default => LoyaltyEntryType::EARN,
            },
            'source' => $source,
            'points' => $points,
            'batch_id' => $batch->id,
            'idempotency_key' => 'fixture:'.Str::uuid(),
            'occurred_at' => now(),
        ]);

        return $batch;
    }

    protected function guestToken(Guest $guest): string
    {
        return $guest->createToken('t')->plainTextToken;
    }

    protected function staffToken(string ...$permissions): string
    {
        $user = User::factory()->create();
        if ($permissions !== []) {
            $user->givePermissionTo($permissions);
        }

        return $user->createToken('t')->plainTextToken;
    }

    protected function presetToken(string $role): string
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user->createToken('t')->plainTextToken;
    }

    /**
     * A reservation in `$state` (a ReservationFactory state: checkedIn,
     * confirmed, ...) with its generated folio.
     *
     * @return array{0: Reservation, 1: Folio}
     */
    protected function generatedStay(string $total = '300.00', string $state = 'checkedIn'): array
    {
        $reservation = Reservation::factory()->{$state}()->create(['total_usd' => $total]);
        $folio = app(GenerateFolioAction::class)->handle($reservation)['data'];

        return [$reservation, $folio];
    }

    /**
     * Row count of each loyalty table, keyed by table name - for "touches no
     * loyalty table" assertions.
     *
     * @return array<string, int>
     */
    protected function loyaltyRowCounts(): array
    {
        $counts = [];
        foreach (self::LOYALTY_TABLES as $table) {
            $counts[$table] = DB::table($table)->count();
        }

        return $counts;
    }
}
