<?php

namespace App\Exceptions;

class HoldExpiredException extends DomainException
{
    public function errorCode(): string { return 'hold_expired'; }
    public function statusCode(): int   { return 422; }
}
