<?php

namespace App\Exceptions;

class HousekeepingTaskClosedException extends DomainException
{
    public function errorCode(): string { return 'housekeeping_task_closed'; }
    public function statusCode(): int   { return 422; }
}
