<?php

namespace App\Exceptions;

class InquiryStateException extends DomainException
{
    public function errorCode(): string { return 'inquiry_state'; }
    public function statusCode(): int   { return 422; }
}
