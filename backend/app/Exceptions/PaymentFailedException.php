<?php

namespace App\Exceptions;

class PaymentFailedException extends DomainException
{
    public function errorCode(): string { return 'payment_failed'; }
    public function statusCode(): int   { return 422; }
}
