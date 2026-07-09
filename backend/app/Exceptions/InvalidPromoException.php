<?php

namespace App\Exceptions;

class InvalidPromoException extends DomainException
{
    public function errorCode(): string { return 'invalid_promo'; }
    public function statusCode(): int   { return 422; }
}
