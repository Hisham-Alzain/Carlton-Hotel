<?php

namespace App\Exceptions;

class NotFoundException extends DomainException
{
    public function errorCode(): string { return 'not_found'; }
    public function statusCode(): int { return 404; }
}
