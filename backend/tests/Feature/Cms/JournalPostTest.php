<?php

namespace Tests\Feature\Cms;

use App\Models\JournalPost;
use App\Models\User;
use App\Support\TranslatableRules;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class JournalPostTest extends TestCase
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
            'slug'         => 'a-jasmine-courtyard-in-winter',
            'title'        => ['en' => 'A Jasmine Courtyard in Winter', 'ar' => 'فناء الياسمين في الشتاء'],
            'excerpt'      => ['en' => 'Keeping the courtyard in bloom.', 'ar' => 'الحفاظ على إزهار الفناء.'],
            'body'         => ['en' => 'The jasmine is not a winter plant.', 'ar' => 'الياسمين ليس نبات شتاء.'],
            'category'     => ['en' => 'The Hotel', 'ar' => 'الفندق'],
            'published_on' => '2026-01-18',
            'is_active'    => true,
        ], $overrides);
    }

    public function test_admin_can_crud_journal_post(): void
    {
        $token = $this->editorToken();

        $uuid = $this->withToken($token)->postJson('/api/cms/journal-posts', $this->payload())
            ->assertStatus(201)
            ->assertJsonPath('data.title.ar', 'فناء الياسمين في الشتاء')
            ->assertJsonPath('data.slug', 'a-jasmine-courtyard-in-winter')
            ->assertJsonPath('data.published_on', '2026-01-18')
            ->json('data.uuid');

        $this->withToken($token)->getJson("/api/cms/journal-posts/{$uuid}")->assertOk();

        $this->withToken($token)
            ->putJson("/api/cms/journal-posts/{$uuid}", ['excerpt' => ['en' => 'Six weeks of work.', 'ar' => 'ستة أسابيع من العمل.']])
            ->assertOk()
            ->assertJsonPath('data.excerpt.en', 'Six weeks of work.');

        $this->withToken($token)->deleteJson("/api/cms/journal-posts/{$uuid}")->assertStatus(204);
        // Recoverable now: the row stays, marked, and vanishes from every query.
        $this->assertSoftDeleted('journal_posts', ['uuid' => $uuid]);
    }

    public function test_unauthenticated_request_is_rejected(): void
    {
        $this->getJson('/api/cms/journal-posts')->assertStatus(401);
        $this->postJson('/api/cms/journal-posts', $this->payload())->assertStatus(401);
    }

    public function test_staff_without_cms_permission_is_forbidden(): void
    {
        $token = User::factory()->create()->createToken('t')->plainTextToken;

        $this->withToken($token)->getJson('/api/cms/journal-posts')->assertStatus(403);
        $this->withToken($token)->postJson('/api/cms/journal-posts', $this->payload())->assertStatus(403);
    }

    public function test_cms_view_alone_reads_but_cannot_write(): void
    {
        $token = $this->tokenWith('cms.view');
        $post  = JournalPost::factory()->create();

        $this->withToken($token)->getJson('/api/cms/journal-posts')->assertOk();
        $this->withToken($token)->getJson("/api/cms/journal-posts/{$post->uuid}")->assertOk();

        $this->withToken($token)->postJson('/api/cms/journal-posts', $this->payload())->assertStatus(403);
        $this->withToken($token)->putJson("/api/cms/journal-posts/{$post->uuid}", ['sort_order' => 3])->assertStatus(403);
        $this->withToken($token)->deleteJson("/api/cms/journal-posts/{$post->uuid}")->assertStatus(403);
    }

    public function test_missing_required_locale_fails_validation_with_keyed_errors(): void
    {
        $this->withToken($this->editorToken())
            ->postJson('/api/cms/journal-posts', $this->payload(['body' => ['en' => 'English only.']]))
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonStructure(['success', 'message', 'errors', 'request_id'])
            ->assertJsonValidationErrors(['body.ar']);
    }

    public function test_title_body_slug_and_date_are_required_on_create(): void
    {
        $this->withToken($this->editorToken())
            ->postJson('/api/cms/journal-posts', ['is_active' => true])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['slug', 'published_on', 'title.en', 'title.ar', 'body.en', 'body.ar']);
    }

    public function test_slug_must_be_unique_and_url_shaped(): void
    {
        $token = $this->editorToken();
        $this->withToken($token)->postJson('/api/cms/journal-posts', $this->payload())->assertStatus(201);

        $this->withToken($token)
            ->postJson('/api/cms/journal-posts', $this->payload(['title' => ['en' => 'Other', 'ar' => 'آخر']]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['slug']);

        $this->withToken($token)
            ->postJson('/api/cms/journal-posts', $this->payload(['slug' => 'Not A Slug!']))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['slug']);
    }

    public function test_every_configured_locale_round_trips(): void
    {
        $locales = TranslatableRules::locales();

        $body = [];
        foreach ($locales as $locale) {
            $body[$locale] = "body-in-{$locale}";
        }

        $this->withToken($this->editorToken())
            ->postJson('/api/cms/journal-posts', $this->payload(['body' => $body]))
            ->assertStatus(201);

        $returned = $this->getJson('/api/public/journal')->assertOk()->json('data.items.0.body');

        foreach ($locales as $locale) {
            $this->assertSame("body-in-{$locale}", $returned[$locale] ?? null, "locale {$locale} did not round-trip");
        }
    }

    // ── Public endpoints ──────────────────────────────────────────────────

    public function test_public_index_hides_inactive_posts(): void
    {
        JournalPost::factory()->count(2)->create();
        JournalPost::factory()->inactive()->count(3)->create();

        $res = $this->getJson('/api/public/journal')->assertOk();

        $this->assertCount(2, $res->json('data.items'));
        $this->assertSame(2, $res->json('data.meta.total'));
    }

    public function test_public_show_404s_an_inactive_post(): void
    {
        $draft = JournalPost::factory()->inactive()->create(['slug' => 'a-draft-post']);

        $this->getJson("/api/public/journal/{$draft->slug}")
            ->assertStatus(404)
            ->assertJsonPath('success', false);
    }

    public function test_public_show_resolves_by_slug_not_uuid(): void
    {
        $post = JournalPost::factory()->create(['slug' => 'chef-karim-on-spring']);

        $this->getJson('/api/public/journal/chef-karim-on-spring')
            ->assertOk()
            ->assertJsonPath('data.slug', 'chef-karim-on-spring')
            ->assertJsonPath('data.uuid', $post->uuid);

        // The uuid is the CMS's key, not the website's — it must not resolve here.
        $this->getJson("/api/public/journal/{$post->uuid}")->assertStatus(404);
    }

    public function test_public_index_orders_by_published_on_descending(): void
    {
        JournalPost::factory()->publishedOn('2026-01-01')->create(['slug' => 'oldest']);
        JournalPost::factory()->publishedOn('2026-06-01')->create(['slug' => 'newest']);
        JournalPost::factory()->publishedOn('2026-03-01')->create(['slug' => 'middle']);

        $slugs = collect($this->getJson('/api/public/journal')->assertOk()->json('data.items'))
            ->pluck('slug')
            ->all();

        $this->assertSame(['newest', 'middle', 'oldest'], $slugs);
    }

    /**
     * THE REGRESSION GUARD FOR THE DISPLAY-DATE DECISION.
     *
     * `published_on` is editorial metadata, not a schedule. If someone adds
     * `where('published_on', '<=', now())` to the public query — which reads
     * like an obvious improvement — a post an editor dated next month silently
     * disappears from the website with no error anywhere. This test is the only
     * thing that makes that change loud.
     */
    public function test_future_dated_active_post_is_still_returned_publicly(): void
    {
        $future = JournalPost::factory()->futureDated()->create(['slug' => 'dated-next-month']);
        JournalPost::factory()->publishedOn('2026-01-01')->create(['slug' => 'dated-in-january']);

        $items = $this->getJson('/api/public/journal')->assertOk()->json('data.items');

        $this->assertCount(2, $items, 'a future-dated active post must not be filtered out of the public index');
        // It is also first, because the list is ordered by the date descending.
        $this->assertSame('dated-next-month', $items[0]['slug']);

        // …and reachable on its own URL, not merely counted in the list.
        $this->getJson("/api/public/journal/{$future->slug}")
            ->assertOk()
            ->assertJsonPath('data.published_on', $future->published_on->toDateString());
    }

    public function test_public_index_is_paginated_and_enveloped(): void
    {
        JournalPost::factory()->count(3)->create();

        $this->getJson('/api/public/journal')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'success', 'message', 'request_id',
                'data' => ['items', 'meta' => ['current_page', 'per_page', 'total', 'last_page']],
            ]);
    }

    public function test_public_index_honours_per_page_and_ignores_cms_filters(): void
    {
        JournalPost::factory()->count(3)->create();
        JournalPost::factory()->inactive()->count(2)->create();

        $this->getJson('/api/public/journal?per_page=2')
            ->assertOk()
            ->assertJsonPath('data.meta.per_page', 2)
            ->assertJsonCount(2, 'data.items');

        // A CMS filter param must not reach the public query and expose drafts.
        $res = $this->getJson('/api/public/journal?is_active=false')->assertOk();
        $this->assertCount(3, $res->json('data.items'));

        // Nor may a date filter — that would be the scheduling feature by the
        // back door, driven by the client instead of the server.
        $res = $this->getJson('/api/public/journal?published_on[lte]=1999-01-01')->assertOk();
        $this->assertCount(3, $res->json('data.items'));
    }

    // ── CMS list screen ───────────────────────────────────────────────────

    public function test_cms_index_filters_by_active_and_searches_translated_copy(): void
    {
        $token = $this->editorToken();
        JournalPost::factory()->create(['slug' => 'a', 'title' => ['en' => 'Jasmine in Winter', 'ar' => 'الياسمين شتاءً']]);
        JournalPost::factory()->create(['slug' => 'b', 'title' => ['en' => 'Spring Menu', 'ar' => 'قائمة الربيع']]);
        JournalPost::factory()->inactive()->create(['slug' => 'c', 'title' => ['en' => 'Jasmine Draft', 'ar' => 'مسودة الياسمين']]);

        $active = $this->withToken($token)->getJson('/api/cms/journal-posts?is_active=true')->assertOk();
        $this->assertCount(2, $active->json('data.items'));

        $search = $this->withToken($token)->getJson('/api/cms/journal-posts?search=jasmine')->assertOk();
        $this->assertCount(2, $search->json('data.items'), 'the CMS list sees drafts');

        $both = $this->withToken($token)->getJson('/api/cms/journal-posts?search=jasmine&is_active=true')->assertOk();
        $this->assertCount(1, $both->json('data.items'));
    }

    public function test_cms_index_may_narrow_by_published_on_range(): void
    {
        $token = $this->editorToken();
        JournalPost::factory()->publishedOn('2026-01-01')->create(['slug' => 'jan']);
        JournalPost::factory()->publishedOn('2026-06-01')->create(['slug' => 'jun']);

        $res = $this->withToken($token)->getJson('/api/cms/journal-posts?published_on[gte]=2026-05-01')->assertOk();

        $this->assertCount(1, $res->json('data.items'));
        $this->assertSame('jun', $res->json('data.items.0.slug'));
    }

    // ── Cover image ───────────────────────────────────────────────────────

    /**
     * The cover is the first image on the morph, so the upload route is what
     * makes `cover_image` non-null — and the delete route is scoped to the post
     * it names, not to any media row a caller can guess a uuid for.
     */
    public function test_cover_image_uploads_and_deletes_through_the_post_it_belongs_to(): void
    {
        Storage::fake('public');

        $token = $this->editorToken();
        $post  = JournalPost::factory()->create(['slug' => 'a-post-with-a-cover']);
        $other = JournalPost::factory()->create(['slug' => 'another-post']);

        $mediaUuid = $this->withToken($token)
            ->postJson("/api/cms/journal-posts/{$post->uuid}/images", ['image' => UploadedFile::fake()->image('cover.jpg')])
            ->assertStatus(201)
            ->json('data.uuid');

        $this->getJson("/api/public/journal/{$post->slug}")
            ->assertOk()
            ->assertJsonPath('data.cover_image', fn ($url) => is_string($url) && $url !== '')
            ->assertJsonCount(1, 'data.images');

        // Deleting through a post that does not own the image must fail, and the
        // image must survive it.
        $this->withToken($token)
            ->deleteJson("/api/cms/journal-posts/{$other->uuid}/images/{$mediaUuid}")
            ->assertStatus(404);
        $this->assertDatabaseHas('media', ['uuid' => $mediaUuid]);

        $this->withToken($token)
            ->deleteJson("/api/cms/journal-posts/{$post->uuid}/images/{$mediaUuid}")
            ->assertStatus(204);
        $this->assertDatabaseMissing('media', ['uuid' => $mediaUuid]);
    }

    /**
     * The seeder carries hand-written trilingual editorial copy that no factory
     * exercises. Without this, a typo in it only surfaces when someone runs
     * `migrate --seed` — and by then it has broken their database, not a test.
     *
     * It also pins the three articles to the ones the live site shows under
     * `news.items`. If someone reseeds this module with invented articles, the
     * Phase 6 swap would silently replace the hotel's real headlines with
     * fiction, and nothing else would catch it.
     */
    public function test_seeder_loads_the_real_site_copy(): void
    {
        $this->seed(\Database\Seeders\CmsContentSeeder::class);

        // Three real site articles, one future-dated fixture, one draft.
        $this->assertSame(5, JournalPost::count());

        // The exact headlines the public website renders today.
        foreach ([
            'new-lighting-design-refreshes-the-lobby'  => 'New Lighting Design Refreshes The Lobby',
            'restoring-carltons-heritage-facade'       => "Restoring Carlton's Heritage Façade",
            'a-new-look-for-our-garden-lounge'         => 'A New Look for Our Garden Lounge',
        ] as $slug => $englishTitle) {
            $post = JournalPost::where('slug', $slug)->first();
            $this->assertNotNull($post, "the site's article '{$slug}' is not seeded");
            $this->assertSame($englishTitle, $post->getTranslation('title', 'en', false));

            // en/ar/fr all come from the site's own translations.
            foreach (['en', 'ar', 'fr'] as $locale) {
                $this->assertNotEmpty(
                    $post->getTranslation('title', $locale, false),
                    "seeded article {$slug} is missing its {$locale} title",
                );
                $this->assertNotEmpty(
                    $post->getTranslation('body', $locale, false),
                    "seeded article {$slug} is missing its {$locale} body",
                );
            }
        }

        $first = JournalPost::where('slug', 'new-lighting-design-refreshes-the-lobby')->firstOrFail();

        // Distinct per locale — not one string copied three times.
        $this->assertNotSame(
            $first->getTranslation('title', 'en', false),
            $first->getTranslation('title', 'ar', false),
        );
        $this->assertNotSame(
            $first->getTranslation('title', 'en', false),
            $first->getTranslation('title', 'fr', false),
        );

        // The cover image morph is wired: the seeder attaches one placeholder.
        $this->assertNotNull($first->images->first());

        $items = $this->getJson('/api/public/journal?per_page=100')->assertOk()->json('data.items');

        // The draft is hidden; the four published posts are not.
        $this->assertCount(4, $items);

        // …including the one the seeder deliberately dated next month, which is
        // therefore first in the descending order.
        $this->assertSame('the-carlton-standard-a-note-on-arrivals', $items[0]['slug']);
        $this->assertTrue(
            $items[0]['published_on'] > now()->toDateString(),
            'the seeded future-dated post must keep its future date',
        );
        $this->assertNotNull($items[0]['cover_image']);
    }
}
