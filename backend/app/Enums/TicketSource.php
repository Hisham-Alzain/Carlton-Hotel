<?php

namespace App\Enums;

use App\Enums\Concerns\HasValues;

/**
 * Where a ticket came from. STAFF is forced server-side by
 * `POST /support-tickets` (Phase 7, D-04); `guest_app` is deferred with guest
 * ticket creation, so no dead value is declared. The DB default stays chatbot.
 */
enum TicketSource: string
{
    use HasValues;

    case CHATBOT = 'chatbot';
    case STAFF   = 'staff';
}
