<?php

/*
|--------------------------------------------------------------------------
| CMS content locales
|--------------------------------------------------------------------------
|
| Translatable CMS fields are Spatie `HasTranslations` over `json` columns, so
| there are no per-locale columns and adding a locale needs no migration. The
| only thing that has to know the locale set is validation — which reads this
| config through `App\Support\TranslatableRules`.
|
| `locales`          — every locale the CMS will accept and store.
| `required_locales` — the subset an editor must fill before content saves.
|                      Everything else is optional and may be back-filled later;
|                      readers degrade to `APP_FALLBACK_LOCALE`.
|
| Both may be overridden with a comma-separated env list, e.g.
| `CMS_LOCALES=en,ar,fr,tr,es,de`.
|
*/

$list = static function (mixed $value, array $default): array {
    if (! is_string($value) || trim($value) === '') {
        return $default;
    }

    $parsed = array_values(array_unique(array_filter(
        array_map('trim', explode(',', $value)),
        static fn (string $locale): bool => $locale !== '',
    )));

    return $parsed !== [] ? $parsed : $default;
};

return [

    'locales' => $list(env('CMS_LOCALES'), ['en', 'ar', 'fr', 'tr', 'es']),

    'required_locales' => $list(env('CMS_REQUIRED_LOCALES'), ['en', 'ar']),

];
