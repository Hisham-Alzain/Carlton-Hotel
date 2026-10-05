<?php

namespace Tests\Feature\Loyalty;

use App\Enums\LoyaltyBatchStatus;
use App\Enums\LoyaltyEntryType;
use App\Http\Resources\Loyalty\LoyaltyReportResource;
use App\Models\Guest;
use App\Models\LoyaltyEarnBatch;
use App\Models\LoyaltyLedgerEntry;
use App\Services\Loyalty\LoyaltyReportService;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\BuildsLoyaltyFixtures;
use Tests\Concerns\CountsDomainQueries;
use Tests\TestCase;

/**
 * Phase 10 plan 14 (LOY-19, LOY-20; Q7, Q19): the staff loyalty report. Exact
 * per-type totals over a hotel-local period (half-open UTC window from
 * HotelClock::dayWindow), plus outstanding points and their USD liability,
 * behind loyalty.view only.
 */
class LoyaltyReportTest extends TestCase
{
    use BuildsLoyaltyFixtures, CountsDomainQueries, RefreshDatabase;

    private const URL = '/api/cms/loyalty/reports';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        config(['hotel.timezone' => 'Europe/London']);
    }

    private function report(array $query = [], ?string $token = null, array $headers = ['Accept-Language' => 'en']): TestResponse
    {
        $this->app['auth']->forgetGuards();
        $url = self::URL.($query ? '?'.http_build_query($query) : '');

        return $this->withToken($token ?? $this->staffToken('loyalty.view'))->getJson($url, $headers);
    }

    /** A London wall-clock instant, as the UTC instant the ledger stores. */
    private function london(string $local): CarbonImmutable
    {
        return CarbonImmutable::parse($local, 'Europe/London')->utc();
    }

    private function entry(LoyaltyEntryType $type, int $points, CarbonInterface $at, array $extra = []): LoyaltyLedgerEntry
    {
        return LoyaltyLedgerEntry::factory()->create(array_merge([
            'type' => $type,
            'points' => $points,
            'occurred_at' => $at,
        ], $extra));
    }

    // ------------------------------------------------------------------ sums

    public function test_every_type_is_summed_into_its_own_bucket_and_refunds_are_not_issued(): void
    {
        $this->configureLoyalty();
        $at = $this->london('2027-03-15 12:00:00');

        $this->entry(LoyaltyEntryType::EARN, 1000, $at);
        $this->entry(LoyaltyEntryType::EARN, 500, $at);
        $this->entry(LoyaltyEntryType::ADJUST, 200, $at);
        $this->entry(LoyaltyEntryType::ADJUST, -50, $at);
        $this->entry(LoyaltyEntryType::REDEEM, -300, $at);
        $this->entry(LoyaltyEntryType::EXPIRE, -120, $at);
        $this->entry(LoyaltyEntryType::REFUND, 80, $at);
        $this->entry(LoyaltyEntryType::CLAWBACK, -60, $at, ['shortfall_points' => 15]);

        $this->report(['date_from' => '2027-03-01', 'date_to' => '2027-03-31'])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.period', ['date_from' => '2027-03-01', 'date_to' => '2027-03-31', 'timezone' => 'Europe/London'])
            ->assertJsonPath('data.issued_points', 1700)
            ->assertJsonPath('data.redeemed_points', 300)
            ->assertJsonPath('data.expired_points', 120)
            ->assertJsonPath('data.refunded_points', 80)
            ->assertJsonPath('data.clawed_back_points', 60)
            ->assertJsonPath('data.adjusted_out_points', 50);
    }

    public function test_the_response_carries_exactly_the_contract_keys_as_integers(): void
    {
        $this->configureLoyalty(['redeem_value_usd' => '0.0100']);
        $this->entry(LoyaltyEntryType::EARN, 10, $this->london('2027-03-15 12:00:00'));

        $data = $this->report(['date_from' => '2027-03-01', 'date_to' => '2027-03-31'])->assertOk()->json('data');

        $this->assertEqualsCanonicalizing([
            'period', 'issued_points', 'redeemed_points', 'expired_points', 'refunded_points',
            'clawed_back_points', 'adjusted_out_points', 'outstanding_points', 'liability_usd',
        ], array_keys($data));

        foreach (['issued_points', 'redeemed_points', 'expired_points', 'refunded_points', 'clawed_back_points', 'adjusted_out_points', 'outstanding_points'] as $key) {
            $this->assertIsInt($data[$key], $key);
            $this->assertGreaterThanOrEqual(0, $data[$key], $key);
        }
    }

    public function test_an_empty_period_is_all_zeros(): void
    {
        $this->configureLoyalty(['redeem_value_usd' => '0.0100']);

        $this->report(['date_from' => '2027-03-01', 'date_to' => '2027-03-31'])
            ->assertOk()
            ->assertJsonPath('data.issued_points', 0)
            ->assertJsonPath('data.redeemed_points', 0)
            ->assertJsonPath('data.expired_points', 0)
            ->assertJsonPath('data.refunded_points', 0)
            ->assertJsonPath('data.clawed_back_points', 0)
            ->assertJsonPath('data.adjusted_out_points', 0)
            ->assertJsonPath('data.outstanding_points', 0)
            ->assertJsonPath('data.liability_usd', '0.00');
    }

    // ------------------------------------------------------------------ window

    public function test_the_window_is_hotel_local_and_half_open(): void
    {
        $this->configureLoyalty();
        // March 31 2027 is British Summer Time (UTC+1).
        $this->entry(LoyaltyEntryType::EARN, 1, $this->london('2027-03-31 23:59:59'));   // last second in
        $this->entry(LoyaltyEntryType::EARN, 10, $this->london('2027-04-01 00:00:00'));  // next day: out
        $this->entry(LoyaltyEntryType::EARN, 100, $this->london('2027-02-28 23:59:59')); // day before: out
        $this->entry(LoyaltyEntryType::EARN, 1000, $this->london('2027-03-01 00:00:00')); // first second in

        $this->report(['date_from' => '2027-03-01', 'date_to' => '2027-03-31'])
            ->assertOk()
            ->assertJsonPath('data.issued_points', 1001);
    }

    public function test_a_period_crossing_the_dst_change_keeps_entries_in_the_short_day(): void
    {
        $this->configureLoyalty();
        // 2027-03-28 is the London spring-forward day: it lasts 23 hours (00:00 GMT to 00:00 BST).
        $this->entry(LoyaltyEntryType::EARN, 1, $this->london('2027-03-28 00:30:00'));  // in
        $this->entry(LoyaltyEntryType::EARN, 10, $this->london('2027-03-28 23:30:00')); // in (22:30 UTC)
        $this->entry(LoyaltyEntryType::EARN, 100, $this->london('2027-03-29 00:30:00')); // out (23:30 UTC)
        $this->entry(LoyaltyEntryType::EARN, 1000, $this->london('2027-03-27 23:30:00')); // out

        $this->report(['date_from' => '2027-03-28', 'date_to' => '2027-03-28'])
            ->assertOk()
            ->assertJsonPath('data.issued_points', 11);
    }

    public function test_without_params_both_bounds_are_the_hotel_local_today(): void
    {
        $this->configureLoyalty();
        // 23:30 UTC on 2027-06-14 is already 00:30 on 2027-06-15 in London (BST).
        $this->travelTo(CarbonImmutable::parse('2027-06-14 23:30:00', 'UTC'));

        $this->entry(LoyaltyEntryType::EARN, 7, $this->london('2027-06-15 00:10:00'));  // today in London
        $this->entry(LoyaltyEntryType::EARN, 100, $this->london('2027-06-14 23:50:00')); // yesterday in London

        $this->report()
            ->assertOk()
            ->assertJsonPath('data.period', ['date_from' => '2027-06-15', 'date_to' => '2027-06-15', 'timezone' => 'Europe/London'])
            ->assertJsonPath('data.issued_points', 7);
    }

    // ------------------------------------------------------------------ outstanding / liability

    public function test_outstanding_sums_only_active_unexpired_batches_and_ignores_the_period(): void
    {
        $this->configureLoyalty(['redeem_value_usd' => '0.0100']);
        $guest = Guest::factory()->create();

        $this->grantPoints($guest, 10000, now()->addMonths(3));
        $this->grantPoints($guest, 2345, now()->addYears(1));
        // Active status but already past its expiry (sweep has not run): excluded.
        LoyaltyEarnBatch::factory()->create([
            'guest_id' => $guest->id, 'points' => 999, 'points_remaining' => 999,
            'status' => LoyaltyBatchStatus::ACTIVE, 'expires_at' => now()->subHour(),
        ]);
        LoyaltyEarnBatch::factory()->expired()->create(['guest_id' => $guest->id, 'points' => 500]);
        LoyaltyEarnBatch::factory()->depleted()->create(['guest_id' => $guest->id, 'points' => 400]);
        LoyaltyEarnBatch::factory()->create([
            'guest_id' => $guest->id, 'points' => 777, 'points_remaining' => 777,
            'status' => LoyaltyBatchStatus::REVERSED,
        ]);

        // A period years in the past: the ledger there is empty but outstanding is "now".
        $this->report(['date_from' => '2020-01-01', 'date_to' => '2020-01-31'])
            ->assertOk()
            ->assertJsonPath('data.issued_points', 0)
            ->assertJsonPath('data.outstanding_points', 12345)
            ->assertJsonPath('data.liability_usd', '123.45');
    }

    public function test_liability_is_null_while_the_redeem_value_is_unset(): void
    {
        $this->configureLoyalty(['redeem_value_usd' => null, 'max_redeem_percent' => null]);
        $this->grantPoints(Guest::factory()->create(), 12345, now()->addMonths(3));

        $this->report(['date_from' => '2027-03-01', 'date_to' => '2027-03-31'])
            ->assertOk()
            ->assertJsonPath('data.outstanding_points', 12345)
            ->assertJsonPath('data.liability_usd', null);
    }

    public function test_liability_is_null_with_no_settings_row_at_all(): void
    {
        $this->grantPoints(Guest::factory()->create(), 50, now()->addMonths(3));

        $this->report(['date_from' => '2027-03-01', 'date_to' => '2027-03-31'])
            ->assertOk()
            ->assertJsonPath('data.outstanding_points', 50)
            ->assertJsonPath('data.liability_usd', null);
    }

    public function test_liability_rounds_half_up_to_the_cent(): void
    {
        $this->configureLoyalty(['redeem_value_usd' => '0.0100']);
        $this->grantPoints(Guest::factory()->create(), 1234, now()->addMonths(3)); // 12.34 exactly
        $this->grantPoints(Guest::factory()->create(), 1, now()->addMonths(3));    // 12.35 total

        $this->report(['date_from' => '2027-03-01', 'date_to' => '2027-03-31'])
            ->assertOk()
            ->assertJsonPath('data.liability_usd', '12.35');
    }

    // ------------------------------------------------------------------ validation

    public function test_only_one_of_the_two_dates_is_a_422(): void
    {
        $this->report(['date_from' => '2027-03-01'])
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'validation_failed')
            ->assertJsonValidationErrors(['date_to']);

        $this->report(['date_to' => '2027-03-01'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['date_from']);
    }

    public function test_malformed_and_impossible_dates_are_a_422(): void
    {
        $this->report(['date_from' => '2027-02-30', 'date_to' => '2027-03-01'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['date_from']);

        $this->report(['date_from' => '27-3-1', 'date_to' => '2027-03-01'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['date_from']);

        $this->report(['date_from' => '2027-03-01', 'date_to' => 'tomorrow'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['date_to']);
    }

    public function test_date_to_before_date_from_is_a_422(): void
    {
        $this->report(['date_from' => '2027-03-05', 'date_to' => '2027-03-04'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['date_to']);
    }

    public function test_a_366_day_period_is_allowed_and_a_367_day_one_is_not(): void
    {
        $this->configureLoyalty();

        // 2027-01-01..2027-12-31 is 365 days inclusive; ..2028-01-01 is exactly 366; ..2028-01-02 is 367.
        $this->report(['date_from' => '2027-01-01', 'date_to' => '2027-12-31'])->assertOk();
        $this->report(['date_from' => '2027-01-01', 'date_to' => '2028-01-01'])->assertOk();

        $response = $this->report(['date_from' => '2027-01-01', 'date_to' => '2028-01-02'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['date_to']);

        $this->assertContains(__('custom.validation.loyalty_report_period_too_long'), $response->json('errors.date_to'));
        $this->assertSame('The report period cannot be longer than 366 days.', __('custom.validation.loyalty_report_period_too_long'));
    }

    public function test_the_too_long_message_is_localized(): void
    {
        $response = $this->report(['date_from' => '2027-01-01', 'date_to' => '2028-01-02'], null, ['Accept-Language' => 'ar'])
            ->assertStatus(422);

        $arabic = trans('custom.validation.loyalty_report_period_too_long', [], 'ar');
        $this->assertNotSame('custom.validation.loyalty_report_period_too_long', $arabic);
        $this->assertNotSame(trans('custom.validation.loyalty_report_period_too_long', [], 'en'), $arabic);
        $this->assertContains($arabic, $response->json('errors.date_to'));
    }

    // ------------------------------------------------------------------ auth

    public function test_no_token_and_a_guest_token_are_401(): void
    {
        $this->app['auth']->forgetGuards();
        $this->getJson(self::URL)->assertStatus(401);

        $this->app['auth']->forgetGuards();
        $this->withToken($this->guestToken(Guest::factory()->create()))->getJson(self::URL)->assertStatus(401);
    }

    public function test_users_without_loyalty_view_are_403(): void
    {
        $this->report([], $this->staffToken())->assertStatus(403)->assertJsonPath('error_code', 'forbidden');
        $this->report([], $this->staffToken('loyalty.manage'))->assertStatus(403);
        $this->report([], $this->staffToken('loyalty.adjust'))->assertStatus(403);
        $this->report([], $this->staffToken('reports.view'))->assertStatus(403);
    }

    // ------------------------------------------------------------------ query budget

    public function test_the_query_budget_is_fixed_and_does_not_grow_with_the_ledger(): void
    {
        $this->configureLoyalty(['redeem_value_usd' => '0.0100']);
        $guest = Guest::factory()->create();
        $types = [LoyaltyEntryType::EARN, LoyaltyEntryType::ADJUST, LoyaltyEntryType::REDEEM, LoyaltyEntryType::EXPIRE, LoyaltyEntryType::REFUND, LoyaltyEntryType::CLAWBACK];

        $measure = function (): int {
            return $this->countDomainQueries(function (): void {
                $data = app(LoyaltyReportService::class)->report('2027-03-01', '2027-03-31')['data'];
                (new LoyaltyReportResource($data))->resolve(Request::create('/'));
            });
        };

        $empty = $measure();

        foreach (range(1, 50) as $n) {
            $type = $types[$n % count($types)];
            $this->entry($type, in_array($type, [LoyaltyEntryType::EARN, LoyaltyEntryType::REFUND], true) ? $n : -$n, $this->london('2027-03-15 12:00:00'), ['guest_id' => $guest->id]);
        }
        $this->grantPoints($guest, 100, now()->addMonths(3));

        $full = $measure();

        $this->assertSame($empty, $full, 'the report must not run a query per entry');
        // Pinned: settings row, one grouped ledger aggregate, one outstanding sum.
        $this->assertSame(3, $full);
    }
}
