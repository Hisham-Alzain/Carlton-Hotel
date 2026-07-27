<?php

namespace App\Models;

use App\Enums\PaymentMethod;
use App\Enums\ReservationSource;
use App\Enums\ReservationStatus;
use App\Traits\HasUuid;
use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Reservation extends Model
{
    use HasFactory, HasUuid, LogsActivity;

    protected $fillable = [
        'guest_id', 'booking_code', 'source', 'external_ref', 'external_channel',
        'check_in', 'check_out', 'checked_in_at', 'checked_out_at', 'dnd_until',
        'status', 'hold_expires_at', 'payment_method',
        'total_usd', 'promo_code_id', 'last_name', 'phone',
    ];

    protected $casts = [
        'status'          => ReservationStatus::class,
        'source'          => ReservationSource::class,
        'payment_method'  => PaymentMethod::class,
        'check_in'        => 'date',
        'check_out'       => 'date',
        'checked_in_at'   => 'datetime',
        'checked_out_at'  => 'datetime',
        'dnd_until'       => 'datetime',
        'hold_expires_at' => 'datetime',
        'total_usd'       => 'decimal:2',
    ];

    public function guest(): BelongsTo   { return $this->belongsTo(Guest::class); }
    public function promoCode(): BelongsTo { return $this->belongsTo(PromoCode::class); }
    public function rooms(): HasMany     { return $this->hasMany(ReservationRoom::class); }
    public function payments(): MorphMany { return $this->morphMany(Payment::class, 'payable'); }
    public function documents(): HasMany  { return $this->hasMany(GuestDocument::class); }

    /**
     * Reservations that still hold their room.
     *
     * Single source of truth for "this booking occupies inventory", shared by
     * availability counting and by staff room assignment so the two can never
     * disagree about whether a room is free. An unverified hold only counts
     * while it has not expired.
     */
    public function scopeHoldingInventory(Builder $query): Builder
    {
        return $query->where(function (Builder $q) {
            $q->whereIn('status', [
                ReservationStatus::PENDING,
                ReservationStatus::CONFIRMED,
                ReservationStatus::CHECKED_IN,
            ])->orWhere(function (Builder $q) {
                $q->where('status', ReservationStatus::PENDING_VERIFICATION)
                  ->where('hold_expires_at', '>', now());
            });
        });
    }

    public function isHoldExpired(): bool
    {
        return $this->status === ReservationStatus::PENDING_VERIFICATION
            && $this->hold_expires_at
            && $this->hold_expires_at->isPast();
    }

    public function nights(): int
    {
        return (int) $this->check_in->diffInDays($this->check_out);
    }

    /** Nights left to sleep, floored at zero on or after the checkout date. */
    public function nightsRemaining(): int
    {
        return (int) max(0, now()->startOfDay()->diffInDays($this->check_out, false));
    }

    /** Do-not-disturb is on while the expiry is still in the future. */
    public function isDndActive(): bool
    {
        return $this->dnd_until !== null && $this->dnd_until->isFuture();
    }

    public function folio(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(Folio::class);
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
