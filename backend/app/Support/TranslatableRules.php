<?php

namespace App\Support;

/**
 * Builds per-locale validation rules for `HasTranslations` fields out of
 * `config('cms')`, so adding a locale is a one-line config change instead of an
 * edit to every FormRequest under `App\Http\Requests\Cms`.
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
 */
final class TranslatableRules
{
    /**
     * Every locale the CMS accepts.
     *
     * @return list<string>
     */
    public static function locales(): array
    {
        return self::normalize(config('cms.locales'), ['en']);
    }

    /**
     * The locales an editor must fill in — always a subset of `locales()`.
     *
     * @return list<string>
     */
    public static function requiredLocales(): array
    {
        return array_values(array_intersect(
            self::locales(),
            self::normalize(config('cms.required_locales'), []),
        ));
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
     * @param  list<string>  $fallback
     * @return list<string>
     */
    private static function normalize(mixed $value, array $fallback): array
    {
        if (! is_array($value) || $value === []) {
            return $fallback;
        }

        return array_values(array_unique(array_map('strval', $value)));
    }
}
