<?php

namespace Tests\Feature\Cms;

use App\Models\Media;
use App\Models\Promotion;
use App\Models\Testimonial;
use App\Models\User;
use App\Support\TranslatableRules;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TestimonialTest extends TestCase
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
            'author_name'  => 'Sarah & James M.',
            'author_title' => ['en' => 'London, United Kingdom', 'ar' => 'لندن، المملكة المتحدة'],
            'quote'        => ['en' => 'Every detail felt considered.', 'ar' => 'كانت كل تفصيلة مدروسة.'],
            'rating'       => 5,
            'is_active'    => true,
        ], $overrides);
    }

    public function test_admin_can_crud_testimonial(): void
    {
        $token = $this->editorToken();

        $uuid = $this->withToken($token)->postJson('/api/cms/testimonials', $this->payload())
            ->assertStatus(201)
            ->assertJsonPath('data.author_name', 'Sarah & James M.')
            ->assertJsonPath('data.quote.ar', 'كانت كل تفصيلة مدروسة.')
            ->json('data.uuid');

        $this->withToken($token)->getJson("/api/cms/testimonials/{$uuid}")->assertOk();

        $this->withToken($token)
            ->putJson("/api/cms/testimonials/{$uuid}", ['quote' => ['en' => 'Reworded.', 'ar' => 'أعيدت صياغته.']])
            ->assertOk()
            ->assertJsonPath('data.quote.en', 'Reworded.');

        $this->withToken($token)->deleteJson("/api/cms/testimonials/{$uuid}")->assertStatus(204);
        $this->assertDatabaseCount('testimonials', 0);
    }

    public function test_unauthenticated_request_is_rejected(): void
    {
        Testimonial::factory()->create();

        $this->getJson('/api/cms/testimonials')->assertStatus(401);
        $this->postJson('/api/cms/testimonials', $this->payload())->assertStatus(401);
    }

    public function test_staff_without_cms_permission_cannot_read_or_write(): void
    {
        $token = User::factory()->create()->createToken('t')->plainTextToken;

        $this->withToken($token)->getJson('/api/cms/testimonials')->assertStatus(403);
        $this->withToken($token)->postJson('/api/cms/testimonials', $this->payload())->assertStatus(403);
    }

    /**
     * `cms.view` is a genuine read-only grant, not a decorative permission —
     * it reads and is refused every write verb.
     */
    public function test_cms_view_alone_reads_but_cannot_write(): void
    {
        $token = $this->tokenWith('cms.view');
        $testimonial = Testimonial::factory()->create();

        $this->withToken($token)->getJson('/api/cms/testimonials')->assertOk();
        $this->withToken($token)->getJson("/api/cms/testimonials/{$testimonial->uuid}")->assertOk();

        $this->withToken($token)->postJson('/api/cms/testimonials', $this->payload())->assertStatus(403);
        $this->withToken($token)->putJson("/api/cms/testimonials/{$testimonial->uuid}", ['rating' => 4])->assertStatus(403);
        $this->withToken($token)->deleteJson("/api/cms/testimonials/{$testimonial->uuid}")->assertStatus(403);
    }

    public function test_missing_required_locale_fails_validation_with_keyed_errors(): void
    {
        $this->withToken($this->editorToken())
            ->postJson('/api/cms/testimonials', $this->payload([
                'quote' => ['en' => 'English only.'],
            ]))
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonStructure(['success', 'message', 'errors', 'request_id'])
            ->assertJsonValidationErrors(['quote.ar']);
    }

    public function test_rating_outside_one_to_five_is_rejected(): void
    {
        $token = $this->editorToken();

        $this->withToken($token)->postJson('/api/cms/testimonials', $this->payload(['rating' => 6]))
            ->assertStatus(422)->assertJsonValidationErrors(['rating']);

        $this->withToken($token)->postJson('/api/cms/testimonials', $this->payload(['rating' => 0]))
            ->assertStatus(422)->assertJsonValidationErrors(['rating']);
    }

    public function test_author_title_is_optional(): void
    {
        $this->withToken($this->editorToken())
            ->postJson('/api/cms/testimonials', array_diff_key($this->payload(), ['author_title' => null]))
            ->assertStatus(201);
    }

    /**
     * Optional locales are writable and round-trip whole. The resource must emit
     * the entire locale map, not the negotiated locale, because the website
     * switches language client-side off a single fetch.
     */
    public function test_every_configured_locale_round_trips(): void
    {
        $locales = TranslatableRules::locales();
        $this->assertContains('tr', $locales, 'expected tr among the configured locales');

        $quote = [];
        foreach ($locales as $locale) {
            $quote[$locale] = "quote-in-{$locale}";
        }

        $this->withToken($this->editorToken())
            ->postJson('/api/cms/testimonials', $this->payload(['quote' => $quote]))
            ->assertStatus(201);

        $body = $this->getJson('/api/public/testimonials')->assertOk()->json('data.items.0.quote');

        foreach ($locales as $locale) {
            $this->assertSame("quote-in-{$locale}", $body[$locale] ?? null, "locale {$locale} did not round-trip");
        }

        // `Accept-Language` localizes `message`, never a content field. If it
        // narrowed `quote` to the negotiated locale, client-side language
        // switching would need a refetch per language.
        $negotiated = $this->withHeaders(['Accept-Language' => 'ar-SA,ar;q=0.9'])
            ->getJson('/api/public/testimonials')
            ->assertOk()
            ->json('data.items.0.quote');

        $this->assertSame($body, $negotiated, 'Accept-Language must not narrow a translatable content field');
    }

    public function test_public_index_hides_inactive_testimonials(): void
    {
        Testimonial::factory()->count(2)->create();
        Testimonial::factory()->inactive()->create();

        $res = $this->getJson('/api/public/testimonials')->assertOk();

        $this->assertCount(2, $res->json('data.items'));
        $this->assertSame(2, $res->json('data.meta.total'));
    }

    public function test_public_index_is_paginated_and_enveloped(): void
    {
        Testimonial::factory()->count(3)->create();

        $this->getJson('/api/public/testimonials')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'success', 'message', 'request_id',
                'data' => ['items', 'meta' => ['current_page', 'per_page', 'total', 'last_page']],
            ]);
    }

    public function test_public_index_honours_per_page_but_ignores_cms_filters(): void
    {
        Testimonial::factory()->count(3)->create();
        Testimonial::factory()->inactive()->count(2)->create();

        $this->getJson('/api/public/testimonials?per_page=2')
            ->assertOk()
            ->assertJsonPath('data.meta.per_page', 2)
            ->assertJsonCount(2, 'data.items');

        // A filter param must not reach the public query and expose drafts.
        $res = $this->getJson('/api/public/testimonials?is_active=false')->assertOk();
        $this->assertCount(3, $res->json('data.items'));
    }

    public function test_cms_index_can_filter_by_active_and_search_by_author(): void
    {
        $token = $this->editorToken();
        Testimonial::factory()->create(['author_name' => 'Valentina R.']);
        Testimonial::factory()->inactive()->create(['author_name' => 'Henri D.']);

        $active = $this->withToken($token)->getJson('/api/cms/testimonials?is_active=true')->assertOk();
        $this->assertCount(1, $active->json('data.items'));

        $found = $this->withToken($token)->getJson('/api/cms/testimonials?search=Valentina')->assertOk();
        $this->assertCount(1, $found->json('data.items'));
        $this->assertSame('Valentina R.', $found->json('data.items.0.author_name'));
    }

    public function test_admin_can_upload_and_expose_an_avatar(): void
    {
        Storage::fake('public');
        $testimonial = Testimonial::factory()->create();

        $this->withToken($this->editorToken())
            ->postJson("/api/cms/testimonials/{$testimonial->uuid}/images", [
                'image' => UploadedFile::fake()->image('portrait.jpg'),
            ])
            ->assertStatus(201);

        $this->assertDatabaseCount('media', 1);
        $this->assertNotNull(
            $this->getJson('/api/public/testimonials')->assertOk()->json('data.items.0.avatar')
        );
    }

    /**
     * Media deletion is scoped to the parent named in the route — a testimonial's
     * route must not be able to delete a promotion's image.
     */
    public function test_media_cannot_be_deleted_through_the_wrong_parent(): void
    {
        Storage::fake('public');
        $testimonial = Testimonial::factory()->create();
        $promotion   = Promotion::factory()->create();

        $this->withToken($this->editorToken())
            ->postJson("/api/cms/promotions/{$promotion->uuid}/images", [
                'image' => UploadedFile::fake()->image('promo.jpg'),
            ])->assertStatus(201);

        $media = Media::firstOrFail();

        $this->withToken($this->editorToken())
            ->deleteJson("/api/cms/testimonials/{$testimonial->uuid}/images/{$media->uuid}")
            ->assertNotFound();

        $this->assertDatabaseCount('media', 1);
    }
}
