<?php

namespace Tests\Feature\Loyalty;

use App\Enums\LoyaltyBatchSource;
use App\Enums\LoyaltyEntryType;
use App\Models\Guest;
use App\Models\LoyaltyLedgerEntry;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\BuildsLoyaltyFixtures;
use Tests\TestCase;

/**
 * Phase 10 LOY-07 (Q16, FA-10.06-2, Pitfall 14): staff with loyalty.view read
 * the same account and ledger as the guest, for any guest, plus the staff-only
 * reason and performer.
 */
class LoyaltyAccountStaffViewTest extends TestCase
{
    use BuildsLoyaltyFixtures;
    use RefreshDatabase;

    private const ACCOUNT_KEYS = [
        'available_points', 'expiring_soon_points', 'expiring_soon_window_days', 'next_expiry_at',
        'lifetime_earned_points', 'lifetime_redeemed_points', 'program', 'redeem_value_usd',
        'min_redeem_points', 'max_redeem_percent', 'guest',
    ];

    private const STAFF_ENTRY_KEYS = [
        'uuid', 'type', 'label', 'source', 'source_label', 'points', 'shortfall_points', 'discount_usd',
        'occurred_at', 'expires_at', 'reservation', 'folio', 'voucher', 'reason', 'performed_by',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function staffGet(string $token, string $path): TestResponse
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($token)->getJson($path, ['Accept-Language' => 'en']);
    }

    public function test_staff_with_view_reads_the_account_of_any_guest(): void
    {
        $this->configureLoyalty();
        $guest = Guest::factory()->create(['name' => 'Layla Haddad']);
        $this->grantPoints($guest, 100, now()->addDays(10));
        $this->grantPoints($guest, 200, now()->addDays(90));

        $response = $this->staffGet($this->staffToken('loyalty.view'), "/api/cms/loyalty/guests/{$guest->uuid}")
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.available_points', 300)
            ->assertJsonPath('data.expiring_soon_points', 100)
            ->assertJsonPath('data.guest', ['uuid' => $guest->uuid, 'name' => 'Layla Haddad'])
            ->assertJsonMissingPath('data.tier')
            ->assertJsonMissingPath('data.guest.id');

        $this->assertEqualsCanonicalizing(self::ACCOUNT_KEYS, array_keys($response->json('data')));
    }

    public function test_super_admin_and_a_view_only_user_can_both_read(): void
    {
        $this->configureLoyalty();
        $guest = Guest::factory()->create();
        $superAdmin = User::factory()->superAdmin()->create();

        $this->staffGet($superAdmin->createToken('t')->plainTextToken, "/api/cms/loyalty/guests/{$guest->uuid}")->assertStatus(200);
        $this->staffGet($this->staffToken('loyalty.view'), "/api/cms/loyalty/guests/{$guest->uuid}/ledger")->assertStatus(200);
    }

    public function test_the_staff_account_works_for_an_unconfigured_program(): void
    {
        $guest = Guest::factory()->create();

        $this->staffGet($this->staffToken('loyalty.view'), "/api/cms/loyalty/guests/{$guest->uuid}")
            ->assertStatus(200)
            ->assertJsonPath('data.program', ['earning' => false, 'points_discount' => false, 'rewards' => true])
            ->assertJsonPath('data.redeem_value_usd', null);
    }

    public function test_staff_ledger_includes_reason_and_performer(): void
    {
        $this->configureLoyalty();
        $guest = Guest::factory()->create();
        $staff = User::factory()->create(['name' => 'Rana Staff']);
        $other = Guest::factory()->create();

        $batch = $this->grantPoints($guest, 50, now()->addMonths(12));
        LoyaltyLedgerEntry::query()->where('batch_id', $batch->id)->update([
            'performed_by' => $staff->id,
            'reason' => 'Goodwill after a noisy night',
        ]);
        LoyaltyLedgerEntry::factory()->create([
            'guest_id' => $guest->id, 'type' => LoyaltyEntryType::EARN, 'source' => LoyaltyBatchSource::STAY,
            'points' => 10, 'occurred_at' => now()->subDay(),
        ]);
        $this->grantPoints($other, 5000);

        $response = $this->staffGet($this->staffToken('loyalty.view'), "/api/cms/loyalty/guests/{$guest->uuid}/ledger")
            ->assertStatus(200)
            ->assertJsonPath('data.meta.total', 2);

        [$manual, $system] = $response->json('data.items');

        $this->assertEqualsCanonicalizing(self::STAFF_ENTRY_KEYS, array_keys($manual));
        $this->assertSame('Goodwill after a noisy night', $manual['reason']);
        $this->assertSame(['uuid' => $staff->uuid, 'name' => 'Rana Staff'], $manual['performed_by']);
        $this->assertSame('manual', $manual['source']);
        $this->assertSame(50, $manual['points']);

        $this->assertEqualsCanonicalizing(self::STAFF_ENTRY_KEYS, array_keys($system));
        $this->assertNull($system['reason']);
        $this->assertNull($system['performed_by']);
        $response->assertJsonMissingPath('data.items.0.id')->assertJsonMissingPath('data.items.0.performed_by.id');
    }

    public function test_staff_ledger_filters_and_paginates(): void
    {
        $this->configureLoyalty();
        $guest = Guest::factory()->create();
        $this->grantPoints($guest, 100);
        LoyaltyLedgerEntry::factory()->create([
            'guest_id' => $guest->id, 'type' => LoyaltyEntryType::REDEEM, 'points' => -40, 'occurred_at' => now()->subDay(),
        ]);

        $token = $this->staffToken('loyalty.view');

        $this->staffGet($token, "/api/cms/loyalty/guests/{$guest->uuid}/ledger?type[eq]=redeem")
            ->assertStatus(200)
            ->assertJsonPath('data.meta.total', 1)
            ->assertJsonPath('data.items.0.type', 'redeem');

        $this->staffGet($token, "/api/cms/loyalty/guests/{$guest->uuid}/ledger?per_page=1")
            ->assertStatus(200)
            ->assertJsonPath('data.meta.per_page', 1)
            ->assertJsonPath('data.meta.last_page', 2);
    }

    public function test_requires_a_staff_token(): void
    {
        $guest = Guest::factory()->create();

        foreach (["/api/cms/loyalty/guests/{$guest->uuid}", "/api/cms/loyalty/guests/{$guest->uuid}/ledger"] as $path) {
            $this->flushHeaders();
            $this->app['auth']->forgetGuards();
            $this->getJson($path)->assertStatus(401);

            // A guest token, even the guest's own, never reaches the staff route.
            $this->staffGet($this->guestToken($guest), $path)->assertStatus(401);
        }
    }

    public function test_view_permission_is_required(): void
    {
        $guest = Guest::factory()->create();
        $tokens = [
            'no permission' => $this->staffToken(),
            'loyalty.manage only' => $this->staffToken('loyalty.manage'),
            'loyalty.adjust only' => $this->staffToken('loyalty.adjust'),
            'reports.view only' => $this->staffToken('reports.view'),
            'guests.view only' => $this->staffToken('guests.view'),
        ];

        foreach (["/api/cms/loyalty/guests/{$guest->uuid}", "/api/cms/loyalty/guests/{$guest->uuid}/ledger"] as $path) {
            foreach ($tokens as $label => $token) {
                $this->staffGet($token, $path)
                    ->assertStatus(403)
                    ->assertJsonPath('error_code', 'forbidden');
                $this->assertTrue(true, $label);
            }
        }
    }

    public function test_every_preset_role_is_forbidden(): void
    {
        $guest = Guest::factory()->create();

        foreach (['reception', 'kitchen', 'housekeeping', 'concierge', 'events', 'content_editor', 'content_manager'] as $role) {
            $this->staffGet($this->presetToken($role), "/api/cms/loyalty/guests/{$guest->uuid}")->assertStatus(403);
        }
    }

    public function test_an_unknown_guest_uuid_answers_404(): void
    {
        $token = $this->staffToken('loyalty.view');
        $missing = '00000000-0000-4000-8000-000000000000';

        $this->staffGet($token, "/api/cms/loyalty/guests/{$missing}")->assertStatus(404);
        $this->staffGet($token, "/api/cms/loyalty/guests/{$missing}/ledger")->assertStatus(404);
    }

    public function test_a_non_integer_points_filter_answers_422(): void
    {
        $guest = Guest::factory()->create();

        $this->staffGet($this->staffToken('loyalty.view'), "/api/cms/loyalty/guests/{$guest->uuid}/ledger?points[gte]=abc")
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'validation_failed');
    }
}
