<?php

namespace App\Exceptions;

class RoomOutOfOrderException extends DomainException
{
    public function errorCode(): string { return 'room_out_of_order'; }
    public function statusCode(): int   { return 422; }
}
