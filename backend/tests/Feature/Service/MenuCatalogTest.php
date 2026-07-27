<?php

namespace Tests\Feature\Service;

use App\Models\DiningVenue;
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
        $venue = DiningVenue::factory()->create();

        $this->actingAs($admin, 'users')
             ->postJson('/api/cms/menu-categories', [
                 'dining_venue_uuid' => $venue->uuid,
                 'name' => ['en' => 'Starters', 'ar' => 'مقبلات'],
                 'sort_order' => 1,
             ])
             ->assertCreated()
             ->assertJsonPath('data.name.en', 'Starters')
             ->assertJsonPath('data.slug', 'starters')
             ->assertJsonPath('data.dining_venue_uuid', $venue->uuid);
    }

    public function test_menu_category_requires_a_venue(): void
    {
        $this->actingAs($this->makeAdmin(), 'users')
             ->postJson('/api/cms/menu-categories', ['name' => ['en' => 'Starters', 'ar' => 'مقبلات']])
             ->assertStatus(422)
             ->assertJsonPath('error_code', 'validation_failed');
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

    // ── Public, venue-scoped menu ─────────────────────────────────────────

    public function test_public_menu_returns_only_that_venues_items(): void
    {
        $venue = DiningVenue::factory()->create();
        $other = DiningVenue::factory()->create();

        MenuItem::factory()->count(2)->create([
            'menu_category_id' => MenuCategory::factory()->forVenue($venue),
        ]);
        MenuItem::factory()->create([
            'menu_category_id' => MenuCategory::factory()->forVenue($other),
        ]);

        $res = $this->getJson("/api/public/dining-venues/{$venue->uuid}/menu")->assertOk();
        $this->assertCount(2, $res->json('data.items'));
    }

    public function test_public_menu_filters_by_category_slug(): void
    {
        $venue    = DiningVenue::factory()->create();
        $starters = MenuCategory::factory()->forVenue($venue)->create(['slug' => 'starters']);
        $desserts = MenuCategory::factory()->forVenue($venue)->create(['slug' => 'dessert']);

        MenuItem::factory()->count(2)->create(['menu_category_id' => $starters->id]);
        MenuItem::factory()->create(['menu_category_id' => $desserts->id]);

        $res = $this->getJson("/api/public/dining-venues/{$venue->uuid}/menu?type=starters")->assertOk();

        $this->assertCount(2, $res->json('data.items'));
        $this->assertSame('starters', $res->json('data.items.0.type'));
    }

    public function test_public_menu_exposes_vegan_flag_and_price(): void
    {
        $venue    = DiningVenue::factory()->create();
        $category = MenuCategory::factory()->forVenue($venue)->create();
        MenuItem::factory()->vegan()->create([
            'menu_category_id' => $category->id,
            'price_usd'        => 9.50,
        ]);

        $res = $this->getJson("/api/public/dining-venues/{$venue->uuid}/menu")->assertOk();

        $this->assertTrue($res->json('data.items.0.is_vegan'));
        $this->assertSame('9.50', $res->json('data.items.0.price_usd'));
        $this->assertNull($res->json('data.items.0.photo'));
    }

    public function test_public_menu_hides_inactive_items_and_categories(): void
    {
        $venue    = DiningVenue::factory()->create();
        $active   = MenuCategory::factory()->forVenue($venue)->create();
        $inactive = MenuCategory::factory()->forVenue($venue)->create(['is_active' => false]);

        MenuItem::factory()->create(['menu_category_id' => $active->id]);
        MenuItem::factory()->create(['menu_category_id' => $active->id, 'is_active' => false]);
        MenuItem::factory()->create(['menu_category_id' => $inactive->id]);

        $res = $this->getJson("/api/public/dining-venues/{$venue->uuid}/menu")->assertOk();
        $this->assertCount(1, $res->json('data.items'));
    }

    public function test_menu_categories_endpoint_returns_filter_chips(): void
    {
        $venue = DiningVenue::factory()->create();
        MenuCategory::factory()->forVenue($venue)->create(['slug' => 'breakfast', 'sort_order' => 0]);
        MenuCategory::factory()->forVenue($venue)->create(['slug' => 'dessert', 'sort_order' => 1]);

        $res = $this->getJson("/api/public/dining-venues/{$venue->uuid}/menu-categories")->assertOk();

        $this->assertSame('breakfast', $res->json('data.0.slug'));
        $this->assertSame('dessert', $res->json('data.1.slug'));
    }

    public function test_menu_of_inactive_venue_returns_404(): void
    {
        $venue = DiningVenue::factory()->create(['is_active' => false]);
        $this->getJson("/api/public/dining-venues/{$venue->uuid}/menu")->assertNotFound();
    }
}
