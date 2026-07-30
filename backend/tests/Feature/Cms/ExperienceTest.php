<?php

namespace Tests\Feature\Cms;

use App\Models\Experience;
use App\Models\Media;
use App\Models\Promotion;
use App\Models\User;
use App\Support\TranslatableRules;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ExperienceTest extends TestCase
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

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'slug'             => 'rooftop-dawn',
            'title'            => ['en' => 'Rooftop Dawn', 'ar' => 'سطح الفجر'],
            'description'      => ['en' => 'The rooftop, reserved for you at first light.', 'ar' => 'السطح محجوز لك عند الفجر.'],
            'category'         => 'privilege',
            'duration_minutes' => 120,
            'is_active'        => true,
        ], $overrides);
    }

    public function test_admin_can_crud_experience(): void
    {
        $token = $this->editorToken();

        $uuid = $this->withToken($token)->postJson('/api/cms/experiences', $this->payload())
            ->assertStatus(201)
            ->assertJsonPath('data.title.ar', 'سطح الفجر')
            ->assertJsonPath('data.category', 'privilege')
            ->json('data.uuid');

        $this->withToken($token)->getJson("/api/cms/experiences/{$uuid}")->assertOk();

        $this->withToken($token)
            ->putJson("/api/cms/experiences/{$uuid}", ['duration_minutes' => 180, 'price_usd' => '250.00'])
            ->assertOk()
            ->assertJsonPath('data.duration_minutes', 180)
            ->assertJsonPath('data.price_usd', '250.00');

        $this->withToken($token)->deleteJson("/api/cms/experiences/{$uuid}")->assertStatus(204);
        $this->assertDatabaseCount('experiences', 0);
    }

    public function test_unauthenticated_request_is_rejected(): void
    {
        $this->getJson('/api/cms/experiences')->assertStatus(401);
        $this->postJson('/api/cms/experiences', $this->payload())->assertStatus(401);
    }

    public function test_staff_without_cms_permission_is_forbidden(): void
    {
        $token = User::factory()->create()->createToken('t')->plainTextToken;

        $this->withToken($token)->getJson('/api/cms/experiences')->assertStatus(403);
        $this->withToken($token)->postJson('/api/cms/experiences', $this->payload())->assertStatus(403);
    }

    public function test_cms_view_alone_reads_but_cannot_write(): void
    {
        $token = $this->tokenWith('cms.view');
        $experience = Experience::factory()->create();

        $this->withToken($token)->getJson('/api/cms/experiences')->assertOk();
        $this->withToken($token)->getJson("/api/cms/experiences/{$experience->uuid}")->assertOk();

        $this->withToken($token)->postJson('/api/cms/experiences', $this->payload())->assertStatus(403);
        $this->withToken($token)->putJson("/api/cms/experiences/{$experience->uuid}", ['sort_order' => 3])->assertStatus(403);
        $this->withToken($token)->deleteJson("/api/cms/experiences/{$experience->uuid}")->assertStatus(403);
    }

    public function test_missing_required_locale_fails_validation_with_keyed_errors(): void
    {
        $this->withToken($this->editorToken())
            ->postJson('/api/cms/experiences', $this->payload(['description' => ['en' => 'English only.']]))
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonStructure(['success', 'message', 'errors', 'request_id'])
            ->assertJsonValidationErrors(['description.ar']);
    }

    public function test_slug_title_description_and_category_are_required_on_create(): void
    {
        $this->withToken($this->editorToken())
            ->postJson('/api/cms/experiences', ['is_active' => true])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['slug', 'category', 'title.en', 'title.ar', 'description.en', 'description.ar']);
    }

    public function test_slug_must_be_unique_and_url_shaped(): void
    {
        $token = $this->editorToken();
        Experience::factory()->create(['slug' => 'taken-slug']);

        $this->withToken($token)
            ->postJson('/api/cms/experiences', $this->payload(['slug' => 'taken-slug']))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['slug']);

        $this->withToken($token)
            ->postJson('/api/cms/experiences', $this->payload(['slug' => 'Not A Slug']))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['slug']);
    }

    /**
     * Re-saving a record without touching its slug must not collide with itself —
     * the `unique` rule has to exempt the row being updated.
     */
    public function test_updating_a_record_may_keep_its_own_slug(): void
    {
        $experience = Experience::factory()->create(['slug' => 'qasioun-sunset']);

        $this->withToken($this->editorToken())
            ->putJson("/api/cms/experiences/{$experience->uuid}", ['slug' => 'qasioun-sunset', 'sort_order' => 4])
            ->assertOk()
            ->assertJsonPath('data.slug', 'qasioun-sunset');
    }

    public function test_every_configured_locale_round_trips(): void
    {
        $locales = TranslatableRules::locales();

        $description = [];
        foreach ($locales as $locale) {
            $description[$locale] = "description-in-{$locale}";
        }

        $this->withToken($this->editorToken())
            ->postJson('/api/cms/experiences', $this->payload(['description' => $description]))
            ->assertStatus(201);

        $body = $this->getJson('/api/public/experiences')->assertOk()->json('data.items.0.description');

        foreach ($locales as $locale) {
            $this->assertSame("description-in-{$locale}", $body[$locale] ?? null, "locale {$locale} did not round-trip");
        }
    }

    public function test_public_index_hides_inactive_experiences_and_keeps_editor_order(): void
    {
        Experience::factory()->create(['sort_order' => 2]);
        Experience::factory()->create(['sort_order' => 1]);
        Experience::factory()->inactive()->create(['sort_order' => 0]);

        $res = $this->getJson('/api/public/experiences')->assertOk();

        $items = $res->json('data.items');
        $this->assertCount(2, $items);
        $this->assertSame(2, $res->json('data.meta.total'));
        // sort_order 1 before sort_order 2 — the hidden row must not reorder them.
        $this->assertSame(
            Experience::where('sort_order', 1)->value('uuid'),
            $items[0]['uuid'],
        );
    }

    public function test_public_index_is_paginated_and_enveloped(): void
    {
        Experience::factory()->count(3)->create();

        $this->getJson('/api/public/experiences')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'success', 'message', 'request_id',
                'data' => ['items', 'meta' => ['current_page', 'per_page', 'total', 'last_page']],
            ]);
    }

    public function test_public_index_honours_per_page_and_ignores_cms_filters(): void
    {
        Experience::factory()->count(3)->create();
        Experience::factory()->inactive()->count(2)->create();

        $this->getJson('/api/public/experiences?per_page=2')
            ->assertOk()
            ->assertJsonPath('data.meta.per_page', 2)
            ->assertJsonCount(2, 'data.items');

        // A CMS filter param must not reach the public query and expose drafts.
        $res = $this->getJson('/api/public/experiences?is_active=false')->assertOk();
        $this->assertCount(3, $res->json('data.items'));
    }

    public function test_public_show_returns_a_published_experience(): void
    {
        $experience = Experience::factory()->create(['slug' => 'bab-sharqi']);

        $this->getJson("/api/public/experiences/{$experience->uuid}")
            ->assertOk()
            ->assertJsonPath('data.slug', 'bab-sharqi');
    }

    /**
     * A draft's uuid must not be a working preview link — the public detail route
     * has to 404 on it, not merely omit it from the list.
     */
    public function test_public_show_404s_on_a_draft_experience(): void
    {
        $experience = Experience::factory()->inactive()->create();

        $this->getJson("/api/public/experiences/{$experience->uuid}")->assertStatus(404);
    }

    public function test_cms_index_filters_by_category_active_and_duration(): void
    {
        $token = $this->editorToken();
        Experience::factory()->create(['category' => 'culture', 'duration_minutes' => 120]);
        Experience::factory()->create(['category' => 'gastronomy', 'duration_minutes' => 300]);
        Experience::factory()->inactive()->create(['category' => 'culture', 'duration_minutes' => 240]);

        $culture = $this->withToken($token)->getJson('/api/cms/experiences?category=culture')->assertOk();
        $this->assertCount(2, $culture->json('data.items'));

        $active = $this->withToken($token)->getJson('/api/cms/experiences?is_active=true')->assertOk();
        $this->assertCount(2, $active->json('data.items'));

        $both = $this->withToken($token)->getJson('/api/cms/experiences?category=culture&is_active=true')->assertOk();
        $this->assertCount(1, $both->json('data.items'));

        $long = $this->withToken($token)->getJson('/api/cms/experiences?duration_minutes[gte]=240')->assertOk();
        $this->assertCount(2, $long->json('data.items'));
    }

    public function test_cms_index_searches_translated_titles(): void
    {
        $token = $this->editorToken();
        Experience::factory()->create(['slug' => 'meze-masterclass', 'title' => ['en' => 'Syrian Meze Masterclass', 'ar' => 'تحضير المازة السورية']]);
        Experience::factory()->create(['slug' => 'in-suite-cinema', 'title' => ['en' => 'In-Suite Cinema Evening', 'ar' => 'أمسية سينما']]);

        $found = $this->withToken($token)->getJson('/api/cms/experiences?search=meze')->assertOk();
        $this->assertCount(1, $found->json('data.items'));
        $this->assertSame('meze-masterclass', $found->json('data.items.0.slug'));
    }

    public function test_admin_can_upload_and_expose_an_image(): void
    {
        Storage::fake('public');
        $experience = Experience::factory()->create();

        $this->withToken($this->editorToken())
            ->postJson("/api/cms/experiences/{$experience->uuid}/images", [
                'image' => UploadedFile::fake()->image('spice.jpg'),
            ])
            ->assertStatus(201);

        $this->assertDatabaseCount('media', 1);

        // Proves the request reached `MediaController::storeExperience()` with the
        // route's `Experience` resolved, not merely that something returned 201.
        $media = Media::firstOrFail();
        $this->assertSame($experience->getMorphClass(), $media->mediable_type);
        $this->assertSame($experience->id, $media->mediable_id);

        $this->assertNotNull(
            $this->getJson('/api/public/experiences')->assertOk()->json('data.items.0.image')
        );
    }

    /**
     * Media deletion is scoped to the parent named in the route — an experience's
     * route must not be able to delete a promotion's image.
     */
    public function test_media_cannot_be_deleted_through_the_wrong_parent(): void
    {
        Storage::fake('public');
        $experience = Experience::factory()->create();
        $promotion  = Promotion::factory()->create();

        $this->withToken($this->editorToken())
            ->postJson("/api/cms/promotions/{$promotion->uuid}/images", [
                'image' => UploadedFile::fake()->image('promo.jpg'),
            ])->assertStatus(201);

        $media = Media::firstOrFail();

        $this->withToken($this->editorToken())
            ->deleteJson("/api/cms/experiences/{$experience->uuid}/images/{$media->uuid}")
            ->assertNotFound();

        $this->assertDatabaseCount('media', 1);
    }

    /**
     * Uninterpretable filter values must be an error, not a silent guess — a
     * caller cannot otherwise tell a typo from a real empty result.
     */
    public function test_unparseable_filter_values_are_rejected_rather_than_guessed(): void
    {
        Experience::factory()->count(2)->create();

        $this->withToken($this->editorToken())
            ->getJson('/api/cms/experiences?is_active=trve')
            ->assertStatus(422);

        $this->withToken($this->editorToken())
            ->getJson('/api/cms/experiences?duration_minutes[gte]=two-hours')
            ->assertStatus(422);

        // An empty value means "no filter", not "false".
        $res = $this->withToken($this->editorToken())
            ->getJson('/api/cms/experiences?is_active=')
            ->assertOk();
        $this->assertCount(2, $res->json('data.items'));
    }

    /**
     * The seeder carries hand-written trilingual marketing copy that no factory
     * exercises. Without this, a typo in it only surfaces when someone runs
     * `migrate --seed` — and by then it has broken their database, not a test.
     */
    public function test_seeder_loads_the_real_site_copy(): void
    {
        Storage::fake('public');
        $this->seed(\Database\Seeders\CmsContentSeeder::class);

        $this->assertSame(12, Experience::count(), 'expected the twelve experiences the site hardcodes');

        $first = Experience::orderBy('sort_order')->first();
        $this->assertSame('spice-journey', $first->slug, 'seeded order must match the site\'s experienceMeta');
        $this->assertSame('gastronomy', $first->category);
        // The upper bound of the site's "2–3 hours", not a rounded guess.
        $this->assertSame(180, $first->duration_minutes);
        $this->assertNull($first->price_usd, 'the site publishes no prices — none may be invented');

        // en/ar/fr are all seeded from real translations; tr/es are left to editors.
        foreach (['en', 'ar', 'fr'] as $locale) {
            $this->assertNotEmpty(
                $first->getTranslation('title', $locale, false),
                "seeded experience is missing its {$locale} title",
            );
            $this->assertNotEmpty(
                $first->getTranslation('description', $locale, false),
                "seeded experience is missing its {$locale} description",
            );
        }

        // Distinct per locale — not the same string copied three times.
        $this->assertNotSame(
            $first->getTranslation('description', 'en', false),
            $first->getTranslation('description', 'fr', false),
        );

        // Every category the site's filter chips offer is present, as a stable
        // lowercase key rather than a translated label.
        $this->assertSame(
            ['culture', 'gastronomy', 'privilege'],
            Experience::query()->distinct()->orderBy('category')->pluck('category')->all(),
        );

        // Slugs are the site's own ids, so a content migration can match records.
        $this->assertContains('umayyad-mosque', Experience::pluck('slug')->all());

        $this->getJson('/api/public/experiences?per_page=100')
            ->assertOk()
            ->assertJsonCount(12, 'data.items');
    }
}
