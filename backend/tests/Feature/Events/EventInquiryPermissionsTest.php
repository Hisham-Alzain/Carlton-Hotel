<?php

namespace Tests\Feature\Events;

use App\Models\EventInquiry;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Phase 8 (D-12, D-14): every event-inquiry staff route is gated by events.*,
 * never tickets.*. Reception and concierge lost event access.
 */
class EventInquiryPermissionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function roleToken(string $role): string
    {
        $this->app['auth']->forgetGuards();

        return User::factory()->create()->assignRole($role)->createToken('t')->plainTextToken;
    }

    private function permissionToken(string ...$permissions): string
    {
        $this->app['auth']->forgetGuards();
        $user = User::factory()->create();
        $user->givePermissionTo($permissions);

        return $user->createToken('t')->plainTextToken;
    }

    private function base(EventInquiry $inquiry): string
    {
        return "/api/cms/event-inquiries/{$inquiry->uuid}";
    }

    public function test_events_preset_reaches_every_event_route(): void
    {
        $inquiry = EventInquiry::factory()->create();
        $staff   = User::factory()->create();

        $this->withToken($this->roleToken('events'))->getJson('/api/cms/event-inquiries')->assertOk();
        $this->withToken($this->roleToken('events'))->getJson($this->base($inquiry))->assertOk();
        $this->withToken($this->roleToken('events'))
            ->patchJson($this->base($inquiry) . '/status', ['status' => 'in_review'])->assertOk();
        $this->withToken($this->roleToken('events'))
            ->patchJson($this->base($inquiry) . '/assign', ['user_uuid' => $staff->uuid])->assertOk();
    }

    public static function lostRoles(): array
    {
        return [['reception'], ['concierge']];
    }

    #[DataProvider('lostRoles')]
    public function test_reception_and_concierge_lose_event_access(string $role): void
    {
        $inquiry = EventInquiry::factory()->create();
        $staff   = User::factory()->create();

        $this->withToken($this->roleToken($role))->getJson('/api/cms/event-inquiries')
            ->assertStatus(403)->assertJsonPath('error_code', 'forbidden');
        $this->withToken($this->roleToken($role))->getJson($this->base($inquiry))->assertStatus(403);
        $this->withToken($this->roleToken($role))
            ->patchJson($this->base($inquiry) . '/status', ['status' => 'in_review'])->assertStatus(403);
        $this->withToken($this->roleToken($role))
            ->patchJson($this->base($inquiry) . '/assign', ['user_uuid' => $staff->uuid])->assertStatus(403);

        $this->assertSame('new', $inquiry->fresh()->status->value);
    }

    public function test_tickets_permissions_alone_are_403(): void
    {
        $inquiry = EventInquiry::factory()->create();

        $this->withToken($this->permissionToken('tickets.view'))
            ->getJson('/api/cms/event-inquiries')->assertStatus(403);
        $this->withToken($this->permissionToken('tickets.assign'))
            ->patchJson($this->base($inquiry) . '/status', ['status' => 'in_review'])->assertStatus(403);
    }

    public function test_events_view_cannot_write(): void
    {
        $inquiry = EventInquiry::factory()->create();
        $staff   = User::factory()->create();

        $this->withToken($this->permissionToken('events.view'))->getJson('/api/cms/event-inquiries')->assertOk();
        $this->withToken($this->permissionToken('events.view'))
            ->patchJson($this->base($inquiry) . '/status', ['status' => 'in_review'])->assertStatus(403);
        $this->withToken($this->permissionToken('events.view'))
            ->patchJson($this->base($inquiry) . '/assign', ['user_uuid' => $staff->uuid])->assertStatus(403);
    }

    public function test_super_admin_passes(): void
    {
        $this->app['auth']->forgetGuards();
        $admin = User::factory()->superAdmin()->create();

        $this->withToken($admin->createToken('t')->plainTextToken)
            ->getJson('/api/cms/event-inquiries')->assertOk();
    }

    public function test_requires_a_token(): void
    {
        $this->getJson('/api/cms/event-inquiries')
            ->assertStatus(401)->assertJsonPath('error_code', 'unauthorized');
    }
}
