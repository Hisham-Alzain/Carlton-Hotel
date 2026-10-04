<?php
namespace App\Models;

use App\Enums\BedType;
use App\Enums\FloorPreference;
use App\Enums\GuestAccountStatus;
use App\Enums\PillowType;
use App\Models\Reservation;
use App\Traits\HasUuid;
use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Activitylog\Support\LogOptions;

class Guest extends Authenticatable
{
    use HasApiTokens, HasFactory, HasUuid, LogsActivity;

    protected $fillable = [
        'uuid', 'name', 'phone', 'phone_country', 'phone_verified_at',
        'email', 'email_verified_at', 'first_name', 'last_name', 'preferred_locale',
        // Phase 4 (D-08): preferences.
        'bed_type', 'pillow_type', 'floor_preference', 'preferences_other', 'preferences_updated_at',
    ];

    protected $hidden = [];

    /**
     * Phase 9.1 (D-05): `account_status` / `account_deleted_at` are deliberately
     * NOT fillable (a profile PUT can never set them) and so never logged by
     * `logFillable()`; DeleteGuestAccountAction sets them with forceFill. A
     * deleted account is anonymized in place, never soft-deleted: soft-delete
     * scopes would hide the row from `$reservation->guest` and accounting joins.
     */
    protected $attributes = [
        'account_status' => 'active',
    ];

    protected function casts(): array
    {
        return [
            'account_status'     => GuestAccountStatus::class,
            'account_deleted_at' => 'datetime',
            'phone_verified_at' => 'datetime',
            'email_verified_at' => 'datetime',
            'bed_type'               => BedType::class,
            'pillow_type'            => PillowType::class,
            'floor_preference'       => FloorPreference::class,
            'preferences_updated_at' => 'datetime',
        ];
    }

    /**
     * Restates the `App\Traits\LogsActivity` chain on purpose — a trait method
     * cannot be reached through `parent::` — and adds the D-08 exclusions:
     * the free-text note and the allergy-adjacent pillow choice never reach
     * activity_log. A free-text-only edit therefore logs only
     * `preferences_updated_at`, by design.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->logExcept(['preferences_other', 'pillow_type']);
    }

    public function markPhoneVerified(): void
    {
        $this->phone_verified_at = now();
        $this->save();
    }

    public function markEmailVerified(): void
    {
        $this->email_verified_at = now();
        $this->save();
    }

    public function isDeleted(): bool
    {
        return $this->account_status === GuestAccountStatus::DELETED;
    }

    /** Accounts that have not been erased (Phase 9.1, D-12). */
    public function scopeActiveAccounts(Builder $query): Builder
    {
        return $query->where('account_status', GuestAccountStatus::ACTIVE->value);
    }

    public function scopeByPhone($query, string $e164)
    {
        return $query->where('phone', $e164);
    }

    public function scopeByEmail($query, string $email)
    {
        return $query->where('email', $email);
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class);
    }

    public function activeReservations(): HasMany
    {
        return $this->hasMany(Reservation::class)
            ->whereNotIn('status', ['cancelled', 'checked_out']);
    }

    public function deviceTokens(): HasMany
    {
        return $this->hasMany(DeviceToken::class);
    }

    public function guestNotifications(): HasMany
    {
        return $this->hasMany(GuestNotification::class);
    }

    public function conversations(): HasMany
    {
        return $this->hasMany(Conversation::class);
    }

    /** Internal staff notes (Phase 4, D-06): append-only, never on guest routes. */
    public function notes(): HasMany
    {
        return $this->hasMany(GuestNote::class);
    }

    /** Phase 10: the guest's earn lots (consumed FIFO by the loyalty actions). */
    public function loyaltyBatches(): HasMany
    {
        return $this->hasMany(LoyaltyEarnBatch::class);
    }

    /** Phase 10: the append-only points ledger. */
    public function loyaltyLedgerEntries(): HasMany
    {
        return $this->hasMany(LoyaltyLedgerEntry::class);
    }

    /** Phase 10: vouchers bought from the rewards catalog. */
    public function loyaltyVouchers(): HasMany
    {
        return $this->hasMany(LoyaltyVoucher::class);
    }
}
