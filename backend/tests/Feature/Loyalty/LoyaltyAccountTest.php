<?php

namespace Tests\Feature\Loyalty;

use App\Enums\LoyaltyBatchSource;
use App\Enums\LoyaltyEntryType;
use App\Http\Resources\Loyalty\LoyaltyAccountResource;
use App\Http\Resources\Loyalty\LoyaltyLedgerEntryResource;
use App\Models\Guest;
use App\Models\LoyaltyEarnBatch;
use App\Models\LoyaltyLedgerEntry;
use App\Models\LoyaltySetting;
use App\Models\LoyaltyVoucher;
use App\Models\Reservation;
use App\Models\User;
use App\Services\Loyalty\LoyaltyAccountService;
use Carbon\CarbonImmutable;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\BuildsLoyaltyFixtures;
use Tests\Concerns\CountsDomainQueries;
use Tests\TestCase;

/**
 * Phase 10 LOY-06/LOY-08 (Q15, Q16, Pitfall 9, Pitfall 14): the guest reads
 * exactly their own balance and ledger. Contract strings are asserted key by
 * key because clients branch on them.
 */
class LoyaltyAccountTest extends TestCase
{
    use BuildsLoyaltyFixtures;
    use CountsDomainQueries;
    use RefreshDatabase;

    private const ACCOUNT_KEYS = [
        'available_points', 'expiring_soon_points', 'expiring_soon_window_days', 'next_expiry_at',
        'lifetime_earned_points', 'lifetime_redeemed_points', 'program', 'redeem_value_usd',
        'min_redeem_points', 'max_redeem_percent',
    ];

    private const GUEST_ENTRY_KEYS = [
        'uuid', 'type', 'label', 'source', 'source_label', 'points', 'shortfall_points', 'discount_usd',
        'occurred_at', 'expires_at', 'reservation', 'folio', 'voucher',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->travelTo(CarbonImmutable::parse('2026-10-04 12:00:00'));
    }

    private function asGuest(Guest $guest, string $uri, string $locale = 'en'): TestResponse
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($this->guestToken($guest))->getJson($uri, ['Accept-Language' => $locale]);
    }

    /** A ledger row for `$guest`; `$attributes` override the factory defaults. */
    private function entry(Guest $guest, LoyaltyEntryType $type, int $points, array $attributes = []): LoyaltyLedgerEntry
    {
        return LoyaltyLedgerEntry::factory()->create(array_merge([
            'guest_id' => $guest->id,
            'type' => $type,
            'points' => $points,
        ], $attributes));
    }

    // ----------------------------------------------------------------- account

    public function test_account_returns_the_contract_for_the_caller_only(): void
    {
        $this->configureLoyalty(['expiry_warning_days' => 30]);
        $guest = Guest::factory()->create();
        $soon = $this->grantPoints($guest, 100, now()->addDays(10));
        $this->grantPoints($guest, 200, now()->addDays(60));
        $this->grantPoints($guest, 50, now()->subDay()); // expired, sweep has not run

        $response = $this->asGuest($guest, '/api/loyalty/account')->assertStatus(200);

        $data = $response->json('data');
        $this->assertEqualsCanonicalizing(self::ACCOUNT_KEYS, array_keys($data));
        $this->assertSame([], array_values(array_filter(array_keys($data), fn ($k) => str_contains($k, 'tier'))));
        $response->assertJsonMissingPath('data.tier')
            ->assertJsonMissingPath('data.guest')
            ->assertJsonPath('data.available_points', 300)
            ->assertJsonPath('data.expiring_soon_points', 100)
            ->assertJsonPath('data.expiring_soon_window_days', 30)
            ->assertJsonPath('data.next_expiry_at', $soon->expires_at->toIso8601String())
            ->assertJsonPath('data.program', ['earning' => true, 'points_discount' => true, 'rewards' => true])
            ->assertJsonPath('data.redeem_value_usd', '0.0100')
            ->assertJsonPath('data.min_redeem_points', 100)
            ->assertJsonPath('data.max_redeem_percent', '50.00');
    }

    public function test_the_warning_window_is_read_at_request_time(): void
    {
        $this->configureLoyalty(['expiry_warning_days' => 30]);
        $guest = Guest::factory()->create();
        $this->grantPoints($guest, 100, now()->addDays(10));
        $this->grantPoints($guest, 200, now()->addDays(60));

        $this->asGuest($guest, '/api/loyalty/account')
            ->assertJsonPath('data.expiring_soon_points', 100)
            ->assertJsonPath('data.expiring_soon_window_days', 30);

        $this->configureLoyalty(['expiry_warning_days' => 5]);

        $this->asGuest($guest, '/api/loyalty/account')
            ->assertJsonPath('data.expiring_soon_points', 0)
            ->assertJsonPath('data.expiring_soon_window_days', 5);

        $this->configureLoyalty(['expiry_warning_days' => 90]);

        $this->asGuest($guest, '/api/loyalty/account')
            ->assertJsonPath('data.expiring_soon_points', 300);
    }

    public function test_lifetime_figures_are_net_of_clawbacks_and_refunds(): void
    {
        $this->configureLoyalty();
        $guest = Guest::factory()->create();
        $this->entry($guest, LoyaltyEntryType::EARN, 500, ['source' => LoyaltyBatchSource::STAY]);
        $this->entry($guest, LoyaltyEntryType::ADJUST, 50, ['source' => LoyaltyBatchSource::MANUAL]);
        $this->entry($guest, LoyaltyEntryType::CLAWBACK, -100);
        $this->entry($guest, LoyaltyEntryType::REDEEM, -200);
        $this->entry($guest, LoyaltyEntryType::REFUND, 80, ['source' => LoyaltyBatchSource::REFUND]);
        // A deduction and an expiry are neither earned nor redeemed.
        $this->entry($guest, LoyaltyEntryType::ADJUST, -30, ['source' => LoyaltyBatchSource::MANUAL]);
        $this->entry($guest, LoyaltyEntryType::EXPIRE, -40);

        $this->asGuest($guest, '/api/loyalty/account')
            ->assertStatus(200)
            ->assertJsonPath('data.lifetime_earned_points', 450)
            ->assertJsonPath('data.lifetime_redeemed_points', 120);
    }

    public function test_lifetime_figures_are_floored_at_zero(): void
    {
        $this->configureLoyalty();
        $guest = Guest::factory()->create();
        $this->entry($guest, LoyaltyEntryType::EARN, 100, ['source' => LoyaltyBatchSource::STAY]);
        $this->entry($guest, LoyaltyEntryType::CLAWBACK, -300);
        $this->entry($guest, LoyaltyEntryType::REDEEM, -50);
        $this->entry($guest, LoyaltyEntryType::REFUND, 90, ['source' => LoyaltyBatchSource::REFUND]);

        $this->asGuest($guest, '/api/loyalty/account')
            ->assertJsonPath('data.lifetime_earned_points', 0)
            ->assertJsonPath('data.lifetime_redeemed_points', 0);
    }

    public function test_a_guest_with_no_history_gets_zeroes_and_a_null_next_expiry(): void
    {
        $this->configureLoyalty();

        $this->asGuest(Guest::factory()->create(), '/api/loyalty/account')
            ->assertStatus(200)
            ->assertJsonPath('data.available_points', 0)
            ->assertJsonPath('data.expiring_soon_points', 0)
            ->assertJsonPath('data.next_expiry_at', null)
            ->assertJsonPath('data.lifetime_earned_points', 0)
            ->assertJsonPath('data.lifetime_redeemed_points', 0);
    }

    public function test_account_works_when_the_program_is_unconfigured(): void
    {
        $this->assertSame(0, LoyaltySetting::query()->count());
        $guest = Guest::factory()->create();

        $this->asGuest($guest, '/api/loyalty/account')
            ->assertStatus(200)
            ->assertJsonPath('data.program', ['earning' => false, 'points_discount' => false, 'rewards' => true])
            ->assertJsonPath('data.redeem_value_usd', null)
            ->assertJsonPath('data.max_redeem_percent', null)
            ->assertJsonPath('data.min_redeem_points', 1)
            ->assertJsonPath('data.expiring_soon_window_days', 30);

        $this->assertSame(0, LoyaltySetting::query()->count(), 'reading the account never writes the settings row');
    }

    public function test_account_ignores_another_guests_batches_and_entries(): void
    {
        $this->configureLoyalty();
        $a = Guest::factory()->create();
        $b = Guest::factory()->create();
        $this->grantPoints($a, 100, now()->addDays(10));
        $this->grantPoints($b, 9000, now()->addDays(5));
        $this->entry($b, LoyaltyEntryType::REDEEM, -700);

        $this->asGuest($a, '/api/loyalty/account')
            ->assertJsonPath('data.available_points', 100)
            ->assertJsonPath('data.expiring_soon_points', 100)
            ->assertJsonPath('data.lifetime_earned_points', 100)
            ->assertJsonPath('data.lifetime_redeemed_points', 0);
    }

    public function test_account_requires_a_guest_token(): void
    {
        $this->configureLoyalty();
        $this->getJson('/api/loyalty/account')->assertStatus(401);

        $this->flushHeaders();
        $this->app['auth']->forgetGuards();
        $this->withToken($this->staffToken('loyalty.view', 'loyalty.manage', 'loyalty.adjust'))
            ->getJson('/api/loyalty/account')
            ->assertStatus(401);
    }

    public function test_account_query_budget_is_fixed(): void
    {
        $this->configureLoyalty();
        $guest = Guest::factory()->create();
        foreach (range(1, 12) as $n) {
            $this->grantPoints($guest, 10 * $n, now()->addDays($n));
        }

        // Pinned: program row, balances (one aggregate), lifetime figures (one grouped query).
        $count = $this->countDomainQueries(function () use ($guest): void {
            $data = app(LoyaltyAccountService::class)->account($guest)['data'];
            (new LoyaltyAccountResource($data))->resolve(Request::create('/'));
        });

        $this->assertSame(3, $count);
    }

    // ------------------------------------------------------------------ ledger

    public function test_ledger_lists_newest_first_with_every_contract_key_and_no_staff_fields(): void
    {
        $this->configureLoyalty();
        $guest = Guest::factory()->create();
        $staff = User::factory()->create(['name' => 'Rana Staff']);
        [$reservation, $folio] = $this->generatedStay();
        $voucher = LoyaltyVoucher::factory()->create(['guest_id' => $guest->id]);

        $earnBatch = LoyaltyEarnBatch::factory()->create([
            'guest_id' => $guest->id, 'source' => LoyaltyBatchSource::STAY, 'points' => 500, 'points_remaining' => 500,
            'expires_at' => now()->addMonths(24),
        ]);
        $this->entry($guest, LoyaltyEntryType::EARN, 500, [
            'source' => LoyaltyBatchSource::STAY, 'batch_id' => $earnBatch->id, 'folio_id' => $folio->id,
            'occurred_at' => now()->subHours(3),
        ]);

        $adjustBatch = LoyaltyEarnBatch::factory()->create([
            'guest_id' => $guest->id, 'source' => LoyaltyBatchSource::MANUAL, 'points' => 50, 'points_remaining' => 50,
            'expires_at' => now()->addMonths(12),
        ]);
        $this->entry($guest, LoyaltyEntryType::ADJUST, 50, [
            'source' => LoyaltyBatchSource::MANUAL, 'batch_id' => $adjustBatch->id, 'performed_by' => $staff->id,
            'reason' => 'Goodwill after a noisy night', 'occurred_at' => now()->subHours(2),
        ]);

        $this->entry($guest, LoyaltyEntryType::REDEEM, -200, [
            'reservation_id' => $reservation->id, 'voucher_id' => $voucher->id, 'discount_usd' => '2.00',
            'occurred_at' => now()->subHour(),
        ]);

        $response = $this->asGuest($guest, '/api/loyalty/ledger')->assertStatus(200);

        $items = $response->json('data.items');
        $this->assertCount(3, $items);
        $this->assertSame(['redeem', 'adjust', 'earn'], array_column($items, 'type'));
        $response->assertJsonPath('data.meta.total', 3);

        foreach ($items as $item) {
            $this->assertEqualsCanonicalizing(self::GUEST_ENTRY_KEYS, array_keys($item));
        }
        $response->assertJsonMissingPath('data.items.0.reason')
            ->assertJsonMissingPath('data.items.1.reason')
            ->assertJsonMissingPath('data.items.1.performed_by')
            ->assertJsonMissingPath('data.items.1.id');
        $this->assertStringNotContainsString('Goodwill', $response->getContent());
        $this->assertStringNotContainsString('Rana Staff', $response->getContent());

        [$redeem, $adjust, $earn] = $items;

        $this->assertSame('Points redeemed', $redeem['label']);
        $this->assertNull($redeem['source']);
        $this->assertNull($redeem['source_label']);
        $this->assertSame(-200, $redeem['points']);
        $this->assertSame(0, $redeem['shortfall_points']);
        $this->assertSame('2.00', $redeem['discount_usd']);
        $this->assertNull($redeem['expires_at']);
        $this->assertSame(['uuid' => $reservation->uuid, 'booking_code' => $reservation->booking_code], $redeem['reservation']);
        $this->assertSame(['uuid' => $voucher->uuid, 'code' => $voucher->code], $redeem['voucher']);
        $this->assertNull($redeem['folio']);

        $this->assertSame('manual', $adjust['source']);
        $this->assertSame('Staff adjustment', $adjust['source_label']);
        $this->assertSame('Manual adjustment', $adjust['label']);
        $this->assertSame(50, $adjust['points']);
        $this->assertSame($adjustBatch->expires_at->toIso8601String(), $adjust['expires_at']);
        $this->assertNull($adjust['reservation']);
        $this->assertNull($adjust['voucher']);
        $this->assertNull($adjust['discount_usd']);

        $this->assertSame('stay', $earn['source']);
        $this->assertSame('Room stay', $earn['source_label']);
        $this->assertSame('Points earned', $earn['label']);
        $this->assertSame($earnBatch->expires_at->toIso8601String(), $earn['expires_at']);
        $this->assertSame(['uuid' => $folio->uuid], $earn['folio']);
        $this->assertSame(now()->subHours(3)->toIso8601String(), $earn['occurred_at']);
    }

    public function test_ledger_labels_follow_the_request_locale(): void
    {
        $this->configureLoyalty();
        $guest = Guest::factory()->create();
        $this->grantPoints($guest, 100, null, LoyaltyBatchSource::STAY);

        $item = $this->asGuest($guest, '/api/loyalty/ledger', 'ar')->assertStatus(200)->json('data.items.0');

        $this->assertNotSame('Points earned', $item['label']);
        $this->assertNotSame('Room stay', $item['source_label']);
        $this->assertMatchesRegularExpression('/\p{Arabic}/u', $item['label']);
        $this->assertMatchesRegularExpression('/\p{Arabic}/u', $item['source_label']);
        $this->assertSame('earn', $item['type'], 'the contract type string never translates');
    }

    public function test_ledger_is_paginated_with_a_stable_newest_first_tiebreak(): void
    {
        $this->configureLoyalty();
        $guest = Guest::factory()->create();
        $same = now()->subHour();
        $first = $this->entry($guest, LoyaltyEntryType::ADJUST, 1, ['occurred_at' => $same]);
        $second = $this->entry($guest, LoyaltyEntryType::ADJUST, 2, ['occurred_at' => $same]);
        $oldest = $this->entry($guest, LoyaltyEntryType::ADJUST, 3, ['occurred_at' => now()->subDay()]);

        $page = $this->asGuest($guest, '/api/loyalty/ledger?per_page=2')->assertStatus(200);
        $this->assertSame([$second->uuid, $first->uuid], array_column($page->json('data.items'), 'uuid'));
        $page->assertJsonPath('data.meta.total', 3)->assertJsonPath('data.meta.per_page', 2)->assertJsonPath('data.meta.last_page', 2);

        $next = $this->asGuest($guest, '/api/loyalty/ledger?per_page=2&page=2');
        $this->assertSame([$oldest->uuid], array_column($next->json('data.items'), 'uuid'));
    }

    public function test_ledger_filters_by_type_source_date_and_points(): void
    {
        $this->configureLoyalty();
        $guest = Guest::factory()->create();
        $this->entry($guest, LoyaltyEntryType::EARN, 500, ['source' => LoyaltyBatchSource::STAY, 'occurred_at' => now()->subDays(10)]);
        $this->entry($guest, LoyaltyEntryType::EARN, 40, ['source' => LoyaltyBatchSource::SERVICE, 'occurred_at' => now()->subDays(5)]);
        $this->entry($guest, LoyaltyEntryType::REDEEM, -200, ['occurred_at' => now()->subDays(2)]);
        $this->entry($guest, LoyaltyEntryType::ADJUST, -10, ['source' => LoyaltyBatchSource::MANUAL, 'occurred_at' => now()->subDay()]);

        $types = fn (string $query) => array_column(
            $this->asGuest($guest, '/api/loyalty/ledger?'.$query)->assertStatus(200)->json('data.items'),
            'type',
        );

        $this->assertSame(['earn', 'earn'], $types('type[eq]=earn'));
        $this->assertSame(['adjust', 'redeem'], $types('type[in]=adjust,redeem'));
        $this->assertSame(['earn'], $types('source[eq]=service'));
        $this->assertSame(['adjust', 'earn', 'earn'], $types('source[in]=manual,stay,service'));

        $since = rawurlencode(now()->subDays(6)->format('Y-m-d H:i:s'));
        $this->assertSame(['adjust', 'redeem', 'earn'], $types("occurred_at[gte]={$since}"));
        $until = rawurlencode(now()->subDays(6)->format('Y-m-d H:i:s'));
        $this->assertSame(['earn'], $types("occurred_at[lte]={$until}"));

        $this->assertSame(['earn', 'earn'], $types('points[gte]=1'));
        $this->assertSame(['adjust', 'redeem'], $types('points[lte]=-1'));
        $this->assertSame(['earn'], $types('points[gte]=100'));
        $this->assertSame(['adjust', 'earn'], $types('points[gte]=-10&points[lte]=40'));
    }

    public function test_ledger_never_shows_another_guests_rows(): void
    {
        $this->configureLoyalty();
        $a = Guest::factory()->create();
        $b = Guest::factory()->create();
        $mine = $this->entry($a, LoyaltyEntryType::ADJUST, 10);
        $theirs = $this->entry($b, LoyaltyEntryType::ADJUST, 99, ['reason' => 'secret staff note']);

        $response = $this->asGuest($a, '/api/loyalty/ledger')->assertStatus(200);

        $this->assertSame([$mine->uuid], array_column($response->json('data.items'), 'uuid'));
        $this->assertStringNotContainsString($theirs->uuid, $response->getContent());
        $this->assertStringNotContainsString('secret staff note', $response->getContent());

        // A client-supplied identity is never trusted.
        $spoof = $this->asGuest($a, '/api/loyalty/ledger?guest_id='.$b->id.'&guest='.$b->uuid);
        $this->assertSame([$mine->uuid], array_column($spoof->json('data.items'), 'uuid'));
    }

    public function test_ledger_requires_a_guest_token(): void
    {
        $this->getJson('/api/loyalty/ledger')->assertStatus(401);

        $this->flushHeaders();
        $this->app['auth']->forgetGuards();
        $this->withToken($this->staffToken('loyalty.view', 'loyalty.manage', 'loyalty.adjust'))
            ->getJson('/api/loyalty/ledger')
            ->assertStatus(401);
    }

    public function test_a_non_integer_points_filter_answers_422(): void
    {
        $guest = Guest::factory()->create();

        $response = $this->asGuest($guest, '/api/loyalty/ledger?points[gte]=abc')
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'validation_failed');

        $this->assertArrayHasKey('points.gte', $response->json('errors'));
        $this->asGuest($guest, '/api/loyalty/ledger?points[lte]=1.5')->assertStatus(422);
    }

    public function test_ledger_query_count_does_not_grow_with_the_number_of_entries(): void
    {
        $this->configureLoyalty();
        [$reservation, $folio] = $this->generatedStay();
        $staff = User::factory()->create();

        $measure = function (int $entries) use ($reservation, $folio, $staff): int {
            $guest = Guest::factory()->create();
            $voucher = LoyaltyVoucher::factory()->create(['guest_id' => $guest->id]);
            foreach (range(1, $entries) as $n) {
                $batch = LoyaltyEarnBatch::factory()->create([
                    'guest_id' => $guest->id, 'points' => 10, 'points_remaining' => 10, 'expires_at' => now()->addDays($n),
                ]);
                $this->entry($guest, LoyaltyEntryType::EARN, 10, [
                    'source' => LoyaltyBatchSource::STAY, 'batch_id' => $batch->id, 'reservation_id' => $reservation->id,
                    'folio_id' => $folio->id, 'voucher_id' => $voucher->id, 'performed_by' => $staff->id,
                    'occurred_at' => now()->subMinutes($n),
                ]);
            }

            return $this->countDomainQueries(function () use ($guest): void {
                $page = app(LoyaltyAccountService::class)->ledger($guest)['data'];
                LoyaltyLedgerEntryResource::collection($page)->resolve(Request::create('/'));
            });
        };

        $one = $measure(1);
        $fifteen = $measure(15);

        $this->assertSame($one, $fifteen, 'ledger listing must not run a query per entry');
        // Pinned: count, rows, then one eager load each for batch, reservation, folio, voucher, performer.
        $this->assertSame(7, $fifteen);
    }
}
