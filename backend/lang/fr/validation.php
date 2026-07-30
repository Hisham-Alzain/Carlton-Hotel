<?php

/*
|--------------------------------------------------------------------------
| Filter-layer bridge — intentionally three keys, not a full translation
|--------------------------------------------------------------------------
|
| Every FormRequest in this project routes its messages through
| `BaseRequest::messages()`, which reads `custom.validation.*` — so request
| bodies are already localized by `lang/<locale>/custom.php`.
|
| `App\Base\BaseFilter` is the one exception: it rejects an uninterpretable
| query-string value with Laravel's built-in `validation.boolean`,
| `validation.integer` and `validation.string` keys. Those ship only in
| `lang/en`, so a 422 from `?is_active=trve` returned a localized `message`
| next to English `errors` text — one response in two languages.
|
| These three entries close that gap without touching the filter. They are a
| bridge, not the destination: once `BaseFilter` asks for `custom.validation.*`
| like every other layer does, this file (and its fr/tr/es siblings) should be
| deleted. Sourcing the strings from `custom.php` rather than restating them
| keeps a single spelling per locale in the meantime, so the two paths cannot
| drift while both exist. Any key omitted here still falls back to
| `lang/en/validation.php`.
|
*/

$custom = require __DIR__.'/custom.php';

return array_filter(
    [
        'boolean' => $custom['validation']['boolean'] ?? null,
        'integer' => $custom['validation']['integer'] ?? null,
        'string'  => $custom['validation']['string'] ?? null,
    ],
    static fn (mixed $message): bool => is_string($message) && $message !== '',
);
