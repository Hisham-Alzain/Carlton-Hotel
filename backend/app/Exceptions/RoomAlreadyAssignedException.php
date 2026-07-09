<?php

namespace App\Exceptions;

class RoomAlreadyAssignedException extends DomainException
{
    public function errorCode(): string { return 'room_already_assigned'; }
    public function statusCode(): int   { return 409; }
}
