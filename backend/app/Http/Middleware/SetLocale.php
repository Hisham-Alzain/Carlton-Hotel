<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $fallback = config('app.locale', 'en');
        $locale   = $request->header('Accept-Language', $fallback);
        // Supported locales are config-driven — see config/cms.php.
        $supported = (array) config('cms.locales', ['en', 'ar']);
        $locale = in_array($locale, $supported, true) ? $locale : 'en';
        app()->setLocale($locale);
        return $next($request);
    }
}
