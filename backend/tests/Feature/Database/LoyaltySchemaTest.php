<?php

namespace Tests\Feature\Database;

use App\Actions\Folio\GenerateFolioAction;
use App\Enums\LoyaltyApplicationStatus;
use App\Enums\LoyaltyBatchSource;
use App\Enums\LoyaltyBatchStatus;
use App\Enums\LoyaltyEntryType;
use App\Enums\LoyaltyRewardType;
use App\Enums\LoyaltyVoucherStatus;
use App\Models\Folio;
use App\Models\Guest;
use App\Models\LoyaltyAllocation;
use App\Models\LoyaltyEarnBatch;
use App\Models\LoyaltyLedgerEntry;
use App\Models\LoyaltyReservationApplication;
use App\Models\LoyaltyReward;
use App\Models\LoyaltySetting;
use App\Models\LoyaltyVoucher;
use App\Models\Reservation;
use App\Models\User;
use App\Traits\LogsActivity;
use Carbon\CarbonInterface;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Laravel\Sanctum\PersonalAccessToken;
use Spatie\Activitylog\Models\Activity;
use Tests\Concerns\BuildsLoyaltyFixtures;
use Tests\TestCase;

/**
 * Phase 10 storage contract (10-01): seven additive loyalty tables, every
 * once-only backstop (LOY-04, LOY-17), restrict/null-on-delete FKs, indexes,
 * the model layer, the audit scope and the shared fixture trait.
 */
class LoyaltySchemaTest extends TestCase
{
    use BuildsLoyaltyFixtures, RefreshDatabase;

    // ── Raw-row helpers (independent of factories) ──────────────────────

    private function guestId(): int
    {
        return Guest::factory()->create()->id;
    }

    private function userId(): int
    {
        return User::factory()->create()->id;
    }

    private function insert(string $table, array $row): int
    {
        return DB::table($table)->insertGetId($row);
    }

    private function settingRow(array $o = []): array
    {
        return array_merge(['singleton' => 1, 'created_at' => now(), 'updated_at' => now()], $o);
    }

    private function rewardRow(array $o = []): array
    {
        return array_merge([
            'uuid' => (string) Str::uuid(), 'name' => json_encode(['en' => 'Reward', 'ar' => 'مكافأة']),
            'type' => 'discount_voucher', 'points_cost' => 100, 'voucher_valid_days' => 90,
            'created_at' => now(), 'updated_at' => now(),
        ], $o);
    }

    private function batchRow(int $guestId, array $o = []): array
    {
        return array_merge([
            'uuid' => (string) Str::uuid(), 'guest_id' => $guestId, 'source' => 'manual',
            'points' => 100, 'points_remaining' => 100, 'earned_at' => now(),
            'expires_at' => now()->addMonths(24), 'status' => 'active',
            'created_at' => now(), 'updated_at' => now(),
        ], $o);
    }

    private function voucherRow(int $guestId, array $o = []): array
    {
        return array_merge([
            'uuid' => (string) Str::uuid(), 'code' => 'LOY-'.Str::upper(Str::random(8)), 'guest_id' => $guestId,
            'type' => 'discount_voucher', 'reward_name' => json_encode(['en' => 'Reward']),
            'points_spent' => 100, 'status' => 'active', 'expires_at' => now()->addDays(90),
            'created_at' => now(), 'updated_at' => now(),
        ], $o);
    }

    private function ledgerRow(int $guestId, array $o = []): array
    {
        return array_merge([
            'uuid' => (string) Str::uuid(), 'guest_id' => $guestId, 'type' => 'adjust', 'points' => 100,
            'idempotency_key' => 'raw:'.Str::uuid(), 'occurred_at' => now(), 'created_at' => now(),
        ], $o);
    }

    private function applicationRow(int $reservationId, int $guestId, array $o = []): array
    {
        return array_merge([
            'uuid' => (string) Str::uuid(), 'reservation_id' => $reservationId, 'guest_id' => $guestId,
            'idempotency_key' => 'K-'.Str::random(12), 'status' => 'applied',
            'created_at' => now(), 'updated_at' => now(),
        ], $o);
    }

    private function reservationId(?int $guestId = null): int
    {
        return Reservation::factory()->create($guestId ? ['guest_id' => $guestId] : [])->id;
    }

    /** @param list<string> $columns */
    private function assertIndex(string $table, array $columns, ?bool $unique = null): void
    {
        $match = collect(Schema::getIndexes($table))->first(
            fn (array $i) => $i['columns'] === $columns && ($unique === null || $i['unique'] === $unique),
        );

        $this->assertNotNull($match, "{$table} is missing index (".implode(',', $columns).')');
    }

    // ── Columns and defaults ────────────────────────────────────────────

    public function test_tables_have_exactly_the_specified_columns(): void
    {
        $expected = [
            'loyalty_settings' => [
                'id', 'singleton', 'earn_rate', 'redeem_value_usd', 'expiry_months', 'expiry_warning_days',
                'min_redeem_points', 'max_redeem_percent', 'updated_by', 'created_at', 'updated_at',
            ],
            'loyalty_rewards' => [
                'id', 'uuid', 'name', 'description', 'type', 'points_cost', 'discount_usd', 'voucher_valid_days',
                'is_active', 'sort_order', 'created_at', 'updated_at', 'deleted_at',
            ],
            'loyalty_earn_batches' => [
                'id', 'uuid', 'guest_id', 'source', 'folio_id', 'awarded_by', 'reason', 'points', 'points_remaining',
                'earned_at', 'expires_at', 'expiry_warned_at', 'status', 'created_at', 'updated_at',
            ],
            'loyalty_vouchers' => [
                'id', 'uuid', 'code', 'guest_id', 'loyalty_reward_id', 'type', 'reward_name', 'value_usd',
                'points_spent', 'status', 'expires_at', 'reservation_id', 'used_at', 'created_at', 'updated_at',
            ],
            'loyalty_ledger_entries' => [
                'id', 'uuid', 'guest_id', 'type', 'source', 'points', 'batch_id', 'voucher_id', 'reservation_id',
                'folio_id', 'reverses_entry_id', 'performed_by', 'reason', 'shortfall_points', 'discount_usd',
                'idempotency_key', 'occurred_at', 'created_at',
            ],
            'loyalty_allocations' => ['id', 'ledger_entry_id', 'batch_id', 'points'],
            'loyalty_reservation_applications' => [
                'id', 'uuid', 'reservation_id', 'guest_id', 'idempotency_key', 'redeem_entry_id', 'points_redeemed',
                'points_discount_usd', 'voucher_id', 'voucher_discount_usd', 'status', 'reversed_at',
                'created_at', 'updated_at',
            ],
        ];

        foreach ($expected as $table => $columns) {
            $this->assertTrue(Schema::hasTable($table), $table);
            $this->assertEqualsCanonicalizing($columns, Schema::getColumnListing($table), $table);
        }

        $this->assertFalse(Schema::hasColumn('loyalty_settings', 'uuid'));
        $this->assertTrue(Schema::hasColumn('loyalty_ledger_entries', 'created_at'));
        $this->assertFalse(Schema::hasColumn('loyalty_ledger_entries', 'updated_at'));
    }

    public function test_no_column_is_added_to_reservations_guests_or_folios(): void
    {
        foreach (['reservations', 'guests', 'folios'] as $table) {
            foreach (Schema::getColumnListing($table) as $column) {
                $this->assertStringNotContainsString('loyalty', $column, "{$table}.{$column}");
            }
        }
    }

    public function test_settings_table_is_empty_and_applies_column_defaults(): void
    {
        $this->assertDatabaseCount('loyalty_settings', 0);

        $id = $this->insert('loyalty_settings', $this->settingRow());
        $row = DB::table('loyalty_settings')->find($id);

        $this->assertSame(24, (int) $row->expiry_months);
        $this->assertSame(30, (int) $row->expiry_warning_days);
        foreach (['earn_rate', 'redeem_value_usd', 'min_redeem_points', 'max_redeem_percent', 'updated_by'] as $column) {
            $this->assertNull($row->{$column}, $column);
        }
    }

    public function test_other_column_defaults(): void
    {
        $guest = $this->guestId();

        $reward = DB::table('loyalty_rewards')->find($this->insert('loyalty_rewards', $this->rewardRow()));
        $this->assertSame(1, (int) $reward->is_active);
        $this->assertSame(0, (int) $reward->sort_order);

        $entry = DB::table('loyalty_ledger_entries')->find($this->insert('loyalty_ledger_entries', $this->ledgerRow($guest)));
        $this->assertSame(0, (int) $entry->shortfall_points);

        $application = DB::table('loyalty_reservation_applications')->find(
            $this->insert('loyalty_reservation_applications', $this->applicationRow($this->reservationId($guest), $guest)),
        );
        $this->assertSame(0, (int) $application->points_redeemed);
        $this->assertEquals(0, $application->points_discount_usd);
        $this->assertEquals(0, $application->voucher_discount_usd);
    }

    public function test_config_carries_only_the_two_program_constants(): void
    {
        $this->assertSame(30, config('loyalty.restored_voucher_grace_days'));
        $this->assertSame(1000000, config('loyalty.max_adjust_points'));
        $this->assertEqualsCanonicalizing(['restored_voucher_grace_days', 'max_adjust_points'], array_keys(config('loyalty')));
    }

    // ── Unique backstops ────────────────────────────────────────────────

    public function test_settings_singleton_is_unique(): void
    {
        $this->insert('loyalty_settings', $this->settingRow());

        $this->expectException(UniqueConstraintViolationException::class);
        $this->insert('loyalty_settings', $this->settingRow());
    }

    public function test_reward_uuid_is_unique(): void
    {
        $this->insert('loyalty_rewards', $this->rewardRow(['uuid' => 'dup-uuid']));

        $this->expectException(UniqueConstraintViolationException::class);
        $this->insert('loyalty_rewards', $this->rewardRow(['uuid' => 'dup-uuid']));
    }

    public function test_batch_folio_and_source_pair_is_unique(): void
    {
        $guest = $this->guestId();
        $folio = Folio::factory()->create()->id;
        $this->insert('loyalty_earn_batches', $this->batchRow($guest, ['folio_id' => $folio, 'source' => 'stay']));

        $this->expectException(UniqueConstraintViolationException::class);
        $this->insert('loyalty_earn_batches', $this->batchRow($guest, ['folio_id' => $folio, 'source' => 'stay']));
    }

    public function test_batches_without_a_folio_may_share_a_source(): void
    {
        $guest = $this->guestId();
        $this->insert('loyalty_earn_batches', $this->batchRow($guest, ['folio_id' => null, 'source' => 'manual']));
        $this->insert('loyalty_earn_batches', $this->batchRow($guest, ['folio_id' => null, 'source' => 'manual']));

        $this->assertDatabaseCount('loyalty_earn_batches', 2);
    }

    public function test_one_folio_may_earn_once_per_source(): void
    {
        $guest = $this->guestId();
        $folio = Folio::factory()->create()->id;
        $this->insert('loyalty_earn_batches', $this->batchRow($guest, ['folio_id' => $folio, 'source' => 'stay']));
        $this->insert('loyalty_earn_batches', $this->batchRow($guest, ['folio_id' => $folio, 'source' => 'service']));

        $this->assertDatabaseCount('loyalty_earn_batches', 2);
    }

    public function test_voucher_code_is_unique(): void
    {
        $guest = $this->guestId();
        $this->insert('loyalty_vouchers', $this->voucherRow($guest, ['code' => 'LOY-AAAA1111']));

        $this->expectException(UniqueConstraintViolationException::class);
        $this->insert('loyalty_vouchers', $this->voucherRow($guest, ['code' => 'LOY-AAAA1111']));
    }

    public function test_voucher_uuid_is_unique(): void
    {
        $guest = $this->guestId();
        $this->insert('loyalty_vouchers', $this->voucherRow($guest, ['uuid' => 'dup-uuid']));

        $this->expectException(UniqueConstraintViolationException::class);
        $this->insert('loyalty_vouchers', $this->voucherRow($guest, ['uuid' => 'dup-uuid']));
    }

    public function test_voucher_reservation_id_is_unique_when_set(): void
    {
        $guest = $this->guestId();
        $reservation = $this->reservationId($guest);
        $this->insert('loyalty_vouchers', $this->voucherRow($guest, ['reservation_id' => $reservation]));

        // Many unused vouchers (null reservation) coexist.
        $this->insert('loyalty_vouchers', $this->voucherRow($guest));
        $this->insert('loyalty_vouchers', $this->voucherRow($guest));

        $this->expectException(UniqueConstraintViolationException::class);
        $this->insert('loyalty_vouchers', $this->voucherRow($guest, ['reservation_id' => $reservation]));
    }

    public function test_ledger_idempotency_key_is_unique(): void
    {
        $guest = $this->guestId();
        $this->insert('loyalty_ledger_entries', $this->ledgerRow($guest, ['idempotency_key' => 'earn:folio:1']));

        $this->expectException(UniqueConstraintViolationException::class);
        $this->insert('loyalty_ledger_entries', $this->ledgerRow($guest, ['idempotency_key' => 'earn:folio:1']));
    }

    public function test_ledger_uuid_is_unique(): void
    {
        $guest = $this->guestId();
        $this->insert('loyalty_ledger_entries', $this->ledgerRow($guest, ['uuid' => 'dup-uuid']));

        $this->expectException(UniqueConstraintViolationException::class);
        $this->insert('loyalty_ledger_entries', $this->ledgerRow($guest, ['uuid' => 'dup-uuid']));
    }

    public function test_an_entry_is_reversed_at_most_once(): void
    {
        $guest = $this->guestId();
        $earn = $this->insert('loyalty_ledger_entries', $this->ledgerRow($guest, ['type' => 'earn']));

        // Entries that reverse nothing may repeat (nulls never collide).
        $this->insert('loyalty_ledger_entries', $this->ledgerRow($guest));
        $this->insert('loyalty_ledger_entries', $this->ledgerRow($guest));

        $this->insert('loyalty_ledger_entries', $this->ledgerRow($guest, ['type' => 'clawback', 'points' => -100, 'reverses_entry_id' => $earn]));

        $this->expectException(UniqueConstraintViolationException::class);
        $this->insert('loyalty_ledger_entries', $this->ledgerRow($guest, ['type' => 'clawback', 'points' => -100, 'reverses_entry_id' => $earn]));
    }

    public function test_allocation_pair_is_unique(): void
    {
        $guest = $this->guestId();
        $entry = $this->insert('loyalty_ledger_entries', $this->ledgerRow($guest));
        $batch = $this->insert('loyalty_earn_batches', $this->batchRow($guest));
        $this->insert('loyalty_allocations', ['ledger_entry_id' => $entry, 'batch_id' => $batch, 'points' => 10]);

        $this->expectException(UniqueConstraintViolationException::class);
        $this->insert('loyalty_allocations', ['ledger_entry_id' => $entry, 'batch_id' => $batch, 'points' => 20]);
    }

    public function test_application_reservation_id_is_unique(): void
    {
        $guest = $this->guestId();
        $reservation = $this->reservationId($guest);
        $this->insert('loyalty_reservation_applications', $this->applicationRow($reservation, $guest));

        $this->expectException(UniqueConstraintViolationException::class);
        $this->insert('loyalty_reservation_applications', $this->applicationRow($reservation, $guest));
    }

    public function test_application_idempotency_key_is_unique_per_guest(): void
    {
        $guest = $this->guestId();
        $this->insert('loyalty_reservation_applications', $this->applicationRow($this->reservationId($guest), $guest, ['idempotency_key' => 'K-1']));

        // The same key for another guest is fine.
        $other = $this->guestId();
        $this->insert('loyalty_reservation_applications', $this->applicationRow($this->reservationId($other), $other, ['idempotency_key' => 'K-1']));

        $this->expectException(UniqueConstraintViolationException::class);
        $this->insert('loyalty_reservation_applications', $this->applicationRow($this->reservationId($guest), $guest, ['idempotency_key' => 'K-1']));
    }

    public function test_application_redeem_entry_is_unique(): void
    {
        $guest = $this->guestId();
        $entry = $this->insert('loyalty_ledger_entries', $this->ledgerRow($guest, ['type' => 'redeem', 'points' => -100]));
        $this->insert('loyalty_reservation_applications', $this->applicationRow($this->reservationId($guest), $guest, ['redeem_entry_id' => $entry]));

        $this->expectException(UniqueConstraintViolationException::class);
        $this->insert('loyalty_reservation_applications', $this->applicationRow($this->reservationId($guest), $guest, ['redeem_entry_id' => $entry]));
    }

    public function test_applications_may_share_one_voucher(): void
    {
        $guest = $this->guestId();
        $voucher = $this->insert('loyalty_vouchers', $this->voucherRow($guest));

        $this->insert('loyalty_reservation_applications', $this->applicationRow($this->reservationId($guest), $guest, ['voucher_id' => $voucher]));
        $this->insert('loyalty_reservation_applications', $this->applicationRow($this->reservationId($guest), $guest, ['voucher_id' => $voucher]));

        $this->assertSame(2, DB::table('loyalty_reservation_applications')->where('voucher_id', $voucher)->count());
    }

    // ── Foreign keys ────────────────────────────────────────────────────

    public function test_guest_referenced_by_a_batch_cannot_be_deleted(): void
    {
        $guest = $this->guestId();
        $this->insert('loyalty_earn_batches', $this->batchRow($guest));

        $this->expectException(QueryException::class);
        $this->expectExceptionMessage('FOREIGN KEY');
        DB::table('guests')->where('id', $guest)->delete();
    }

    public function test_batch_referenced_by_an_allocation_cannot_be_deleted(): void
    {
        $guest = $this->guestId();
        $entry = $this->insert('loyalty_ledger_entries', $this->ledgerRow($guest));
        $batch = $this->insert('loyalty_earn_batches', $this->batchRow($guest));
        $this->insert('loyalty_allocations', ['ledger_entry_id' => $entry, 'batch_id' => $batch, 'points' => 10]);

        $this->expectException(QueryException::class);
        $this->expectExceptionMessage('FOREIGN KEY');
        DB::table('loyalty_earn_batches')->where('id', $batch)->delete();
    }

    public function test_user_referenced_by_the_ledger_cannot_be_deleted(): void
    {
        $user = $this->userId();
        $this->insert('loyalty_ledger_entries', $this->ledgerRow($this->guestId(), ['performed_by' => $user]));

        $this->expectException(QueryException::class);
        $this->expectExceptionMessage('FOREIGN KEY');
        DB::table('users')->where('id', $user)->delete();
    }

    public function test_ledger_entry_that_is_reversed_cannot_be_deleted(): void
    {
        $guest = $this->guestId();
        $earn = $this->insert('loyalty_ledger_entries', $this->ledgerRow($guest, ['type' => 'earn']));
        $this->insert('loyalty_ledger_entries', $this->ledgerRow($guest, ['type' => 'clawback', 'points' => -100, 'reverses_entry_id' => $earn]));

        $this->expectException(QueryException::class);
        $this->expectExceptionMessage('FOREIGN KEY');
        DB::table('loyalty_ledger_entries')->where('id', $earn)->delete();
    }

    public function test_force_deleting_a_reward_nulls_the_voucher_link_and_keeps_the_voucher(): void
    {
        $guest = $this->guestId();
        $reward = LoyaltyReward::factory()->create();
        $id = $this->insert('loyalty_vouchers', $this->voucherRow($guest, ['loyalty_reward_id' => $reward->id]));

        $reward->forceDelete();

        $voucher = DB::table('loyalty_vouchers')->find($id);
        $this->assertNotNull($voucher);
        $this->assertNull($voucher->loyalty_reward_id);
        $this->assertSame(['en' => 'Reward'], json_decode($voucher->reward_name, true));
    }

    // ── Indexes ─────────────────────────────────────────────────────────

    public function test_expected_indexes_exist(): void
    {
        $this->assertIndex('loyalty_earn_batches', ['guest_id', 'status', 'expires_at']);
        $this->assertIndex('loyalty_earn_batches', ['status', 'expires_at']);
        $this->assertIndex('loyalty_earn_batches', ['status', 'expiry_warned_at', 'expires_at']);
        $this->assertIndex('loyalty_earn_batches', ['folio_id', 'source'], true);
        $this->assertIndex('loyalty_earn_batches', ['folio_id']);
        $this->assertIndex('loyalty_earn_batches', ['awarded_by']);

        $this->assertIndex('loyalty_vouchers', ['guest_id', 'status']);
        $this->assertIndex('loyalty_vouchers', ['status', 'expires_at']);
        $this->assertIndex('loyalty_vouchers', ['loyalty_reward_id']);
        $this->assertIndex('loyalty_vouchers', ['reservation_id'], true);
        $this->assertIndex('loyalty_vouchers', ['code'], true);

        $this->assertIndex('loyalty_ledger_entries', ['guest_id', 'occurred_at', 'id']);
        $this->assertIndex('loyalty_ledger_entries', ['type', 'occurred_at']);
        $this->assertIndex('loyalty_ledger_entries', ['reservation_id']);
        $this->assertIndex('loyalty_ledger_entries', ['folio_id']);
        $this->assertIndex('loyalty_ledger_entries', ['batch_id']);
        $this->assertIndex('loyalty_ledger_entries', ['voucher_id']);
        $this->assertIndex('loyalty_ledger_entries', ['performed_by']);
        $this->assertIndex('loyalty_ledger_entries', ['idempotency_key'], true);
        $this->assertIndex('loyalty_ledger_entries', ['reverses_entry_id'], true);

        $this->assertIndex('loyalty_allocations', ['batch_id']);
        $this->assertIndex('loyalty_allocations', ['ledger_entry_id', 'batch_id'], true);

        $this->assertIndex('loyalty_reservation_applications', ['voucher_id'], false);
        $this->assertIndex('loyalty_reservation_applications', ['status']);
        $this->assertIndex('loyalty_reservation_applications', ['reservation_id'], true);
        $this->assertIndex('loyalty_reservation_applications', ['guest_id', 'idempotency_key'], true);
        $this->assertIndex('loyalty_reservation_applications', ['redeem_entry_id'], true);

        $this->assertIndex('loyalty_rewards', ['type']);
        $this->assertIndex('loyalty_rewards', ['is_active', 'sort_order']);
        $this->assertIndex('loyalty_rewards', ['sort_order']);

        $this->assertIndex('loyalty_settings', ['updated_by']);
        $this->assertIndex('loyalty_settings', ['singleton'], true);
    }

    // ── Model layer ─────────────────────────────────────────────────────

    public function test_every_factory_creates_a_row(): void
    {
        foreach ([
            LoyaltySetting::class, LoyaltyReward::class, LoyaltyEarnBatch::class, LoyaltyLedgerEntry::class,
            LoyaltyAllocation::class, LoyaltyVoucher::class, LoyaltyReservationApplication::class,
        ] as $model) {
            $this->assertNotNull($model::factory()->create()->getKey(), $model);
        }
    }

    public function test_factories_do_not_collide_across_fifty_creations(): void
    {
        foreach ([
            LoyaltyReward::class, LoyaltyEarnBatch::class, LoyaltyLedgerEntry::class,
            LoyaltyAllocation::class, LoyaltyVoucher::class, LoyaltyReservationApplication::class,
        ] as $model) {
            $this->assertCount(50, $model::factory()->count(50)->create(), $model);
        }
    }

    public function test_setting_factory_unset_state_and_instance_defaults(): void
    {
        $set = LoyaltySetting::factory()->create()->fresh();
        $this->assertSame('1.0000', $set->earn_rate);
        $this->assertSame('0.0100', $set->redeem_value_usd);
        $this->assertSame('50.00', $set->max_redeem_percent);
        $this->assertSame(100, $set->min_redeem_points);

        LoyaltySetting::query()->delete();
        $unset = LoyaltySetting::factory()->unset()->create()->fresh();
        foreach (['earn_rate', 'redeem_value_usd', 'min_redeem_points', 'max_redeem_percent'] as $column) {
            $this->assertNull($unset->{$column}, $column);
        }

        $fresh = new LoyaltySetting;
        $this->assertSame(1, $fresh->singleton);
        $this->assertSame(24, $fresh->expiry_months);
        $this->assertSame(30, $fresh->expiry_warning_days);
    }

    public function test_setting_updated_by_relation(): void
    {
        $user = User::factory()->create();
        $set = LoyaltySetting::factory()->create(['updated_by' => $user->id]);

        $this->assertTrue($set->updatedBy->is($user));
    }

    public function test_reward_is_soft_deletable_with_translated_name(): void
    {
        $reward = LoyaltyReward::factory()->create();

        $this->assertNotEmpty($reward->uuid);
        $this->assertEqualsCanonicalizing(['en', 'ar'], array_keys($reward->getTranslations('name')));
        $this->assertEqualsCanonicalizing(['en', 'ar'], array_keys($reward->getTranslations('description')));

        $reward->delete();
        $this->assertSoftDeleted('loyalty_rewards', ['id' => $reward->id]);
        $this->assertNotNull(LoyaltyReward::withTrashed()->find($reward->id));
    }

    public function test_reward_factory_states(): void
    {
        $this->assertSame(LoyaltyRewardType::DISCOUNT_VOUCHER, LoyaltyReward::factory()->create()->fresh()->type);
        $this->assertSame('25.00', LoyaltyReward::factory()->create()->fresh()->discount_usd);
        $this->assertSame(LoyaltyRewardType::FREE_NIGHT, LoyaltyReward::factory()->freeNight()->create()->fresh()->type);
        $this->assertSame(LoyaltyRewardType::ROOM_UPGRADE, LoyaltyReward::factory()->roomUpgrade()->create()->fresh()->type);
        $this->assertFalse(LoyaltyReward::factory()->inactive()->create()->fresh()->is_active);
    }

    public function test_batch_relations_and_factory_states(): void
    {
        $guest = Guest::factory()->create();
        $folio = Folio::factory()->create();
        $user = User::factory()->create();

        $batch = LoyaltyEarnBatch::factory()->forFolio($folio, LoyaltyBatchSource::STAY)->create([
            'guest_id' => $guest->id, 'awarded_by' => $user->id,
        ]);
        $entry = LoyaltyLedgerEntry::factory()->create(['guest_id' => $guest->id, 'batch_id' => $batch->id]);
        $alloc = LoyaltyAllocation::factory()->create(['ledger_entry_id' => $entry->id, 'batch_id' => $batch->id, 'points' => 10]);

        $this->assertTrue($batch->guest->is($guest));
        $this->assertTrue($batch->folio->is($folio));
        $this->assertTrue($batch->awardedBy->is($user));
        $this->assertSame(LoyaltyBatchSource::STAY, $batch->fresh()->source);
        $this->assertCount(1, $batch->allocations);
        $this->assertTrue($batch->allocations->first()->is($alloc));
        $this->assertCount(1, $batch->entries);

        $this->assertSame(LoyaltyBatchStatus::EXPIRED, LoyaltyEarnBatch::factory()->expired()->create()->fresh()->status);
        $depleted = LoyaltyEarnBatch::factory()->depleted()->create()->fresh();
        $this->assertSame(LoyaltyBatchStatus::DEPLETED, $depleted->status);
        $this->assertSame(0, $depleted->points_remaining);

        $at = now()->addDays(10)->startOfSecond();
        $this->assertTrue(LoyaltyEarnBatch::factory()->expiringAt($at)->create()->fresh()->expires_at->equalTo($at));
    }

    public function test_ledger_entry_relations(): void
    {
        $guest = Guest::factory()->create();
        $user = User::factory()->create();
        $folio = Folio::factory()->create();
        $reservation = Reservation::factory()->create(['guest_id' => $guest->id]);
        $batch = LoyaltyEarnBatch::factory()->create(['guest_id' => $guest->id]);
        $voucher = LoyaltyVoucher::factory()->create(['guest_id' => $guest->id]);

        $earn = LoyaltyLedgerEntry::factory()->create([
            'guest_id' => $guest->id, 'type' => LoyaltyEntryType::EARN, 'batch_id' => $batch->id,
            'folio_id' => $folio->id, 'voucher_id' => $voucher->id, 'reservation_id' => $reservation->id,
            'performed_by' => $user->id,
        ]);
        $claw = LoyaltyLedgerEntry::factory()->create([
            'guest_id' => $guest->id, 'type' => LoyaltyEntryType::CLAWBACK, 'points' => -500, 'reverses_entry_id' => $earn->id,
        ]);
        LoyaltyAllocation::factory()->create(['ledger_entry_id' => $earn->id, 'batch_id' => $batch->id, 'points' => 5]);

        $this->assertTrue($earn->batch->is($batch));
        $this->assertTrue($earn->voucher->is($voucher));
        $this->assertTrue($earn->reservation->is($reservation));
        $this->assertTrue($earn->folio->is($folio));
        $this->assertTrue($earn->performer->is($user));
        $this->assertTrue($earn->guest->is($guest));
        $this->assertCount(1, $earn->allocations);
        $this->assertTrue($claw->reversedEntry->is($earn));
        $this->assertTrue($earn->reversal->is($claw));
        $this->assertNull($claw->reversal);
    }

    public function test_voucher_relations_and_factory_states(): void
    {
        $guest = Guest::factory()->create();
        $reward = LoyaltyReward::factory()->create();
        $reservation = Reservation::factory()->create(['guest_id' => $guest->id]);

        $voucher = LoyaltyVoucher::factory()->create(['guest_id' => $guest->id, 'loyalty_reward_id' => $reward->id]);
        $this->assertMatchesRegularExpression('/^LOY-[0-9A-HJKMNP-TV-Z]{8}$/', $voucher->code);
        $this->assertTrue($voucher->guest->is($guest));
        $this->assertTrue($voucher->reward->is($reward));

        // A soft-deleted reward stays reachable through the voucher.
        $reward->delete();
        $this->assertNotNull($voucher->fresh()->reward);

        $redeem = LoyaltyLedgerEntry::factory()->create([
            'guest_id' => $guest->id, 'type' => LoyaltyEntryType::REDEEM, 'points' => -100, 'voucher_id' => $voucher->id,
        ]);
        $this->assertTrue($voucher->redeemEntry->is($redeem));

        $used = LoyaltyVoucher::factory()->used($reservation)->create(['guest_id' => $guest->id])->fresh();
        $this->assertSame(LoyaltyVoucherStatus::USED, $used->status);
        $this->assertTrue($used->reservation->is($reservation));
        $this->assertNotNull($used->used_at);

        $this->assertSame(LoyaltyVoucherStatus::EXPIRED, LoyaltyVoucher::factory()->expired()->create()->fresh()->status);
        $this->assertSame(LoyaltyVoucherStatus::ACTIVE, $voucher->fresh()->status);
        $this->assertTrue($voucher->fresh()->expires_at->isFuture());
    }

    public function test_application_relations_and_reservation_and_guest_hooks(): void
    {
        $guest = Guest::factory()->create();
        $reservation = Reservation::factory()->create(['guest_id' => $guest->id]);
        $voucher = LoyaltyVoucher::factory()->create(['guest_id' => $guest->id]);
        $redeem = LoyaltyLedgerEntry::factory()->create(['guest_id' => $guest->id, 'type' => LoyaltyEntryType::REDEEM, 'points' => -100]);

        $application = LoyaltyReservationApplication::factory()->create([
            'reservation_id' => $reservation->id, 'guest_id' => $guest->id,
            'voucher_id' => $voucher->id, 'redeem_entry_id' => $redeem->id,
        ]);

        $this->assertTrue($application->reservation->is($reservation));
        $this->assertTrue($application->guest->is($guest));
        $this->assertTrue($application->voucher->is($voucher));
        $this->assertTrue($application->redeemEntry->is($redeem));
        $this->assertTrue($reservation->loyaltyApplication->is($application));

        $batch = LoyaltyEarnBatch::factory()->create(['guest_id' => $guest->id]);
        $this->assertTrue($guest->loyaltyBatches->first()->is($batch));
        $this->assertTrue($guest->loyaltyLedgerEntries->contains($redeem));
        $this->assertTrue($guest->loyaltyVouchers->first()->is($voucher));
    }

    public function test_casts(): void
    {
        $batch = LoyaltyEarnBatch::factory()->create()->fresh();
        $this->assertSame(LoyaltyBatchSource::MANUAL, $batch->source);
        $this->assertSame(LoyaltyBatchStatus::ACTIVE, $batch->status);
        $this->assertSame(500, $batch->points);
        $this->assertSame(500, $batch->points_remaining);
        $this->assertInstanceOf(CarbonInterface::class, $batch->earned_at);
        $this->assertInstanceOf(CarbonInterface::class, $batch->expires_at);
        $this->assertNull($batch->expiry_warned_at);

        $entry = LoyaltyLedgerEntry::factory()->create(['discount_usd' => '12.5', 'source' => 'stay'])->fresh();
        $this->assertSame(LoyaltyEntryType::ADJUST, $entry->type);
        $this->assertSame(LoyaltyBatchSource::STAY, $entry->source);
        $this->assertSame(500, $entry->points);
        $this->assertSame(0, $entry->shortfall_points);
        $this->assertSame('12.50', $entry->discount_usd);
        $this->assertInstanceOf(CarbonInterface::class, $entry->occurred_at);

        $voucher = LoyaltyVoucher::factory()->create(['value_usd' => '25'])->fresh();
        $this->assertSame(LoyaltyRewardType::DISCOUNT_VOUCHER, $voucher->type);
        $this->assertSame('25.00', $voucher->value_usd);
        $this->assertIsArray($voucher->reward_name);
        $this->assertIsInt($voucher->points_spent);

        $application = LoyaltyReservationApplication::factory()->create(['points_discount_usd' => '5', 'points_redeemed' => 500])->fresh();
        $this->assertSame(LoyaltyApplicationStatus::APPLIED, $application->status);
        $this->assertSame('5.00', $application->points_discount_usd);
        $this->assertSame(500, $application->points_redeemed);
        $this->assertNull($application->reversed_at);
    }

    // ── Audit scope ─────────────────────────────────────────────────────

    public function test_activity_log_trait_is_used_only_by_the_four_audited_models(): void
    {
        $audited = [LoyaltySetting::class, LoyaltyReward::class, LoyaltyVoucher::class, LoyaltyReservationApplication::class];
        $ledger = [LoyaltyLedgerEntry::class, LoyaltyEarnBatch::class, LoyaltyAllocation::class];

        foreach ($audited as $model) {
            $this->assertContains(LogsActivity::class, class_uses_recursive($model), $model);
        }
        foreach ($ledger as $model) {
            $this->assertNotContains(LogsActivity::class, class_uses_recursive($model), $model);
        }
    }

    public function test_updating_a_voucher_status_writes_an_activity_row(): void
    {
        $voucher = LoyaltyVoucher::factory()->create();
        $count = fn () => Activity::query()->where('subject_type', LoyaltyVoucher::class)->where('subject_id', $voucher->id)->count();
        $before = $count();

        $voucher->update(['status' => LoyaltyVoucherStatus::VOID]);

        $this->assertSame($before + 1, $count());
    }

    public function test_ledger_entries_batches_and_allocations_write_no_activity(): void
    {
        $entry = LoyaltyLedgerEntry::factory()->create();
        LoyaltyAllocation::factory()->create();

        $this->assertSame(0, Activity::query()->where('subject_type', LoyaltyLedgerEntry::class)->count());
        $this->assertSame(0, Activity::query()->where('subject_type', LoyaltyEarnBatch::class)->count());
        $this->assertSame(0, Activity::query()->where('subject_type', LoyaltyAllocation::class)->count());

        $this->assertNotNull($entry->fresh()->created_at);
        $this->assertArrayNotHasKey('updated_at', $entry->fresh()->getAttributes());
    }

    // ── BuildsLoyaltyFixtures ───────────────────────────────────────────

    public function test_fixture_configure_loyalty_upserts_the_singleton(): void
    {
        $first = $this->configureLoyalty();
        $this->assertSame('1.0000', $first->earn_rate);

        $second = $this->configureLoyalty(['earn_rate' => '2.0000', 'max_redeem_percent' => null]);

        $this->assertSame(1, LoyaltySetting::query()->count());
        $this->assertTrue($second->is($first));
        $this->assertSame('2.0000', $second->earn_rate);
        $this->assertNull($second->max_redeem_percent);
    }

    public function test_fixture_grant_points_keeps_ledger_and_batches_in_agreement(): void
    {
        $guest = Guest::factory()->create();

        $manual = $this->grantPoints($guest, 500);
        $stay = $this->grantPoints($guest, 300, now()->addMonths(3), LoyaltyBatchSource::STAY);
        $refund = $this->grantPoints($guest, 200, null, LoyaltyBatchSource::REFUND);

        $this->assertSame(500, $manual->points_remaining);
        $this->assertTrue($stay->expires_at->between(now()->addMonths(3)->subMinute(), now()->addMonths(3)->addMinute()));

        $types = [
            $manual->id => LoyaltyEntryType::ADJUST,
            $stay->id => LoyaltyEntryType::EARN,
            $refund->id => LoyaltyEntryType::REFUND,
        ];
        foreach ($types as $batchId => $type) {
            $entry = LoyaltyLedgerEntry::query()->where('batch_id', $batchId)->sole();
            $this->assertSame($type, $entry->type);
            $this->assertStringStartsWith('fixture:', $entry->idempotency_key);
        }

        $this->assertSame(
            (int) $guest->loyaltyBatches()->sum('points_remaining'),
            (int) $guest->loyaltyLedgerEntries()->sum('points'),
        );
    }

    public function test_fixture_row_counts_cover_the_seven_tables(): void
    {
        $this->assertSame([
            'loyalty_settings' => 0, 'loyalty_rewards' => 0, 'loyalty_earn_batches' => 0,
            'loyalty_vouchers' => 0, 'loyalty_ledger_entries' => 0, 'loyalty_allocations' => 0,
            'loyalty_reservation_applications' => 0,
        ], $this->loyaltyRowCounts());

        $this->grantPoints(Guest::factory()->create(), 100);

        $counts = $this->loyaltyRowCounts();
        $this->assertSame(1, $counts['loyalty_earn_batches']);
        $this->assertSame(1, $counts['loyalty_ledger_entries']);
    }

    public function test_fixture_tokens_and_generated_stay(): void
    {
        $guest = Guest::factory()->create();
        $this->assertTrue(PersonalAccessToken::findToken($this->guestToken($guest))->tokenable->is($guest));

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->assertNotEmpty($this->staffToken());
        $this->assertNotEmpty($this->presetToken('reception'));

        [$reservation, $folio] = $this->generatedStay('300.00');
        $this->assertInstanceOf(Reservation::class, $reservation);
        $this->assertInstanceOf(Folio::class, $folio);
        $this->assertSame($reservation->id, $folio->reservation_id);
        $this->assertSame('300.00', (string) $folio->total_usd);
        $this->assertInstanceOf(GenerateFolioAction::class, app(GenerateFolioAction::class));
    }
}
