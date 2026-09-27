<?php

namespace App\Enums;

use App\Enums\Concerns\HasValues;

/** A folio line-item dispute's lifecycle (D-09). Only OPEN is a live flag. */
enum FolioDisputeStatus: string
{
    use HasValues;

    case OPEN     = 'open';
    case RESOLVED = 'resolved';
    case REJECTED = 'rejected';
}
