<?php

namespace Tests\Feature\Cms;

use App\Enums\SettingType;
use App\Models\SiteSetting;
use App\Models\User;
use Database\Seeders\CmsContentSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * `site_settings.value` must match the `type` the same row declares.
 *
 * `value` is a json column on purpose — a bare phone number, a locale map of
 * headlines and a locale map of address lines all live in it — and the request
 * used to validate it as `present` and nothing else. `type` was validated
 * against the enum, so the row *said* `url` or `bool`, but nothing checked the
 * value against that claim: a `url` slot accepted an array, a `bool` accepted
 * `"banana"`, and a locale map accepted `{de: …}` for a locale the CMS does not
 * serve. `Api\SiteSettingController` renders these into the public site's
 * footer, contact block and hero verbatim, so each of those is a broken page.
 *
 * Restore `'settings.*.value' => ['present']` as the only value rule in
 * `UpsertSiteSettingsRequest` and every test in the two "rejected" sections goes
 * red — each asserts a 422 on a payload that used to be stored.
 */
class SiteSettingValueTypeTest extends TestCase
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

    /** @param  list<array<string, mixed>>  $settings */
    private function upsert(array $settings): \Illuminate\Testing\TestResponse
    {
        return $this->withToken($this->editorToken())
            ->putJson('/api/cms/settings', ['settings' => $settings]);
    }

    // ── The shapes the module really stores are all still accepted ─────────

    /**
     * THE REGRESSION GUARD FOR THE FIX ITSELF.
     *
     * Every row `CmsContentSeeder::siteSettings()` writes, replayed through the
     * endpoint. The seeder is the module's own statement of what a legal settings
     * table looks like — trilingual copy, a bare scalar phone number, a locale map
     * of *lists*, and four `url` rows whose value is null — so a rule that any of
     * it fails is a rule that would have rejected production data.
     *
     * Read out of the database rather than restated here, so the assertion cannot
     * drift from the seeder it is checking.
     */
    public function test_every_shape_the_seeder_writes_is_accepted_by_the_endpoint(): void
    {
        $this->seed(CmsContentSeeder::class);

        $seeded = SiteSetting::query()->orderBy('group')->orderBy('key')->get();

        $this->assertGreaterThan(0, $seeded->count(), 'the seeder wrote no settings — the guard proves nothing');

        $payload = $seeded->map(fn (SiteSetting $setting): array => [
            'group'     => $setting->group,
            'key'       => $setting->key,
            'value'     => $setting->value,
            'type'      => $setting->type instanceof SettingType ? $setting->type->value : $setting->type,
            'is_active' => $setting->is_active,
        ])->all();

        $this->upsert($payload)->assertOk();

        // Round-tripped, not merely accepted: the locale map of lists comes back
        // as a locale map of lists.
        $this->assertSame(
            ['Kafr Sousa', 'Damascus', 'Syrian Arab Republic'],
            SiteSetting::where(['group' => 'contact', 'key' => 'address_lines'])->value('value')['en'],
        );
        $this->assertSame(
            '+963 (0)11 000 00 00',
            SiteSetting::where(['group' => 'contact', 'key' => 'phone'])->value('value'),
        );
        $this->assertNull(SiteSetting::where(['group' => 'social', 'key' => 'instagram'])->value('value'));
    }

    public function test_a_scalar_and_a_locale_map_are_both_accepted_for_a_copy_type(): void
    {
        $this->upsert([
            ['group' => 'contact', 'key' => 'phone', 'value' => '+963 (0)11 000 00 00', 'type' => 'text'],
            ['group' => 'footer', 'key' => 'tagline', 'value' => ['en' => 'A sanctuary.', 'ar' => 'ملاذ.', 'fr' => 'Un sanctuaire.'], 'type' => 'text'],
            ['group' => 'pages', 'key' => 'about_body', 'value' => ['en' => '<p>Long copy.</p>'], 'type' => 'richtext'],
        ])->assertOk();

        $this->assertDatabaseCount('site_settings', 3);
    }

    public function test_null_clears_a_setting_of_any_type(): void
    {
        $settings = [];

        foreach (SettingType::values() as $index => $type) {
            $settings[] = ['group' => 'seo', 'key' => "slot_{$index}", 'value' => null, 'type' => $type];
        }

        $this->upsert($settings)->assertOk();

        $this->assertDatabaseCount('site_settings', count(SettingType::values()));
    }

    public function test_a_url_is_accepted_absolute_root_relative_and_per_locale(): void
    {
        $this->upsert([
            ['group' => 'social', 'key' => 'instagram', 'value' => 'https://instagram.com/carltonsyria', 'type' => 'url'],
            ['group' => 'footer', 'key' => 'careers_link', 'value' => '/careers', 'type' => 'url'],
            ['group' => 'contact', 'key' => 'mail_link', 'value' => 'mailto:reservations@carltonsyria.com', 'type' => 'url'],
            ['group' => 'booking', 'key' => 'portal', 'value' => ['en' => 'https://book.example.com/en', 'ar' => 'https://book.example.com/ar'], 'type' => 'url'],
        ])->assertOk();

        $this->assertDatabaseCount('site_settings', 4);
    }

    public function test_a_bool_accepts_both_booleans(): void
    {
        $this->upsert([
            ['group' => 'booking', 'key' => 'enabled', 'value' => true, 'type' => 'bool'],
            ['group' => 'booking', 'key' => 'waitlist', 'value' => false, 'type' => 'bool'],
        ])->assertOk();

        $this->assertTrue(SiteSetting::where('key', 'enabled')->value('value'));
        $this->assertFalse(SiteSetting::where('key', 'waitlist')->value('value'));
    }

    /** An `image` slot holds a resolved media URL or the storage path behind it. */
    public function test_an_image_accepts_a_url_or_a_storage_path(): void
    {
        $this->upsert([
            ['group' => 'seo', 'key' => 'og_image', 'value' => 'https://cdn.example.com/og.jpg', 'type' => 'image'],
            ['group' => 'hero', 'key' => 'backdrop', 'value' => 'media/hero/backdrop.jpg', 'type' => 'image'],
        ])->assertOk();

        $this->assertDatabaseCount('site_settings', 2);
    }

    // ── Values that contradict their type are rejected ─────────────────────

    public function test_a_bool_cannot_hold_a_string(): void
    {
        $this->upsert([
            ['group' => 'booking', 'key' => 'enabled', 'value' => 'banana', 'type' => 'bool'],
        ])
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'validation_failed')
            ->assertJsonValidationErrors(['settings.0.value']);

        $this->assertDatabaseCount('site_settings', 0);
    }

    /**
     * A feature flag is not translated, so a locale map is the caller having sent
     * a different setting than the one it named.
     */
    public function test_a_bool_cannot_hold_a_locale_map(): void
    {
        $this->upsert([
            ['group' => 'booking', 'key' => 'enabled', 'value' => ['en' => true, 'ar' => false], 'type' => 'bool'],
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['settings.0.value']);
    }

    public function test_a_url_cannot_hold_an_array_of_lines(): void
    {
        $this->upsert([
            ['group' => 'social', 'key' => 'instagram', 'value' => ['carltonsyria', 'carlton'], 'type' => 'url'],
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['settings.0.value']);

        $this->assertDatabaseCount('site_settings', 0);
    }

    public function test_a_url_cannot_hold_free_text(): void
    {
        $this->upsert([
            ['group' => 'social', 'key' => 'instagram', 'value' => 'follow us on instagram!', 'type' => 'url'],
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['settings.0.value']);
    }

    /**
     * A `text` slot renders as one line of copy. An array whose keys are `0` and
     * `1` is a list, and the site would print the locale keys or nothing at all.
     */
    public function test_a_copy_type_cannot_hold_a_bare_list(): void
    {
        $this->upsert([
            ['group' => 'footer', 'key' => 'tagline', 'value' => ['A sanctuary.', 'In Damascus.'], 'type' => 'text'],
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['settings.0.value']);
    }

    /**
     * `cms.locales` is the single source of truth for which languages exist. A
     * map carrying `de` is copy the site has no way to render — and no way to
     * report, because it would simply never be read.
     */
    public function test_a_locale_map_cannot_carry_an_unconfigured_locale(): void
    {
        $this->upsert([
            ['group' => 'footer', 'key' => 'tagline', 'value' => ['en' => 'A sanctuary.', 'de' => 'Eine Zuflucht.'], 'type' => 'text'],
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['settings.0.value']);
    }

    /** Every locale the CMS *does* accept passes — the check is a whitelist, not en/ar. */
    public function test_every_configured_locale_is_accepted_in_a_map(): void
    {
        $this->upsert([
            ['group' => 'footer', 'key' => 'tagline', 'value' => [
                'en' => 'A sanctuary.', 'ar' => 'ملاذ.', 'fr' => 'Un sanctuaire.',
                'tr' => 'Bir sığınak.', 'es' => 'Un santuario.',
            ], 'type' => 'text'],
        ])->assertOk();
    }

    public function test_a_locale_map_cannot_nest_another_map_for_a_copy_type(): void
    {
        $this->upsert([
            ['group' => 'footer', 'key' => 'tagline', 'value' => ['en' => ['line1' => 'A sanctuary.']], 'type' => 'text'],
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['settings.0.value.en']);
    }

    /**
     * The `json` widget is a list / key-value editor, so a bare string there is
     * the caller having picked the wrong type — the CMS would render an empty
     * list editor over a value it cannot show.
     */
    public function test_a_json_type_cannot_hold_a_bare_string(): void
    {
        $this->upsert([
            ['group' => 'contact', 'key' => 'address_lines', 'value' => 'Kafr Sousa, Damascus', 'type' => 'json'],
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['settings.0.value']);
    }

    /** The one real nested shape stays legal: a locale map of lists. */
    public function test_a_json_type_accepts_a_locale_map_of_lists(): void
    {
        $this->upsert([
            ['group' => 'contact', 'key' => 'address_lines', 'value' => [
                'en' => ['Kafr Sousa', 'Damascus', 'Syrian Arab Republic'],
                'ar' => ['كفر سوسة', 'دمشق', 'الجمهورية العربية السورية'],
            ], 'type' => 'json'],
        ])->assertOk();

        $this->assertSame(
            ['Kafr Sousa', 'Damascus', 'Syrian Arab Republic'],
            SiteSetting::where('key', 'address_lines')->value('value')['en'],
        );
    }

    /**
     * One bad row rejects the whole payload — the upsert is one editorial act,
     * and the type check has to run before the transaction, not inside it.
     */
    public function test_a_single_type_mismatch_rejects_the_whole_form(): void
    {
        $this->upsert([
            ['group' => 'footer', 'key' => 'tagline', 'value' => ['en' => 'Fine.', 'ar' => 'جيد.'], 'type' => 'text'],
            ['group' => 'booking', 'key' => 'enabled', 'value' => 'banana', 'type' => 'bool'],
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['settings.1.value'])
            ->assertJsonMissingValidationErrors(['settings.0.value']);

        $this->assertDatabaseCount('site_settings', 0);
    }

    /**
     * An unknown `type` is reported once, as a `type` error. Guessing a shape for
     * it would add a second, contradictory message about the value.
     */
    public function test_an_unknown_type_is_reported_on_type_and_not_on_value(): void
    {
        $this->upsert([
            ['group' => 'footer', 'key' => 'tagline', 'value' => ['en' => 'x'], 'type' => 'markdown'],
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['settings.0.type'])
            ->assertJsonMissingValidationErrors(['settings.0.value']);
    }

    /** `present` still holds: omitting `value` is an error, not a silent blanking. */
    public function test_omitting_value_entirely_is_still_an_error(): void
    {
        $this->upsert([
            ['group' => 'footer', 'key' => 'tagline', 'type' => 'text'],
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['settings.0.value']);
    }
}
