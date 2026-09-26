<?php

namespace App\Models;

use App\Enums\PaymentMethod;
use App\Enums\ReservationSource;
use App\Enums\ReservationStatus;
use App\Support\HotelClock;
use App\Traits\HasUuid;
use App\Traits\LogsActivity;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Spatie\Activitylog\Support\LogOptions;

class Reservation extends Model
{
    use HasFactory, HasUuid, LogsActivity;

    /**
     * No `digital_key_*` column is mass-assignable: the key is a credential and
     * is written only with forceFill() by IssueDigitalKeyAction /
     * RevokeDigitalKeyAction (Phase 4, D-11).
     */
    protected $fillable = [
        'guest_id', 'booking_code', 'source', 'external_ref', 'external_channel',
        'check_in', 'check_out', 'checked_in_at', 'checked_out_at', 'dnd_until',
        'status', 'hold_expires_at', 'payment_method',
        'total_usd', 'promo_code_id', 'last_name', 'phone', 'notes',
        'arrival_time', 'online_check_in_submitted_at',
    ];

    /** The key and its hash never serialise (D-11); guests read it through StayPayload. */
    protected $hidden = ['digital_key_code', 'digital_key_hash'];

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
        'online_check_in_submitted_at' => 'datetime',
        'digital_key_code'             => 'encrypted',
        'digital_key_issued_at'        => 'datetime',
        'digital_key_expires_at'       => 'datetime',
        'digital_key_revoked_at'       => 'datetime',
    ];

    protected static function booted(): void
    {
        // The digital key expires at the live check_out (D-11): moving the
        // departure moves an unrevoked key's expiry with it. A revoked key's
        // record is left as it was.
        static::saving(function (Reservation $reservation) {
            if ($reservation->isDirty('check_out')
                && $reservation->check_out !== null
                && $reservation->digital_key_issued_at !== null
                && $reservation->digital_key_revoked_at === null) {
                $reservation->digital_key_expires_at = HotelClock::checkOutAt($reservation->check_out);
            }
        });
    }

    /**
     * Restates the `App\Traits\LogsActivity` chain on purpose — a trait method
     * cannot be reached through `parent::` — and keeps the digital key and its
     * hash out of activity_log (D-11). The key columns are not fillable, so
     * logFillable() would skip them anyway; the exclusion makes that explicit
     * and survives a future `$fillable` change.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->logExcept(['digital_key_code', 'digital_key_hash']);
    }

    public function guest(): BelongsTo   { return $this->belongsTo(Guest::class); }
    public function promoCode(): BelongsTo { return $this->belongsTo(PromoCode::class); }
    public function rooms(): HasMany     { return $this->hasMany(ReservationRoom::class); }
    public function payments(): MorphMany { return $this->morphMany(Payment::class, 'payable'); }
    public function documents(): HasMany  { return $this->hasMany(GuestDocument::class); }
    /** `check_in_approvals.reservation_id` is unique, so at most one. */
    public function checkInApproval(): HasOne { return $this->hasOne(CheckInApproval::class); }

    /**
     * Issued, not revoked and not yet expired. Computed at read time, so an
     * expired key is hidden before the sweep gets round to revoking it (D-11).
     */
    public function hasActiveDigitalKey(): bool
    {
        return $this->digital_key_issued_at !== null
            && $this->digital_key_revoked_at === null
            && $this->digital_key_expires_at !== null
            && $this->digital_key_expires_at->isFuture();
    }

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

    /**
     * Whether the hotel-local business date falls inside the stay: arrival day
     * up to the last night, `check_in <= today < check_out` (D-02). The
     * departure day itself is outside the window. Pass `HotelClock::today()`.
     */
    public function isWithinStayWindow(CarbonImmutable $hotelToday): bool
    {
        $today = $hotelToday->toDateString();

        return $this->check_in->toDateString() <= $today
            && $today < $this->check_out->toDateString();
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
