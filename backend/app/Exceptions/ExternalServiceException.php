<?php

namespace App\Exceptions;

class ExternalServiceException extends DomainException
{
    public function errorCode(): string { return 'external_service_error'; }
    public function statusCode(): int { return 502; }
}
