<?php

namespace App\Enums;

use App\Enums\Concerns\HasValues;

/**
 * Lifecycle of a guest account (Phase 9.1, D-05). `deleted` means the guest
 * erased their account: the row is kept and anonymized because it anchors
 * accounting records, so this is a status rather than a soft delete.
 */
enum GuestAccountStatus: string
{
    use HasValues;

    case ACTIVE  = 'active';
    case DELETED = 'deleted';
}
