<?php

namespace App\Http\Middleware;

use App\Exceptions\NoActiveReservationException;
use App\Support\GuestEntitlement;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureIsCheckedIn
{
    public function handle(Request $request, Closure $next): Response
    {
        $guest = $request->user('guests');

        if (! $guest || ! GuestEntitlement::isCheckedIn($guest)) {
            throw new NoActiveReservationException(__('custom.errors.no_active_reservation'));
        }

        return $next($request);
    }
}
