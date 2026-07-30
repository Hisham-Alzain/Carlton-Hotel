<?php

namespace Tests\Feature\Cms;

use App\Models\Faq;
use App\Models\User;
use App\Support\TranslatableRules;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FaqTest extends TestCase
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
            'question'  => ['en' => 'Is parking available?', 'ar' => 'هل يتوفر موقف للسيارات؟'],
            'answer'    => ['en' => 'Yes, secure underground valet parking.', 'ar' => 'نعم، خدمة صف السيارات.'],
            'is_active' => true,
        ], $overrides);
    }

    public function test_admin_can_crud_faq(): void
    {
        $token = $this->editorToken();

        $uuid = $this->withToken($token)->postJson('/api/cms/faqs', $this->payload())
            ->assertStatus(201)
            ->assertJsonPath('data.question.ar', 'هل يتوفر موقف للسيارات؟')
            ->json('data.uuid');

        $this->withToken($token)->getJson("/api/cms/faqs/{$uuid}")->assertOk();

        $this->withToken($token)
            ->putJson("/api/cms/faqs/{$uuid}", ['answer' => ['en' => 'Yes — valet only.', 'ar' => 'نعم — صف حصري.']])
            ->assertOk()
            ->assertJsonPath('data.answer.en', 'Yes — valet only.');

        $this->withToken($token)->deleteJson("/api/cms/faqs/{$uuid}")->assertStatus(204);
        $this->assertDatabaseCount('faqs', 0);
    }

    public function test_unauthenticated_request_is_rejected(): void
    {
        $this->getJson('/api/cms/faqs')->assertStatus(401);
        $this->postJson('/api/cms/faqs', $this->payload())->assertStatus(401);
    }

    public function test_staff_without_cms_permission_is_forbidden(): void
    {
        $token = User::factory()->create()->createToken('t')->plainTextToken;

        $this->withToken($token)->getJson('/api/cms/faqs')->assertStatus(403);
        $this->withToken($token)->postJson('/api/cms/faqs', $this->payload())->assertStatus(403);
    }

    public function test_cms_view_alone_reads_but_cannot_write(): void
    {
        $token = $this->tokenWith('cms.view');
        $faq = Faq::factory()->create();

        $this->withToken($token)->getJson('/api/cms/faqs')->assertOk();
        $this->withToken($token)->getJson("/api/cms/faqs/{$faq->uuid}")->assertOk();

        $this->withToken($token)->postJson('/api/cms/faqs', $this->payload())->assertStatus(403);
        $this->withToken($token)->putJson("/api/cms/faqs/{$faq->uuid}", ['sort_order' => 3])->assertStatus(403);
        $this->withToken($token)->deleteJson("/api/cms/faqs/{$faq->uuid}")->assertStatus(403);
    }

    public function test_missing_required_locale_fails_validation_with_keyed_errors(): void
    {
        $this->withToken($this->editorToken())
            ->postJson('/api/cms/faqs', $this->payload(['answer' => ['en' => 'English only.']]))
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonStructure(['success', 'message', 'errors', 'request_id'])
            ->assertJsonValidationErrors(['answer.ar']);
    }

    public function test_question_and_answer_are_both_required_on_create(): void
    {
        $this->withToken($this->editorToken())
            ->postJson('/api/cms/faqs', ['is_active' => true])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['question.en', 'question.ar', 'answer.en', 'answer.ar']);
    }

    public function test_every_configured_locale_round_trips(): void
    {
        $locales = TranslatableRules::locales();

        $answer = [];
        foreach ($locales as $locale) {
            $answer[$locale] = "answer-in-{$locale}";
        }

        $this->withToken($this->editorToken())
            ->postJson('/api/cms/faqs', $this->payload(['answer' => $answer]))
            ->assertStatus(201);

        $body = $this->getJson('/api/public/faqs')->assertOk()->json('data.items.0.answer');

        foreach ($locales as $locale) {
            $this->assertSame("answer-in-{$locale}", $body[$locale] ?? null, "locale {$locale} did not round-trip");
        }
    }

    public function test_public_index_hides_inactive_faqs_and_keeps_editor_order(): void
    {
        Faq::factory()->create(['sort_order' => 2]);
        Faq::factory()->create(['sort_order' => 1]);
        Faq::factory()->inactive()->create(['sort_order' => 0]);

        $res = $this->getJson('/api/public/faqs')->assertOk();

        $items = $res->json('data.items');
        $this->assertCount(2, $items);
        $this->assertSame(2, $res->json('data.meta.total'));
        // sort_order 1 before sort_order 2 — the hidden row must not reorder them.
        $this->assertSame(
            Faq::where('sort_order', 1)->value('uuid'),
            $items[0]['uuid'],
        );
    }

    public function test_public_index_is_paginated_and_enveloped(): void
    {
        Faq::factory()->count(3)->create();

        $this->getJson('/api/public/faqs')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'success', 'message', 'request_id',
                'data' => ['items', 'meta' => ['current_page', 'per_page', 'total', 'last_page']],
            ]);
    }

    public function test_public_index_honours_per_page_and_ignores_cms_filters(): void
    {
        Faq::factory()->count(3)->create();
        Faq::factory()->inactive()->count(2)->create();

        $this->getJson('/api/public/faqs?per_page=2')
            ->assertOk()
            ->assertJsonPath('data.meta.per_page', 2)
            ->assertJsonCount(2, 'data.items');

        // A CMS filter param must not reach the public query and expose drafts.
        $res = $this->getJson('/api/public/faqs?is_active=false')->assertOk();
        $this->assertCount(3, $res->json('data.items'));
    }

    public function test_cms_index_filters_by_category_and_active(): void
    {
        $token = $this->editorToken();
        Faq::factory()->create(['category' => 'booking']);
        Faq::factory()->create(['category' => 'dining']);
        Faq::factory()->inactive()->create(['category' => 'booking']);

        $booking = $this->withToken($token)->getJson('/api/cms/faqs?category=booking')->assertOk();
        $this->assertCount(2, $booking->json('data.items'));

        $active = $this->withToken($token)->getJson('/api/cms/faqs?is_active=true')->assertOk();
        $this->assertCount(2, $active->json('data.items'));

        $both = $this->withToken($token)->getJson('/api/cms/faqs?category=booking&is_active=true')->assertOk();
        $this->assertCount(1, $both->json('data.items'));
    }

    /**
     * The seeder carries hand-written trilingual hotel copy that no factory
     * exercises. Without this, a typo in it only surfaces when someone runs
     * `migrate --seed` — and by then it has broken their database, not a test.
     */
    public function test_seeder_loads_the_real_site_copy(): void
    {
        $this->seed(\Database\Seeders\CmsContentSeeder::class);

        $this->assertSame(8, Faq::count(), 'expected the eight questions the site hardcodes');
        $this->assertSame(3, \App\Models\Testimonial::count());

        $first = Faq::orderBy('sort_order')->first();

        // en/ar/fr are all seeded from real translations; tr/es are left to editors.
        foreach (['en', 'ar', 'fr'] as $locale) {
            $this->assertNotEmpty(
                $first->getTranslation('question', $locale, false),
                "seeded FAQ is missing its {$locale} question",
            );
            $this->assertNotEmpty(
                $first->getTranslation('answer', $locale, false),
                "seeded FAQ is missing its {$locale} answer",
            );
        }

        // Distinct per locale — not the same string copied three times.
        $this->assertNotSame(
            $first->getTranslation('question', 'en', false),
            $first->getTranslation('question', 'fr', false),
        );

        $this->getJson('/api/public/faqs?per_page=100')
            ->assertOk()
            ->assertJsonCount(8, 'data.items');
    }

    /**
     * Uninterpretable filter values must be an error, not a silent guess — a
     * caller cannot otherwise tell a typo from a real empty result.
     */
    public function test_unparseable_is_active_is_rejected_rather_than_guessed(): void
    {
        Faq::factory()->count(2)->create();

        $this->withToken($this->editorToken())
            ->getJson('/api/cms/faqs?is_active=trve')
            ->assertStatus(422);

        // An empty value means "no filter", not "false".
        $res = $this->withToken($this->editorToken())
            ->getJson('/api/cms/faqs?is_active=')
            ->assertOk();
        $this->assertCount(2, $res->json('data.items'));
    }
}
