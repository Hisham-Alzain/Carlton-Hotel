<?php

namespace App\Models;

use App\Enums\LoyaltyBatchSource;
use App\Enums\LoyaltyBatchStatus;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One credit of loyalty points with its own expiry (Phase 10, M-9). Consumed
 * FIFO by `expires_at, id`; `points_remaining` is what is still spendable.
 *
 * Deliberately NOT activity-logged: the append-only ledger is the audit trail,
 * and the daily sweeps would otherwise flood `activity_log`.
 */
class LoyaltyEarnBatch extends Model
{
    use HasFactory, HasUuid;

    protected $fillable = [
        'guest_id', 'source', 'folio_id', 'awarded_by', 'reason', 'points', 'points_remaining',
        'earned_at', 'expires_at', 'expiry_warned_at', 'status',
    ];

    protected function casts(): array
    {
        return [
            'source' => LoyaltyBatchSource::class,
            'status' => LoyaltyBatchStatus::class,
            'points' => 'integer',
            'points_remaining' => 'integer',
            'earned_at' => 'datetime',
            'expires_at' => 'datetime',
            'expiry_warned_at' => 'datetime',
        ];
    }

    public function guest(): BelongsTo
    {
        return $this->belongsTo(Guest::class);
    }

    public function folio(): BelongsTo
    {
        return $this->belongsTo(Folio::class);
    }

    public function awardedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'awarded_by');
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(LoyaltyAllocation::class, 'batch_id');
    }

    /** Ledger entries that point at this batch (the earn/adjust/refund that created it, and its expiry). */
    public function entries(): HasMany
    {
        return $this->hasMany(LoyaltyLedgerEntry::class, 'batch_id');
    }
}
