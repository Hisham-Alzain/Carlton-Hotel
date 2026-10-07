<?php

namespace Tests\Feature\Loyalty;

use App\Actions\Auth\OtpDispatcher;
use App\Actions\Folio\GenerateFolioAction;
use App\Actions\Guest\DeleteGuestAccountAction;
use App\Actions\Loyalty\ExpireLoyaltyBatchesAction;
use App\Actions\Loyalty\ForfeitLoyaltyBalanceAction;
use App\Contracts\FirebaseServiceInterface;
use App\Enums\FolioStatus;
use App\Enums\GuestAccountStatus;
use App\Enums\LoyaltyBatchSource;
use App\Enums\LoyaltyBatchStatus;
use App\Enums\LoyaltyEntryType;
use App\Enums\LoyaltyVoucherStatus;
use App\Enums\ReservationStatus;
use App\Exceptions\GuestAccountDeletionBlockedException;
use App\Models\Folio;
use App\Models\Guest;
use App\Models\LoyaltyEarnBatch;
use App\Models\LoyaltyLedgerEntry;
use App\Models\LoyaltySetting;
use App\Models\LoyaltyVoucher;
use App\Models\Payment;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\RoomType;
use App\Support\HotelClock;
use App\Support\LoyaltyLedger;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Testing\TestResponse;
use RuntimeException;
use Spatie\Activitylog\Models\Activity;
use Tests\Concerns\BuildsLoyaltyFixtures;
use Tests\Concerns\RecordsRowLocks;
use Tests\Support\FakeFirebaseService;
use Tests\TestCase;

/**
 * Phase 10 gap LOY-23 (with LOY-09, LOY-17, LOY-19): deleting a guest account
 * (9.1 DeleteGuestAccountAction) forfeits the loyalty balance as `expire`
 * entries and closes every active voucher inside the deletion transaction,
 * audits counts only, and a deleted account never gets a balance back through
 * a later cancellation or settlement.
 *
 * G-7: every guest, reservation, folio, batch and voucher state is reached
 * through public routes, actions and factories; no table is written directly.
 */
class LoyaltyForfeitOnDeletionTest extends TestCase
{
    use BuildsLoyaltyFixtures;
    use RecordsRowLocks;
    use RefreshDatabase;

    private const PHONE = '+963955700111';

    private const EMAIL = 'forfeit.me@example.com';

    private const EN = ['Accept-Language' => 'en'];

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->app->instance(FirebaseServiceInterface::class, new FakeFirebaseService());
        OtpDispatcher::reset();
    }

    // ------------------------------------------------------------------ helpers

    private function guest(): Guest
    {
        return Guest::factory()->create(['phone' => self::PHONE, 'email' => self::EMAIL]);
    }

    private function deleteMe(Guest $guest): TestResponse
    {
        return $this->deleteMeWith($this->guestToken($guest));
    }

    private function deleteMeWith(string $token): TestResponse
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($token)->deleteJson('/api/auth/guest/me', ['confirm' => true], self::EN);
    }

    private function erase(Guest $guest): array
    {
        return app(DeleteGuestAccountAction::class)->handle($guest);
    }

    private function voucher(Guest $guest, array $overrides = []): LoyaltyVoucher
    {
        return LoyaltyVoucher::factory()->create(array_merge(['guest_id' => $guest->id], $overrides));
    }

    private function staffGet(string $url, string $permission = 'loyalty.view'): TestResponse
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($this->staffToken($permission))->getJson($url, self::EN);
    }

    private function report(): TestResponse
    {
        $today = HotelClock::today()->toDateString();

        return $this->staffGet('/api/cms/loyalty/reports?date_from='.$today.'&date_to='.$today);
    }

    private function deletionAudit(Guest $guest): array
    {
        return Activity::query()
            ->where('description', 'guest.account_deleted')
            ->where('subject_id', $guest->id)
            ->sole()
            ->properties
            ->toArray();
    }

    private function activeBatchCount(Guest $guest): int
    {
        return LoyaltyEarnBatch::where('guest_id', $guest->id)->where('status', LoyaltyBatchStatus::ACTIVE->value)->count();
    }

    private function activeVoucherCount(Guest $guest): int
    {
        return LoyaltyVoucher::where('guest_id', $guest->id)->where('status', LoyaltyVoucherStatus::ACTIVE->value)->count();
    }

    /** @return array<int, array<string, mixed>> */
    private function snapshot(Guest $guest): array
    {
        $batches = LoyaltyEarnBatch::where('guest_id', $guest->id)->orderBy('id')->get()->map->getAttributes()->all();
        $vouchers = LoyaltyVoucher::where('guest_id', $guest->id)->orderBy('id')->get()->map->getAttributes()->all();

        return ['batches' => $batches, 'vouchers' => $vouchers];
    }

    /** @param list<string> $locked */
    private function firstLock(array $locked, string $table): int|false
    {
        return collect($locked)->search(fn (string $sql) => str_contains($sql, 'from "'.$table.'"'));
    }

    /** @param list<string> $locked */
    private function assertOrder(array $locked, array $tables): void
    {
        $positions = [];
        foreach ($tables as $table) {
            $position = $this->firstLock($locked, $table);
            $this->assertNotFalse($position, "no `for update` select on {$table}; saw:\n".implode("\n", $locked));
            $positions[] = $position;
        }

        $sorted = $positions;
        sort($sorted);
        $this->assertSame($sorted, $positions, 'lock order must be '.implode(' -> ', $tables));
    }

    // ------------------------------------------------------------------ deletion forfeits

    public function test_deleting_the_account_forfeits_points_closes_the_voucher_and_staff_still_see_the_guest(): void
    {
        $this->configureLoyalty();
        $guest = $this->guest();
        $first = $this->grantPoints($guest, 60, now()->addDays(30));
        $second = $this->grantPoints($guest, 40, now()->addDays(60));
        $voucher = $this->voucher($guest);

        $this->deleteMe($guest)->assertOk()->assertJsonPath('success', true);

        $this->assertTrue($guest->fresh()->isDeleted());
        $expires = LoyaltyLedgerEntry::where('type', LoyaltyEntryType::EXPIRE->value)->where('guest_id', $guest->id)->get();
        $this->assertCount(2, $expires);
        $this->assertEqualsCanonicalizing(
            ['expire:batch:'.$first->id, 'expire:batch:'.$second->id],
            $expires->pluck('idempotency_key')->all(),
        );
        $this->assertSame(LoyaltyVoucherStatus::VOID, $voucher->fresh()->status);
        $this->assertSame(0, app(LoyaltyLedger::class)->available($guest->id));

        // Review item 11 (FA-10.16-8): 200 with the 9.1-redacted data, not 404.
        $this->staffGet('/api/cms/loyalty/guests/'.$guest->uuid)
            ->assertOk()
            ->assertJsonPath('data.available_points', 0)
            ->assertJsonPath('data.guest.uuid', $guest->uuid)
            ->assertJsonPath('data.guest.name', null);

        $ledger = $this->staffGet('/api/cms/loyalty/guests/'.$guest->uuid.'/ledger')->assertOk();
        $types = collect($ledger->json('data.items'))->pluck('type');
        $this->assertSame(2, $types->filter(fn ($type) => $type === LoyaltyEntryType::EXPIRE->value)->count());
    }

    public function test_the_deletion_audit_entry_carries_loyalty_counts_only(): void
    {
        $guest = $this->guest();
        $this->grantPoints($guest, 60, now()->addDays(30));
        $this->grantPoints($guest, 40, now()->addDays(60));
        $voucher = $this->voucher($guest);
        $activityBefore = Activity::count();
        $lastId = (int) Activity::max('id');

        $this->deleteMe($guest)->assertOk();

        $this->assertSame(1, Activity::where('description', 'guest.account_deleted')->count());
        $this->assertSame($activityBefore + 1, Activity::count(), 'the voucher change is not logged separately');

        $properties = $this->deletionAudit($guest);
        $this->assertSame(['retained', 'loyalty'], array_keys($properties));
        $this->assertSame(['forfeited_points' => 100, 'expired_batches' => 2, 'closed_vouchers' => 1], $properties['loyalty']);

        foreach (Activity::all() as $row) {
            $blob = $row->description.' '.json_encode($row->properties).' '.json_encode($row->attribute_changes);
            foreach ([$voucher->code, self::PHONE, self::EMAIL] as $needle) {
                $this->assertStringNotContainsString($needle, $blob, "activity_log #{$row->id} holds {$needle}");
            }
        }
        // Rows written by the deletion carry no guest uuid either (subject ids are internal integers).
        foreach (Activity::where('id', '>', $lastId)->get() as $row) {
            $blob = $row->description.' '.json_encode($row->properties).' '.json_encode($row->attribute_changes);
            $this->assertStringNotContainsString($guest->uuid, $blob);
        }
    }

    public function test_deleting_again_changes_nothing(): void
    {
        $guest = $this->guest();
        $this->grantPoints($guest, 60);
        $this->voucher($guest);
        $this->erase($guest);
        $before = [$this->loyaltyRowCounts(), $this->snapshot($guest), Activity::count()];

        $this->assertSame(['data' => null, 'code' => 200], $this->erase($guest->fresh()));

        $this->assertSame($before, [$this->loyaltyRowCounts(), $this->snapshot($guest), Activity::count()]);
    }

    public function test_the_report_drops_forfeited_points_from_outstanding_and_counts_them_as_expired(): void
    {
        $this->configureLoyalty(['redeem_value_usd' => '0.0100']);
        $leaving = $this->guest();
        $staying = Guest::factory()->create();
        $this->grantPoints($leaving, 100);
        $this->grantPoints($staying, 30);

        $this->report()->assertOk()
            ->assertJsonPath('data.outstanding_points', 130)
            ->assertJsonPath('data.liability_usd', '1.30');

        $this->erase($leaving);

        $this->report()->assertOk()
            ->assertJsonPath('data.outstanding_points', 30)
            ->assertJsonPath('data.liability_usd', '0.30')
            ->assertJsonPath('data.expired_points', 100);
    }

    public function test_the_deletion_locks_guest_then_batches_then_vouchers_and_no_reservation_or_folio(): void
    {
        $guest = $this->guest();
        $this->grantPoints($guest, 60);
        $this->voucher($guest);
        Reservation::factory()->cancelled()->create(['guest_id' => $guest->id]);

        $locked = $this->lockedSelects(fn () => $this->erase($guest));

        $this->assertNotEmpty($locked);
        $this->assertStringContainsString('from "guests"', $locked[0]);
        $this->assertOrder($locked, ['guests', 'loyalty_earn_batches', 'loyalty_vouchers']);
        $this->assertFalse($this->firstLock($locked, 'reservations'), 'the deletion must not lock reservations');
        $this->assertFalse($this->firstLock($locked, 'folios'), 'the deletion must not lock folios');
    }

    public function test_a_blocked_deletion_forfeits_nothing(): void
    {
        $guest = $this->guest();
        $this->grantPoints($guest, 60);
        $this->voucher($guest);
        Reservation::factory()->confirmed()->create([
            'guest_id' => $guest->id,
            'check_in' => HotelClock::today()->addDays(3)->toDateString(),
            'check_out' => HotelClock::today()->addDays(5)->toDateString(),
        ]);
        $before = [$this->loyaltyRowCounts(), $this->snapshot($guest)];

        try {
            $this->erase($guest);
            $this->fail('A guest with a live reservation must not be deleted.');
        } catch (GuestAccountDeletionBlockedException) {
            $this->addToAssertionCount(1);
        }

        $this->assertSame($before, [$this->loyaltyRowCounts(), $this->snapshot($guest)]);
        $this->assertFalse($guest->fresh()->isDeleted());
    }

    public function test_a_failing_forfeit_rolls_the_whole_deletion_back(): void
    {
        $guest = $this->guest();
        $this->grantPoints($guest, 60);
        $this->voucher($guest);
        $before = $this->snapshot($guest);
        $this->app->bind(ForfeitLoyaltyBalanceAction::class, fn () => new class(app(LoyaltyLedger::class)) extends ForfeitLoyaltyBalanceAction
        {
            public function handle(Guest $lockedGuest): array
            {
                throw new RuntimeException('forfeit failed');
            }
        });

        try {
            $this->erase($guest);
            $this->fail('The forfeit failure must propagate.');
        } catch (RuntimeException $e) {
            $this->assertSame('forfeit failed', $e->getMessage());
        }

        $fresh = $guest->fresh();
        $this->assertSame(GuestAccountStatus::ACTIVE, $fresh->account_status);
        $this->assertSame(self::PHONE, $fresh->phone);
        $this->assertSame($before, $this->snapshot($guest));
        $this->assertSame(1, $this->activeBatchCount($guest));
        $this->assertSame(1, $this->activeVoucherCount($guest));
    }

    public function test_a_guest_without_loyalty_data_or_settings_deletes_with_zero_counts(): void
    {
        $this->assertSame(0, LoyaltySetting::count());
        $guest = $this->guest();
        $before = $this->loyaltyRowCounts();

        $this->assertSame(['data' => null, 'code' => 200], $this->erase($guest));

        $this->assertTrue($guest->fresh()->isDeleted());
        $this->assertSame(
            ['forfeited_points' => 0, 'expired_batches' => 0, 'closed_vouchers' => 0],
            $this->deletionAudit($guest)['loyalty'],
        );
        $this->assertSame($before, $this->loyaltyRowCounts());
    }

    public function test_the_daily_sweep_finds_nothing_left_after_a_deletion(): void
    {
        $guest = $this->guest();
        $this->grantPoints($guest, 60, now()->subMinute());
        $this->grantPoints($guest, 40);
        $this->voucher($guest, ['expires_at' => now()->subMinute()]);
        $this->voucher($guest);

        $this->erase($guest);
        $ledgerRows = LoyaltyLedgerEntry::count();

        $sweep = app(ExpireLoyaltyBatchesAction::class)->handle();

        $this->assertSame(['batches_expired' => 0, 'vouchers_expired' => 0], $sweep['data']);
        $this->assertSame($ledgerRows, LoyaltyLedgerEntry::count());
    }

    // ------------------------------------------------------------------ settle after deletion (review item 1)

    public function test_a_folio_settled_after_the_deletion_earns_nothing(): void
    {
        $this->configureLoyalty(['earn_rate' => '1.0000']);
        $guest = $this->guest();
        $reservation = Reservation::factory()->confirmed()->create([
            'guest_id' => $guest->id,
            'check_in' => HotelClock::today()->subDays(3)->toDateString(),
            'check_out' => HotelClock::today()->subDay()->toDateString(),
            'total_usd' => '300.00',
        ]);

        // Preconditions: a stale confirmed no-show with no folio does not block the deletion (9.1 D-07).
        $this->assertFalse(Folio::where('reservation_id', $reservation->id)->exists());
        $this->assertSame(['data' => null, 'code' => 200], $this->erase($guest));
        $this->assertTrue($guest->fresh()->isDeleted());

        $folio = app(GenerateFolioAction::class)->handle($reservation)['data'];
        $this->assertSame(FolioStatus::OPEN, $folio->status);
        $lines = $folio->items()->get();
        $this->assertCount(1, $lines);
        $this->assertSame('reservation', $lines->first()->source_type);
        $this->assertSame('300.00', (string) $lines->first()->amount_usd);
        Payment::factory()->create([
            'payable_type' => Reservation::class,
            'payable_id' => $reservation->id,
            'amount_usd' => '300.00',
        ]);
        $before = $this->loyaltyRowCounts();

        $this->app['auth']->forgetGuards();
        $this->withToken($this->staffToken('folios.settle'))
            ->postJson("/api/cms/folios/{$folio->uuid}/settle", [], self::EN)
            ->assertOk()
            ->assertJsonPath('data.status', 'settled');

        $this->assertSame(0, LoyaltyEarnBatch::where('guest_id', $guest->id)->count());
        $this->assertSame(0, LoyaltyLedgerEntry::where('type', LoyaltyEntryType::EARN->value)->where('folio_id', $folio->id)->count());
        $this->assertSame($before, $this->loyaltyRowCounts());

        $skip = Activity::where('description', 'loyalty.earn_skipped_deleted')->sole();
        $this->assertSame(Folio::class, $skip->subject_type);
        $this->assertSame($folio->id, (int) $skip->subject_id);
        $this->assertNull($skip->causer_type);
        $this->assertNull($skip->causer_id);
        $this->assertSame(['skipped_points' => 300], $skip->properties->toArray());
        $this->assertSame(0, Activity::where('description', 'loyalty.earn_skipped_cancelled')->count());
    }

    // ------------------------------------------------------------------ cancel after deletion (review items 2 and 5)

    /** LoyaltyReversalOnCancelTest program and a 150.00 room type with six rooms (2 nights = 300.00). */
    private function bookingProgram(): RoomType
    {
        $this->configureLoyalty([
            'redeem_value_usd' => '0.0100',
            'max_redeem_percent' => '50.00',
            'min_redeem_points' => 100,
            'earn_rate' => '1.0000',
        ]);
        $roomType = RoomType::factory()->create(['base_price_usd' => 150, 'is_active' => true]);
        Room::factory()->count(6)->create(['room_type_id' => $roomType->id, 'is_active' => true]);

        return $roomType;
    }

    private function bookWith(Guest $guest, RoomType $roomType, array $extra): Reservation
    {
        $this->app['auth']->forgetGuards();

        $response = $this->withToken($this->guestToken($guest))
            ->withHeaders(['Idempotency-Key' => 'K-1'] + self::EN)
            ->postJson('/api/reservations', array_merge([
                'room_type_uuid' => $roomType->uuid,
                'check_in' => now()->addDays(10)->toDateString(),
                'check_out' => now()->addDays(12)->toDateString(),
                'payment_method' => 'on_arrival',
            ], $extra))
            ->assertStatus(201);

        return Reservation::where('uuid', $response->json('data.uuid'))->firstOrFail();
    }

    private function cancelAsStaff(Reservation $reservation): TestResponse
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($this->staffToken('reservations.cancel'))
            ->deleteJson('/api/cms/reservations/'.$reservation->uuid, [], self::EN);
    }

    /**
     * The starting state of every cancel case, asserted: a pending booking with no folio, made
     * stale by moving past its check_out (not live under 9.1 D-07), then the account deleted (200).
     */
    private function deleteAfterStaleBooking(Guest $guest, Reservation $reservation): void
    {
        $this->assertSame(ReservationStatus::PENDING, $reservation->fresh()->status);
        $this->assertFalse(Folio::where('reservation_id', $reservation->id)->exists());

        $this->travelTo(now()->addDays(13));
        $this->assertTrue(
            $reservation->fresh()->check_out->toDateString() < HotelClock::today()->toDateString(),
            'the booking must be a stale no-show',
        );

        $this->deleteMe($guest)->assertOk();
        $this->assertTrue($guest->fresh()->isDeleted());
    }

    private function assertNothingSpendableLeft(Guest $guest): void
    {
        $this->assertSame(0, app(LoyaltyLedger::class)->available($guest->id));
        $this->assertSame(0, $this->activeBatchCount($guest));
        $this->assertSame(0, $this->activeVoucherCount($guest));
        $this->assertSame(0, LoyaltyEarnBatch::where('guest_id', $guest->id)->where('points_remaining', '<', 0)->count());
    }

    public function test_cancel_after_deletion_re_forfeits_a_revived_depleted_batch(): void
    {
        $roomType = $this->bookingProgram();
        $guest = $this->guest();
        $source = $this->grantPoints($guest, 10000);
        $reservation = $this->bookWith($guest, $roomType, ['loyalty_points' => 10000]);
        $this->assertSame(LoyaltyBatchStatus::DEPLETED, $source->fresh()->status);

        $this->deleteAfterStaleBooking($guest, $reservation);
        $this->assertSame(
            ['forfeited_points' => 0, 'expired_batches' => 0, 'closed_vouchers' => 0],
            $this->deletionAudit($guest)['loyalty'],
        );

        $this->cancelAsStaff($reservation)->assertStatus(204);

        $redeem = LoyaltyLedgerEntry::where('type', LoyaltyEntryType::REDEEM->value)->sole();
        $refund = LoyaltyLedgerEntry::where('type', LoyaltyEntryType::REFUND->value)->where('reverses_entry_id', $redeem->id)->sole();
        $this->assertSame(10000, $refund->points);

        $source->refresh();
        $this->assertSame(LoyaltyBatchStatus::EXPIRED, $source->status);
        $this->assertSame(0, $source->points_remaining);
        $expire = LoyaltyLedgerEntry::where('type', LoyaltyEntryType::EXPIRE->value)->where('batch_id', $source->id)->sole();
        $this->assertSame('expire:batch:'.$source->id, $expire->idempotency_key);
        $this->assertSame(-10000, $expire->points);
        $this->assertSame(0, LoyaltyEarnBatch::where('source', LoyaltyBatchSource::REFUND->value)->count());
        $this->assertNothingSpendableLeft($guest);
    }

    public function test_cancel_after_deletion_forfeits_the_new_refund_batch(): void
    {
        $roomType = $this->bookingProgram();
        $guest = $this->guest();
        $source = $this->grantPoints($guest, 20000);
        $reservation = $this->bookWith($guest, $roomType, ['loyalty_points' => 10000]);
        $this->assertSame(10000, $source->fresh()->points_remaining);

        $this->deleteAfterStaleBooking($guest, $reservation);
        $this->assertSame(
            ['forfeited_points' => 10000, 'expired_batches' => 1, 'closed_vouchers' => 0],
            $this->deletionAudit($guest)['loyalty'],
        );

        $locked = $this->lockedSelects(fn () => $this->cancelAsStaff($reservation)->assertStatus(204));

        $this->assertOrder($locked, [
            'reservations', 'folios', 'guests', 'loyalty_reservation_applications', 'loyalty_earn_batches', 'loyalty_vouchers',
        ]);

        $refund = LoyaltyLedgerEntry::where('type', LoyaltyEntryType::REFUND->value)->sole();
        $this->assertSame(10000, $refund->points);

        $source->refresh();
        $this->assertSame(LoyaltyBatchStatus::EXPIRED, $source->status);
        $this->assertSame(1, LoyaltyLedgerEntry::where('type', LoyaltyEntryType::EXPIRE->value)->where('batch_id', $source->id)->count());

        $refundBatch = LoyaltyEarnBatch::where('guest_id', $guest->id)->where('source', LoyaltyBatchSource::REFUND->value)->sole();
        $this->assertSame(10000, $refundBatch->points);
        $this->assertSame(LoyaltyBatchStatus::EXPIRED, $refundBatch->status);
        $this->assertSame(0, $refundBatch->points_remaining);
        $this->assertSame(
            'expire:batch:'.$refundBatch->id,
            LoyaltyLedgerEntry::where('type', LoyaltyEntryType::EXPIRE->value)->where('batch_id', $refundBatch->id)->sole()->idempotency_key,
        );
        $this->assertNothingSpendableLeft($guest);
    }

    public function test_cancel_after_deletion_voids_the_restored_voucher(): void
    {
        $roomType = $this->bookingProgram();
        $guest = $this->guest();
        $voucher = $this->voucher($guest, ['expires_at' => now()->addDays(90)]);
        $reservation = $this->bookWith($guest, $roomType, ['voucher_code' => $voucher->code]);
        $this->assertSame(LoyaltyVoucherStatus::USED, $voucher->fresh()->status);

        $this->deleteAfterStaleBooking($guest, $reservation);
        $this->assertSame(0, $this->deletionAudit($guest)['loyalty']['closed_vouchers']);

        $this->cancelAsStaff($reservation)->assertStatus(204);

        $fresh = $voucher->fresh();
        $this->assertSame(LoyaltyVoucherStatus::VOID, $fresh->status);
        $this->assertNull($fresh->reservation_id);
        $this->assertNothingSpendableLeft($guest);
    }

    // ------------------------------------------------------------------ sign in again (review item 3 evidence)

    private function signIn(string $phone): array
    {
        RateLimiter::clear("otp:min:{$phone}:login");
        RateLimiter::clear("otp:hour:{$phone}:login");
        $this->app['auth']->forgetGuards();

        $this->postJson('/api/auth/guest/request-otp', ['phone' => $phone, 'channel' => 'sms', 'purpose' => 'login'], self::EN)
            ->assertOk();

        return $this->postJson('/api/auth/guest/verify-otp', [
            'phone' => $phone, 'code' => OtpDispatcher::lastCode(), 'purpose' => 'login',
        ], self::EN)->assertOk()->json('data');
    }

    public function test_signing_in_again_gives_a_new_account_with_zero_points(): void
    {
        $this->configureLoyalty();
        $first = $this->signIn(self::PHONE);
        $old = Guest::where('uuid', $first['guest']['uuid'])->firstOrFail();
        $this->grantPoints($old, 500);

        $this->deleteMeWith($first['token'])->assertOk();

        $second = $this->signIn(self::PHONE);
        $this->assertNotSame($first['guest']['uuid'], $second['guest']['uuid']);

        $this->app['auth']->forgetGuards();
        $this->withToken($second['token'])->getJson('/api/loyalty/account', self::EN)
            ->assertOk()
            ->assertJsonPath('data.available_points', 0)
            ->assertJsonPath('data.lifetime_earned_points', 0);
        $this->assertSame(0, app(LoyaltyLedger::class)->available($old->id));
    }
}
