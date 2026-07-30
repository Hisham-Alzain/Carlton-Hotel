<?php

namespace Tests\Feature\Service;

use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\ServiceCategory;
use App\Models\ServiceItem;
use App\Models\User;
use App\Support\TranslatableRules;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The menu and service-catalog modules were never refactored onto
 * `App\Support\TranslatableRules`, and they are the last two that were not.
 *
 * Two consequences, both proved here:
 *
 * 1. **Only en/ar were accepted.** The four requests hardcoded `name.en` /
 *    `name.ar`, so `config('cms.locales')` growing to `en,ar,fr,tr,es` reached
 *    every other module and not these: a French room type saved, a French menu
 *    item was a 422 — and `fr` is not hypothetical, `CmsContentSeeder` writes
 *    French copy throughout.
 * 2. **`PUT` demanded a full payload.** `update()` reused the create request, so
 *    every field was `required`: changing a dish's price meant resending its
 *    translations, its category and its flags, and a client that sent only the
 *    price got a 422 — or, on the nullable fields, silently blanked what it left
 *    out.
 *
 * en/ar semantics are unchanged, and deliberately so: `TranslatableRules::for()`
 * emits `['required', …]` for the required locales, which is exactly what the
 * hardcoded keys said. `test_the_required_locales_are_still_exactly_en_and_ar`
 * asserts that against the rule arrays themselves, not just through the wire.
 *
 * Revert any Store request to its hardcoded `name.en`/`name.ar` pair and the
 * `_accepts_french` tests go red. Point a controller's `update()` back at its
 * Store request and the `_partial_update_` tests for that module go red.
 */
class CatalogLocaleAndPartialUpdateTest extends TestCase
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

    // ── The locale set is config, not a hardcoded pair ─────────────────────

    /**
     * The guard on "do not change en/ar semantics": the required locales are
     * still exactly en and ar, and the rule they carry is still `required`.
     * Everything the config adds beyond them is `nullable`.
     */
    public function test_the_required_locales_are_still_exactly_en_and_ar(): void
    {
        $this->assertSame(['en', 'ar'], TranslatableRules::requiredLocales());

        $rules = TranslatableRules::for('name', ['string', 'max:255']);

        $this->assertSame(['required', 'string', 'max:255'], $rules['name.en']);
        $this->assertSame(['required', 'string', 'max:255'], $rules['name.ar']);

        foreach (array_diff(TranslatableRules::locales(), ['en', 'ar']) as $locale) {
            $this->assertSame(['nullable', 'string', 'max:255'], $rules["name.{$locale}"]);
        }
    }

    public function test_menu_category_accepts_french_and_still_requires_english_and_arabic(): void
    {
        $token = $this->editorToken();
        $venue = \App\Models\DiningVenue::factory()->create();

        $this->withToken($token)
            ->postJson('/api/cms/menu-categories', [
                'dining_venue_uuid' => $venue->uuid,
                'name' => ['en' => 'Starters', 'ar' => 'مقبلات', 'fr' => 'Entrées', 'tr' => 'Başlangıçlar', 'es' => 'Entrantes'],
            ])
            ->assertCreated()
            ->assertJsonPath('data.name.fr', 'Entrées')
            ->assertJsonPath('data.name.es', 'Entrantes');

        // Arabic is still mandatory — the refactor widened the accepted set, it
        // did not relax the required one.
        $this->withToken($token)
            ->postJson('/api/cms/menu-categories', [
                'dining_venue_uuid' => $venue->uuid,
                'name' => ['en' => 'Mains', 'fr' => 'Plats'],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name.ar']);
    }

    public function test_menu_item_accepts_french(): void
    {
        $category = MenuCategory::factory()->create();

        $this->withToken($this->editorToken())
            ->postJson('/api/cms/menu-items', [
                'menu_category_uuid' => $category->uuid,
                'name'        => ['en' => 'Hummus', 'ar' => 'حمص', 'fr' => 'Houmous'],
                'description' => ['en' => 'Chickpeas.', 'fr' => 'Pois chiches.'],
                'price_usd'   => 8.50,
            ])
            ->assertCreated()
            ->assertJsonPath('data.name.fr', 'Houmous')
            ->assertJsonPath('data.description.fr', 'Pois chiches.');
    }

    public function test_service_category_accepts_french(): void
    {
        $this->withToken($this->editorToken())
            ->postJson('/api/cms/service-categories', [
                'code'        => 'spa',
                'name'        => ['en' => 'Spa', 'ar' => 'سبا', 'fr' => 'Spa & bien-être'],
                'description' => ['en' => 'Treatments.', 'fr' => 'Soins.'],
                'kind'        => 'catalog',
                'department'  => 'concierge',
            ])
            ->assertCreated()
            ->assertJsonPath('data.name.fr', 'Spa & bien-être');
    }

    public function test_service_item_accepts_french(): void
    {
        $category = ServiceCategory::factory()->create();

        $this->withToken($this->editorToken())
            ->postJson('/api/cms/service-items', [
                'service_category_uuid' => $category->uuid,
                'name'             => ['en' => 'Hot Stone', 'ar' => 'أحجار ساخنة', 'fr' => 'Pierres chaudes'],
                'expected_minutes' => 90,
            ])
            ->assertCreated()
            ->assertJsonPath('data.name.fr', 'Pierres chaudes');
    }

    /** A locale the config does not list is still refused — it is a whitelist. */
    public function test_an_unconfigured_locale_key_is_not_stored(): void
    {
        $category = MenuCategory::factory()->create();

        $this->withToken($this->editorToken())
            ->postJson('/api/cms/menu-items', [
                'menu_category_uuid' => $category->uuid,
                'name'      => ['en' => 'Hummus', 'ar' => 'حمص', 'de' => 'Kichererbsenpüree'],
                'price_usd' => 8.50,
            ])
            ->assertCreated()
            ->assertJsonMissingPath('data.name.de');
    }

    // ── PUT is a partial update ───────────────────────────────────────────

    public function test_menu_item_partial_update_leaves_unsent_fields_alone(): void
    {
        $item = MenuItem::factory()->create([
            'name'        => ['en' => 'Hummus', 'ar' => 'حمص'],
            'description' => ['en' => 'Chickpeas.', 'ar' => 'حمص مطحون.'],
            'price_usd'   => 8.50,
            'is_vegan'    => true,
        ]);

        $this->withToken($this->editorToken())
            ->putJson("/api/cms/menu-items/{$item->uuid}", ['price_usd' => 12.00])
            ->assertOk()
            ->assertJsonPath('data.price_usd', '12.00')
            ->assertJsonPath('data.name.en', 'Hummus')
            ->assertJsonPath('data.name.ar', 'حمص')
            ->assertJsonPath('data.description.en', 'Chickpeas.')
            ->assertJsonPath('data.is_vegan', true)
            // The dish did not move courses just because the payload was quiet.
            ->assertJsonPath('data.menu_category_uuid', $item->category->uuid);
    }

    public function test_menu_category_partial_update_leaves_the_slug_and_venue_alone(): void
    {
        $category = MenuCategory::factory()->create([
            'slug' => 'starters',
            'name' => ['en' => 'Starters', 'ar' => 'مقبلات'],
        ]);
        $venueUuid = $category->venue->uuid;

        $this->withToken($this->editorToken())
            ->putJson("/api/cms/menu-categories/{$category->uuid}", ['sort_order' => 9])
            ->assertOk()
            ->assertJsonPath('data.sort_order', 9)
            // The public `?type=<slug>` links the site already serves must not be
            // re-slugged by an unrelated edit.
            ->assertJsonPath('data.slug', 'starters')
            ->assertJsonPath('data.name.en', 'Starters')
            ->assertJsonPath('data.dining_venue_uuid', $venueUuid);
    }

    /**
     * The create request derives the slug from the English name. Renaming a
     * category on update must NOT re-derive it — that would silently break every
     * link already pointing at the old slug.
     */
    public function test_renaming_a_menu_category_does_not_reslug_it(): void
    {
        $category = MenuCategory::factory()->create([
            'slug' => 'starters',
            'name' => ['en' => 'Starters', 'ar' => 'مقبلات'],
        ]);

        $this->withToken($this->editorToken())
            ->putJson("/api/cms/menu-categories/{$category->uuid}", ['name' => ['en' => 'Small Plates']])
            ->assertOk()
            ->assertJsonPath('data.name.en', 'Small Plates')
            ->assertJsonPath('data.slug', 'starters');
    }

    public function test_service_category_partial_update_leaves_unsent_fields_alone(): void
    {
        $category = ServiceCategory::factory()->create([
            'code'       => 'housekeeping',
            'name'       => ['en' => 'Housekeeping', 'ar' => 'التدبير المنزلي'],
            'kind'       => 'catalog',
            'department' => 'housekeeping',
            'icon'       => 'broom',
            'sort_order' => 0,
        ]);

        $this->withToken($this->editorToken())
            ->putJson("/api/cms/service-categories/{$category->uuid}", ['sort_order' => 4])
            ->assertOk()
            ->assertJsonPath('data.sort_order', 4)
            ->assertJsonPath('data.code', 'housekeeping')
            ->assertJsonPath('data.kind', 'catalog')
            ->assertJsonPath('data.department', 'housekeeping')
            ->assertJsonPath('data.icon', 'broom')
            ->assertJsonPath('data.name.en', 'Housekeeping');
    }

    /**
     * `Rule::unique(...)->ignore()` has to be on the update request: without it a
     * category cannot be saved without changing its own code.
     */
    public function test_a_service_category_can_resend_its_own_code(): void
    {
        $category = ServiceCategory::factory()->create(['code' => 'housekeeping']);
        ServiceCategory::factory()->create(['code' => 'concierge']);

        $token = $this->editorToken();

        $this->withToken($token)
            ->putJson("/api/cms/service-categories/{$category->uuid}", ['code' => 'housekeeping', 'sort_order' => 2])
            ->assertOk()
            ->assertJsonPath('data.code', 'housekeeping');

        // …but another category's code is still taken.
        $this->withToken($token)
            ->putJson("/api/cms/service-categories/{$category->uuid}", ['code' => 'concierge'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['code']);
    }

    public function test_service_item_partial_update_leaves_unsent_fields_alone(): void
    {
        $item = ServiceItem::factory()->create([
            'name'             => ['en' => 'Hot Stone', 'ar' => 'أحجار ساخنة'],
            'expected_minutes' => 90,
            'price_usd'        => 110.00,
            'sort_order'       => 3,
        ]);

        $this->withToken($this->editorToken())
            ->putJson("/api/cms/service-items/{$item->uuid}", ['expected_minutes' => 60])
            ->assertOk()
            ->assertJsonPath('data.expected_minutes', 60)
            ->assertJsonPath('data.price_usd', '110.00')
            ->assertJsonPath('data.name.en', 'Hot Stone');

        $item->refresh();
        $this->assertSame(3, $item->sort_order);
        $this->assertSame($item->service_category_id, $item->fresh()->service_category_id);
    }

    /**
     * Null still means something on this module: a `price_usd` of null is
     * *complimentary*, not "leave it alone". Omitting the key is what leaves it
     * alone — a distinction the create request could not express.
     */
    public function test_an_explicit_null_price_makes_a_service_item_complimentary(): void
    {
        $item = ServiceItem::factory()->priced(110.00)->create();

        $this->withToken($this->editorToken())
            ->putJson("/api/cms/service-items/{$item->uuid}", ['price_usd' => null])
            ->assertOk()
            ->assertJsonPath('data.price_usd', null);

        $this->assertNull($item->fresh()->price_usd);
    }

    /** A partial update still validates what it does send. */
    public function test_a_partial_update_still_rejects_a_bad_value(): void
    {
        $item = MenuItem::factory()->create();

        $this->withToken($this->editorToken())
            ->putJson("/api/cms/menu-items/{$item->uuid}", ['price_usd' => -5])
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'validation_failed')
            ->assertJsonValidationErrors(['price_usd']);
    }

    /**
     * A required locale can be omitted on update but never blanked — the
     * `sometimes|required` half of `TranslatableRules::sometimes()`.
     */
    public function test_a_required_translation_cannot_be_blanked_on_update(): void
    {
        $item = MenuItem::factory()->create(['name' => ['en' => 'Hummus', 'ar' => 'حمص']]);

        $this->withToken($this->editorToken())
            ->putJson("/api/cms/menu-items/{$item->uuid}", ['name' => ['en' => '', 'ar' => 'حمص']])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name.en']);
    }

    // ── The gates are unchanged ───────────────────────────────────────────

    public function test_updates_are_still_gated_on_cms_edit(): void
    {
        $item  = MenuItem::factory()->create();
        $token = User::factory()->create()->createToken('t')->plainTextToken;

        $this->putJson("/api/cms/menu-items/{$item->uuid}", ['price_usd' => 1])->assertStatus(401);

        $this->withToken($token)
            ->putJson("/api/cms/menu-items/{$item->uuid}", ['price_usd' => 1])
            ->assertStatus(403);
    }
}
