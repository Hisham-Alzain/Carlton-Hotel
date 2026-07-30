<?php

namespace Tests\Feature\Cms;

use App\Models\GalleryCategory;
use App\Models\GalleryItem;
use App\Models\Media;
use App\Models\Promotion;
use App\Models\User;
use App\Support\TranslatableRules;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class GalleryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function tokenWith(string ...$permissions): string
    {
        $user = User::factory()->create();
        foreach ($permissions as $permission) {
            $user->givePermissionTo($permission);
        }
        return $user->createToken('t')->plainTextToken;
    }

    private function editorToken(): string
    {
        return $this->tokenWith('cms.edit');
    }

    private function categoryPayload(array $overrides = []): array
    {
        return array_merge([
            'slug'      => 'rooms',
            'name'      => ['en' => 'Rooms', 'ar' => 'الغرف'],
            'is_active' => true,
        ], $overrides);
    }

    private function itemPayload(GalleryCategory $category, array $overrides = []): array
    {
        return array_merge([
            'gallery_category_uuid' => $category->uuid,
            'caption'               => ['en' => 'Grand Suite living room', 'ar' => 'صالة معيشة الجناح الكبير'],
            'is_active'             => true,
        ], $overrides);
    }

    // ── Categories ────────────────────────────────────────────────────

    public function test_admin_can_crud_gallery_category(): void
    {
        $token = $this->editorToken();

        $uuid = $this->withToken($token)->postJson('/api/cms/gallery-categories', $this->categoryPayload())
            ->assertStatus(201)
            ->assertJsonPath('data.name.ar', 'الغرف')
            ->json('data.uuid');

        $this->withToken($token)->getJson("/api/cms/gallery-categories/{$uuid}")->assertOk();

        $this->withToken($token)
            ->putJson("/api/cms/gallery-categories/{$uuid}", ['name' => ['en' => 'Rooms & Suites', 'ar' => 'الغرف والأجنحة']])
            ->assertOk()
            ->assertJsonPath('data.name.en', 'Rooms & Suites');

        $this->withToken($token)->deleteJson("/api/cms/gallery-categories/{$uuid}")->assertStatus(204);
        $this->assertDatabaseCount('gallery_categories', 0);
    }

    public function test_category_slug_must_be_unique_and_url_shaped(): void
    {
        $token = $this->editorToken();
        GalleryCategory::factory()->create(['slug' => 'rooms']);

        $this->withToken($token)
            ->postJson('/api/cms/gallery-categories', $this->categoryPayload(['slug' => 'rooms']))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['slug']);

        $this->withToken($token)
            ->postJson('/api/cms/gallery-categories', $this->categoryPayload(['slug' => 'Rooms & Suites']))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['slug']);
    }

    public function test_updating_a_category_may_keep_its_own_slug(): void
    {
        $category = GalleryCategory::factory()->create(['slug' => 'damascus']);

        $this->withToken($this->editorToken())
            ->putJson("/api/cms/gallery-categories/{$category->uuid}", ['slug' => 'damascus', 'sort_order' => 3])
            ->assertOk()
            ->assertJsonPath('data.slug', 'damascus');
    }

    // ── Items ─────────────────────────────────────────────────────────

    public function test_admin_can_crud_gallery_item(): void
    {
        $token    = $this->editorToken();
        $category = GalleryCategory::factory()->create(['slug' => 'rooms']);
        $other    = GalleryCategory::factory()->create(['slug' => 'dining']);

        $uuid = $this->withToken($token)->postJson('/api/cms/gallery-items', $this->itemPayload($category))
            ->assertStatus(201)
            ->assertJsonPath('data.caption.ar', 'صالة معيشة الجناح الكبير')
            ->assertJsonPath('data.category_slug', 'rooms')
            ->json('data.uuid');

        $this->withToken($token)->getJson("/api/cms/gallery-items/{$uuid}")->assertOk();

        // Reassigning the chip goes through the uuid, never the internal id.
        $this->withToken($token)
            ->putJson("/api/cms/gallery-items/{$uuid}", ['gallery_category_uuid' => $other->uuid])
            ->assertOk()
            ->assertJsonPath('data.category_slug', 'dining');

        $this->withToken($token)->deleteJson("/api/cms/gallery-items/{$uuid}")->assertStatus(204);
        $this->assertDatabaseCount('gallery_items', 0);
    }

    public function test_item_requires_an_existing_category_uuid(): void
    {
        $token = $this->editorToken();

        $this->withToken($token)
            ->postJson('/api/cms/gallery-items', ['caption' => ['en' => 'Orphan', 'ar' => 'يتيم']])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['gallery_category_uuid']);

        $this->withToken($token)
            ->postJson('/api/cms/gallery-items', [
                'gallery_category_uuid' => '11111111-1111-1111-1111-111111111111',
                'caption'               => ['en' => 'Orphan', 'ar' => 'يتيم'],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['gallery_category_uuid']);
    }

    /**
     * A photograph has no meaning outside its chip, so retiring a chip must take
     * its photographs with it — the FK cascades rather than orphaning rows the
     * website can never render.
     */
    public function test_deleting_a_category_cascades_to_its_items(): void
    {
        $category = GalleryCategory::factory()->create();
        $keep     = GalleryCategory::factory()->create();

        GalleryItem::factory()->count(3)->create(['gallery_category_id' => $category->id]);
        GalleryItem::factory()->create(['gallery_category_id' => $keep->id]);

        $this->assertDatabaseCount('gallery_items', 4);

        $this->withToken($this->editorToken())
            ->deleteJson("/api/cms/gallery-categories/{$category->uuid}")
            ->assertStatus(204);

        $this->assertDatabaseCount('gallery_items', 1);
        $this->assertSame(0, GalleryItem::where('gallery_category_id', $category->id)->count());
        $this->assertSame(1, GalleryItem::where('gallery_category_id', $keep->id)->count());
    }

    // ── Auth ──────────────────────────────────────────────────────────

    public function test_unauthenticated_request_is_rejected(): void
    {
        $this->getJson('/api/cms/gallery-categories')->assertStatus(401);
        $this->getJson('/api/cms/gallery-items')->assertStatus(401);
        $this->postJson('/api/cms/gallery-categories', $this->categoryPayload())->assertStatus(401);
    }

    public function test_staff_without_cms_permission_is_forbidden(): void
    {
        $token = User::factory()->create()->createToken('t')->plainTextToken;

        $this->withToken($token)->getJson('/api/cms/gallery-categories')->assertStatus(403);
        $this->withToken($token)->getJson('/api/cms/gallery-items')->assertStatus(403);
        $this->withToken($token)->postJson('/api/cms/gallery-categories', $this->categoryPayload())->assertStatus(403);
    }

    public function test_cms_view_alone_reads_but_cannot_write(): void
    {
        $token    = $this->tokenWith('cms.view');
        $category = GalleryCategory::factory()->create();
        $item     = GalleryItem::factory()->create(['gallery_category_id' => $category->id]);

        $this->withToken($token)->getJson('/api/cms/gallery-categories')->assertOk();
        $this->withToken($token)->getJson("/api/cms/gallery-categories/{$category->uuid}")->assertOk();
        $this->withToken($token)->getJson('/api/cms/gallery-items')->assertOk();
        $this->withToken($token)->getJson("/api/cms/gallery-items/{$item->uuid}")->assertOk();

        $this->withToken($token)->postJson('/api/cms/gallery-categories', $this->categoryPayload())->assertStatus(403);
        $this->withToken($token)->putJson("/api/cms/gallery-categories/{$category->uuid}", ['sort_order' => 2])->assertStatus(403);
        $this->withToken($token)->deleteJson("/api/cms/gallery-categories/{$category->uuid}")->assertStatus(403);
        $this->withToken($token)->postJson('/api/cms/gallery-items', $this->itemPayload($category))->assertStatus(403);
        $this->withToken($token)->putJson("/api/cms/gallery-items/{$item->uuid}", ['sort_order' => 2])->assertStatus(403);
        $this->withToken($token)->deleteJson("/api/cms/gallery-items/{$item->uuid}")->assertStatus(403);
    }

    // ── Validation ────────────────────────────────────────────────────

    public function test_missing_required_locale_fails_validation_with_keyed_errors(): void
    {
        $category = GalleryCategory::factory()->create();

        $this->withToken($this->editorToken())
            ->postJson('/api/cms/gallery-items', $this->itemPayload($category, ['caption' => ['en' => 'English only.']]))
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonStructure(['success', 'message', 'errors', 'request_id'])
            ->assertJsonValidationErrors(['caption.ar']);

        $this->withToken($this->editorToken())
            ->postJson('/api/cms/gallery-categories', $this->categoryPayload(['name' => ['en' => 'Rooms']]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name.ar']);
    }

    public function test_every_configured_locale_round_trips(): void
    {
        $locales  = TranslatableRules::locales();
        $category = GalleryCategory::factory()->create();

        $caption = [];
        foreach ($locales as $locale) {
            $caption[$locale] = "caption-in-{$locale}";
        }

        $this->withToken($this->editorToken())
            ->postJson('/api/cms/gallery-items', $this->itemPayload($category, ['caption' => $caption]))
            ->assertStatus(201);

        $body = $this->getJson('/api/public/gallery')->assertOk()->json('data.items.0.caption');

        foreach ($locales as $locale) {
            $this->assertSame("caption-in-{$locale}", $body[$locale] ?? null, "locale {$locale} did not round-trip");
        }
    }

    // ── Public reads ──────────────────────────────────────────────────

    public function test_public_categories_hide_inactive_chips_and_keep_editor_order(): void
    {
        GalleryCategory::factory()->create(['slug' => 'second', 'sort_order' => 2]);
        GalleryCategory::factory()->create(['slug' => 'first', 'sort_order' => 1]);
        GalleryCategory::factory()->inactive()->create(['slug' => 'hidden', 'sort_order' => 0]);

        $res = $this->getJson('/api/public/gallery-categories')->assertOk();

        $items = $res->json('data.items');
        $this->assertCount(2, $items);
        $this->assertSame(2, $res->json('data.meta.total'));
        // sort_order 1 before sort_order 2 — the hidden chip must not reorder them.
        $this->assertSame('first', $items[0]['slug']);
    }

    public function test_public_gallery_hides_inactive_items(): void
    {
        $category = GalleryCategory::factory()->create();
        GalleryItem::factory()->count(2)->create(['gallery_category_id' => $category->id]);
        GalleryItem::factory()->inactive()->create(['gallery_category_id' => $category->id]);

        $res = $this->getJson('/api/public/gallery')->assertOk();

        $this->assertCount(2, $res->json('data.items'));
        $this->assertSame(2, $res->json('data.meta.total'));
    }

    /**
     * The site groups strictly by chip, so a photograph in a hidden chip has
     * nowhere to render — publishing it anyway would leak a draft section.
     */
    public function test_public_gallery_hides_items_whose_category_is_inactive(): void
    {
        $visible = GalleryCategory::factory()->create(['slug' => 'rooms']);
        $draft   = GalleryCategory::factory()->inactive()->create(['slug' => 'spa']);

        GalleryItem::factory()->create(['gallery_category_id' => $visible->id]);
        GalleryItem::factory()->count(2)->create(['gallery_category_id' => $draft->id]);

        $res = $this->getJson('/api/public/gallery')->assertOk();

        $this->assertCount(1, $res->json('data.items'));
        $this->assertSame('rooms', $res->json('data.items.0.category_slug'));
    }

    public function test_public_gallery_orders_by_category_then_item(): void
    {
        $dining = GalleryCategory::factory()->create(['slug' => 'dining', 'sort_order' => 1]);
        $rooms  = GalleryCategory::factory()->create(['slug' => 'rooms', 'sort_order' => 0]);

        GalleryItem::factory()->create(['gallery_category_id' => $dining->id, 'sort_order' => 0]);
        GalleryItem::factory()->create(['gallery_category_id' => $rooms->id, 'sort_order' => 1]);
        GalleryItem::factory()->create(['gallery_category_id' => $rooms->id, 'sort_order' => 0]);

        $slugs = collect($this->getJson('/api/public/gallery')->assertOk()->json('data.items'))
            ->pluck('category_slug')
            ->all();

        $this->assertSame(['rooms', 'rooms', 'dining'], $slugs);
    }

    public function test_public_reads_are_paginated_and_enveloped(): void
    {
        $category = GalleryCategory::factory()->create();
        GalleryItem::factory()->count(3)->create(['gallery_category_id' => $category->id]);

        foreach (['/api/public/gallery', '/api/public/gallery-categories'] as $url) {
            $this->getJson($url)
                ->assertOk()
                ->assertJsonPath('success', true)
                ->assertJsonStructure([
                    'success', 'message', 'request_id',
                    'data' => ['items', 'meta' => ['current_page', 'per_page', 'total', 'last_page']],
                ]);
        }
    }

    public function test_public_gallery_honours_per_page_and_ignores_cms_filters(): void
    {
        $category = GalleryCategory::factory()->create();
        GalleryItem::factory()->count(3)->create(['gallery_category_id' => $category->id]);
        GalleryItem::factory()->inactive()->count(2)->create(['gallery_category_id' => $category->id]);

        $this->getJson('/api/public/gallery?per_page=2')
            ->assertOk()
            ->assertJsonPath('data.meta.per_page', 2)
            ->assertJsonCount(2, 'data.items');

        // A CMS filter param must not reach the public query and expose drafts.
        $res = $this->getJson('/api/public/gallery?is_active=false')->assertOk();
        $this->assertCount(3, $res->json('data.items'));

        $chips = $this->getJson('/api/public/gallery-categories?is_active=false')->assertOk();
        $this->assertCount(1, $chips->json('data.items'));
    }

    // ── CMS filtering ─────────────────────────────────────────────────

    public function test_cms_item_index_filters_by_category_slug_and_active(): void
    {
        $token  = $this->editorToken();
        $rooms  = GalleryCategory::factory()->create(['slug' => 'rooms']);
        $dining = GalleryCategory::factory()->create(['slug' => 'dining']);

        GalleryItem::factory()->count(2)->create(['gallery_category_id' => $rooms->id]);
        GalleryItem::factory()->inactive()->create(['gallery_category_id' => $rooms->id]);
        GalleryItem::factory()->create(['gallery_category_id' => $dining->id]);

        $inRooms = $this->withToken($token)->getJson('/api/cms/gallery-items?category=rooms')->assertOk();
        $this->assertCount(3, $inRooms->json('data.items'));

        $active = $this->withToken($token)->getJson('/api/cms/gallery-items?is_active=true')->assertOk();
        $this->assertCount(3, $active->json('data.items'));

        $both = $this->withToken($token)->getJson('/api/cms/gallery-items?category=rooms&is_active=true')->assertOk();
        $this->assertCount(2, $both->json('data.items'));
    }

    public function test_cms_item_index_searches_translated_captions(): void
    {
        $category = GalleryCategory::factory()->create();
        GalleryItem::factory()->create(['gallery_category_id' => $category->id, 'caption' => ['en' => 'Rooftop lounge with glass ceiling', 'ar' => 'صالة على السطح']]);
        GalleryItem::factory()->create(['gallery_category_id' => $category->id, 'caption' => ['en' => 'Marble double vanity', 'ar' => 'مغسلة رخامية']]);

        $found = $this->withToken($this->editorToken())->getJson('/api/cms/gallery-items?search=rooftop')->assertOk();

        $this->assertCount(1, $found->json('data.items'));
        $this->assertSame('Rooftop lounge with glass ceiling', $found->json('data.items.0.caption.en'));
    }

    /**
     * Uninterpretable filter values must be an error, not a silent guess — a
     * caller cannot otherwise tell a typo from a real empty result.
     */
    public function test_unparseable_is_active_is_rejected_rather_than_guessed(): void
    {
        $category = GalleryCategory::factory()->create();
        GalleryItem::factory()->count(2)->create(['gallery_category_id' => $category->id]);

        $this->withToken($this->editorToken())
            ->getJson('/api/cms/gallery-items?is_active=trve')
            ->assertStatus(422);

        // An empty value means "no filter", not "false".
        $res = $this->withToken($this->editorToken())
            ->getJson('/api/cms/gallery-items?is_active=')
            ->assertOk();
        $this->assertCount(2, $res->json('data.items'));
    }

    // ── Media ─────────────────────────────────────────────────────────

    public function test_admin_can_upload_and_expose_a_gallery_photograph(): void
    {
        Storage::fake('public');
        $item = GalleryItem::factory()->create();

        $this->withToken($this->editorToken())
            ->postJson("/api/cms/gallery-items/{$item->uuid}/images", [
                'image' => UploadedFile::fake()->image('suite.jpg'),
            ])
            ->assertStatus(201);

        $this->assertDatabaseCount('media', 1);
        $this->assertNotNull(
            $this->getJson('/api/public/gallery')->assertOk()->json('data.items.0.image')
        );
    }

    /**
     * Media deletion is scoped to the parent named in the route — a gallery
     * item's route must not be able to delete a promotion's image.
     */
    public function test_media_cannot_be_deleted_through_the_wrong_parent(): void
    {
        Storage::fake('public');
        $item      = GalleryItem::factory()->create();
        $promotion = Promotion::factory()->create();

        $this->withToken($this->editorToken())
            ->postJson("/api/cms/promotions/{$promotion->uuid}/images", [
                'image' => UploadedFile::fake()->image('promo.jpg'),
            ])->assertStatus(201);

        $media = Media::firstOrFail();

        $this->withToken($this->editorToken())
            ->deleteJson("/api/cms/gallery-items/{$item->uuid}/images/{$media->uuid}")
            ->assertNotFound();

        $this->assertDatabaseCount('media', 1);
    }

    // ── Seeded copy ───────────────────────────────────────────────────

    /**
     * The seeder carries hand-written trilingual captions and chip labels that no
     * factory exercises. Without this, a typo in them only surfaces when someone
     * runs `migrate --seed` — and by then it has broken their database.
     */
    public function test_seeder_loads_the_real_site_copy(): void
    {
        Storage::fake('public');
        $this->seed(\Database\Seeders\CmsContentSeeder::class);

        $this->assertSame(
            ['rooms', 'dining', 'lobby', 'damascus'],
            GalleryCategory::orderBy('sort_order')->pluck('slug')->all(),
            'chip order must match the site\'s categoryLabels',
        );
        $this->assertSame(19, GalleryItem::count(), 'expected the nineteen photographs the site ships');

        $rooms = GalleryCategory::where('slug', 'rooms')->firstOrFail();
        $this->assertSame(7, $rooms->items()->count());

        foreach (['en', 'ar', 'fr'] as $locale) {
            $this->assertNotEmpty(
                $rooms->getTranslation('name', $locale, false),
                "seeded chip is missing its {$locale} name",
            );
        }
        $this->assertSame('Chambres', $rooms->getTranslation('name', 'fr', false));

        $first = $rooms->items()->orderBy('sort_order')->first();

        foreach (['en', 'ar', 'fr'] as $locale) {
            $this->assertNotEmpty(
                $first->getTranslation('caption', $locale, false),
                "seeded photograph is missing its {$locale} caption",
            );
        }

        // Distinct per locale — not the same string copied three times.
        $this->assertNotSame(
            $first->getTranslation('caption', 'en', false),
            $first->getTranslation('caption', 'fr', false),
        );

        // Each photograph is captioned by what it actually shows. The site pairs
        // captions by index and is five places off for Lobby and Damascus (see
        // CmsContentSeeder::gallery()); a lobby tile must not read "indoor pool".
        $lobby = GalleryCategory::where('slug', 'lobby')->firstOrFail();
        foreach ($lobby->items as $item) {
            $this->assertStringNotContainsStringIgnoringCase(
                'pool',
                $item->getTranslation('caption', 'en', false),
                'a Lobby photograph is carrying a retired pool caption',
            );
        }

        $this->getJson('/api/public/gallery?per_page=100')
            ->assertOk()
            ->assertJsonCount(19, 'data.items');

        $this->getJson('/api/public/gallery-categories')
            ->assertOk()
            ->assertJsonCount(4, 'data.items');
    }
}
