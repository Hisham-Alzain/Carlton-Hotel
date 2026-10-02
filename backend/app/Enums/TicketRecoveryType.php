<?php

namespace App\Enums;

use App\Enums\Concerns\HasValues;

/**
 * Service-recovery kinds recorded on a ticket (Phase 7, D-14). Only
 * FOLIO_CREDIT is ledger-backed (it links a Phase 5 credit line, council A7);
 * the others carry an informational recorded value at most. The dashboard's
 * `transport_hold` maps to OTHER.
 */
enum TicketRecoveryType: string
{
    use HasValues;

    case FOLIO_CREDIT     = 'folio_credit';
    case RATE_DISCOUNT    = 'rate_discount';
    case COURTESY_AMENITY = 'courtesy_amenity';
    case ROOM_UPGRADE     = 'room_upgrade';
    case LATE_CHECKOUT    = 'late_checkout';
    case APOLOGY          = 'apology';
    case OTHER            = 'other';
}
