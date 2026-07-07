<?php

namespace App\Exceptions;

class ForbiddenException extends DomainException
{
    public function errorCode(): string { return 'forbidden'; }
    public function statusCode(): int { return 403; }
}
