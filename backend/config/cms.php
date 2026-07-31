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

/*
|--------------------------------------------------------------------------
| Recycle bin retention
|--------------------------------------------------------------------------
|
| `DELETE` on CMS content is recoverable: the row is marked, its media rows and
| the stored files are kept deliberately so a restore comes back whole, and only
| `DELETE …/{uuid}/force` removes any of it. Nothing ever called that verb on the
| bin's behalf, so a deleted record — and every photograph on it — sat in the
| database and on the disk for good. "Recoverable" quietly meant "permanent", and
| storage only grew.
|
| `cms:purge-bin` closes that: it force-deletes, through Eloquent, everything
| binned longer than `retention_days`, so `CascadesSoftDeletes` takes the
| descendants and `PurgesMedia` unlinks the files.
|
| `retention_days` — how long a binned record stays recoverable.
| `chunk`          — rows held in memory per pass of the purge.
|
| ## Why 90 days
|
| The window has to cover how long a hotel plausibly takes to NOTICE a mistake,
| which is set by the hotel's calendar and not by the developer's. Editorial
| content here is seasonal: a terrace page, a summer menu, a campaign promotion
| and the photography on a room type are looked at when the next season is
| prepared or when the quarterly content review comes round. The realistic worst
| case is not "the editor deletes the wrong row and gasps" — that is undone in
| minutes through the bin — it is "the F&B manager asks in October why the
| summer terrace page is gone". Thirty days is shorter than one editorial cycle
| and would have destroyed it, silently, before anyone with the authority to
| care had looked. A week is barely longer than a public holiday plus the annual
| leave of the one person who knew.
|
| Pulling the other way: a year means an object store carrying the full
| photography of content nobody has asked about across four quarterly reviews,
| and a bin that in practice is never emptied — which is the state this setting
| exists to leave.
|
| 90 days is one full editorial quarter plus the review that closes it. Long
| enough that the seasonal question gets asked while the answer still exists,
| short enough that the disk is bounded by a quarter of deletions rather than by
| the age of the property.
|
| Deliberately NOT tuned for convenience of testing — the command takes `--days`
| for that, and the tests pass it explicitly rather than shortening the default.
|
*/

$positiveInt = static function (mixed $value, int $default): int {
    return is_numeric($value) && (int) $value > 0 ? (int) $value : $default;
};

return [

    'locales' => $list(env('CMS_LOCALES'), ['en', 'ar', 'fr', 'tr', 'es']),

    'required_locales' => $list(env('CMS_REQUIRED_LOCALES'), ['en', 'ar']),

    'recycle_bin' => [
        'retention_days' => $positiveInt(env('CMS_BIN_RETENTION_DAYS'), 90),
        'chunk'          => $positiveInt(env('CMS_BIN_PURGE_CHUNK'), 200),
    ],

];
