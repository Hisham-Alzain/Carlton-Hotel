<?php

namespace App\Exceptions;

class UnauthorizedException extends DomainException
{
    public function errorCode(): string { return 'unauthorized'; }
    public function statusCode(): int { return 401; }
}
