<?php

namespace Tests\Feature\Service;

use App\Enums\Department;
use App\Models\Guest;
use App\Models\Reservation;
use App\Models\ServiceCategory;
use App\Models\ServiceItem;
use App\Models\User;
use Database\Seeders\GuestServiceCatalogSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ServiceCatalogTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function checkedInGuest(): Guest
    {
        $guest = Guest::factory()->create();
        Reservation::factory()->checkedIn()->create(['guest_id' => $guest->id]);
        return $guest;
    }

    private function editorToken(): string
    {
        $user = User::factory()->create();
        $user->givePermissionTo('cms.edit');
        return $user->createToken('t')->plainTextToken;
    }

    // ── Public catalog ────────────────────────────────────────────────────

    public function test_seeded_catalog_exposes_the_eight_categories(): void
    {
        $this->seed(GuestServiceCatalogSeeder::class);

        $res = $this->getJson('/api/public/service-catalog')->assertOk();

        $codes = array_column($res->json('data'), 'code');
        $this->assertSame([
            'room_service', 'housekeeping', 'laundry', 'concierge',
            'transport', 'restaurant', 'maintenance', 'do_not_disturb',
        ], $codes);
    }

    public function test_catalog_categories_carry_their_items_with_expected_time(): void
    {
        $this->seed(GuestServiceCatalogSeeder::class);

        $res  = $this->getJson('/api/public/service-catalog')->assertOk();
        $room = collect($res->json('data'))->firstWhere('code', 'room_service');

        $this->assertSame('catalog', $room['kind']);
        $this->assertCount(3, $room['items']);
        $this->assertSame('Carlton Breakfast', $room['items'][0]['name']['en']);
        $this->assertSame('Full breakfast selection with fresh juice', $room['items'][0]['description']['en']);
        $this->assertSame(30, $room['items'][0]['expected_minutes']);
        $this->assertNotEmpty($room['items'][0]['name']['ar']);
    }

    public function test_direct_categories_hide_their_default_item_but_expose_its_uuid(): void
    {
        $this->seed(GuestServiceCatalogSeeder::class);

        $res       = $this->getJson('/api/public/service-catalog')->assertOk();
        $concierge = collect($res->json('data'))->firstWhere('code', 'concierge');

        $this->assertSame('direct', $concierge['kind']);
        $this->assertSame([], $concierge['items']);
        $this->assertNotEmpty($concierge['default_item_uuid']);
    }

    public function test_link_and_toggle_categories_carry_no_items(): void
    {
        $this->seed(GuestServiceCatalogSeeder::class);

        $data       = collect($this->getJson('/api/public/service-catalog')->assertOk()->json('data'));
        $restaurant = $data->firstWhere('code', 'restaurant');
        $dnd        = $data->firstWhere('code', 'do_not_disturb');

        $this->assertSame('link', $restaurant['kind']);
        $this->assertSame('dining', $restaurant['link_target']);
        $this->assertSame([], $restaurant['items']);

        $this->assertSame('toggle', $dnd['kind']);
        $this->assertNull($dnd['default_item_uuid']);
    }

    public function test_inactive_categories_and_items_are_hidden(): void
    {
        $category = ServiceCategory::factory()->create();
        ServiceItem::factory()->create(['service_category_id' => $category->id]);
        ServiceItem::factory()->inactive()->create(['service_category_id' => $category->id]);
        ServiceCategory::factory()->inactive()->create();

        $res = $this->getJson('/api/public/service-catalog')->assertOk();

        $this->assertCount(1, $res->json('data'));
        $this->assertCount(1, $res->json('data.0.items'));
    }

    // ── Requesting a microservice ─────────────────────────────────────────

    public function test_guest_can_request_a_catalog_item_with_special_instructions(): void
    {
        $guest    = $this->checkedInGuest();
        $category = ServiceCategory::factory()->create([
            'code'       => 'room_service',
            'department' => Department::KITCHEN,
        ]);
        $item = ServiceItem::factory()->create(['service_category_id' => $category->id]);

        $this->actingAs($guest, 'guests')
            ->postJson('/api/service-requests', [
                'service_item_uuid' => $item->uuid,
                'notes'             => 'No nuts please',
            ])
            ->assertCreated()
            ->assertJsonPath('data.type', 'room_service')
            ->assertJsonPath('data.department', 'kitchen')
            ->assertJsonPath('data.notes', 'No nuts please')
            ->assertJsonPath('data.category_code', 'room_service')
            ->assertJsonPath('data.service_item.uuid', $item->uuid);
    }

    public function test_request_department_comes_from_the_category_not_the_type_map(): void
    {
        $guest    = $this->checkedInGuest();
        // `laundry` maps to housekeeping in the legacy map, but the category
        // declares reception — the declared value must win.
        $category = ServiceCategory::factory()->create([
            'code'       => 'laundry',
            'department' => Department::RECEPTION,
        ]);
        $item = ServiceItem::factory()->create(['service_category_id' => $category->id]);

        $this->actingAs($guest, 'guests')
            ->postJson('/api/service-requests', ['service_item_uuid' => $item->uuid])
            ->assertCreated()
            ->assertJsonPath('data.department', 'reception');
    }

    public function test_legacy_free_string_type_still_works(): void
    {
        $guest = $this->checkedInGuest();

        $this->actingAs($guest, 'guests')
            ->postJson('/api/service-requests', ['type' => 'wake_up_call'])
            ->assertCreated()
            ->assertJsonPath('data.department', 'concierge')
            ->assertJsonPath('data.service_item', null);
    }

    public function test_maintenance_routes_to_the_new_maintenance_department(): void
    {
        $guest = $this->checkedInGuest();

        $this->actingAs($guest, 'guests')
            ->postJson('/api/service-requests', ['type' => 'maintenance'])
            ->assertCreated()
            ->assertJsonPath('data.department', 'maintenance');
    }

    public function test_request_without_type_or_item_is_rejected(): void
    {
        $guest = $this->checkedInGuest();

        $this->actingAs($guest, 'guests')
            ->postJson('/api/service-requests', ['notes' => 'something'])
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'validation_failed');
    }

    public function test_inactive_item_uuid_is_rejected(): void
    {
        $guest = $this->checkedInGuest();
        $item  = ServiceItem::factory()->inactive()->create();

        $this->actingAs($guest, 'guests')
            ->postJson('/api/service-requests', ['service_item_uuid' => $item->uuid])
            ->assertStatus(422);
    }

    public function test_guest_not_checked_in_cannot_request(): void
    {
        $item = ServiceItem::factory()->create();

        $this->actingAs(Guest::factory()->create(), 'guests')
            ->postJson('/api/service-requests', ['service_item_uuid' => $item->uuid])
            ->assertStatus(403);
    }

    // ── Billing ───────────────────────────────────────────────────────────

    public function test_a_priced_catalog_request_is_charged_to_the_folio(): void
    {
        $guest       = Guest::factory()->create();
        $reservation = Reservation::factory()->checkedIn()->create([
            'guest_id'  => $guest->id,
            'total_usd' => 100.00,
        ]);
        $item = ServiceItem::factory()->priced(18.00)->create([
            'name' => ['en' => 'Carlton Breakfast', 'ar' => 'فطور كارلتون'],
        ]);

        $this->actingAs($guest, 'guests')
            ->postJson('/api/service-requests', ['service_item_uuid' => $item->uuid])
            ->assertCreated();

        $folio = app(\App\Actions\Folio\GenerateFolioAction::class)->handle($reservation)['data'];

        $line = $folio->items->firstWhere('source_type', 'service_request');
        $this->assertNotNull($line, 'priced catalog request should produce a folio line');
        $this->assertSame('Carlton Breakfast', $line->description);
        $this->assertSame('18.00', (string) $line->amount_usd);
        $this->assertSame('118.00', (string) $folio->total_usd);
    }

    public function test_a_complimentary_catalog_request_adds_no_folio_line(): void
    {
        $guest       = Guest::factory()->create();
        $reservation = Reservation::factory()->checkedIn()->create([
            'guest_id'  => $guest->id,
            'total_usd' => 100.00,
        ]);
        $item = ServiceItem::factory()->create(); // price_usd null

        $this->actingAs($guest, 'guests')
            ->postJson('/api/service-requests', ['service_item_uuid' => $item->uuid])
            ->assertCreated();

        $folio = app(\App\Actions\Folio\GenerateFolioAction::class)->handle($reservation)['data'];

        $this->assertNull($folio->items->firstWhere('source_type', 'service_request'));
        $this->assertSame('100.00', (string) $folio->total_usd);
    }

    public function test_a_cancelled_request_is_not_charged(): void
    {
        $guest       = Guest::factory()->create();
        $reservation = Reservation::factory()->checkedIn()->create([
            'guest_id'  => $guest->id,
            'total_usd' => 100.00,
        ]);
        $item = ServiceItem::factory()->priced(18.00)->create();

        $uuid = $this->actingAs($guest, 'guests')
            ->postJson('/api/service-requests', ['service_item_uuid' => $item->uuid])
            ->assertCreated()
            ->json('data.uuid');

        \App\Models\ServiceRequest::where('uuid', $uuid)
            ->update(['status' => \App\Enums\ServiceRequestStatus::CANCELLED]);

        $folio = app(\App\Actions\Folio\GenerateFolioAction::class)->handle($reservation)['data'];

        $this->assertNull($folio->items->firstWhere('source_type', 'service_request'));
        $this->assertSame('100.00', (string) $folio->total_usd);
    }

    // ── Admin CRUD ────────────────────────────────────────────────────────

    public function test_admin_can_create_a_category_and_an_item(): void
    {
        $token = $this->editorToken();

        $category = $this->withToken($token)
            ->postJson('/api/cms/service-categories', [
                'code'       => 'spa',
                'name'       => ['en' => 'Spa', 'ar' => 'سبا'],
                'kind'       => 'catalog',
                'department' => 'concierge',
            ])
            ->assertStatus(201)
            ->json('data.uuid');

        $this->withToken($token)
            ->postJson('/api/cms/service-items', [
                'service_category_uuid' => $category,
                'name'             => ['en' => 'Hot Stone', 'ar' => 'أحجار ساخنة'],
                'expected_minutes' => 90,
                'price_usd'        => 110,
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.expected_minutes', 90)
            ->assertJsonPath('data.price_usd', '110.00');
    }

    public function test_catalog_category_requires_a_department(): void
    {
        $this->withToken($this->editorToken())
            ->postJson('/api/cms/service-categories', [
                'code' => 'spa',
                'name' => ['en' => 'Spa', 'ar' => 'سبا'],
                'kind' => 'catalog',
            ])
            ->assertStatus(422);
    }

    public function test_staff_without_cms_edit_cannot_write_the_catalog(): void
    {
        $token = User::factory()->create()->createToken('t')->plainTextToken;

        $this->withToken($token)
            ->postJson('/api/cms/service-categories', [])
            ->assertStatus(403);
    }
}
