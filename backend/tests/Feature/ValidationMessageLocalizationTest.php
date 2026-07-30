<?php

namespace Tests\Feature;

use App\Base\BaseRequest;
use App\Models\RoomType;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Contracts\Validation\InvokableRule;
use Illuminate\Contracts\Validation\Rule as RuleContract;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionClass;
use Tests\TestCase;

/**
 * The `errors` half of a 422 must be a sentence in the caller's language.
 *
 * `BaseRequest::messages()` routes every FormRequest rule through
 * `custom.validation.*`, which exists in all five locales. Any rule it did *not*
 * map fell through to Laravel's own `validation.php` — which this project ships
 * only in `lang/en` — and because `.env` sets `APP_FALLBACK_LOCALE=ar`, the
 * lookup found nothing and the translator returned the key itself. An
 * ar/fr/tr/es client asking for a room type with `base_price_usd: "abc"` got
 * `{"base_price_usd": ["validation.numeric"]}`: a localized `message` beside an
 * untranslated `errors` map, in a response that had already negotiated a locale.
 *
 * `lang/{ar,fr,tr,es}/validation.php` used to paper over three of those keys for
 * the filter layer. `BaseFilter` now asks for `custom.validation.*` like every
 * other layer, so the bridge is gone and this class is what keeps it unneeded.
 *
 * Assertions are deliberately not `assertNotSame($en, $ar)` — a raw key is the
 * same string in both locales *and* differs from the English sentence, so that
 * comparison passes on precisely the bug. These tests reject anything shaped
 * like a translation key and require Arabic script in the Arabic payload.
 */
class ValidationMessageLocalizationTest extends TestCase
{
    use RefreshDatabase;

    /** Every locale `config/cms.php` accepts. */
    private const LOCALES = ['en', 'ar', 'fr', 'tr', 'es'];

    /**
     * Rules that never produce a message, so they need no translation:
     * presence modifiers and the `exclude*` family.
     *
     * @var list<string>
     */
    private const SILENT_RULES = [
        'nullable', 'sometimes', 'bail',
        'exclude', 'exclude_if', 'exclude_unless', 'exclude_with', 'exclude_without',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function editorToken(): string
    {
        return User::factory()->create()->givePermissionTo('cms.edit')
            ->createToken('t')->plainTextToken;
    }

    /**
     * @return array<string, array{string}>
     */
    public static function localeProvider(): array
    {
        return array_combine(
            self::LOCALES,
            array_map(static fn (string $locale): array => [$locale], self::LOCALES),
        );
    }

    /**
     * Every message in an `errors` map must be a sentence, never a key — and in
     * Arabic it must actually be written in Arabic.
     *
     * @param  array<string, mixed>  $errors
     * @param  list<string>          $expectedFields
     */
    private function assertErrorsAreLocalized(
        ?array $errors,
        string $locale,
        array $expectedFields,
        string $context,
    ): void {
        $this->assertIsArray($errors, "{$context} [{$locale}]: the envelope carried no `errors` map.");

        foreach ($expectedFields as $field) {
            $this->assertArrayHasKey($field, $errors, "{$context} [{$locale}]: expected an error for [{$field}].");
        }

        $flat = [];
        array_walk_recursive($errors, static function ($message) use (&$flat): void {
            $flat[] = (string) $message;
        });

        $this->assertNotEmpty($flat, "{$context} [{$locale}]: `errors` was empty.");

        foreach ($flat as $message) {
            $this->assertDoesNotMatchRegularExpression(
                '/^(custom\.)?validation\./',
                $message,
                "{$context} [{$locale}]: returned the raw translation key \"{$message}\" where a sentence belongs.",
            );

            if ($locale === 'ar') {
                $this->assertMatchesRegularExpression(
                    '/\p{Arabic}/u',
                    $message,
                    "{$context} [ar]: \"{$message}\" contains no Arabic script.",
                );
            }
        }
    }

    // ── Rule families, end to end through real endpoints ──────────────────

    /**
     * `numeric`, `array`, `integer`, `gte` and `Rule::enum` in one request —
     * none of them were mapped before, and `gte`/`enum` are the two Laravel
     * resolves through code paths that ignore a short-named custom message.
     */
    #[DataProvider('localeProvider')]
    public function test_room_type_rule_rejections_are_translated(string $locale): void
    {
        $res = $this->withToken($this->editorToken())
            ->withHeaders(['Accept-Language' => $locale])
            ->postJson('/api/cms/room-types', [
                'name'           => ['en' => 'Suite', 'ar' => 'جناح'],
                'description'    => ['en' => 'A suite.', 'ar' => 'جناح.'],
                'amenities'      => 'not-an-array',
                'view_type'      => 'no-such-view',
                'base_occupancy' => 4,
                'max_occupancy'  => 2,          // gte:base_occupancy
                'size_sqm'       => 'wide',     // numeric
                'base_price_usd' => 'expensive',// numeric
                'sort_order'     => 'first',    // integer
            ])
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'validation_failed');

        $this->assertErrorsAreLocalized(
            $res->json('errors'),
            $locale,
            ['amenities', 'view_type', 'max_occupancy', 'size_sqm', 'base_price_usd', 'sort_order'],
            'POST /api/cms/room-types',
        );

        // `Rule::enum()` needs its own assertion: Laravel 13's Enum rule falls
        // back to a *hardcoded English* sentence when `validation.enum` is
        // missing, so it never trips the raw-key check above — it just answers
        // an Arabic client in English. Only the class-keyed entry in
        // BaseRequest::messages() makes this line pass.
        $this->assertSame(
            trans('custom.validation.enum', ['attribute' => 'view type'], $locale),
            $res->json('errors.view_type.0'),
            "POST /api/cms/room-types [{$locale}]: the enum rejection is not localized.",
        );
    }

    /** `date`, `after_or_equal` and `after` — the booking date rules. */
    #[DataProvider('localeProvider')]
    public function test_date_rule_rejections_are_translated(string $locale): void
    {
        $roomType = RoomType::factory()->create();

        $res = $this->withHeaders(['Accept-Language' => $locale])
            ->getJson('/api/public/availability?' . http_build_query([
                'room_type_uuid' => $roomType->uuid,
                'check_in'       => '2000-01-01',   // after_or_equal:today
                'check_out'      => 'not-a-date',   // date
            ]))
            ->assertStatus(422);

        $this->assertErrorsAreLocalized(
            $res->json('errors'),
            $locale,
            ['check_in', 'check_out'],
            'GET /api/public/availability',
        );
    }

    /** `file`, `image` and `mimes` — the media upload contract. */
    #[DataProvider('localeProvider')]
    public function test_upload_rule_rejections_are_translated(string $locale): void
    {
        Storage::fake('public');
        $roomType = RoomType::factory()->create();

        $res = $this->withToken($this->editorToken())
            ->withHeaders(['Accept-Language' => $locale])
            ->postJson("/api/cms/room-types/{$roomType->uuid}/images", [
                'image' => UploadedFile::fake()->create('handbook.pdf', 12, 'application/pdf'),
            ])
            ->assertStatus(422);

        $this->assertErrorsAreLocalized(
            $res->json('errors'),
            $locale,
            ['image'],
            'POST /api/cms/room-types/{uuid}/images',
        );
    }

    /** `size` — the six-digit OTP. Reached without a real reservation. */
    #[DataProvider('localeProvider')]
    public function test_size_rule_rejection_is_translated(string $locale): void
    {
        $res = $this->withHeaders(['Accept-Language' => $locale])
            ->postJson('/api/reservations/guest/verify', [
                'reservation_uuid' => (string) Str::uuid(),
                'otp_code'         => '123',
            ])
            ->assertStatus(422);

        $this->assertErrorsAreLocalized(
            $res->json('errors'),
            $locale,
            ['otp_code'],
            'POST /api/reservations/guest/verify',
        );
    }

    /** `present` and `regex` — the settings upsert. */
    #[DataProvider('localeProvider')]
    public function test_settings_rule_rejections_are_translated(string $locale): void
    {
        $res = $this->withToken($this->editorToken())
            ->withHeaders(['Accept-Language' => $locale])
            ->putJson('/api/cms/settings', [
                'settings' => [
                    ['group' => 'Not A Group', 'key' => 'hero_title', 'type' => 'text'],
                ],
            ])
            ->assertStatus(422);

        $this->assertErrorsAreLocalized(
            $res->json('errors'),
            $locale,
            ['settings.0.group', 'settings.0.value'],
            'PUT /api/cms/settings',
        );
    }

    // ── The map itself ───────────────────────────────────────────────────

    /**
     * Every rule `BaseRequest::messages()` maps must resolve to a real string in
     * all five locales — asserted against the key, so a missing entry (which the
     * translator echoes back as the key) fails here rather than in a client.
     */
    #[DataProvider('localeProvider')]
    public function test_every_mapped_rule_has_a_translation(string $locale): void
    {
        foreach ($this->mappedRules() as $rule) {
            $key        = "custom.validation.{$rule}";
            $translated = trans($key, ['attribute' => 'field'], $locale);

            $this->assertNotSame($key, $translated, "lang/{$locale} does not provide [{$key}].");

            if ($locale !== 'en') {
                $this->assertNotSame(
                    trans($key, ['attribute' => 'field'], 'en'),
                    $translated,
                    "lang/{$locale} returns the English string for [{$key}].",
                );
            }
        }
    }

    /**
     * The audit, as an executable guard: instantiate every FormRequest, read its
     * real `rules()`, and require each rule that can produce a message to be
     * mapped. Adding `'digits_between:2,4'` to a request without a
     * `custom.validation.digits_between` entry fails here — before a client sees
     * `"validation.digits_between"`.
     */
    public function test_every_rule_reachable_from_a_form_request_is_mapped(): void
    {
        $mapped = $this->mappedRules();
        $found  = [];

        foreach ($this->formRequestClasses() as $class) {
            $request = $class::create('/', 'POST');
            $request->setContainer($this->app);

            foreach ($request->rules() as $ruleList) {
                $list = is_string($ruleList) ? explode('|', $ruleList) : (array) $ruleList;

                foreach ($list as $rule) {
                    // Mirrors ValidationRuleParser::prepareRule. In/Unique/Exists
                    // stringify to `in:…`/`unique:…` and are validated as string
                    // rules; a Rule/ValidationRule object keeps its identity,
                    // fails with its own message, and is looked up by class name
                    // (only Rule::enum() here) — see BaseRequest::messages().
                    if (is_object($rule)) {
                        if ($rule instanceof RuleContract
                            || $rule instanceof ValidationRule
                            || $rule instanceof InvokableRule) {
                            $found[Str::snake(class_basename($rule))] = $class;
                        }

                        continue;
                    }

                    if (! is_string($rule) || $rule === '') {
                        continue;
                    }

                    $name = strtolower(explode(':', $rule)[0]);

                    if ($name !== '' && ! in_array($name, self::SILENT_RULES, true)) {
                        $found[$name] = $class;
                    }
                }
            }
        }

        $this->assertNotEmpty($found, 'No FormRequest rules were discovered — the scan is broken.');

        $unmapped = array_diff_key($found, array_flip($mapped));

        $this->assertSame(
            [],
            $unmapped,
            'These validation rules are reachable but absent from BaseRequest::messages(), so they '
            . "return a raw key in ar/fr/tr/es:\n"
            . implode("\n", array_map(
                static fn (string $rule, string $class): string => "  {$rule}  (e.g. {$class})",
                array_keys($unmapped),
                $unmapped,
            )),
        );
    }

    // ── The filter layer reads the same keys ─────────────────────────────

    /**
     * `BaseFilter` rejected an uninterpretable query value with Laravel's own
     * `validation.boolean`/`integer`/`string` keys. Asserted against the exact
     * `custom.validation.*` string, so pointing it back at `validation.*` fails
     * even in English — where the bridge files never existed and the old keys
     * still resolve.
     */
    #[DataProvider('localeProvider')]
    public function test_the_filter_layer_reads_custom_validation_keys(string $locale): void
    {
        $token = $this->editorToken();

        $boolean = $this->withToken($token)->withHeaders(['Accept-Language' => $locale])
            ->getJson('/api/cms/room-types?is_active=trve')
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'validation_failed');

        $this->assertSame(
            trans('custom.validation.boolean', ['attribute' => 'is_active'], $locale),
            $boolean->json('errors.is_active.0'),
        );

        $integer = $this->withToken($token)->withHeaders(['Accept-Language' => $locale])
            ->getJson('/api/cms/event-spaces?capacity=abc')
            ->assertStatus(422);

        $this->assertSame(
            trans('custom.validation.integer', ['attribute' => 'capacity'], $locale),
            $integer->json('errors.capacity.0'),
        );

        // `?author_name[like][]=x` is the array-into-a-scalar-operator case.
        $string = $this->withToken($token)->withHeaders(['Accept-Language' => $locale])
            ->getJson('/api/cms/testimonials?' . http_build_query(['author_name' => ['like' => ['nested']]]))
            ->assertStatus(422);

        $this->assertSame(
            trans('custom.validation.string', ['attribute' => 'author_name.like'], $locale),
            $string->json('errors')['author_name.like'][0] ?? null,
        );
    }

    /**
     * The bridge is gone and must stay gone. It restated three keys per locale
     * next to `custom.php`; a re-added file would let the two spellings drift
     * and quietly hide a `BaseFilter` regression from the assertions above.
     */
    public function test_the_locale_validation_bridge_files_are_gone(): void
    {
        foreach (['ar', 'fr', 'tr', 'es'] as $locale) {
            $this->assertFileDoesNotExist(
                lang_path("{$locale}/validation.php"),
                "lang/{$locale}/validation.php is back. Filter and request messages both come from "
                . 'custom.validation.* now — put the string there instead.',
            );
        }

        // lang/en/validation.php stays: it is Laravel's own file and still backs
        // anything outside this project's request/filter layers.
        $this->assertFileExists(lang_path('en/validation.php'));
    }

    // ── helpers ──────────────────────────────────────────────────────────

    /**
     * Rule names mapped by `BaseRequest::messages()`, with the `Rule::enum()`
     * class key folded back to `enum`.
     *
     * @return list<string>
     */
    private function mappedRules(): array
    {
        $request = new class extends BaseRequest
        {
            public function rules(): array
            {
                return [];
            }
        };
        $request->setContainer($this->app);

        return array_values(array_map(
            static fn (string $key): string => class_exists($key) ? Str::snake(class_basename($key)) : $key,
            array_keys($request->messages()),
        ));
    }

    /**
     * @return list<class-string<BaseRequest>>
     */
    private function formRequestClasses(): array
    {
        $root  = app_path('Http' . DIRECTORY_SEPARATOR . 'Requests');
        $found = [];

        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($items as $file) {
            /** @var \SplFileInfo $file */
            if (! $file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $relative = substr($file->getPathname(), strlen($root) + 1, -4);
            $class    = 'App\\Http\\Requests\\' . str_replace(DIRECTORY_SEPARATOR, '\\', $relative);

            if (! class_exists($class)) {
                continue;
            }

            $reflection = new ReflectionClass($class);

            if ($reflection->isAbstract() || ! $reflection->isSubclassOf(BaseRequest::class)) {
                continue;
            }

            $found[] = $class;
        }

        $this->assertNotEmpty($found, 'No FormRequest classes found under app/Http/Requests.');

        return $found;
    }
}
