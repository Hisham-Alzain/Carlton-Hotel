<?php

namespace Tests\Feature\Service;

use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * @group p7
 */
class MenuCatalogTest extends TestCase
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

    public function test_admin_can_create_menu_category(): void
    {
        $admin = $this->makeAdmin();

        $this->actingAs($admin, 'users')
             ->postJson('/api/cms/menu-categories', [
                 'name' => ['en' => 'Starters', 'ar' => 'مقبلات'],
                 'sort_order' => 1,
             ])
             ->assertCreated()
             ->assertJsonPath('data.name.en', 'Starters');
    }

    public function test_admin_can_create_menu_item_under_category(): void
    {
        $admin    = $this->makeAdmin();
        $category = MenuCategory::factory()->create();

        $this->actingAs($admin, 'users')
             ->postJson('/api/cms/menu-items', [
                 'menu_category_uuid' => $category->uuid,
                 'name'      => ['en' => 'Hummus', 'ar' => 'حمص'],
                 'price_usd' => 8.50,
             ])
             ->assertCreated()
             ->assertJsonPath('data.menu_category_uuid', $category->uuid);
    }

    public function test_admin_can_update_and_delete_menu_item(): void
    {
        $admin = $this->makeAdmin();
        $item  = MenuItem::factory()->create();

        $this->actingAs($admin, 'users')
             ->putJson("/api/cms/menu-items/{$item->uuid}", [
                 'menu_category_uuid' => $item->category->uuid,
                 'name'      => ['en' => 'Updated', 'ar' => 'محدث'],
                 'price_usd' => 12.00,
             ])
             ->assertOk()
             ->assertJsonPath('data.price_usd', '12.00');

        $this->actingAs($admin, 'users')
             ->deleteJson("/api/cms/menu-items/{$item->uuid}")
             ->assertStatus(204);
    }

    public function test_permission_gate_blocks_unpermitted_user(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'users')
             ->postJson('/api/cms/menu-categories', ['name' => ['en' => 'X', 'ar' => 'س']])
             ->assertForbidden();
    }
}
