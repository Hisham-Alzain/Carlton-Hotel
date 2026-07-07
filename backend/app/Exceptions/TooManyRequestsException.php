<?php

namespace App\Exceptions;

class TooManyRequestsException extends DomainException
{
    public function errorCode(): string { return 'too_many_requests'; }
    public function statusCode(): int { return 429; }
}
