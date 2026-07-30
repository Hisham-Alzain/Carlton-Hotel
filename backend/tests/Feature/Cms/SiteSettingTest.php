<?php

namespace Tests\Feature\Cms;

use App\Enums\SettingType;
use App\Models\SiteSetting;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class SiteSettingTest extends TestCase
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

    /**
     * A payload mixing the two value shapes the json column exists for: a
     * translated locale map and a bare scalar.
     */
    private function payload(array $settings = []): array
    {
        return ['settings' => $settings !== [] ? $settings : [
            ['group' => 'contact', 'key' => 'phone', 'value' => '+963 (0)11 000 00 00', 'type' => 'text'],
            ['group' => 'footer', 'key' => 'tagline', 'value' => ['en' => 'A sanctuary.', 'ar' => 'ملاذ.'], 'type' => 'text'],
        ]];
    }

    // ── CMS reads ─────────────────────────────────────────────────────────

    public function test_cms_index_returns_every_setting_bucketed_by_group(): void
    {
        SiteSetting::factory()->create(['group' => 'contact', 'key' => 'phone', 'value' => '+963 (0)11 000 00 00']);
        SiteSetting::factory()->create(['group' => 'contact', 'key' => 'email', 'value' => 'reservations@carltonsyria.com']);
        SiteSetting::factory()->inactive()->create(['group' => 'social', 'key' => 'instagram', 'value' => null, 'type' => SettingType::URL]);

        $res = $this->withToken($this->editorToken())->getJson('/api/cms/settings')->assertOk();

        $data = $res->json('data');

        $this->assertSame(['contact', 'social'], array_keys($data));
        $this->assertCount(2, $data['contact']);
        // Drafts are the editor's business — unlike the public map, this includes them.
        $this->assertCount(1, $data['social']);
        $this->assertFalse($data['social'][0]['is_active']);
        $this->assertSame('url', $data['social'][0]['type']);
    }

    public function test_cms_index_is_not_paginated(): void
    {
        SiteSetting::factory()->count(40)->create();

        $res = $this->withToken($this->editorToken())->getJson('/api/cms/settings?per_page=2')->assertOk();

        $data = $res->json('data');

        $this->assertArrayNotHasKey('items', $data);
        $this->assertArrayNotHasKey('meta', $data);
        // All 40 rows come back, `per_page` notwithstanding: the CMS renders the
        // whole settings form, and half a form is a form that loses data.
        $this->assertCount(40, $data['contact']);
    }

    public function test_unauthenticated_request_is_rejected(): void
    {
        $this->getJson('/api/cms/settings')->assertStatus(401);
        $this->putJson('/api/cms/settings', $this->payload())->assertStatus(401);
    }

    public function test_staff_without_cms_permission_is_forbidden(): void
    {
        $token = User::factory()->create()->createToken('t')->plainTextToken;

        $this->withToken($token)->getJson('/api/cms/settings')->assertStatus(403);
        $this->withToken($token)->putJson('/api/cms/settings', $this->payload())->assertStatus(403);
    }

    public function test_cms_view_alone_reads_but_cannot_write(): void
    {
        $token = $this->tokenWith('cms.view');
        SiteSetting::factory()->create(['key' => 'phone']);

        $this->withToken($token)->getJson('/api/cms/settings')->assertOk();
        $this->withToken($token)->putJson('/api/cms/settings', $this->payload())->assertStatus(403);
    }

    // ── Bulk upsert ───────────────────────────────────────────────────────

    public function test_bulk_upsert_creates_then_updates_in_place(): void
    {
        $token = $this->editorToken();

        $this->withToken($token)->putJson('/api/cms/settings', $this->payload())
            ->assertOk()
            ->assertJsonPath('data.contact.0.key', 'phone')
            ->assertJsonPath('data.contact.0.value', '+963 (0)11 000 00 00')
            ->assertJsonPath('data.footer.0.value.ar', 'ملاذ.');

        $this->assertDatabaseCount('site_settings', 2);

        // Same (group, key) again — an update, not a second row.
        $this->withToken($token)->putJson('/api/cms/settings', $this->payload([
            ['group' => 'contact', 'key' => 'phone', 'value' => '+963 (0)11 111 11 11', 'type' => 'text'],
        ]))
            ->assertOk()
            ->assertJsonPath('data.contact.0.value', '+963 (0)11 111 11 11');

        $this->assertDatabaseCount('site_settings', 2);
    }

    public function test_bulk_upsert_stores_every_value_shape_the_json_column_exists_for(): void
    {
        $this->withToken($this->editorToken())->putJson('/api/cms/settings', $this->payload([
            ['group' => 'contact', 'key' => 'phone', 'value' => '+963 (0)11 000 00 00', 'type' => 'text'],
            ['group' => 'contact', 'key' => 'address_lines', 'value' => ['en' => ['Kafr Sousa', 'Damascus'], 'ar' => ['كفر سوسة', 'دمشق']], 'type' => 'json'],
            ['group' => 'booking', 'key' => 'enabled', 'value' => true, 'type' => 'bool'],
            ['group' => 'seo', 'key' => 'og_image', 'value' => null, 'type' => 'image'],
        ]))->assertOk();

        $this->assertSame('+963 (0)11 000 00 00', SiteSetting::where('key', 'phone')->value('value'));
        $this->assertSame(['en' => ['Kafr Sousa', 'Damascus'], 'ar' => ['كفر سوسة', 'دمشق']], SiteSetting::where('key', 'address_lines')->value('value'));
        $this->assertTrue(SiteSetting::where('key', 'enabled')->value('value'));
        $this->assertNull(SiteSetting::where('key', 'og_image')->value('value'));
    }

    /**
     * A save that does not mention `is_active` must not re-publish a setting an
     * editor deliberately hid. Defaulting it to true in the upsert would make
     * every unrelated form submit un-hide the whole table.
     */
    public function test_upsert_leaves_is_active_alone_when_the_payload_omits_it(): void
    {
        SiteSetting::factory()->inactive()->create(['group' => 'social', 'key' => 'instagram', 'value' => null]);

        $this->withToken($this->editorToken())->putJson('/api/cms/settings', $this->payload([
            ['group' => 'social', 'key' => 'instagram', 'value' => 'https://instagram.com/carltonsyria', 'type' => 'url'],
        ]))->assertOk();

        $setting = SiteSetting::where('key', 'instagram')->firstOrFail();

        $this->assertSame('https://instagram.com/carltonsyria', $setting->value);
        $this->assertFalse($setting->is_active, 'a hidden setting must stay hidden until someone says otherwise');

        // …and flips when the payload does say so.
        $this->withToken($this->editorToken())->putJson('/api/cms/settings', $this->payload([
            ['group' => 'social', 'key' => 'instagram', 'value' => 'https://instagram.com/carltonsyria', 'type' => 'url', 'is_active' => true],
        ]))->assertOk();

        $this->assertTrue(SiteSetting::where('key', 'instagram')->firstOrFail()->is_active);
    }

    /**
     * THE ATOMICITY PROOF.
     *
     * A settings form is one editorial act. If the write for row 2 fails, row 1
     * must not be live: a footer whose tagline updated and whose copyright did
     * not is a worse state than the one before the save, and the editor has no
     * way to see which half landed.
     *
     * The failure is injected with a model event rather than a mock, because the
     * point is to fail *inside* the loop, after the first row has already been
     * written — which is exactly the case a missing transaction gets wrong.
     * Remove `DB::transaction` from `UpsertSiteSettingsAction::handle()` and this
     * test goes red on the row count.
     */
    public function test_bulk_upsert_is_atomic(): void
    {
        SiteSetting::saving(function (SiteSetting $setting): void {
            if ($setting->key === 'explodes') {
                throw new RuntimeException('simulated mid-payload write failure');
            }
        });

        $this->withToken($this->editorToken())->putJson('/api/cms/settings', $this->payload([
            ['group' => 'footer', 'key' => 'tagline', 'value' => ['en' => 'Written first.', 'ar' => 'أولاً.'], 'type' => 'text'],
            ['group' => 'footer', 'key' => 'explodes', 'value' => 'boom', 'type' => 'text'],
            ['group' => 'footer', 'key' => 'copyright', 'value' => ['en' => 'Never reached.', 'ar' => 'لم يُصل.'], 'type' => 'text'],
        ]))
            ->assertStatus(500)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error_code', 'server_error');

        // Nothing at all — not even the row that had already been written when
        // the failure hit.
        $this->assertDatabaseCount('site_settings', 0);
        $this->assertDatabaseMissing('site_settings', ['key' => 'tagline']);
    }

    // ── Validation ────────────────────────────────────────────────────────

    public function test_an_invalid_type_is_rejected(): void
    {
        $this->withToken($this->editorToken())->putJson('/api/cms/settings', $this->payload([
            ['group' => 'footer', 'key' => 'tagline', 'value' => 'x', 'type' => 'markdown'],
        ]))
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error_code', 'validation_failed')
            ->assertJsonValidationErrors(['settings.0.type']);

        $this->assertDatabaseCount('site_settings', 0);
    }

    public function test_every_enum_type_is_accepted(): void
    {
        $settings = [];

        foreach (SettingType::values() as $i => $type) {
            $settings[] = ['group' => 'seo', 'key' => "sample_{$i}", 'value' => null, 'type' => $type];
        }

        $this->withToken($this->editorToken())->putJson('/api/cms/settings', ['settings' => $settings])->assertOk();

        $this->assertDatabaseCount('site_settings', count(SettingType::values()));
    }

    public function test_group_key_type_and_value_are_all_required(): void
    {
        $this->withToken($this->editorToken())
            ->putJson('/api/cms/settings', ['settings' => [[]]])
            ->assertStatus(422)
            ->assertJsonValidationErrors([
                'settings.0.group',
                'settings.0.key',
                'settings.0.type',
                // `present`, so omitting `value` is an error rather than a
                // silent blanking of the stored value.
                'settings.0.value',
            ]);
    }

    public function test_an_empty_settings_array_is_rejected(): void
    {
        $this->withToken($this->editorToken())
            ->putJson('/api/cms/settings', ['settings' => []])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['settings']);
    }

    /**
     * Two entries for one (group, key) means the client built the payload wrong.
     * The database cannot catch it — the upsert would just write the row twice —
     * so it would otherwise be a silent "last one wins".
     */
    public function test_a_payload_naming_the_same_group_and_key_twice_is_rejected(): void
    {
        $this->withToken($this->editorToken())->putJson('/api/cms/settings', $this->payload([
            ['group' => 'footer', 'key' => 'tagline', 'value' => 'first', 'type' => 'text'],
            ['group' => 'footer', 'key' => 'tagline', 'value' => 'second', 'type' => 'text'],
        ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['settings.1.key']);

        $this->assertDatabaseCount('site_settings', 0);

        // The same key under a different group is not a duplicate — `cta_label`
        // legitimately exists in both `booking` and `hero`.
        $this->withToken($this->editorToken())->putJson('/api/cms/settings', $this->payload([
            ['group' => 'booking', 'key' => 'cta_label', 'value' => 'Book Now', 'type' => 'text'],
            ['group' => 'hero', 'key' => 'cta_label', 'value' => 'Reserve Your Stay', 'type' => 'text'],
        ]))->assertOk();

        $this->assertDatabaseCount('site_settings', 2);
    }

    /**
     * The composite unique is the row's real identity. Without it two rows could
     * claim one key and `publicMap()` would non-deterministically pick a winner.
     */
    public function test_composite_unique_index_prevents_duplicate_group_and_key(): void
    {
        SiteSetting::factory()->create(['group' => 'contact', 'key' => 'phone']);

        // Same key, different group — allowed.
        SiteSetting::factory()->create(['group' => 'footer', 'key' => 'phone']);

        // Same group, different key — allowed.
        SiteSetting::factory()->create(['group' => 'contact', 'key' => 'email']);

        $this->expectException(QueryException::class);
        SiteSetting::factory()->create(['group' => 'contact', 'key' => 'phone']);
    }

    // ── Public endpoint ───────────────────────────────────────────────────

    /**
     * The public shape is a flat `{group: {key: value}}` map — NOT `{items, meta}`
     * and NOT paginated. This is a deliberate exception to the pagination
     * convention: the website funnels list responses through `normalizeList()`
     * (`src/app/api/envelope.ts`), which looks for `data.items` as an array and
     * silently returns an empty list for anything else. A paginated settings
     * response would therefore render a blank footer with nothing in the console.
     */
    public function test_public_response_is_a_flat_grouped_map_and_not_paginated(): void
    {
        SiteSetting::factory()->create(['group' => 'contact', 'key' => 'phone', 'value' => '+963 (0)11 000 00 00']);
        SiteSetting::factory()->create(['group' => 'footer', 'key' => 'tagline', 'value' => ['en' => 'A sanctuary.', 'ar' => 'ملاذ.']]);

        $res = $this->getJson('/api/public/settings')->assertOk();

        $res->assertJsonStructure(['success', 'message', 'request_id', 'data' => ['contact' => ['phone'], 'footer' => ['tagline']]]);

        $data = $res->json('data');

        // The two keys `normalizeList()` would look for are absent by design.
        $this->assertArrayNotHasKey('items', $data);
        $this->assertArrayNotHasKey('meta', $data);

        // Values sit directly under group.key — no per-row {key, value, type}
        // wrapper for the site to unpick.
        $this->assertSame('+963 (0)11 000 00 00', $data['contact']['phone']);
        $this->assertSame(['en' => 'A sanctuary.', 'ar' => 'ملاذ.'], $data['footer']['tagline']);
    }

    public function test_public_map_is_never_truncated_by_a_per_page_parameter(): void
    {
        SiteSetting::factory()->count(40)->create(['group' => 'contact']);

        $data = $this->getJson('/api/public/settings?per_page=2')->assertOk()->json('data');

        $this->assertCount(40, $data['contact']);
    }

    public function test_public_map_hides_inactive_settings(): void
    {
        SiteSetting::factory()->create(['group' => 'contact', 'key' => 'phone', 'value' => 'published']);
        SiteSetting::factory()->inactive()->create(['group' => 'contact', 'key' => 'fax', 'value' => 'hidden']);
        SiteSetting::factory()->inactive()->create(['group' => 'social', 'key' => 'instagram', 'value' => null]);

        $data = $this->getJson('/api/public/settings')->assertOk()->json('data');

        $this->assertSame(['phone' => 'published'], $data['contact']);
        // A group whose every row is a draft does not appear at all — an empty
        // object would make the site render an empty social bar.
        $this->assertArrayNotHasKey('social', $data);
    }

    public function test_public_endpoint_needs_no_authentication(): void
    {
        SiteSetting::factory()->create(['group' => 'contact', 'key' => 'phone', 'value' => 'x']);

        $this->getJson('/api/public/settings')->assertOk()->assertJsonPath('success', true);
    }

    /**
     * The seeder carries hand-picked trilingual copy lifted from the live site
     * that no factory exercises. Without this, a typo in it only surfaces when
     * someone runs `migrate --seed`.
     */
    public function test_seeder_loads_the_real_site_copy(): void
    {
        $this->seed(\Database\Seeders\CmsContentSeeder::class);

        $this->assertSame(20, SiteSetting::count());
        $this->assertSame(
            ['booking', 'contact', 'footer', 'hero', 'seo', 'social'],
            SiteSetting::query()->distinct()->orderBy('group')->pluck('group')->all(),
        );

        // Scalar settings stay scalar: the phone number the site hardcodes as a
        // `tel:` href in Navigation, Footer, LocationPage and SupportPage.
        $this->assertSame(
            '+963 (0)11 000 00 00',
            SiteSetting::where(['group' => 'contact', 'key' => 'phone'])->value('value'),
        );
        $this->assertSame(
            'reservations@carltonsyria.com',
            SiteSetting::where(['group' => 'contact', 'key' => 'email'])->value('value'),
        );

        // Translated settings carry en/ar/fr — all three exist as human copy on
        // the site. tr/es are left to editors, never machine-translated.
        $tagline = SiteSetting::where(['group' => 'footer', 'key' => 'tagline'])->value('value');
        foreach (['en', 'ar', 'fr'] as $locale) {
            $this->assertNotEmpty($tagline[$locale] ?? null, "seeded footer tagline is missing {$locale}");
        }
        $this->assertNotSame($tagline['en'], $tagline['fr']);

        // The `json` widget type round-trips a nested list per locale.
        $lines = SiteSetting::where(['group' => 'contact', 'key' => 'address_lines'])->value('value');
        $this->assertSame(['Kafr Sousa', 'Damascus', 'Syrian Arab Republic'], $lines['en']);

        // The four social slots are seeded but inactive — the site's Footer.tsx
        // still has `href: "#"`, so there is no real handle to publish.
        $this->assertSame(4, SiteSetting::where('group', 'social')->where('is_active', false)->count());

        $data = $this->getJson('/api/public/settings')->assertOk()->json('data');

        $this->assertSame('+963 (0)11 000 00 00', $data['contact']['phone']);
        $this->assertSame('Book Now', $data['booking']['cta_label']['en']);
        $this->assertSame('الفندق الفاخر', $data['hero']['eyebrow']['ar']);
        $this->assertArrayNotHasKey('social', $data, 'the unpublished social slots must not reach the site');
    }
}
