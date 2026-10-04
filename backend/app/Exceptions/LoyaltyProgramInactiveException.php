<?php

namespace App\Exceptions;

/** Phase 10 (Q4, M-1: context capability): the capability needs a rate or cap staff have not set. */
class LoyaltyProgramInactiveException extends DomainException
{
    public function errorCode(): string { return 'loyalty_program_inactive'; }
    public function statusCode(): int   { return 422; }
}
