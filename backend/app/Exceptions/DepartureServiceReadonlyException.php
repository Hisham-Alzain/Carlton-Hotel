<?php

namespace App\Exceptions;

/** An express-checkout departure row is derived from the stay and cannot be changed (D-22). */
class DepartureServiceReadonlyException extends DomainException
{
    public function errorCode(): string { return 'departure_service_readonly'; }
    public function statusCode(): int   { return 422; }
}
