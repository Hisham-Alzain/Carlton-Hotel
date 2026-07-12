<?php

namespace Tests\Feature\Service;

use App\Models\PoolCabana;
use App\Models\RestaurantTable;
use App\Models\SpaService;
use App\Models\Transfer;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * @group p7
 */
class BookableCatalogTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function makeAdmin(): User
    {
        $user = User::factory()->create();
        $user->givePermissionTo('cms.edit');
        return $user;
    }

    public function test_admin_can_crud_spa_service(): void
    {
        $admin = $this->makeAdmin();

        $create = $this->actingAs($admin, 'users')->postJson('/api/cms/spa-services', [
            'name' => ['en' => 'Deep Tissue Massage', 'ar' => 'تدليك عميق'],
            'duration_minutes' => 60,
            'price_usd' => 90,
        ])->assertCreated();

        $uuid = $create->json('data.uuid');

        $this->actingAs($admin, 'users')
             ->deleteJson("/api/cms/spa-services/{$uuid}")
             ->assertStatus(204);
    }

    public function test_admin_can_crud_restaurant_table(): void
    {
        $admin = $this->makeAdmin();

        $this->actingAs($admin, 'users')->postJson('/api/cms/restaurant-tables', [
            'table_number' => 'T-101',
            'capacity'     => 4,
        ])->assertCreated()->assertJsonPath('data.table_number', 'T-101');
    }

    public function test_admin_can_crud_pool_cabana(): void
    {
        $admin = $this->makeAdmin();

        $this->actingAs($admin, 'users')->postJson('/api/cms/pool-cabanas', [
            'name'      => ['en' => 'VIP Cabana', 'ar' => 'كابانا VIP'],
            'capacity'  => 4,
            'price_usd' => 150,
        ])->assertCreated();
    }

    public function test_admin_can_crud_transfer(): void
    {
        $admin = $this->makeAdmin();

        $this->actingAs($admin, 'users')->postJson('/api/cms/transfers', [
            'name'      => ['en' => 'Airport Pickup', 'ar' => 'استقبال المطار'],
            'price_usd' => 25,
        ])->assertCreated();
    }

    public function test_public_cannot_access_catalog_admin_routes(): void
    {
        $this->postJson('/api/cms/spa-services', [])->assertUnauthorized();
    }

    public function test_index_lists_catalog_items(): void
    {
        SpaService::factory()->count(3)->create();
        $admin = $this->makeAdmin();

        $this->actingAs($admin, 'users')
             ->getJson('/api/cms/spa-services')
             ->assertOk()
             ->assertJsonCount(3, 'data.items');
    }
}
