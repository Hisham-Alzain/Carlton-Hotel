<?php

namespace Tests\Feature\Cms;

use App\Models\DiningVenue;
use App\Models\Page;
use App\Models\RoomType;
use App\Models\User;
use App\Support\TranslatableRules;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 0 — locale foundation.
 *
 * Translatable CMS fields are JSON columns, so the locale set lives in
 * `config/cms.php` rather than in each FormRequest. These tests pin the two
 * halves of that contract: existing `en`/`ar` clients keep working unchanged,
 * and the additional locales are accepted, stored, and served.
 */
class LocaleFoundationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function editorToken(): string
    {
        $user = User::factory()->create();
        $user->givePermissionTo('cms.edit');

        return $user->createToken('t')->plainTextToken;
    }

    private function roomTypePayload(array $overrides = []): array
    {
        return array_merge([
            'name'           => ['en' => 'Deluxe Suite', 'ar' => 'جناح ديلوكس'],
            'description'    => ['en' => 'Spacious suite', 'ar' => 'جناح واسع'],
            'base_occupancy' => 2,
            'max_occupancy'  => 4,
            'base_price_usd' => 200.00,
        ], $overrides);
    }

    // ── Config & rule builder ─────────────────────────────────────────────

    public function test_config_declares_five_locales_with_en_and_ar_required(): void
    {
        $this->assertSame(['en', 'ar', 'fr', 'tr', 'es'], config('cms.locales'));
        $this->assertSame(['en', 'ar'], config('cms.required_locales'));
    }

    public function test_rule_builder_requires_only_the_required_locales(): void
    {
        $this->assertSame([
            'name.en' => ['required', 'string', 'max:255'],
            'name.ar' => ['required', 'string', 'max:255'],
            'name.fr' => ['nullable', 'string', 'max:255'],
            'name.tr' => ['nullable', 'string', 'max:255'],
            'name.es' => ['nullable', 'string', 'max:255'],
        ], TranslatableRules::for('name', ['string', 'max:255']));
    }

    public function test_rule_builder_preserves_update_semantics(): void
    {
        // `sometimes|required` — omitting the key leaves the stored translation
        // alone, but an explicitly sent blank is still rejected.
        $this->assertSame(
            ['sometimes', 'required', 'string'],
            TranslatableRules::sometimes('description', ['string'])['description.en'],
        );
        $this->assertSame(
            ['nullable', 'string'],
            TranslatableRules::sometimes('description', ['string'])['description.fr'],
        );
    }

    public function test_rule_builder_leaves_wholly_optional_fields_optional(): void
    {
        foreach (TranslatableRules::optional('hours', ['string', 'max:255']) as $rules) {
            $this->assertSame(['nullable', 'string', 'max:255'], $rules);
        }
    }

    // ── Regression guard: en + ar only must keep working ──────────────────

    public function test_create_with_only_en_and_ar_still_succeeds(): void
    {
        $res = $this->withToken($this->editorToken())
            ->postJson('/api/cms/room-types', $this->roomTypePayload())
            ->assertStatus(201)
            ->assertJsonPath('success', true);

        $this->assertSame(
            ['en' => 'Deluxe Suite', 'ar' => 'جناح ديلوكس'],
            $res->json('data.name'),
        );
    }

    public function test_update_with_only_en_and_ar_still_succeeds(): void
    {
        $roomType = RoomType::factory()->create();

        $this->withToken($this->editorToken())
            ->putJson("/api/cms/room-types/{$roomType->uuid}", [
                'name' => ['en' => 'Updated', 'ar' => 'محدث'],
            ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name.en', 'Updated')
            ->assertJsonPath('data.name.ar', 'محدث');
    }

    public function test_page_create_with_only_en_and_ar_still_succeeds(): void
    {
        $this->withToken($this->editorToken())
            ->postJson('/api/cms/pages', [
                'slug'    => 'about-us',
                'title'   => ['en' => 'About us', 'ar' => 'من نحن'],
                'content' => ['en' => 'Body copy', 'ar' => 'النص'],
            ])
            ->assertStatus(201)
            ->assertJsonPath('success', true);
    }

    // ── New locales are accepted, stored and served ───────────────────────

    public function test_update_persists_new_locales_and_public_endpoint_returns_them(): void
    {
        $roomType = RoomType::factory()->create(['is_active' => true]);

        $this->withToken($this->editorToken())
            ->putJson("/api/cms/room-types/{$roomType->uuid}", [
                'name' => [
                    'en' => 'Deluxe Suite',
                    'ar' => 'جناح ديلوكس',
                    'fr' => 'Suite de luxe',
                    'tr' => 'Delüks Süit',
                ],
            ])
            ->assertOk();

        $this->assertSame(
            'Suite de luxe',
            $roomType->fresh()->getTranslation('name', 'fr'),
        );

        $public = $this->getJson("/api/public/room-types/{$roomType->uuid}")->assertOk();

        $this->assertSame('Suite de luxe', $public->json('data.name.fr'));
        $this->assertSame('Delüks Süit', $public->json('data.name.tr'));
        // Existing locales survive the partial write.
        $this->assertSame('Deluxe Suite', $public->json('data.name.en'));
        $this->assertSame('جناح ديلوكس', $public->json('data.name.ar'));
    }

    public function test_create_accepts_all_five_locales(): void
    {
        $res = $this->withToken($this->editorToken())
            ->postJson('/api/cms/room-types', $this->roomTypePayload([
                'name' => [
                    'en' => 'Deluxe Suite',
                    'ar' => 'جناح ديلوكس',
                    'fr' => 'Suite de luxe',
                    'tr' => 'Delüks Süit',
                    'es' => 'Suite de lujo',
                ],
            ]))
            ->assertStatus(201);

        $this->assertSame(
            ['en', 'ar', 'fr', 'tr', 'es'],
            array_keys($res->json('data.name')),
        );
    }

    public function test_optional_translatable_field_accepts_a_new_locale(): void
    {
        $venue = DiningVenue::factory()->create(['is_active' => true]);

        $this->withToken($this->editorToken())
            ->putJson("/api/cms/dining-venues/{$venue->uuid}", [
                'cuisine_type' => ['en' => 'Levantine', 'fr' => 'Levantine', 'es' => 'Levantina'],
            ])
            ->assertOk();

        $this->assertSame('Levantina', $venue->fresh()->getTranslation('cuisine_type', 'es'));
    }

    public function test_page_new_locales_reach_the_public_slug_endpoint(): void
    {
        $page = Page::factory()->create(['slug' => 'about-us', 'is_active' => true]);

        $this->withToken($this->editorToken())
            ->putJson("/api/cms/pages/{$page->uuid}", [
                'title' => ['en' => 'About us', 'ar' => 'من نحن', 'tr' => 'Hakkımızda'],
            ])
            ->assertOk();

        $this->getJson('/api/public/pages/about-us')
            ->assertOk()
            ->assertJsonPath('data.title.tr', 'Hakkımızda');
    }

    // ── Validation: required locales stay required ────────────────────────

    public function test_omitting_a_required_locale_fails_with_the_validation_envelope(): void
    {
        $res = $this->withToken($this->editorToken())
            ->postJson('/api/cms/room-types', $this->roomTypePayload([
                'name' => ['en' => 'Only English'],
            ]))
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error_code', 'validation_failed');

        $this->assertArrayHasKey('name.ar', $res->json('errors'));
        $this->assertArrayNotHasKey('name.en', $res->json('errors'));
    }

    public function test_a_new_locale_cannot_substitute_for_a_required_one(): void
    {
        $res = $this->withToken($this->editorToken())
            ->postJson('/api/cms/room-types', $this->roomTypePayload([
                'name' => ['fr' => 'Suite de luxe', 'tr' => 'Delüks Süit'],
            ]))
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'validation_failed');

        $errors = $res->json('errors');
        $this->assertArrayHasKey('name.en', $errors);
        $this->assertArrayHasKey('name.ar', $errors);
        $this->assertArrayNotHasKey('name.fr', $errors);
        $this->assertArrayNotHasKey('name.tr', $errors);
    }

    public function test_update_still_rejects_an_explicitly_blanked_required_locale(): void
    {
        $roomType = RoomType::factory()->create();

        $this->withToken($this->editorToken())
            ->putJson("/api/cms/room-types/{$roomType->uuid}", [
                'name' => ['en' => '', 'ar' => 'محدث'],
            ])
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error_code', 'validation_failed')
            ->assertJsonStructure(['errors' => ['name.en']]);
    }

    // ── Locale resolution ─────────────────────────────────────────────────

    public function test_accept_language_resolves_a_newly_supported_locale(): void
    {
        $this->withHeaders(['Accept-Language' => 'fr'])
            ->getJson('/api/public/room-types')
            ->assertOk();

        $this->assertSame('fr', app()->getLocale());
        $this->assertSame(__('custom.errors.not_found'), trans('custom.errors.not_found', [], 'fr'));
    }
}
