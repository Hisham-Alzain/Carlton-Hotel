<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $locale = $request->header('Accept-Language', config('app.locale', 'en'));
        $locale = in_array($locale, ['en', 'ar']) ? $locale : 'en';
        app()->setLocale($locale);
        return $next($request);
    }
}
