<?php

namespace Tests\Feature\Loyalty;

use App\Enums\LoyaltyEntryType;
use App\Enums\LoyaltyVoucherStatus;
use App\Models\Guest;
use App\Models\LoyaltyEarnBatch;
use App\Models\LoyaltyLedgerEntry;
use App\Models\LoyaltyReward;
use App\Models\LoyaltyVoucher;
use App\Models\Reservation;
use App\Support\HotelClock;
use Carbon\CarbonImmutable;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;
use RuntimeException;
use Tests\Concerns\BuildsLoyaltyFixtures;
use Tests\Concerns\RecordsRowLocks;
use Tests\TestCase;

/**
 * Phase 10 LOY-13 / LOY-14 (Q4, Q5, Q13, Q25, M-6): a guest redeems a catalog
 * reward into a voucher, once per Idempotency-Key, FIFO and atomically, and
 * lists only their own vouchers.
 */
class LoyaltyRedeemTest extends TestCase
{
    use BuildsLoyaltyFixtures;
    use RecordsRowLocks;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function redeem(Guest $guest, LoyaltyReward|string $reward, ?string $key = 'R-1', ?string $token = null): TestResponse
    {
        $this->app['auth']->forgetGuards();
        $headers = ['Accept-Language' => 'en'];
        if ($key !== null) {
            $headers['Idempotency-Key'] = $key;
        }
        $uuid = $reward instanceof LoyaltyReward ? $reward->uuid : $reward;

        return $this->withToken($token ?? $this->guestToken($guest))
            ->postJson("/api/loyalty/rewards/{$uuid}/redeem", [], $headers);
    }

    private function vouchers(Guest $guest, string $query = ''): TestResponse
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($this->guestToken($guest))->getJson('/api/loyalty/vouchers'.$query);
    }

    public function test_redeeming_a_voucher_spends_fifo_and_issues_the_voucher(): void
    {
        $guest = Guest::factory()->create();
        $first = $this->grantPoints($guest, 1000, now()->addDays(10));
        $second = $this->grantPoints($guest, 1000, now()->addDays(20));
        $third = $this->grantPoints($guest, 1000, now()->addDays(30));
        $reward = LoyaltyReward::factory()->create(['points_cost' => 2500, 'discount_usd' => '25.00', 'voucher_valid_days' => 90]);

        $response = $this->redeem($guest, $reward)
            ->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', __('custom.messages.loyalty_reward_redeemed'))
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.type', 'discount_voucher')
            ->assertJsonPath('data.value_usd', '25.00')
            ->assertJsonPath('data.points_spent', 2500)
            ->assertJsonPath('data.reservation', null)
            ->assertJsonMissingPath('data.id');

        $this->assertMatchesRegularExpression('/^LOY-[0-9A-HJKMNP-TV-Z]{8}$/', $response->json('data.code'));

        $voucher = LoyaltyVoucher::where('uuid', $response->json('data.uuid'))->sole();
        $this->assertSame($guest->id, (int) $voucher->guest_id);
        $this->assertSame($reward->id, (int) $voucher->loyalty_reward_id);
        $this->assertSame(LoyaltyVoucherStatus::ACTIVE, $voucher->status);
        $this->assertSame(['en' => $reward->getTranslation('name', 'en'), 'ar' => $reward->getTranslation('name', 'ar')], $voucher->reward_name);

        $expected = HotelClock::today()->addDays(90)->endOfDay()->utc();
        $this->assertSame($expected->timestamp, $voucher->expires_at->timestamp);
        $this->assertSame($expected->timestamp, CarbonImmutable::parse($response->json('data.expires_at'))->timestamp);

        $entry = LoyaltyLedgerEntry::where('type', LoyaltyEntryType::REDEEM->value)->sole();
        $this->assertSame(-2500, $entry->points);
        $this->assertSame($voucher->id, (int) $entry->voucher_id);
        $this->assertSame('redeem:reward:'.$guest->id.':R-1', $entry->idempotency_key);

        $allocations = $entry->allocations()->orderBy('id')->get();
        $this->assertSame(
            [[$first->id, 1000], [$second->id, 1000], [$third->id, 500]],
            $allocations->map(fn ($a) => [(int) $a->batch_id, $a->points])->all(),
        );
        $this->assertSame(2500, $allocations->sum('points'));
        $this->assertSame(500, LoyaltyEarnBatch::where('guest_id', $guest->id)->sum('points_remaining'));
    }

    public function test_free_night_and_room_upgrade_vouchers_have_no_value(): void
    {
        $guest = Guest::factory()->create();
        $this->grantPoints($guest, 20000);

        $night = $this->redeem($guest, LoyaltyReward::factory()->freeNight()->create(), 'K-N')
            ->assertStatus(201)
            ->assertJsonPath('data.type', 'free_night')
            ->assertJsonPath('data.value_usd', null)
            ->assertJsonPath('data.points_spent', 10000);
        $upgrade = $this->redeem($guest, LoyaltyReward::factory()->roomUpgrade()->create(), 'K-U')
            ->assertStatus(201)
            ->assertJsonPath('data.type', 'room_upgrade')
            ->assertJsonPath('data.value_usd', null);

        $this->assertNotSame($night->json('data.code'), $upgrade->json('data.code'));
    }

    public function test_redemption_works_without_a_settings_row_and_ignores_the_min_redeem_setting(): void
    {
        $guest = Guest::factory()->create();
        $this->grantPoints($guest, 5000);
        $reward = LoyaltyReward::factory()->create(['points_cost' => 2500]);

        $this->assertDatabaseCount('loyalty_settings', 0);
        $this->redeem($guest, $reward, 'K-A')->assertStatus(201);

        $this->configureLoyalty(['min_redeem_points' => 5000]);
        $this->redeem($guest, $reward, 'K-B')->assertStatus(201);
    }

    public function test_a_replay_with_the_same_key_returns_the_same_voucher_and_writes_nothing(): void
    {
        $guest = Guest::factory()->create();
        $this->grantPoints($guest, 3000);
        $reward = LoyaltyReward::factory()->create(['points_cost' => 2500]);

        $first = $this->redeem($guest, $reward)->assertStatus(201);
        $counts = $this->loyaltyRowCounts();

        $replay = $this->redeem($guest, $reward)->assertStatus(200);

        $this->assertSame($first->json('data.uuid'), $replay->json('data.uuid'));
        $this->assertSame($first->json('data.code'), $replay->json('data.code'));
        $this->assertSame($counts, $this->loyaltyRowCounts());
        $this->assertSame(500, LoyaltyEarnBatch::where('guest_id', $guest->id)->sum('points_remaining'));
    }

    public function test_the_same_key_for_another_reward_is_a_conflict(): void
    {
        $guest = Guest::factory()->create();
        $this->grantPoints($guest, 9000);
        $one = LoyaltyReward::factory()->create(['points_cost' => 1000]);
        $two = LoyaltyReward::factory()->create(['points_cost' => 1000]);

        $this->redeem($guest, $one)->assertStatus(201);
        $this->redeem($guest, $two)
            ->assertStatus(409)
            ->assertJsonPath('error_code', 'idempotency_conflict');

        $this->assertSame(1, LoyaltyVoucher::count());
    }

    public function test_a_missing_or_blank_key_is_422(): void
    {
        $guest = Guest::factory()->create();
        $this->grantPoints($guest, 3000);
        $reward = LoyaltyReward::factory()->create();

        foreach ([null, '', '   '] as $key) {
            $this->redeem($guest, $reward, $key)
                ->assertStatus(422)
                ->assertJsonPath('error_code', 'validation_failed')
                ->assertJsonPath('errors.idempotency_key.0', __('custom.errors.idempotency_key_required'));
        }

        $this->assertSame(0, LoyaltyVoucher::count());
    }

    public function test_not_enough_points_is_refused_with_context_and_changes_nothing(): void
    {
        $guest = Guest::factory()->create();
        $this->grantPoints($guest, 2499);
        $reward = LoyaltyReward::factory()->create(['points_cost' => 2500]);

        $this->redeem($guest, $reward)
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'loyalty_insufficient_points')
            ->assertJsonPath('context.available_points', 2499)
            ->assertJsonPath('context.requested_points', 2500);

        $this->assertSame(0, LoyaltyVoucher::count());
        $this->assertSame(2499, LoyaltyEarnBatch::where('guest_id', $guest->id)->sum('points_remaining'));
    }

    public function test_an_inactive_reward_is_unavailable(): void
    {
        $guest = Guest::factory()->create();
        $this->grantPoints($guest, 5000);

        $this->redeem($guest, LoyaltyReward::factory()->inactive()->create())
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'loyalty_reward_unavailable');

        $this->assertSame(0, LoyaltyVoucher::count());
        $this->assertSame(0, LoyaltyLedgerEntry::where('type', LoyaltyEntryType::REDEEM->value)->count());
    }

    public function test_a_trashed_or_unknown_reward_is_404(): void
    {
        $guest = Guest::factory()->create();
        $this->grantPoints($guest, 5000);
        $reward = LoyaltyReward::factory()->create();
        $reward->delete();

        $this->redeem($guest, $reward)->assertStatus(404);
        $this->redeem($guest, '00000000-0000-4000-8000-000000000000')->assertStatus(404);
    }

    public function test_a_failure_after_consume_leaves_no_voucher_and_no_spent_points(): void
    {
        $guest = Guest::factory()->create();
        $batch = $this->grantPoints($guest, 3000);
        $reward = LoyaltyReward::factory()->create(['points_cost' => 2500]);

        // consume() and the voucher insert have already run when the ledger row is written.
        LoyaltyLedgerEntry::creating(function (LoyaltyLedgerEntry $entry): void {
            if ($entry->type === LoyaltyEntryType::REDEEM) {
                throw new RuntimeException('boom');
            }
        });

        $this->redeem($guest, $reward)->assertStatus(500);

        $this->assertSame(0, LoyaltyVoucher::count());
        $this->assertSame(0, LoyaltyLedgerEntry::where('type', LoyaltyEntryType::REDEEM->value)->count());
        $this->assertSame(3000, $batch->fresh()->points_remaining);
    }

    public function test_the_redeem_route_is_throttled_and_guest_only(): void
    {
        $route = collect(Route::getRoutes()->getRoutes())
            ->first(fn ($r) => $r->uri() === 'api/loyalty/rewards/{reward}/redeem' && in_array('POST', $r->methods(), true));

        $this->assertNotNull($route);
        $this->assertContains('throttle:30,1', $route->gatherMiddleware());
        $this->assertContains('auth:guests', $route->gatherMiddleware());
    }

    public function test_redeem_and_vouchers_need_a_guest_token(): void
    {
        $reward = LoyaltyReward::factory()->create();

        $this->app['auth']->forgetGuards();
        $this->postJson("/api/loyalty/rewards/{$reward->uuid}/redeem", [], ['Idempotency-Key' => 'K'])->assertStatus(401);
        $this->app['auth']->forgetGuards();
        $this->getJson('/api/loyalty/vouchers')->assertStatus(401);

        $staff = $this->staffToken('loyalty.view', 'loyalty.manage', 'loyalty.adjust');
        $this->app['auth']->forgetGuards();
        $this->withToken($staff)->postJson("/api/loyalty/rewards/{$reward->uuid}/redeem", [], ['Idempotency-Key' => 'K'])->assertStatus(401);
        $this->app['auth']->forgetGuards();
        $this->withToken($staff)->getJson('/api/loyalty/vouchers')->assertStatus(401);

        $this->assertSame(0, LoyaltyVoucher::count());
    }

    public function test_a_redeem_locks_the_guest_before_the_batches(): void
    {
        $guest = Guest::factory()->create();
        $this->grantPoints($guest, 3000);
        $reward = LoyaltyReward::factory()->create(['points_cost' => 1000]);
        $token = $this->guestToken($guest);

        $locked = $this->lockedSelects(function () use ($guest, $reward, $token) {
            $this->redeem($guest, $reward, 'K-L', $token)->assertStatus(201);
        });

        $tables = array_map(fn (string $sql) => str_contains($sql, 'from "guests"') ? 'guests' : (str_contains($sql, 'from "loyalty_earn_batches"') ? 'batches' : 'other'), $locked);
        $this->assertContains('guests', $tables);
        $this->assertContains('batches', $tables);
        $this->assertLessThan(array_search('batches', $tables, true), array_search('guests', $tables, true), implode("\n", $locked));
    }

    public function test_the_voucher_list_is_scoped_to_the_caller_newest_first_with_every_key(): void
    {
        $guest = Guest::factory()->create();
        $other = Guest::factory()->create();
        $old = LoyaltyVoucher::factory()->create(['guest_id' => $guest->id, 'created_at' => now()->subDays(2)]);
        $new = LoyaltyVoucher::factory()->create(['guest_id' => $guest->id, 'created_at' => now()->subDay()]);
        $foreign = LoyaltyVoucher::factory()->create(['guest_id' => $other->id]);

        $response = $this->vouchers($guest)->assertOk()->assertJsonPath('success', true);

        $items = $response->json('data.items');
        $this->assertSame([$new->uuid, $old->uuid], array_column($items, 'uuid'));
        $this->assertNotContains($foreign->uuid, array_column($items, 'uuid'));
        $this->assertSame(
            ['code', 'expires_at', 'points_spent', 'reservation', 'reward_name', 'status', 'type', 'type_label', 'used_at', 'uuid', 'value_usd'],
            collect(array_keys($items[0]))->sort()->values()->all(),
        );
        $this->assertSame($new->code, $items[0]['code']);
        $this->assertSame('Discount voucher', $items[0]['type_label']);
        $this->assertNull($items[0]['reservation']);
        $this->assertNull($items[0]['used_at']);
    }

    public function test_a_used_voucher_shows_its_reservation(): void
    {
        $guest = Guest::factory()->create();
        $reservation = Reservation::factory()->confirmed()->create(['guest_id' => $guest->id]);
        $voucher = LoyaltyVoucher::factory()->used($reservation)->create(['guest_id' => $guest->id]);

        $item = $this->vouchers($guest)->assertOk()->json('data.items.0');

        $this->assertSame($voucher->uuid, $item['uuid']);
        $this->assertSame('used', $item['status']);
        $this->assertSame($reservation->uuid, $item['reservation']['uuid']);
        $this->assertSame($reservation->booking_code, $item['reservation']['booking_code']);
        $this->assertNotNull($item['used_at']);
    }

    public function test_the_voucher_list_filters_by_status_type_and_points(): void
    {
        $guest = Guest::factory()->create();
        $active = LoyaltyVoucher::factory()->create(['guest_id' => $guest->id, 'points_spent' => 2500]);
        $expired = LoyaltyVoucher::factory()->expired()->create(['guest_id' => $guest->id, 'points_spent' => 1000]);
        $night = LoyaltyVoucher::factory()->create([
            'guest_id' => $guest->id,
            'type' => 'free_night',
            'value_usd' => null,
            'points_spent' => 10000,
        ]);

        $uuids = fn (string $query): array => collect($this->vouchers($guest, $query)->assertOk()->json('data.items'))->pluck('uuid')->sort()->values()->all();

        $this->assertSame([$expired->uuid], $uuids('?status[eq]=expired'));
        $this->assertSame(collect([$active->uuid, $night->uuid])->sort()->values()->all(), $uuids('?status[in]=active,used'));
        $this->assertSame([$night->uuid], $uuids('?type[eq]=free_night'));
        $this->assertSame([$night->uuid], $uuids('?points_spent[gte]=5000'));
        $this->assertSame([$expired->uuid], $uuids('?points_spent[lte]=1500'));
    }

    public function test_a_non_integer_points_filter_is_422(): void
    {
        $guest = Guest::factory()->create();

        $this->vouchers($guest, '?points_spent[gte]=abc')->assertStatus(422);
    }
}
