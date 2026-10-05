<?php

namespace Tests\Feature\Loyalty;

use App\Enums\LoyaltyEntryType;
use App\Enums\LoyaltyRewardType;
use App\Enums\LoyaltyVoucherStatus;
use App\Enums\ModifierType;
use App\Models\Guest;
use App\Models\LoyaltyLedgerEntry;
use App\Models\LoyaltyReservationApplication;
use App\Models\LoyaltyVoucher;
use App\Models\PromoCode;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\RoomType;
use App\Support\LoyaltyLedger;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\BuildsLoyaltyFixtures;
use Tests\Concerns\CountsDomainQueries;
use Tests\Concerns\RecordsRowLocks;
use Tests\TestCase;

/**
 * Phase 10 LOY-16 / LOY-21 (Q5, Q6, Q12, Q16, Q17, M-4..M-7): a guest pays part
 * of a booking with points or one voucher on POST /api/reservations, atomically
 * and idempotently under Idempotency-Key, and the total always equals the
 * preview.
 */
class LoyaltyBookingTest extends TestCase
{
    use BuildsLoyaltyFixtures;
    use CountsDomainQueries;
    use RecordsRowLocks;
    use RefreshDatabase;

    private Guest $guest;

    private RoomType $roomType;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->configureLoyalty([
            'redeem_value_usd' => '0.0100',
            'max_redeem_percent' => '50.00',
            'min_redeem_points' => 100,
            'earn_rate' => '1.0000',
        ]);
        $this->guest = Guest::factory()->create();
        // 2 nights x 150.00 = 300.00; six rooms so several bookings fit.
        $this->roomType = RoomType::factory()->create(['base_price_usd' => 150, 'is_active' => true]);
        Room::factory()->count(6)->create(['room_type_id' => $this->roomType->id, 'is_active' => true]);
    }

    /** @return array<string, mixed> */
    private function body(array $extra = [], ?RoomType $roomType = null): array
    {
        return array_merge([
            'room_type_uuid' => ($roomType ?? $this->roomType)->uuid,
            'check_in' => now()->addDays(10)->toDateString(),
            'check_out' => now()->addDays(12)->toDateString(),
            'payment_method' => 'on_arrival',
        ], $extra);
    }

    private function book(array $extra = [], ?string $key = 'K-1', ?Guest $guest = null, ?RoomType $roomType = null): TestResponse
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($this->guestToken($guest ?? $this->guest))
            ->withHeaders($key === null ? [] : ['Idempotency-Key' => $key])
            ->postJson('/api/reservations', $this->body($extra, $roomType));
    }

    private function preview(array $extra = []): TestResponse
    {
        $this->app['auth']->forgetGuards();
        $query = array_diff_key($this->body($extra), ['payment_method' => 1]);

        return $this->withToken($this->guestToken($this->guest))
            ->getJson('/api/loyalty/preview?'.http_build_query($query));
    }

    private function voucher(array $overrides = []): LoyaltyVoucher
    {
        return LoyaltyVoucher::factory()->create(array_merge(['guest_id' => $this->guest->id], $overrides));
    }

    /** @return array<string, int> */
    private function counts(): array
    {
        return $this->loyaltyRowCounts() + [
            'reservations' => Reservation::count(),
            'reservation_rooms' => DB::table('reservation_rooms')->count(),
        ];
    }

    public function test_booking_with_points_reduces_the_total_and_records_everything(): void
    {
        $this->grantPoints($this->guest, 20000);

        $response = $this->book(['loyalty_points' => 10000])->assertStatus(201)
            ->assertJsonPath('data.total_usd', '200.00')
            ->assertJsonPath('data.loyalty.points_redeemed', 10000)
            ->assertJsonPath('data.loyalty.points_discount_usd', '100.00')
            ->assertJsonPath('data.loyalty.voucher', null)
            ->assertJsonPath('data.loyalty.voucher_discount_usd', '0.00')
            ->assertJsonPath('data.loyalty.upgrade_requested', false)
            ->assertJsonPath('data.loyalty.status', 'applied');

        $reservation = Reservation::where('uuid', $response->json('data.uuid'))->firstOrFail();

        $entry = LoyaltyLedgerEntry::where('type', LoyaltyEntryType::REDEEM->value)->sole();
        $this->assertSame(-10000, $entry->points);
        $this->assertSame($reservation->id, (int) $entry->reservation_id);
        $this->assertSame('100.00', $entry->discount_usd);
        $this->assertSame('redeem:reservation:'.$reservation->id, $entry->idempotency_key);

        $application = LoyaltyReservationApplication::sole();
        $this->assertSame('K-1', $application->idempotency_key);
        $this->assertSame($reservation->id, (int) $application->reservation_id);
        $this->assertSame($entry->id, (int) $application->redeem_entry_id);
        $this->assertSame(10000, $application->points_redeemed);
        $this->assertNull($application->voucher_id);

        $this->assertSame(10000, app(LoyaltyLedger::class)->available($this->guest->id));
    }

    public function test_booking_with_a_discount_voucher_uses_it_up(): void
    {
        $voucher = $this->voucher(['value_usd' => '25.00']);

        $response = $this->book(['voucher_code' => $voucher->code])->assertStatus(201)
            ->assertJsonPath('data.total_usd', '275.00')
            ->assertJsonPath('data.loyalty.points_redeemed', 0)
            ->assertJsonPath('data.loyalty.voucher', ['code' => $voucher->code, 'type' => 'discount_voucher'])
            ->assertJsonPath('data.loyalty.voucher_discount_usd', '25.00');

        $reservation = Reservation::where('uuid', $response->json('data.uuid'))->firstOrFail();
        $fresh = $voucher->fresh();
        $this->assertSame(LoyaltyVoucherStatus::USED, $fresh->status);
        $this->assertSame($reservation->id, (int) $fresh->reservation_id);
        $this->assertNotNull($fresh->used_at);
        $this->assertSame(0, LoyaltyLedgerEntry::where('type', LoyaltyEntryType::REDEEM->value)->count());
        $this->assertSame($voucher->id, (int) LoyaltyReservationApplication::sole()->voucher_id);
    }

    public function test_the_voucher_code_is_case_and_space_insensitive(): void
    {
        $voucher = $this->voucher();

        $this->book(['voucher_code' => '  '.strtolower($voucher->code).' '])->assertStatus(201)
            ->assertJsonPath('data.loyalty.voucher.code', $voucher->code);
    }

    public function test_a_free_night_voucher_takes_one_daily_rate_off(): void
    {
        $voucher = $this->voucher(['type' => LoyaltyRewardType::FREE_NIGHT, 'value_usd' => null]);

        $this->book(['voucher_code' => $voucher->code])->assertStatus(201)
            ->assertJsonPath('data.total_usd', '150.00')
            ->assertJsonPath('data.loyalty.voucher_discount_usd', '150.00')
            ->assertJsonPath('data.loyalty.voucher.type', 'free_night');
    }

    public function test_a_room_upgrade_voucher_is_used_and_flags_an_upgrade_request(): void
    {
        $voucher = $this->voucher(['type' => LoyaltyRewardType::ROOM_UPGRADE, 'value_usd' => null]);

        $this->book(['voucher_code' => $voucher->code])->assertStatus(201)
            ->assertJsonPath('data.total_usd', '300.00')
            ->assertJsonPath('data.loyalty.upgrade_requested', true)
            ->assertJsonPath('data.loyalty.voucher_discount_usd', '0.00');

        $this->assertSame(LoyaltyVoucherStatus::USED, $voucher->fresh()->status);
    }

    public function test_the_booking_total_equals_the_preview_for_identical_inputs(): void
    {
        $this->grantPoints($this->guest, 50000);
        $promo = PromoCode::factory()->create(['type' => ModifierType::PERCENTAGE, 'value' => 10]);
        $discount = $this->voucher(['value_usd' => '40.00']);
        $night = $this->voucher(['type' => LoyaltyRewardType::FREE_NIGHT, 'value_usd' => null]);
        $upgrade = $this->voucher(['type' => LoyaltyRewardType::ROOM_UPGRADE, 'value_usd' => null]);

        $cases = [
            'points' => ['loyalty_points' => 7777],
            'discount voucher' => ['voucher_code' => $discount->code],
            'free night' => ['voucher_code' => $night->code],
            'room upgrade' => ['voucher_code' => $upgrade->code],
            'promo + points' => ['promo_code' => $promo->code, 'loyalty_points' => 9999],
            'neither' => [],
        ];

        foreach ($cases as $label => $extra) {
            $preview = $this->preview($extra)->assertOk()->json('data.net_total_usd');
            $booked = $this->book($extra, 'K-'.md5($label))->assertStatus(201)->json('data.total_usd');

            $this->assertSame($preview, $booked, "preview and booking drifted for: {$label}");
        }
    }

    public function test_a_replay_answers_200_with_the_same_reservation_and_writes_nothing(): void
    {
        $this->grantPoints($this->guest, 20000);
        $first = $this->book(['loyalty_points' => 10000])->assertStatus(201);
        $counts = $this->counts();

        $replay = $this->book(['loyalty_points' => 10000])->assertStatus(200);

        $this->assertSame($first->json('data.uuid'), $replay->json('data.uuid'));
        $this->assertSame('200.00', $replay->json('data.total_usd'));
        $this->assertSame('applied', $replay->json('data.loyalty.status'));
        $this->assertSame($counts, $this->counts());
    }

    public function test_a_voucher_replay_answers_200_even_though_the_voucher_is_now_used(): void
    {
        $voucher = $this->voucher();
        $first = $this->book(['voucher_code' => $voucher->code])->assertStatus(201);
        $counts = $this->counts();

        $this->book(['voucher_code' => strtolower($voucher->code)])->assertStatus(200)
            ->assertJsonPath('data.uuid', $first->json('data.uuid'));

        $this->assertSame($counts, $this->counts());
    }

    public function test_a_replay_with_a_promo_answers_200_and_does_not_count_the_promo_twice(): void
    {
        $this->grantPoints($this->guest, 20000);
        $promo = PromoCode::factory()->create(['type' => ModifierType::PERCENTAGE, 'value' => 10, 'max_uses' => 5]);

        $first = $this->book(['promo_code' => $promo->code, 'loyalty_points' => 5000])->assertStatus(201);
        $this->book(['promo_code' => $promo->code, 'loyalty_points' => 5000])->assertStatus(200)
            ->assertJsonPath('data.uuid', $first->json('data.uuid'));

        $this->assertSame(1, $promo->fresh()->used_count);
    }

    public function test_a_replay_succeeds_when_its_own_booking_took_the_last_room(): void
    {
        $this->grantPoints($this->guest, 20000);
        $scarce = RoomType::factory()->create(['base_price_usd' => 150, 'is_active' => true]);
        Room::factory()->create(['room_type_id' => $scarce->id, 'is_active' => true]);

        $first = $this->book(['loyalty_points' => 10000], 'K-1', null, $scarce)->assertStatus(201);

        // The only room is now held; a fresh request would be refused ...
        $this->book(['loyalty_points' => 10000], 'K-2', null, $scarce)->assertStatus(409)
            ->assertJsonPath('error_code', 'no_availability');

        // ... but the retry of the first one is answered, not re-checked.
        $this->book(['loyalty_points' => 10000], 'K-1', null, $scarce)->assertStatus(200)
            ->assertJsonPath('data.uuid', $first->json('data.uuid'));
    }

    public function test_the_same_key_with_a_different_request_is_an_idempotency_conflict(): void
    {
        $this->grantPoints($this->guest, 20000);
        $this->book(['loyalty_points' => 10000])->assertStatus(201);
        $counts = $this->counts();

        $this->book(['loyalty_points' => 9000])->assertStatus(409)
            ->assertJsonPath('error_code', 'idempotency_conflict');

        $this->book([
            'loyalty_points' => 10000,
            'check_out' => now()->addDays(13)->toDateString(),
        ])->assertStatus(409)->assertJsonPath('error_code', 'idempotency_conflict');

        $this->assertSame($counts, $this->counts());
    }

    public function test_the_same_key_with_a_voucher_instead_of_points_is_a_conflict(): void
    {
        $this->grantPoints($this->guest, 20000);
        $voucher = $this->voucher();
        $this->book(['loyalty_points' => 10000])->assertStatus(201);

        $this->book(['voucher_code' => $voucher->code])->assertStatus(409)
            ->assertJsonPath('error_code', 'idempotency_conflict');

        $this->assertSame(LoyaltyVoucherStatus::ACTIVE, $voucher->fresh()->status);
    }

    public function test_the_same_key_is_independent_between_guests(): void
    {
        $other = Guest::factory()->create();
        $this->grantPoints($this->guest, 20000);
        $this->grantPoints($other, 20000);

        $a = $this->book(['loyalty_points' => 10000])->assertStatus(201);
        $b = $this->book(['loyalty_points' => 10000], 'K-1', $other)->assertStatus(201);

        $this->assertNotSame($a->json('data.uuid'), $b->json('data.uuid'));
        $this->assertSame(2, LoyaltyReservationApplication::count());
    }

    public function test_loyalty_fields_need_the_idempotency_key(): void
    {
        $this->grantPoints($this->guest, 20000);
        $voucher = $this->voucher();
        $counts = $this->counts();

        foreach ([['loyalty_points' => 10000], ['voucher_code' => $voucher->code]] as $extra) {
            $this->book($extra, null)->assertStatus(422)
                ->assertJsonPath('error_code', 'validation_failed')
                ->assertJsonPath('errors.idempotency_key.0', __('custom.errors.idempotency_key_required'));
        }

        $this->assertSame($counts, $this->counts());
    }

    public function test_a_key_without_loyalty_fields_is_ignored(): void
    {
        $this->book([], 'K-1')->assertStatus(201)->assertJsonPath('data.loyalty', null);
        $this->book([], 'K-1')->assertStatus(201);

        $this->assertSame(2, Reservation::count());
        $this->assertSame(0, LoyaltyReservationApplication::count());
    }

    public function test_a_plain_booking_is_unchanged(): void
    {
        $response = $this->book([], null)->assertStatus(201)
            ->assertJsonPath('data.total_usd', '300.00')
            ->assertJsonPath('data.loyalty', null)
            ->assertJsonStructure(['data' => ['uuid', 'booking_code', 'total_usd', 'rooms', 'guest']]);

        $this->assertStringStartsWith('CARL-', $response->json('data.booking_code'));
        $this->assertSame(0, LoyaltyReservationApplication::count());
    }

    public function test_refusals_roll_everything_back(): void
    {
        $this->grantPoints($this->guest, 500);
        $voucher = $this->voucher();
        $promo = PromoCode::factory()->create(['type' => ModifierType::PERCENTAGE, 'value' => 10, 'max_uses' => 5]);
        $counts = $this->counts();

        $cases = [
            'over cap' => [['loyalty_points' => 20000], 'loyalty_over_cap'],
            'below minimum' => [['loyalty_points' => 50], 'loyalty_below_minimum'],
            'insufficient' => [['loyalty_points' => 600], 'loyalty_insufficient_points'],
            'invalid voucher' => [['voucher_code' => 'LOY-NOSUCHCODE'], 'loyalty_voucher_invalid'],
            'conflict' => [['loyalty_points' => 200, 'voucher_code' => $voucher->code], 'loyalty_discount_conflict'],
        ];

        foreach ($cases as $label => [$extra, $code]) {
            $this->book($extra + ['promo_code' => $promo->code], 'K-'.md5($label))->assertStatus(422)
                ->assertJsonPath('error_code', $code);
        }

        $this->configureLoyalty(['max_redeem_percent' => null]);
        $this->book(['loyalty_points' => 200, 'promo_code' => $promo->code], 'K-off')->assertStatus(422)
            ->assertJsonPath('error_code', 'loyalty_program_inactive');

        $this->assertSame($counts, $this->counts());
        $this->assertSame(0, $promo->fresh()->used_count);
        $this->assertSame(LoyaltyVoucherStatus::ACTIVE, $voucher->fresh()->status);
        $this->assertSame(500, app(LoyaltyLedger::class)->available($this->guest->id));
    }

    public function test_no_availability_spends_no_points(): void
    {
        $this->grantPoints($this->guest, 20000);
        $empty = RoomType::factory()->create(['base_price_usd' => 150, 'is_active' => true]);
        $counts = $this->counts();

        $this->book(['loyalty_points' => 10000], 'K-1', null, $empty)->assertStatus(409)
            ->assertJsonPath('error_code', 'no_availability');

        $this->assertSame($counts, $this->counts());
        $this->assertSame(20000, app(LoyaltyLedger::class)->available($this->guest->id));
    }

    public function test_a_client_supplied_discount_or_total_is_ignored(): void
    {
        $this->book(['points_discount_usd' => '300.00', 'total_usd' => '1.00', 'discount_usd' => '300.00'], null)
            ->assertStatus(201)
            ->assertJsonPath('data.total_usd', '300.00');

        $this->assertSame(0, LoyaltyReservationApplication::count());
    }

    public function test_the_loyalty_fields_are_validated(): void
    {
        $this->book(['loyalty_points' => 0])->assertStatus(422)->assertJsonValidationErrors('loyalty_points');
        $this->book(['loyalty_points' => 'many'])->assertStatus(422)->assertJsonValidationErrors('loyalty_points');
        $this->book(['loyalty_points' => 100000001])->assertStatus(422)->assertJsonValidationErrors('loyalty_points');
        $this->book(['voucher_code' => str_repeat('A', 17)])->assertStatus(422)->assertJsonValidationErrors('voucher_code');
    }

    public function test_staff_booking_never_applies_loyalty(): void
    {
        $this->grantPoints($this->guest, 20000);
        $counts = $this->loyaltyRowCounts();

        $this->app['auth']->forgetGuards();
        $this->withToken($this->staffToken('reservations.create'))
            ->postJson('/api/cms/reservations', $this->body([
                'guest_uuid' => $this->guest->uuid,
                'loyalty_points' => 10000,
                'voucher_code' => 'LOY-ANYTHING',
            ]))
            ->assertCreated()
            ->assertJsonPath('data.total_usd', '300.00')
            ->assertJsonPath('data.loyalty', null);

        $this->assertSame($counts, $this->loyaltyRowCounts());
        $this->assertSame(20000, app(LoyaltyLedger::class)->available($this->guest->id));
    }

    public function test_the_public_otp_booking_never_applies_loyalty(): void
    {
        $this->grantPoints($this->guest, 20000);
        $counts = $this->loyaltyRowCounts();

        $this->app['auth']->forgetGuards();
        $this->postJson('/api/reservations/guest', $this->body([
            'first_name' => 'Nour',
            'last_name' => 'Haddad',
            'email' => 'nour.otp@example.com',
            'loyalty_points' => 10000,
        ]))->assertOk();

        $this->assertSame($counts, $this->loyaltyRowCounts());
        $this->assertSame(0, LoyaltyReservationApplication::count());
        $this->assertSame(20000, app(LoyaltyLedger::class)->available($this->guest->id));
    }

    public function test_reservation_reads_include_the_loyalty_block(): void
    {
        $this->grantPoints($this->guest, 20000);
        $voucher = $this->voucher(['value_usd' => '25.00']);
        $uuid = $this->book(['voucher_code' => $voucher->code])->assertStatus(201)->json('data.uuid');

        $this->app['auth']->forgetGuards();
        $this->withToken($this->guestToken($this->guest))->getJson('/api/reservations/'.$uuid)->assertOk()
            ->assertJsonPath('data.loyalty.voucher.code', $voucher->code)
            ->assertJsonPath('data.loyalty.voucher_discount_usd', '25.00')
            ->assertJsonPath('data.loyalty.status', 'applied');

        $this->app['auth']->forgetGuards();
        $this->withToken($this->staffToken('reservations.view'))->getJson('/api/cms/reservations/'.$uuid)->assertOk()
            ->assertJsonPath('data.loyalty.voucher.code', $voucher->code)
            ->assertJsonPath('data.loyalty.voucher_discount_usd', '25.00');
    }

    public function test_the_guest_list_does_not_query_per_reservation(): void
    {
        $this->grantPoints($this->guest, 200000);
        $measure = function (int $extra): int {
            for ($i = 0; $i < $extra; $i++) {
                $this->book(['loyalty_points' => 1000], 'L-'.$extra.'-'.$i)->assertStatus(201);
            }

            $this->app['auth']->forgetGuards();
            $token = $this->guestToken($this->guest);

            return $this->countDomainQueries(function () use ($token): void {
                $this->app['auth']->forgetGuards();
                $this->withToken($token)->getJson('/api/reservations')->assertOk();
            });
        };

        $one = $measure(1);
        $five = $measure(4);

        $this->assertSame(5, Reservation::count());
        $this->assertSame($one, $five, 'the reservation list queried per row');
    }

    public function test_lock_order_for_a_points_booking_is_room_type_guest_batches(): void
    {
        $this->grantPoints($this->guest, 20000);

        $locked = $this->lockedSelects(fn () => $this->book(['loyalty_points' => 10000])->assertStatus(201));

        $this->assertOrder($locked, ['room_types', 'guests', 'loyalty_earn_batches']);
    }

    public function test_lock_order_for_a_voucher_booking_is_room_type_guest_voucher(): void
    {
        $voucher = $this->voucher();

        $locked = $this->lockedSelects(fn () => $this->book(['voucher_code' => $voucher->code])->assertStatus(201));

        $this->assertOrder($locked, ['room_types', 'guests', 'loyalty_vouchers']);
    }

    public function test_a_plain_booking_takes_no_guest_lock(): void
    {
        $locked = $this->lockedSelects(fn () => $this->book([], null)->assertStatus(201));

        $this->assertNotEmpty(array_filter($locked, fn (string $sql) => str_contains($sql, 'from "room_types"')));
        $this->assertEmpty(array_filter($locked, fn (string $sql) => str_contains($sql, 'from "guests"')));
        $this->assertEmpty(array_filter($locked, fn (string $sql) => str_contains($sql, 'from "loyalty_')));
    }

    public function test_booking_needs_a_guest_token(): void
    {
        $this->app['auth']->forgetGuards();
        $this->withHeaders(['Idempotency-Key' => 'K-1'])
            ->postJson('/api/reservations', $this->body(['loyalty_points' => 10000]))
            ->assertStatus(401);

        $this->app['auth']->forgetGuards();
        $this->withToken($this->staffToken('reservations.create'))
            ->withHeaders(['Idempotency-Key' => 'K-1'])
            ->postJson('/api/reservations', $this->body(['loyalty_points' => 10000]))
            ->assertStatus(401);
    }

    /** @param list<string> $locked @param list<string> $tables */
    private function assertOrder(array $locked, array $tables): void
    {
        $positions = [];
        foreach ($tables as $table) {
            $position = null;
            foreach ($locked as $index => $sql) {
                if (str_contains($sql, 'from "'.$table.'"')) {
                    $position = $index;
                    break;
                }
            }
            $this->assertNotNull($position, "no `for update` select on {$table}; saw:\n".implode("\n", $locked));
            $positions[] = $position;
        }

        $sorted = $positions;
        sort($sorted);
        $this->assertSame($sorted, $positions, 'lock order must be '.implode(' -> ', $tables));
    }
}
