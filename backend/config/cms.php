<?php

/*
|--------------------------------------------------------------------------
| CMS content locales
|--------------------------------------------------------------------------
|
| Translatable CMS fields are Spatie `HasTranslations` over `json` columns, so
| there are no per-locale columns and adding a locale needs no migration. This
| file is the SINGLE SOURCE OF TRUTH for which locales exist: validation
| (`App\Support\TranslatableRules`), filtering (`App\Base\BaseFilter`) and
| request locale negotiation (`App\Http\Middleware\SetLocale`) all read it and
| none of them carries a divergent hardcoded fallback.
|
| `locales`          — every locale the CMS will accept and store.
| `required_locales` — the subset an editor must fill before content saves.
|                      Must be a subset of `locales`; everything else is
|                      optional and may be back-filled later.
|
| Both may be overridden with a comma-separated env list, e.g.
| `CMS_LOCALES=en,ar,fr,tr,es,de`.
|
| Locale codes are validated HERE, at the boundary, because they travel into
| places where a stray character changes meaning rather than merely failing:
|
|   - `TranslatableRules` builds validation keys as `"{$field}.{$locale}"`, so
|     `*` would become the Laravel wildcard `name.*` and `pt.BR` a nested path.
|   - `BaseFilter` splices the code into a raw SQL JSON path (`name->en`).
|
| An env value that is set but contains ANY malformed code yields an empty
| list rather than a silently-narrowed one — a misconfiguration must never
| degrade into "a different but plausible locale set". Consumers treat the
| empty list as fatal and fail loudly (see `TranslatableRules::readLocales()`).
|
*/

/** BCP-47-shaped tag: `en`, `pt_BR`, `zh-Hans`, `zh-Hans-CN`. */
$localePattern = '/^[A-Za-z]{2,3}(?:[_-][A-Za-z0-9]{2,8})*$/';

$list = static function (mixed $value, array $default) use ($localePattern): array {
    if (! is_string($value) || trim($value) === '') {
        return $default;
    }

    $parsed = array_values(array_filter(
        array_map('trim', explode(',', $value)),
        static fn (string $locale): bool => $locale !== '',
    ));

    foreach ($parsed as $locale) {
        if (preg_match($localePattern, $locale) !== 1) {
            // Poison the whole list: dropping just the bad entry would hand
            // consumers a locale set nobody configured.
            return [];
        }
    }

    return array_values(array_unique($parsed));
};

return [

    'locales' => $list(env('CMS_LOCALES'), ['en', 'ar', 'fr', 'tr', 'es']),

    'required_locales' => $list(env('CMS_REQUIRED_LOCALES'), ['en', 'ar']),

];
