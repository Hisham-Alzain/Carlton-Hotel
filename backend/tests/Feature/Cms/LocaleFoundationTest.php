<?php

namespace Tests\Feature\Cms;

use App\Models\DiningVenue;
use App\Models\Page;
use App\Models\RoomType;
use App\Models\User;
use App\Support\TranslatableRules;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

/**
 * Phase 0 — locale foundation.
 *
 * Translatable CMS fields are JSON columns, so the locale set lives in
 * `config/cms.php` rather than in each FormRequest. These tests pin the three
 * halves of that contract: existing `en`/`ar` clients keep working unchanged,
 * the additional locales are accepted, stored and served, and a misconfigured
 * locale list fails loudly instead of quietly disabling validation.
 */
class LocaleFoundationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A representative sample of real translated content per locale. These are
     * literal strings on purpose: comparing a locale against itself (or merely
     * counting keys) still passes with the whole `lang/<locale>` directory
     * deleted, because Laravel silently falls back to `APP_FALLBACK_LOCALE`.
     */
    private const SAMPLE_TRANSLATIONS = [
        'en' => [
            'custom.errors.not_found'         => 'Resource not found.',
            'custom.errors.forbidden'         => 'You do not have permission to perform this action.',
            'custom.errors.validation_failed' => 'The given data was invalid.',
            'custom.messages.success'         => 'Success.',
            'custom.messages.created'         => 'Created successfully.',
            'custom.auth.otp_sent'            => 'A verification code has been sent.',
        ],
        'ar' => [
            'custom.errors.not_found'         => 'المورد غير موجود.',
            'custom.errors.forbidden'         => 'ليس لديك صلاحية لتنفيذ هذا الإجراء.',
            'custom.errors.validation_failed' => 'البيانات المدخلة غير صالحة.',
            'custom.messages.success'         => 'تمت العملية بنجاح.',
            'custom.messages.created'         => 'تم الإنشاء بنجاح.',
            'custom.auth.otp_sent'            => 'تم إرسال رمز التحقق.',
        ],
        'fr' => [
            'custom.errors.not_found'         => 'Ressource introuvable.',
            'custom.errors.forbidden'         => "Vous n'avez pas l'autorisation d'effectuer cette action.",
            'custom.errors.validation_failed' => 'Les données fournies sont invalides.',
            'custom.messages.success'         => 'Opération réussie.',
            'custom.messages.created'         => 'Créé avec succès.',
            'custom.auth.otp_sent'            => 'Un code de vérification a été envoyé.',
        ],
        'tr' => [
            'custom.errors.not_found'         => 'Kayıt bulunamadı.',
            'custom.errors.forbidden'         => 'Bu işlemi gerçekleştirme izniniz yok.',
            'custom.errors.validation_failed' => 'Girilen veriler geçersiz.',
            'custom.messages.success'         => 'İşlem başarılı.',
            'custom.messages.created'         => 'Başarıyla oluşturuldu.',
            'custom.auth.otp_sent'            => 'Doğrulama kodu gönderildi.',
        ],
        'es' => [
            'custom.errors.not_found'         => 'Recurso no encontrado.',
            'custom.errors.forbidden'         => 'No tiene permiso para realizar esta acción.',
            'custom.errors.validation_failed' => 'Los datos proporcionados no son válidos.',
            'custom.messages.success'         => 'Operación realizada con éxito.',
            'custom.messages.created'         => 'Creado correctamente.',
            'custom.auth.otp_sent'            => 'Se ha enviado un código de verificación.',
        ],
    ];

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

    // ── Misconfiguration must fail LOUD and CLOSED ────────────────────────
    //
    // `config('cms.locales')` is the single source of truth. No consumer keeps a
    // divergent hardcoded fallback, so a bad list must stop the request rather
    // than quietly validate against a locale set nobody configured.

    public function test_required_locale_outside_the_locale_set_fails_closed(): void
    {
        // Previously this was intersected away, leaving an empty required set —
        // every locale became `nullable` and a create with no `name` at all passed.
        config(['cms.required_locales' => ['de']]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/required_locales/');

        TranslatableRules::for('name', ['string', 'max:255']);
    }

    public function test_a_misconfigured_required_locale_cannot_wave_content_past_validation(): void
    {
        config(['cms.required_locales' => ['de']]);

        // Intersecting `['de']` away left an empty required set, so `en` and `ar`
        // became `nullable` and this French-only payload was accepted (201).
        $this->withToken($this->editorToken())
            ->postJson('/api/cms/room-types', $this->roomTypePayload([
                'name'        => ['fr' => 'Suite de luxe'],
                'description' => ['fr' => 'Suite spacieuse'],
            ]))
            ->assertStatus(500)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error_code', 'server_error');

        $this->assertDatabaseCount('room_types', 0);
    }

    public function test_missing_locale_config_throws_instead_of_falling_back(): void
    {
        config(['cms.locales' => null]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/single source of truth/');

        TranslatableRules::locales();
    }

    #[DataProvider('invalidLocaleCodeProvider')]
    public function test_an_invalid_locale_code_never_reaches_a_validation_rule_key(string $code): void
    {
        // `*` would produce the wildcard key `name.*` (applying `required` to
        // every locale sent); `pt.BR` would produce the nested path `name.pt.BR`.
        config(['cms.locales' => ['en', 'ar', $code]]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/invalid locale code/');

        TranslatableRules::for('name', ['string', 'max:255']);
    }

    public static function invalidLocaleCodeProvider(): array
    {
        return [
            'wildcard'      => ['*'],
            'dotted'        => ['pt.BR'],
            'nested path'   => ['en.name'],
            'blank'         => [' '],
            'sql fragment'  => ["en')--"],
        ];
    }

    public function test_a_regional_locale_code_is_still_accepted(): void
    {
        config(['cms.locales' => ['en', 'ar', 'pt_BR', 'zh-Hans']]);

        $this->assertSame(
            ['name.en', 'name.ar', 'name.pt_BR', 'name.zh-Hans'],
            array_keys(TranslatableRules::for('name', ['string'])),
        );
    }

    // ── The config file itself sanitises at the boundary ──────────────────

    public function test_config_file_poisons_a_locale_list_containing_an_invalid_code(): void
    {
        // Narrowing `en,ar,*` to `['en','ar']` would be a silent degradation to a
        // locale set nobody configured, so the whole list is rejected instead.
        $this->assertSame([], $this->cmsConfigWith(['CMS_LOCALES' => 'en,ar,*'])['locales']);
        $this->assertSame([], $this->cmsConfigWith(['CMS_LOCALES' => 'en,pt.BR'])['locales']);
        $this->assertSame(
            [],
            $this->cmsConfigWith(['CMS_REQUIRED_LOCALES' => 'en,*'])['required_locales'],
        );
    }

    public function test_config_file_keeps_valid_env_lists_and_the_shipped_defaults(): void
    {
        $this->assertSame(
            ['en', 'ar', 'fr', 'tr', 'es', 'de'],
            $this->cmsConfigWith(['CMS_LOCALES' => 'en, ar ,fr,tr,es,de'])['locales'],
        );
        $this->assertSame(['en', 'ar', 'fr', 'tr', 'es'], $this->cmsConfigWith([])['locales']);
    }

    /**
     * Re-evaluate `config/cms.php` with the given env vars in place.
     *
     * @param  array<string, string>  $env
     * @return array<string, list<string>>
     */
    private function cmsConfigWith(array $env): array
    {
        foreach ($env as $key => $value) {
            $_ENV[$key] = $_SERVER[$key] = $value;
            putenv("{$key}={$value}");
        }

        try {
            return require base_path('config/cms.php');
        } finally {
            foreach (array_keys($env) as $key) {
                unset($_ENV[$key], $_SERVER[$key]);
                putenv($key);
            }
        }
    }

    // ── Locale resolution from a real Accept-Language header ──────────────

    public function test_a_real_browser_accept_language_header_resolves_the_language(): void
    {
        // What Chrome actually sends. A strict `in_array()` against the raw
        // header never matches this, so every browser silently got `en`.
        $this->withHeaders(['Accept-Language' => 'fr-FR,fr;q=0.9,en-US;q=0.8,en;q=0.7'])
            ->getJson('/api/public/room-types')
            ->assertOk();

        $this->assertSame('fr', app()->getLocale());
    }

    public function test_quality_values_decide_which_supported_locale_wins(): void
    {
        // German is preferred but unsupported; Turkish outranks English.
        $this->withHeaders(['Accept-Language' => 'de-DE,de;q=0.9,tr;q=0.8,en;q=0.5'])
            ->getJson('/api/public/room-types')
            ->assertOk();

        $this->assertSame('tr', app()->getLocale());
    }

    public function test_a_bare_locale_tag_still_resolves(): void
    {
        $this->withHeaders(['Accept-Language' => 'ar'])
            ->getJson('/api/public/room-types')
            ->assertOk();

        $this->assertSame('ar', app()->getLocale());
    }

    public function test_an_unsupported_language_falls_back_to_the_app_locale(): void
    {
        // The fallback is `config('app.locale')`, not a hardcoded 'en'.
        config(['app.locale' => 'ar']);

        $this->withHeaders(['Accept-Language' => 'de-DE,de;q=0.9,ja;q=0.8'])
            ->getJson('/api/public/room-types')
            ->assertOk();

        $this->assertSame('ar', app()->getLocale());
    }

    public function test_an_empty_accept_language_header_falls_back_to_the_app_locale(): void
    {
        // Symfony's Request::create() injects a default `en-us,en;q=0.5`, so an
        // absent header is simulated by sending an empty one.
        config(['app.locale' => 'es']);

        $this->withHeaders(['Accept-Language' => ''])
            ->getJson('/api/public/room-types')
            ->assertOk();

        $this->assertSame('es', app()->getLocale());
    }

    public function test_an_app_locale_outside_the_supported_set_degrades_to_the_first_supported(): void
    {
        config(['app.locale' => 'de']);

        $this->withHeaders(['Accept-Language' => 'ja-JP'])
            ->getJson('/api/public/room-types')
            ->assertOk();

        $this->assertSame('en', app()->getLocale());
        $this->assertContains(app()->getLocale(), config('cms.locales'));
    }

    // ── The lang/ files actually exist and actually differ ────────────────

    #[DataProvider('localeProvider')]
    public function test_each_locale_serves_its_own_translated_strings(string $locale): void
    {
        foreach (self::SAMPLE_TRANSLATIONS[$locale] as $key => $expected) {
            $this->assertSame(
                $expected,
                trans($key, [], $locale),
                "lang/{$locale} does not provide [{$key}]; Laravel fell back to "
                . config('app.fallback_locale') . '.',
            );

            if ($locale !== 'en') {
                $this->assertNotSame(
                    self::SAMPLE_TRANSLATIONS['en'][$key],
                    trans($key, [], $locale),
                    "lang/{$locale} returns the English string for [{$key}].",
                );
            }
        }
    }

    #[DataProvider('localeProvider')]
    public function test_every_configured_locale_ships_the_full_custom_key_set(string $locale): void
    {
        $path = lang_path("{$locale}/custom.php");
        $this->assertFileExists($path, "lang/{$locale}/custom.php is missing.");

        $reference = array_keys($this->flattenLangFile('en'));
        $actual    = array_keys($this->flattenLangFile($locale));

        $this->assertNotSame([], $reference);
        $this->assertSame(
            [],
            array_values(array_diff($reference, $actual)),
            "lang/{$locale}/custom.php is missing keys present in lang/en.",
        );
        $this->assertSame(
            [],
            array_values(array_diff($actual, $reference)),
            "lang/{$locale}/custom.php declares keys that lang/en does not.",
        );
    }

    public function test_accept_language_localises_the_error_envelope_end_to_end(): void
    {
        // Two 404s that reach the handler from deliberately different places:
        //
        // - `/public/pages/{slug}` is looked up inside the controller, so the
        //   exception is thrown after the whole middleware stack has run.
        // - `/public/room-types/{roomType}` is implicit route-model binding, so
        //   Laravel's own SubstituteBindings raises the 404. While SetLocale was
        //   *appended* to the `api` group it sat after SubstituteBindings and
        //   never ran for this route, so the message came back in the default
        //   locale however the client spelled Accept-Language.
        $routes = [
            'controller-raised' => '/api/public/pages/no-such-page-' . Str::random(8),
            'binding-raised'    => '/api/public/room-types/' . Str::uuid(),
        ];

        $headers = ['fr' => 'fr-FR,fr;q=0.9', 'tr' => 'tr-TR,tr;q=0.9', 'es' => 'es-ES,es;q=0.9'];

        foreach ($routes as $origin => $url) {
            foreach ($headers as $locale => $header) {
                $res = $this->withHeaders(['Accept-Language' => $header])
                    ->getJson($url)
                    ->assertStatus(404)
                    ->assertJsonPath('error_code', 'not_found');

                $this->assertSame(
                    self::SAMPLE_TRANSLATIONS[$locale]['custom.errors.not_found'],
                    $res->json('message'),
                    "A {$origin} 404 ignored `Accept-Language: {$header}`.",
                );
            }
        }
    }

    public function test_a_binding_raised_404_still_carries_the_request_id_header(): void
    {
        // AttachRequestId was appended after SubstituteBindings too, so a
        // binding failure short-circuited it: the envelope's `request_id` was a
        // throwaway uuid minted by the handler and no header matched it, which
        // is precisely the response support most needs to trace.
        $res = $this->getJson('/api/public/room-types/' . Str::uuid())->assertStatus(404);

        $this->assertNotEmpty($res->headers->get('X-Request-Id'));
        $this->assertSame($res->headers->get('X-Request-Id'), $res->json('request_id'));
    }

    // ── Filter rejections localise `errors`, not just `message` ───────────
    //
    // `BaseFilter` turns an uninterpretable query value into the standard
    // `validation_failed` envelope. Its messages used to resolve against
    // Laravel's built-in `validation.*` keys, which ship only in `lang/en`, so
    // a single 422 came back with a localized `message` beside English
    // `errors` text.

    public function test_a_boolean_filter_rejection_localises_the_errors_text(): void
    {
        $token = $this->editorToken();
        $url   = '/api/cms/room-types?is_active=trve';

        $en = $this->withToken($token)->withHeaders(['Accept-Language' => 'en-US,en;q=0.9'])
            ->getJson($url)
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'validation_failed');

        $ar = $this->withToken($token)->withHeaders(['Accept-Language' => 'ar'])
            ->getJson($url)
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'validation_failed');

        $this->assertSame(
            self::SAMPLE_TRANSLATIONS['ar']['custom.errors.validation_failed'],
            $ar->json('message'),
        );
        $this->assertNotSame(
            $en->json('errors.is_active.0'),
            $ar->json('errors.is_active.0'),
            'The filter rejection returned the same `errors` text for en and ar.',
        );
        $this->assertMatchesRegularExpression('/\p{Arabic}/u', (string) $ar->json('errors.is_active.0'));
    }

    public function test_an_integer_filter_rejection_localises_the_errors_text(): void
    {
        $token = $this->editorToken();
        $url   = '/api/cms/event-spaces?capacity=abc';

        $en = $this->withToken($token)->withHeaders(['Accept-Language' => 'en-US,en;q=0.9'])
            ->getJson($url)
            ->assertStatus(422);

        $ar = $this->withToken($token)->withHeaders(['Accept-Language' => 'ar'])
            ->getJson($url)
            ->assertStatus(422);

        $this->assertNotSame(
            $en->json('errors.capacity.0'),
            $ar->json('errors.capacity.0'),
            'The filter rejection returned the same `errors` text for en and ar.',
        );
        $this->assertMatchesRegularExpression('/\p{Arabic}/u', (string) $ar->json('errors.capacity.0'));
    }

    #[DataProvider('localeProvider')]
    public function test_every_locale_translates_the_filter_rejection_messages(string $locale): void
    {
        foreach (['boolean', 'integer', 'string'] as $rule) {
            $key       = "custom.validation.{$rule}";
            $translated = trans($key, ['attribute' => 'is_active'], $locale);

            $this->assertNotSame($key, $translated, "lang/{$locale} does not provide [{$key}].");

            if ($locale !== 'en') {
                $this->assertNotSame(
                    trans($key, ['attribute' => 'is_active'], 'en'),
                    $translated,
                    "lang/{$locale} returns the English string for [{$key}].",
                );
            }
        }
    }

    public function test_accept_language_localises_the_forbidden_envelope_end_to_end(): void
    {
        $user = User::factory()->create();

        $this->withToken($user->createToken('t')->plainTextToken)
            ->withHeaders(['Accept-Language' => 'fr-FR,fr;q=0.9,en;q=0.8'])
            ->postJson('/api/cms/room-types', $this->roomTypePayload())
            ->assertStatus(403)
            ->assertJsonPath('error_code', 'forbidden')
            ->assertJsonPath('message', self::SAMPLE_TRANSLATIONS['fr']['custom.errors.forbidden']);
    }

    public function test_accept_language_localises_the_validation_envelope_end_to_end(): void
    {
        $this->withToken($this->editorToken())
            ->withHeaders(['Accept-Language' => 'es-ES,es;q=0.9,en;q=0.8'])
            ->postJson('/api/cms/room-types', $this->roomTypePayload(['name' => ['en' => 'Only English']]))
            ->assertStatus(422)
            ->assertJsonPath('message', self::SAMPLE_TRANSLATIONS['es']['custom.errors.validation_failed']);
    }

    public static function localeProvider(): array
    {
        return [
            'en' => ['en'],
            'ar' => ['ar'],
            'fr' => ['fr'],
            'tr' => ['tr'],
            'es' => ['es'],
        ];
    }

    /**
     * @return array<string, string>
     */
    private function flattenLangFile(string $locale): array
    {
        $path = lang_path("{$locale}/custom.php");

        if (! is_file($path)) {
            return [];
        }

        return $this->flatten(require $path);
    }

    /**
     * @param  array<string, mixed>  $lines
     * @return array<string, string>
     */
    private function flatten(array $lines, string $prefix = ''): array
    {
        $flat = [];

        foreach ($lines as $key => $value) {
            $path = $prefix === '' ? (string) $key : "{$prefix}.{$key}";

            if (is_array($value)) {
                $flat += $this->flatten($value, $path);

                continue;
            }

            $flat[$path] = (string) $value;
        }

        return $flat;
    }
}
