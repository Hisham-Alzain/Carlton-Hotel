<?php

namespace App\Models;

use App\Enums\TicketRecoveryType;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A service-recovery record hanging off one `recovery` timeline row (Phase 7,
 * D-02). Ticket and recorder are reached through the action (3NF).
 *
 * `amount_usd` for a FOLIO_CREDIT is a snapshot copied from a frozen folio
 * item at link time — the absolute value of the negative credit line — and
 * does not follow later folio edits (council A7/A8). Only FOLIO_CREDIT is
 * ledger-backed; other types carry an informational recorded value.
 */
class TicketRecovery extends Model
{
    use HasFactory, HasUuid;

    public const UPDATED_AT = null;

    protected $fillable = [
        'ticket_action_id', 'type', 'amount_usd', 'description', 'folio_item_id',
    ];

    protected $casts = [
        'type'       => TicketRecoveryType::class,
        'amount_usd' => 'decimal:2',
        'created_at' => 'datetime',
    ];

    public function action(): BelongsTo    { return $this->belongsTo(TicketAction::class, 'ticket_action_id'); }
    public function folioItem(): BelongsTo { return $this->belongsTo(FolioItem::class); }
}
