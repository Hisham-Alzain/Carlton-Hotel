<?php

namespace App\Exceptions;

class HousekeepingTaskTransitionException extends DomainException
{
    public function errorCode(): string { return 'housekeeping_task_transition_invalid'; }
    public function statusCode(): int   { return 422; }
}
