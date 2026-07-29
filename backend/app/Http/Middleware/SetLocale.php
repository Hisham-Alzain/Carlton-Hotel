<?php

namespace App\Http\Middleware;

use App\Support\TranslatableRules;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves the request locale from `Accept-Language`.
 *
 * The supported set is `config('cms.locales')` — the single source of truth,
 * read through `TranslatableRules` so this middleware carries no divergent
 * fallback list of its own.
 */
class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $supported = TranslatableRules::locales();
        $fallback  = $this->fallback($supported);

        // A real browser sends `fr-FR,fr;q=0.9,en;q=0.8`, never a bare `fr`, so
        // the header has to be parsed rather than compared. getPreferredLanguage()
        // honours the quality values and folds region/script subtags, matching
        // `fr-FR` to a supported `fr`. It returns its FIRST argument when nothing
        // matches, so the fallback leads the list to make "no match" explicit
        // instead of implicitly meaning `cms.locales[0]`.
        $candidates = array_values(array_unique([$fallback, ...$supported]));
        $preferred  = $request->getPreferredLanguage($candidates);

        app()->setLocale($this->match($preferred, $supported) ?? $fallback);

        return $next($request);
    }

    /**
     * `config('app.locale')` when the app's own default is one we serve,
     * otherwise the first configured CMS locale. Never a literal.
     *
     * @param  list<string>  $supported
     */
    private function fallback(array $supported): string
    {
        $configured = config('app.locale');

        return is_string($configured) && in_array($configured, $supported, true)
            ? $configured
            : $supported[0];
    }

    /**
     * Map the negotiated tag back onto the exact configured spelling
     * (`getPreferredLanguage()` normalises `pt-BR` to `pt_BR`).
     *
     * @param  list<string>  $supported
     */
    private function match(?string $preferred, array $supported): ?string
    {
        if ($preferred === null) {
            return null;
        }

        $needle = $this->normalize($preferred);

        foreach ($supported as $locale) {
            if ($this->normalize($locale) === $needle) {
                return $locale;
            }
        }

        return null;
    }

    private function normalize(string $locale): string
    {
        return strtolower(str_replace('-', '_', $locale));
    }
}
