<?php

namespace App\Support;

use RuntimeException;

/**
 * Reads the CMS locale set out of `config/cms.php` and builds per-locale
 * validation rules for `HasTranslations` fields from it, so adding a locale is
 * a one-line config change instead of an edit to every FormRequest under
 * `App\Http\Requests\Cms`.
 *
 * Rules come back keyed by `field.locale`, ready to spread into `rules()`:
 *
 *     return [
 *         ...TranslatableRules::for('name', ['string', 'max:255']),
 *         ...TranslatableRules::optional('hours', ['string', 'max:255']),
 *     ];
 *
 * Locales listed in `cms.required_locales` are mandatory; every other locale in
 * `cms.locales` is `nullable`, so new languages never break an existing client.
 *
 * `config('cms.locales')` is the single source of truth for which locales
 * exist. This class carries NO fallback list: a missing, empty, or malformed
 * config is a misconfiguration and throws, because the alternatives both fail
 * open — an empty locale set makes every field `nullable` (silently disabling
 * required-field enforcement), and a substituted default validates content
 * against a locale set nobody configured.
 */
final class TranslatableRules
{
    /**
     * A locale code becomes a validation rule key (`name.en`), so it must not
     * contain `.` (nesting) or `*` (wildcard). Kept in sync with the boundary
     * check in `config/cms.php` and the SQL-path filter in `BaseFilter`.
     */
    private const LOCALE_PATTERN = '/^[A-Za-z]{2,3}(?:[_-][A-Za-z0-9]{2,8})*$/';

    /**
     * Every locale the CMS accepts.
     *
     * @return list<string>
     *
     * @throws RuntimeException when `cms.locales` is missing, empty or malformed
     */
    public static function locales(): array
    {
        return self::readLocales('cms.locales');
    }

    /**
     * The locales an editor must fill in — always a subset of `locales()`.
     *
     * @return list<string>
     *
     * @throws RuntimeException when `cms.required_locales` is missing, empty,
     *                          malformed, or names a locale the CMS does not
     *                          accept
     */
    public static function requiredLocales(): array
    {
        $locales  = self::locales();
        $required = self::readLocales('cms.required_locales');

        // Fail CLOSED. Intersecting instead would turn `CMS_REQUIRED_LOCALES=de`
        // into an empty required set, making every locale `nullable` — and since
        // no request declares a top-level `'name' => ['required','array']`, a
        // create with no `name` at all would then pass validation.
        $unknown = array_values(array_diff($required, $locales));

        if ($unknown !== []) {
            throw new RuntimeException(sprintf(
                'cms.required_locales contains %s, which %s not in cms.locales (%s). '
                . 'Required locales must be a subset of the accepted locales.',
                implode(', ', $unknown),
                count($unknown) === 1 ? 'is' : 'are',
                implode(', ', $locales),
            ));
        }

        return $required;
    }

    /**
     * A mandatory field on create: required locales `required`, the rest `nullable`.
     *
     * @param  list<string>  $rules  rules appended after the presence rule
     * @return array<string, list<string>>
     */
    public static function for(string $field, array $rules = ['string']): array
    {
        return self::build($field, $rules, ['required'], ['nullable']);
    }

    /**
     * A mandatory field on update: required locales `sometimes|required` — omit
     * the key to leave the stored translation alone, but never blank it out.
     *
     * @param  list<string>  $rules
     * @return array<string, list<string>>
     */
    public static function sometimes(string $field, array $rules = ['string']): array
    {
        return self::build($field, $rules, ['sometimes', 'required'], ['nullable']);
    }

    /**
     * A field that is optional in every locale.
     *
     * @param  list<string>  $rules
     * @return array<string, list<string>>
     */
    public static function optional(string $field, array $rules = ['string']): array
    {
        return self::build($field, $rules, ['nullable'], ['nullable']);
    }

    /**
     * @param  list<string>  $rules
     * @param  list<string>  $requiredPrefix
     * @param  list<string>  $optionalPrefix
     * @return array<string, list<string>>
     */
    private static function build(
        string $field,
        array $rules,
        array $requiredPrefix,
        array $optionalPrefix,
    ): array {
        $required = self::requiredLocales();
        $built    = [];

        foreach (self::locales() as $locale) {
            $prefix = in_array($locale, $required, true) ? $requiredPrefix : $optionalPrefix;

            $built["{$field}.{$locale}"] = [...$prefix, ...$rules];
        }

        return $built;
    }

    /**
     * @return list<string>
     *
     * @throws RuntimeException
     */
    private static function readLocales(string $key): array
    {
        $value = config($key);

        if (! is_array($value) || $value === []) {
            throw new RuntimeException(
                "Locale config [{$key}] is missing or empty. It is the single source of truth "
                . 'for the CMS locale set and has no fallback — check CMS_LOCALES / '
                . 'CMS_REQUIRED_LOCALES for a malformed locale code.',
            );
        }

        foreach ($value as $locale) {
            if (! is_string($locale) || preg_match(self::LOCALE_PATTERN, $locale) !== 1) {
                throw new RuntimeException(sprintf(
                    'Locale config [%s] contains the invalid locale code %s. Locale codes become '
                    . 'validation rule keys, where `.` nests and `*` is a wildcard.',
                    $key,
                    is_string($locale) ? "\"{$locale}\"" : get_debug_type($locale),
                ));
            }
        }

        return array_values(array_unique($value));
    }
}
