<?php

namespace Tests\Feature\Loyalty;

use App\Enums\LoyaltyBatchSource;
use App\Enums\LoyaltyBatchStatus;
use App\Enums\LoyaltyEntryType;
use App\Models\Guest;
use App\Models\LoyaltyEarnBatch;
use App\Models\LoyaltyLedgerEntry;
use App\Models\User;
use App\Support\LoyaltyProgram;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Spatie\Activitylog\Models\Activity;
use Tests\Concerns\BuildsLoyaltyFixtures;
use Tests\Concerns\RecordsRowLocks;
use Tests\TestCase;

/**
 * Phase 10 LOY-05 (Q7, Q20, M-6): staff with loyalty.adjust award or deduct
 * points with a mandatory reason, once per Idempotency-Key, audited, and never
 * below a zero balance.
 */
class LoyaltyAdjustTest extends TestCase
{
    use BuildsLoyaltyFixtures;
    use RecordsRowLocks;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function adjust(Guest $guest, array $body, ?string $key = 'K-1', ?string $token = null): TestResponse
    {
        $this->app['auth']->forgetGuards();
        $headers = ['Accept-Language' => 'en'];
        if ($key !== null) {
            $headers['Idempotency-Key'] = $key;
        }

        return $this->withToken($token ?? $this->staffToken('loyalty.adjust'))
            ->postJson("/api/cms/loyalty/guests/{$guest->uuid}/adjustments", $body, $headers);
    }

    private function adjustCount(): int
    {
        return LoyaltyLedgerEntry::where('type', LoyaltyEntryType::ADJUST->value)->count();
    }

    public function test_an_award_creates_a_manual_batch_and_an_adjust_entry(): void
    {
        $guest = Guest::factory()->create();
        $actor = User::factory()->create();
        $actor->givePermissionTo('loyalty.adjust');
        $token = $actor->createToken('t')->plainTextToken;

        $response = $this->adjust($guest, ['points' => 500, 'reason' => 'Goodwill for noise complaint'], 'K-1', $token)
            ->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', __('custom.messages.loyalty_points_adjusted'))
            ->assertJsonPath('data.type', 'adjust')
            ->assertJsonPath('data.source', 'manual')
            ->assertJsonPath('data.points', 500)
            ->assertJsonPath('data.reason', 'Goodwill for noise complaint')
            ->assertJsonPath('data.performed_by.uuid', $actor->uuid)
            ->assertJsonMissingPath('data.id');

        $entry = LoyaltyLedgerEntry::where('uuid', $response->json('data.uuid'))->sole();
        $this->assertSame('adjust:'.$guest->id.':K-1', $entry->idempotency_key);
        $this->assertSame($actor->id, (int) $entry->performed_by);

        $batch = LoyaltyEarnBatch::where('guest_id', $guest->id)->sole();
        $this->assertSame(LoyaltyBatchSource::MANUAL, $batch->source);
        $this->assertSame(500, $batch->points);
        $this->assertSame(500, $batch->points_remaining);
        $this->assertSame(LoyaltyBatchStatus::ACTIVE, $batch->status);
        $this->assertSame($actor->id, (int) $batch->awarded_by);
        $this->assertSame('Goodwill for noise complaint', $batch->reason);
        $this->assertSame($batch->id, (int) $entry->batch_id);
        $this->assertSame(
            LoyaltyProgram::current()->expiresAtFrom(now())->toDateTimeString(),
            $batch->expires_at->copy()->utc()->toDateTimeString(),
        );
    }

    public function test_an_award_works_with_no_settings_row(): void
    {
        $guest = Guest::factory()->create();

        $this->assertDatabaseCount('loyalty_settings', 0);

        $this->adjust($guest, ['points' => 40, 'reason' => 'Welcome gesture'])->assertStatus(201);

        $this->assertEquals(now()->addMonths(24)->toDateString(), LoyaltyEarnBatch::where('guest_id', $guest->id)->sole()->expires_at->toDateString());
    }

    public function test_an_award_is_audited_in_the_activity_log(): void
    {
        $guest = Guest::factory()->create();
        $actor = User::factory()->create();
        $actor->givePermissionTo('loyalty.adjust');

        $response = $this->adjust($guest, ['points' => 25, 'reason' => 'Birthday'], 'K-9', $actor->createToken('t')->plainTextToken)->assertStatus(201);

        $log = Activity::where('description', 'loyalty.points_adjusted')->sole();
        $this->assertSame($guest->id, (int) $log->subject_id);
        $this->assertSame($actor->id, (int) $log->causer_id);
        $this->assertSame($guest->uuid, $log->properties['guest_uuid']);
        $this->assertSame(25, $log->properties['points']);
        $this->assertSame($response->json('data.uuid'), $log->properties['entry_uuid']);
    }

    public function test_a_deduction_consumes_fifo_and_records_the_allocations(): void
    {
        $guest = Guest::factory()->create();
        $first = $this->grantPoints($guest, 300, now()->addDays(10));
        $second = $this->grantPoints($guest, 200, now()->addDays(60));

        $response = $this->adjust($guest, ['points' => -350, 'reason' => 'Chargeback correction'])
            ->assertStatus(201)
            ->assertJsonPath('data.type', 'adjust')
            ->assertJsonPath('data.points', -350)
            ->assertJsonPath('data.reason', 'Chargeback correction');

        $entry = LoyaltyLedgerEntry::where('uuid', $response->json('data.uuid'))->sole();
        $allocations = $entry->allocations()->orderBy('id')->get();
        $this->assertCount(2, $allocations);
        $this->assertSame([$first->id, 300], [(int) $allocations[0]->batch_id, $allocations[0]->points]);
        $this->assertSame([$second->id, 50], [(int) $allocations[1]->batch_id, $allocations[1]->points]);

        $this->assertSame(0, $first->fresh()->points_remaining);
        $this->assertSame(LoyaltyBatchStatus::DEPLETED, $first->fresh()->status);
        $this->assertSame(150, $second->fresh()->points_remaining);
        $this->assertSame(LoyaltyEntryType::ADJUST, $entry->type);
        $this->assertSame(LoyaltyBatchSource::MANUAL, $entry->source);
        $this->assertNotNull($entry->performed_by);
    }

    public function test_an_over_deduction_is_refused_and_writes_nothing(): void
    {
        $guest = Guest::factory()->create();
        $batch = $this->grantPoints($guest, 500);
        $entries = LoyaltyLedgerEntry::count();

        $this->adjust($guest, ['points' => -600, 'reason' => 'Too much'])
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'loyalty_insufficient_points')
            ->assertJsonPath('context.available_points', 500)
            ->assertJsonPath('context.requested_points', 600);

        $this->assertSame($entries, LoyaltyLedgerEntry::count());
        $this->assertSame(500, $batch->fresh()->points_remaining);
        $this->assertSame(0, Activity::where('description', 'loyalty.points_adjusted')->count());
    }

    public function test_a_deduction_never_touches_an_expired_batch(): void
    {
        $guest = Guest::factory()->create();
        $this->grantPoints($guest, 400, now()->subDay());
        $live = $this->grantPoints($guest, 100, now()->addDays(30));

        $this->adjust($guest, ['points' => -150, 'reason' => 'Expired is gone'])
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'loyalty_insufficient_points')
            ->assertJsonPath('context.available_points', 100);

        $this->assertSame(100, $live->fresh()->points_remaining);
    }

    public function test_zero_and_over_magnitude_points_are_a_domain_error(): void
    {
        $guest = Guest::factory()->create();

        foreach ([0, 1000001, -1000001] as $i => $points) {
            $this->adjust($guest, ['points' => $points, 'reason' => 'Out of bounds'], "K-{$i}")
                ->assertStatus(422)
                ->assertJsonPath('error_code', 'loyalty_adjustment_invalid')
                ->assertJsonPath('context.max_adjust_points', 1000000);
        }

        $this->assertSame(0, $this->adjustCount());
        $this->assertSame(0, LoyaltyEarnBatch::count());
    }

    public function test_the_maximum_magnitude_itself_is_allowed(): void
    {
        $guest = Guest::factory()->create();

        $this->adjust($guest, ['points' => 1000000, 'reason' => 'Boundary'])->assertStatus(201);
    }

    public function test_reason_and_points_are_validated(): void
    {
        $guest = Guest::factory()->create();

        $this->adjust($guest, ['points' => 10])
            ->assertStatus(422)->assertJsonPath('error_code', 'validation_failed')->assertJsonValidationErrors('reason');
        $this->adjust($guest, ['points' => 10, 'reason' => 'ab'], 'K-2')
            ->assertStatus(422)->assertJsonValidationErrors('reason');
        $this->adjust($guest, ['points' => 10, 'reason' => str_repeat('x', 501)], 'K-3')
            ->assertStatus(422)->assertJsonValidationErrors('reason');
        $this->adjust($guest, ['reason' => 'No points here'], 'K-4')
            ->assertStatus(422)->assertJsonValidationErrors('points');
        $this->adjust($guest, ['points' => '1.5', 'reason' => 'Fractional'], 'K-5')
            ->assertStatus(422)->assertJsonValidationErrors('points');
        $this->adjust($guest, ['points' => 'many', 'reason' => 'Not a number'], 'K-6')
            ->assertStatus(422)->assertJsonValidationErrors('points');

        $this->assertSame(0, $this->adjustCount());
    }

    public function test_a_missing_or_blank_key_is_422(): void
    {
        $guest = Guest::factory()->create();
        $body = ['points' => 10, 'reason' => 'Needs a key'];

        foreach ([null, '', '   '] as $key) {
            $this->adjust($guest, $body, $key)
                ->assertStatus(422)
                ->assertJsonPath('error_code', 'validation_failed')
                ->assertJsonPath('errors.idempotency_key.0', __('custom.errors.idempotency_key_required'));
        }

        $this->assertSame(0, $this->adjustCount());
    }

    public function test_the_same_key_and_body_replays_the_same_entry(): void
    {
        $guest = Guest::factory()->create();
        $token = $this->staffToken('loyalty.adjust');
        $body = ['points' => 200, 'reason' => 'Once only'];

        $first = $this->adjust($guest, $body, 'K-R', $token)->assertStatus(201);
        $entries = LoyaltyLedgerEntry::count();
        $batches = LoyaltyEarnBatch::count();

        $this->adjust($guest, $body, 'K-R', $token)
            ->assertStatus(200)
            ->assertJsonPath('data.uuid', $first->json('data.uuid'))
            ->assertJsonPath('data.points', 200);

        $this->assertSame($entries, LoyaltyLedgerEntry::count());
        $this->assertSame($batches, LoyaltyEarnBatch::count());
        $this->assertSame(1, Activity::where('description', 'loyalty.points_adjusted')->count());
    }

    public function test_a_replayed_deduction_does_not_consume_twice(): void
    {
        $guest = Guest::factory()->create();
        $batch = $this->grantPoints($guest, 500);
        $token = $this->staffToken('loyalty.adjust');
        $body = ['points' => -100, 'reason' => 'Once only'];

        $this->adjust($guest, $body, 'K-D', $token)->assertStatus(201);
        $this->adjust($guest, $body, 'K-D', $token)->assertStatus(200);

        $this->assertSame(400, $batch->fresh()->points_remaining);
    }

    public function test_the_same_key_with_a_different_body_is_a_conflict(): void
    {
        $guest = Guest::factory()->create();
        $token = $this->staffToken('loyalty.adjust');

        $this->adjust($guest, ['points' => 500, 'reason' => 'Original'], 'K-C', $token)->assertStatus(201);

        $this->adjust($guest, ['points' => 501, 'reason' => 'Original'], 'K-C', $token)
            ->assertStatus(409)->assertJsonPath('error_code', 'idempotency_conflict');
        $this->adjust($guest, ['points' => 500, 'reason' => 'Changed reason'], 'K-C', $token)
            ->assertStatus(409)->assertJsonPath('error_code', 'idempotency_conflict');

        $this->assertSame(1, $this->adjustCount());
    }

    public function test_the_same_key_from_another_staff_member_is_a_conflict(): void
    {
        $guest = Guest::factory()->create();
        $body = ['points' => 500, 'reason' => 'Shared key'];

        $this->adjust($guest, $body, 'K-S', $this->staffToken('loyalty.adjust'))->assertStatus(201);
        $this->adjust($guest, $body, 'K-S', $this->staffToken('loyalty.adjust'))
            ->assertStatus(409)->assertJsonPath('error_code', 'idempotency_conflict');

        $this->assertSame(1, $this->adjustCount());
    }

    public function test_the_same_client_key_for_another_guest_is_a_separate_write(): void
    {
        $token = $this->staffToken('loyalty.adjust');
        $body = ['points' => 50, 'reason' => 'Per guest keys'];
        $first = Guest::factory()->create();
        $second = Guest::factory()->create();

        $this->adjust($first, $body, 'K-G', $token)->assertStatus(201);
        $this->adjust($second, $body, 'K-G', $token)->assertStatus(201);

        $this->assertSame(2, $this->adjustCount());
    }

    public function test_it_requires_a_staff_token(): void
    {
        $guest = Guest::factory()->create();
        $body = ['points' => 10, 'reason' => 'No token'];

        $this->postJson("/api/cms/loyalty/guests/{$guest->uuid}/adjustments", $body, ['Idempotency-Key' => 'K'])->assertStatus(401);

        $this->app['auth']->forgetGuards();
        $this->withToken($this->guestToken($guest))
            ->postJson("/api/cms/loyalty/guests/{$guest->uuid}/adjustments", $body, ['Idempotency-Key' => 'K'])
            ->assertStatus(401);
    }

    public function test_view_and_manage_holders_cannot_adjust(): void
    {
        $guest = Guest::factory()->create();
        $body = ['points' => 10, 'reason' => 'Not allowed'];

        $this->adjust($guest, $body, 'K-1', $this->staffToken('loyalty.view', 'loyalty.manage'))
            ->assertStatus(403)->assertJsonPath('error_code', 'forbidden');
        $this->adjust($guest, $body, 'K-2', $this->staffToken())->assertStatus(403);

        $this->assertSame(0, $this->adjustCount());
    }

    public function test_an_unknown_guest_is_404(): void
    {
        $this->app['auth']->forgetGuards();

        $this->withToken($this->staffToken('loyalty.adjust'))
            ->postJson('/api/cms/loyalty/guests/00000000-0000-4000-8000-000000000000/adjustments', ['points' => 10, 'reason' => 'Nobody'], ['Idempotency-Key' => 'K'])
            ->assertStatus(404);
    }

    public function test_a_deduction_locks_the_guest_before_the_batches(): void
    {
        $guest = Guest::factory()->create();
        $this->grantPoints($guest, 300);
        $token = $this->staffToken('loyalty.adjust');

        $locked = $this->lockedSelects(function () use ($guest, $token) {
            $this->adjust($guest, ['points' => -100, 'reason' => 'Lock order'], 'K-L', $token)->assertStatus(201);
        });

        $tables = array_map(fn (string $sql) => str_contains($sql, 'from "guests"') ? 'guests' : (str_contains($sql, 'from "loyalty_earn_batches"') ? 'batches' : 'other'), $locked);
        $this->assertContains('guests', $tables);
        $this->assertContains('batches', $tables);
        $this->assertLessThan(array_search('batches', $tables, true), array_search('guests', $tables, true), implode("\n", $locked));
    }
}
