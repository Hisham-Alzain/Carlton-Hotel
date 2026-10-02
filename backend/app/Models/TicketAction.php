<?php

namespace App\Models;

use App\Enums\TicketActionType;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use InvalidArgumentException;
use LogicException;

/**
 * One append-only row of a ticket's timeline (Phase 7, D-01). Written only by
 * the ticket single writers (`App\Actions\Tickets\*`) in the same transaction
 * as the ticket change. This IS the audit, so no LogsActivity (council A6).
 *
 * The timeline is canonical over the `tickets` columns for history reads
 * (council A8): status timestamps and escalation history are read from here.
 *
 * Reasons live in `body` for `status_change` and `escalation` rows (reply text
 * for `reply` rows); `created` rows have a null body. `meta` keys are
 * whitelisted per type by `TicketActionType::allowedMetaKeys()`.
 *
 * `message_id` is reserved for TICKET-08 (guest-visible replies): it is not
 * fillable, never written and never exposed this milestone.
 */
class TicketAction extends Model
{
    use HasFactory, HasUuid;

    public const UPDATED_AT = null;

    protected $fillable = [
        'ticket_id', 'user_id', 'type', 'body', 'from_status', 'to_status', 'target_user_id', 'meta',
    ];

    protected $casts = [
        'type'       => TicketActionType::class,
        'meta'       => 'array',
        'created_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $action): void {
            $allowed = $action->type->allowedMetaKeys();
            $unknown = array_diff(array_keys($action->meta ?? []), $allowed);

            if ($unknown !== []) {
                throw new InvalidArgumentException(sprintf(
                    'Meta keys [%s] are not allowed on a %s ticket action.',
                    implode(', ', $unknown),
                    $action->type->value,
                ));
            }
        });

        // Append-only (D-01). A ticket delete removes rows through the FK
        // cascade, which fires no model events.
        static::updating(static fn () => throw new LogicException('Ticket actions are append-only.'));
        static::deleting(static fn () => throw new LogicException('Ticket actions are append-only.'));
    }

    public function ticket(): BelongsTo     { return $this->belongsTo(Ticket::class); }
    public function user(): BelongsTo       { return $this->belongsTo(User::class, 'user_id'); }
    public function targetUser(): BelongsTo { return $this->belongsTo(User::class, 'target_user_id'); }
    public function recovery(): HasOne      { return $this->hasOne(TicketRecovery::class); }
}
