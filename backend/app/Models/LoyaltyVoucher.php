<?php

namespace App\Models;

use App\Enums\LoyaltyEntryType;
use App\Enums\LoyaltyRewardType;
use App\Enums\LoyaltyVoucherStatus;
use App\Traits\HasUuid;
use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Spatie\Activitylog\Support\LogOptions;

/**
 * A voucher a guest bought from the rewards catalog (Phase 10, M-9). `type`,
 * `reward_name` and `value_usd` are snapshots of the reward at redeem time, so
 * a purged reward never breaks history. `reservation_id` is unique and nulled
 * when the voucher is restored on cancel.
 */
class LoyaltyVoucher extends Model
{
    use HasFactory, HasUuid, LogsActivity;

    protected $fillable = [
        'code', 'guest_id', 'loyalty_reward_id', 'type', 'reward_name', 'value_usd', 'points_spent',
        'status', 'expires_at', 'reservation_id', 'used_at',
    ];

    protected function casts(): array
    {
        return [
            'type' => LoyaltyRewardType::class,
            'status' => LoyaltyVoucherStatus::class,
            'reward_name' => 'array',
            'value_usd' => 'decimal:2',
            'points_spent' => 'integer',
            'expires_at' => 'datetime',
            'used_at' => 'datetime',
        ];
    }

    /** Lifecycle fields only: the code and snapshots are immutable. */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['status', 'reservation_id', 'used_at', 'expires_at'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

    public function guest(): BelongsTo
    {
        return $this->belongsTo(Guest::class);
    }

    /** Trashed rewards stay reachable: a voucher outlives its catalog entry. */
    public function reward(): BelongsTo
    {
        return $this->belongsTo(LoyaltyReward::class, 'loyalty_reward_id')->withTrashed();
    }

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class);
    }

    /** The ledger entry that bought this voucher. */
    public function redeemEntry(): HasOne
    {
        return $this->hasOne(LoyaltyLedgerEntry::class, 'voucher_id')
            ->where('loyalty_ledger_entries.type', LoyaltyEntryType::REDEEM->value);
    }
}
