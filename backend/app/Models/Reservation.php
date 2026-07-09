<?php

namespace App\Models;

use App\Traits\HasUuid;
use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Reservation extends Model
{
    use HasFactory, HasUuid, LogsActivity;

    const STATUS_PENDING_VERIFICATION = 'pending_verification';
    const STATUS_PENDING              = 'pending';
    const STATUS_CONFIRMED            = 'confirmed';
    const STATUS_CHECKED_IN           = 'checked_in';
    const STATUS_CHECKED_OUT          = 'checked_out';
    const STATUS_CANCELLED            = 'cancelled';

    const SOURCE_DIRECT  = 'direct';
    const SOURCE_WALK_IN = 'walk_in';

    const PAYMENT_CASH       = 'cash';
    const PAYMENT_ON_ARRIVAL = 'on_arrival';

    protected $fillable = [
        'guest_id', 'booking_code', 'source', 'external_ref', 'external_channel',
        'check_in', 'check_out', 'status', 'hold_expires_at', 'payment_method',
        'total_usd', 'promo_code_id', 'last_name', 'phone',
    ];

    protected $casts = [
        'check_in'        => 'date',
        'check_out'       => 'date',
        'hold_expires_at' => 'datetime',
        'total_usd'       => 'decimal:2',
    ];

    public function guest(): BelongsTo   { return $this->belongsTo(Guest::class); }
    public function promoCode(): BelongsTo { return $this->belongsTo(PromoCode::class); }
    public function rooms(): HasMany     { return $this->hasMany(ReservationRoom::class); }

    public function isHoldExpired(): bool
    {
        return $this->status === self::STATUS_PENDING_VERIFICATION
            && $this->hold_expires_at
            && $this->hold_expires_at->isPast();
    }

    public function nights(): int
    {
        return (int) $this->check_in->diffInDays($this->check_out);
    }

    // OTP contact for booking-code linking: prefer linked guest, fall back to stub columns
    public function otpIdentifier(): ?string
    {
        if ($this->guest) {
            return $this->guest->phone ?? $this->guest->email ?? null;
        }
        return $this->phone ?? null;
    }
}
