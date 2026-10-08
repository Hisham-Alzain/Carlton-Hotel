<?php

namespace App\Exceptions;

/** Thrown when adults + children exceed the room type's max_occupancy; context {max_occupancy, requested}. */
class OccupancyExceededException extends DomainException
{
    public function errorCode(): string
    {
        return 'occupancy_exceeded';
    }

    public function statusCode(): int
    {
        return 422;
    }
}
