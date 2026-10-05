<?php

namespace Tests\Feature\Loyalty;

use App\Models\Guest;
use App\Models\LoyaltyReward;
use App\Models\LoyaltySetting;
use App\Models\LoyaltyVoucher;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;
use Tests\Concerns\BuildsLoyaltyFixtures;
use Tests\TestCase;

/**
 * Phase 10 LOY-11 / LOY-12 / LOY-21 (Q7, Q12, Q18, Pitfall 12): the staff
 * rewards catalog with its recycle bin, and the guest catalog listing.
 */
class LoyaltyRewardTest extends TestCase
{
    use BuildsLoyaltyFixtures;
    use RefreshDatabase;

    private const STAFF = '/api/cms/loyalty/rewards';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => ['en' => '25 USD off', 'ar' => 'خصم 25 دولار'],
            'description' => ['en' => 'Applied to a booking', 'ar' => 'يطبق على الحجز'],
            'type' => 'discount_voucher',
            'points_cost' => 2500,
            'discount_usd' => '25.00',
            'voucher_valid_days' => 90,
            'is_active' => true,
            'sort_order' => 1,
        ], $overrides);
    }

    private function manager(): string
    {
        return $this->staffToken('loyalty.manage');
    }

    private function asStaff(string $token): static
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($token);
    }

    // ── Create ────────────────────────────────────────────────────────────

    public function test_manager_creates_a_discount_voucher_reward(): void
    {
        $this->asStaff($this->manager())
            ->postJson(self::STAFF, $this->payload())
            ->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name.en', '25 USD off')
            ->assertJsonPath('data.name.ar', 'خصم 25 دولار')
            ->assertJsonPath('data.type', 'discount_voucher')
            ->assertJsonPath('data.type_label', __('custom.loyalty.reward_types.discount_voucher'))
            ->assertJsonPath('data.points_cost', 2500)
            ->assertJsonPath('data.discount_usd', '25.00')
            ->assertJsonPath('data.voucher_valid_days', 90)
            ->assertJsonPath('data.is_active', true)
            ->assertJsonPath('data.deleted_at', null)
            ->assertJsonMissingPath('data.id');

        $this->assertNotEmpty(LoyaltyReward::sole()->uuid);
    }

    public function test_free_night_and_room_upgrade_are_created_without_a_discount(): void
    {
        $token = $this->manager();

        foreach (['free_night' => 10000, 'room_upgrade' => 5000] as $type => $cost) {
            $this->asStaff($token)
                ->postJson(self::STAFF, $this->payload(['type' => $type, 'points_cost' => $cost, 'discount_usd' => null]))
                ->assertStatus(201)
                ->assertJsonPath('data.type', $type)
                ->assertJsonPath('data.discount_usd', null);
        }

        $this->asStaff($token)
            ->postJson(self::STAFF, [
                'name' => ['en' => 'Upgrade', 'ar' => 'ترقية'],
                'type' => 'room_upgrade',
                'points_cost' => 100,
                'voucher_valid_days' => 30,
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.is_active', true)
            ->assertJsonPath('data.sort_order', 0)
            ->assertJsonPath('data.description', null);
    }

    // ── Read ──────────────────────────────────────────────────────────────

    public function test_viewer_lists_filters_and_shows(): void
    {
        LoyaltyReward::factory()->create(['sort_order' => 2]);
        $night = LoyaltyReward::factory()->freeNight()->create(['sort_order' => 1]);
        $token = $this->staffToken('loyalty.view');

        $list = $this->asStaff($token)->getJson(self::STAFF)
            ->assertOk()
            ->assertJsonCount(2, 'data.items')
            ->assertJsonPath('data.items.0.uuid', $night->uuid)
            ->assertJsonStructure(['data' => ['items', 'meta']]);
        $this->assertArrayNotHasKey('id', $list->json('data.items.0'));

        $this->asStaff($token)->getJson(self::STAFF.'?type[eq]=free_night')
            ->assertOk()
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.uuid', $night->uuid);

        $this->asStaff($token)->getJson(self::STAFF.'/'.$night->uuid)
            ->assertOk()
            ->assertJsonPath('data.uuid', $night->uuid)
            ->assertJsonPath('data.name.ar', $night->getTranslation('name', 'ar'));
    }

    public function test_manage_only_token_can_also_read(): void
    {
        $reward = LoyaltyReward::factory()->create();
        $token = $this->manager();

        $this->asStaff($token)->getJson(self::STAFF)->assertOk();
        $this->asStaff($token)->getJson(self::STAFF.'/'.$reward->uuid)->assertOk();
    }

    // ── Update ────────────────────────────────────────────────────────────

    public function test_update_changes_points_cost(): void
    {
        $reward = LoyaltyReward::factory()->create();

        $this->asStaff($this->manager())
            ->putJson(self::STAFF.'/'.$reward->uuid, ['points_cost' => 3000])
            ->assertOk()
            ->assertJsonPath('data.points_cost', 3000)
            ->assertJsonPath('data.discount_usd', '25.00');

        $this->assertSame(3000, $reward->fresh()->points_cost);
    }

    public function test_changing_to_a_non_voucher_type_while_a_discount_remains_is_rejected(): void
    {
        $reward = LoyaltyReward::factory()->create();

        $this->asStaff($this->manager())
            ->putJson(self::STAFF.'/'.$reward->uuid, ['type' => 'free_night'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['discount_usd'])
            ->assertJsonPath('errors.discount_usd.0', __('custom.validation.loyalty_discount_usd_voucher_only'));

        $this->asStaff($this->manager())
            ->putJson(self::STAFF.'/'.$reward->uuid, ['type' => 'free_night', 'discount_usd' => null])
            ->assertOk()
            ->assertJsonPath('data.type', 'free_night')
            ->assertJsonPath('data.discount_usd', null);
    }

    public function test_changing_to_a_voucher_without_a_discount_is_rejected(): void
    {
        $reward = LoyaltyReward::factory()->freeNight()->create();

        $this->asStaff($this->manager())
            ->putJson(self::STAFF.'/'.$reward->uuid, ['type' => 'discount_voucher'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['discount_usd']);

        $this->asStaff($this->manager())
            ->putJson(self::STAFF.'/'.$reward->uuid, ['type' => 'discount_voucher', 'discount_usd' => '10.00'])
            ->assertOk()
            ->assertJsonPath('data.discount_usd', '10.00');
    }

    public function test_a_partial_name_update_keeps_the_other_locale(): void
    {
        $reward = LoyaltyReward::factory()->create(['name' => ['en' => 'Original', 'ar' => 'الأصل']]);

        $this->asStaff($this->manager())
            ->putJson(self::STAFF.'/'.$reward->uuid, ['name' => ['ar' => 'اسم جديد']])
            ->assertOk()
            ->assertJsonPath('data.name.en', 'Original')
            ->assertJsonPath('data.name.ar', 'اسم جديد');
    }

    // ── Delete and recycle bin ────────────────────────────────────────────

    public function test_delete_soft_deletes_and_hides_the_reward_everywhere(): void
    {
        $reward = LoyaltyReward::factory()->create();
        $guest = Guest::factory()->create();

        $this->asStaff($this->manager())
            ->deleteJson(self::STAFF.'/'.$reward->uuid)
            ->assertStatus(204);

        $this->assertSoftDeleted('loyalty_rewards', ['uuid' => $reward->uuid]);
        $this->asStaff($this->manager())->getJson(self::STAFF)->assertOk()->assertJsonCount(0, 'data.items');
        $this->asStaff($this->guestToken($guest))->getJson('/api/loyalty/rewards')
            ->assertOk()->assertJsonCount(0, 'data.items');
    }

    public function test_the_bin_lists_restores_and_purges(): void
    {
        $reward = LoyaltyReward::factory()->create();
        $reward->delete();
        $token = $this->staffToken('cms.restore', 'cms.purge');

        $this->asStaff($token)->getJson(self::STAFF.'/trashed')
            ->assertOk()
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.uuid', $reward->uuid);

        $this->asStaff($this->staffToken('cms.restore'))
            ->postJson(self::STAFF.'/'.$reward->uuid.'/restore')
            ->assertOk()
            ->assertJsonPath('data.uuid', $reward->uuid)
            ->assertJsonPath('data.deleted_at', null);
        $this->assertNull($reward->fresh()->deleted_at);

        $reward->delete();

        $this->asStaff($this->staffToken('cms.purge'))
            ->deleteJson(self::STAFF.'/'.$reward->uuid.'/force')
            ->assertStatus(204);
        $this->assertDatabaseMissing('loyalty_rewards', ['uuid' => $reward->uuid]);
    }

    public function test_a_loyalty_manager_without_cms_bin_rights_cannot_use_the_bin(): void
    {
        $reward = LoyaltyReward::factory()->create();
        $reward->delete();
        $token = $this->manager();

        $this->asStaff($token)->getJson(self::STAFF.'/trashed')->assertStatus(403);
        $this->asStaff($token)->postJson(self::STAFF.'/'.$reward->uuid.'/restore')->assertStatus(403);
        $this->asStaff($token)->deleteJson(self::STAFF.'/'.$reward->uuid.'/force')->assertStatus(403);

        $this->assertSoftDeleted('loyalty_rewards', ['uuid' => $reward->uuid]);
    }

    // ── 401 / 403 ─────────────────────────────────────────────────────────

    public function test_unauthenticated_requests_are_rejected_on_every_staff_verb(): void
    {
        $reward = LoyaltyReward::factory()->create();
        $url = self::STAFF.'/'.$reward->uuid;

        $this->getJson(self::STAFF)->assertStatus(401);
        $this->getJson($url)->assertStatus(401);
        $this->postJson(self::STAFF, $this->payload())->assertStatus(401);
        $this->putJson($url, ['points_cost' => 1])->assertStatus(401);
        $this->deleteJson($url)->assertStatus(401);
        $this->getJson(self::STAFF.'/trashed')->assertStatus(401);
        $this->postJson($url.'/restore')->assertStatus(401);
        $this->deleteJson($url.'/force')->assertStatus(401);
    }

    public function test_a_guest_token_cannot_read_the_staff_catalog(): void
    {
        $this->asStaff($this->guestToken(Guest::factory()->create()))
            ->getJson(self::STAFF)
            ->assertStatus(401);
    }

    public function test_a_user_without_permission_is_forbidden_to_read(): void
    {
        $reward = LoyaltyReward::factory()->create();
        $token = $this->staffToken();

        $this->asStaff($token)->getJson(self::STAFF)->assertStatus(403);
        $this->asStaff($token)->getJson(self::STAFF.'/'.$reward->uuid)->assertStatus(403);
    }

    public function test_a_view_only_user_cannot_write(): void
    {
        $reward = LoyaltyReward::factory()->create();
        $token = $this->staffToken('loyalty.view');
        $url = self::STAFF.'/'.$reward->uuid;

        $this->asStaff($token)->postJson(self::STAFF, $this->payload())->assertStatus(403);
        $this->asStaff($token)->putJson($url, ['points_cost' => 1])->assertStatus(403);
        $this->asStaff($token)->deleteJson($url)->assertStatus(403);

        $this->assertSame(1, LoyaltyReward::count());
        $this->assertSame(2500, $reward->fresh()->points_cost);
    }

    // ── 422 ───────────────────────────────────────────────────────────────

    public function test_name_is_required_in_arabic(): void
    {
        $this->asStaff($this->manager())
            ->postJson(self::STAFF, $this->payload(['name' => ['en' => 'Only English']]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name.ar']);
    }

    public function test_an_unknown_type_is_rejected(): void
    {
        $this->asStaff($this->manager())
            ->postJson(self::STAFF, $this->payload(['type' => 'cashback']))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['type']);
    }

    public function test_numeric_bounds_are_enforced(): void
    {
        $token = $this->manager();

        $this->asStaff($token)->postJson(self::STAFF, $this->payload(['points_cost' => 0]))
            ->assertStatus(422)->assertJsonValidationErrors(['points_cost']);
        $this->asStaff($token)->postJson(self::STAFF, $this->payload(['points_cost' => 100000001]))
            ->assertStatus(422)->assertJsonValidationErrors(['points_cost']);
        $this->asStaff($token)->postJson(self::STAFF, $this->payload(['voucher_valid_days' => 0]))
            ->assertStatus(422)->assertJsonValidationErrors(['voucher_valid_days']);
        $this->asStaff($token)->postJson(self::STAFF, $this->payload(['voucher_valid_days' => 731]))
            ->assertStatus(422)->assertJsonValidationErrors(['voucher_valid_days']);
        $this->asStaff($token)->postJson(self::STAFF, $this->payload(['discount_usd' => '0.00']))
            ->assertStatus(422)->assertJsonValidationErrors(['discount_usd']);
        $this->asStaff($token)->postJson(self::STAFF, $this->payload(['discount_usd' => '10.123']))
            ->assertStatus(422)->assertJsonValidationErrors(['discount_usd']);

        $this->assertSame(0, LoyaltyReward::count());
    }

    public function test_a_voucher_reward_requires_a_discount(): void
    {
        $this->asStaff($this->manager())
            ->postJson(self::STAFF, $this->payload(['discount_usd' => null]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['discount_usd']);

        $body = $this->payload();
        unset($body['discount_usd']);

        $this->asStaff($this->manager())
            ->postJson(self::STAFF, $body)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['discount_usd']);
    }

    public function test_a_non_voucher_reward_rejects_a_discount(): void
    {
        $this->asStaff($this->manager())
            ->postJson(self::STAFF, $this->payload(['type' => 'free_night', 'discount_usd' => '10.00']))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['discount_usd'])
            ->assertJsonPath('errors.discount_usd.0', __('custom.validation.loyalty_discount_usd_voucher_only'));
    }

    public function test_validation_errors_are_localized_to_arabic(): void
    {
        $response = $this->withToken($this->manager())
            ->withHeaders(['Accept-Language' => 'ar'])
            ->postJson(self::STAFF, $this->payload(['points_cost' => 0, 'type' => 'free_night', 'discount_usd' => '10.00']))
            ->assertStatus(422);

        foreach (['points_cost', 'discount_usd'] as $field) {
            $this->assertMatchesRegularExpression('/\p{Arabic}/u', $response->json("errors.{$field}.0"), $field);
        }
        $this->assertMatchesRegularExpression('/\p{Arabic}/u', $response->json('message'));
    }

    // ── Guest catalog ─────────────────────────────────────────────────────

    public function test_guest_catalog_lists_active_rewards_in_order_without_a_program_configured(): void
    {
        $this->assertSame(0, LoyaltySetting::count());

        $second = LoyaltyReward::factory()->create(['sort_order' => 5]);
        $first = LoyaltyReward::factory()->freeNight()->create(['sort_order' => 1]);
        LoyaltyReward::factory()->inactive()->create(['sort_order' => 0]);
        LoyaltyReward::factory()->create(['sort_order' => 2])->delete();

        $response = $this->asStaff($this->guestToken(Guest::factory()->create()))
            ->getJson('/api/loyalty/rewards')
            ->assertOk()
            ->assertJsonCount(2, 'data.items')
            ->assertJsonPath('data.items.0.uuid', $first->uuid)
            ->assertJsonPath('data.items.1.uuid', $second->uuid)
            ->assertJsonPath('data.items.0.type_label', __('custom.loyalty.reward_types.free_night'))
            ->assertJsonStructure(['data' => ['items', 'meta']]);

        $this->assertArrayNotHasKey('id', $response->json('data.items.0'));
    }

    public function test_guest_catalog_requires_a_guest_token(): void
    {
        $this->getJson('/api/loyalty/rewards')->assertStatus(401);

        $this->asStaff($this->staffToken('loyalty.view', 'loyalty.manage'))
            ->getJson('/api/loyalty/rewards')
            ->assertStatus(401);
    }

    // ── Audit and purge ───────────────────────────────────────────────────

    public function test_create_and_update_are_audited(): void
    {
        $token = $this->manager();

        $uuid = $this->asStaff($token)->postJson(self::STAFF, $this->payload())->json('data.uuid');
        $reward = LoyaltyReward::where('uuid', $uuid)->sole();

        $this->asStaff($token)->putJson(self::STAFF.'/'.$uuid, ['points_cost' => 2600])->assertOk();

        $logs = Activity::query()
            ->where('subject_type', $reward->getMorphClass())
            ->where('subject_id', $reward->id)
            ->pluck('event')
            ->all();

        $this->assertContains('created', $logs);
        $this->assertContains('updated', $logs);
    }

    public function test_purging_an_expired_reward_keeps_the_voucher_snapshot(): void
    {
        $reward = LoyaltyReward::factory()->create();
        $voucher = LoyaltyVoucher::factory()->create([
            'loyalty_reward_id' => $reward->id,
            'reward_name' => ['en' => '25 USD discount', 'ar' => 'خصم 25 دولار'],
        ]);

        $this->travelTo(now()->subDays(120));
        $reward->delete();
        $this->travelBack();

        $this->artisan('cms:purge-bin')->assertSuccessful();

        $this->assertDatabaseMissing('loyalty_rewards', ['id' => $reward->id]);
        $voucher = $voucher->fresh();
        $this->assertNotNull($voucher);
        $this->assertNull($voucher->loyalty_reward_id);
        $this->assertSame('25 USD discount', $voucher->reward_name['en']);
        $this->assertSame('25.00', $voucher->value_usd);
    }
}
