<?php

namespace App\Models;

use App\Enums\LoyaltyApplicationStatus;
use App\Traits\HasUuid;
use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Support\LogOptions;

/**
 * The loyalty discount applied to one reservation (Phase 10, Q6): points
 * redeemed and/or one voucher. `reservation_id` is unique; `idempotency_key`
 * is unique per guest and is the booking replay guard. A cancel flips `status`
 * to `reversed` once.
 */
class LoyaltyReservationApplication extends Model
{
    use HasFactory, HasUuid, LogsActivity;

    protected $fillable = [
        'reservation_id', 'guest_id', 'idempotency_key', 'redeem_entry_id', 'points_redeemed',
        'points_discount_usd', 'voucher_id', 'voucher_discount_usd', 'status', 'reversed_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => LoyaltyApplicationStatus::class,
            'points_redeemed' => 'integer',
            'points_discount_usd' => 'decimal:2',
            'voucher_discount_usd' => 'decimal:2',
            'reversed_at' => 'datetime',
        ];
    }

    /** The lifecycle only: the amounts are immutable once applied. */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['status', 'reversed_at'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class);
    }

    public function guest(): BelongsTo
    {
        return $this->belongsTo(Guest::class);
    }

    public function voucher(): BelongsTo
    {
        return $this->belongsTo(LoyaltyVoucher::class, 'voucher_id');
    }

    public function redeemEntry(): BelongsTo
    {
        return $this->belongsTo(LoyaltyLedgerEntry::class, 'redeem_entry_id');
    }
}
