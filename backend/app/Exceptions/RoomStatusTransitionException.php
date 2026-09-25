<?php

namespace App\Exceptions;

class RoomStatusTransitionException extends DomainException
{
    public function errorCode(): string { return 'room_status_transition_invalid'; }
    public function statusCode(): int   { return 422; }
}
