<?php

namespace App\Models;

use App\Enums\LoyaltyBatchSource;
use App\Enums\LoyaltyEntryType;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * One signed movement of a guest's points (Phase 10, M-9, Q16). Append-only:
 * rows are inserted and never updated (no `updated_at`), and the ledger is
 * itself the audit trail, so there is deliberately no activity log here.
 * A reversal is a new row whose `reverses_entry_id` points at the original;
 * that column is unique, so an entry is reversed at most once (LOY-17).
 */
class LoyaltyLedgerEntry extends Model
{
    use HasFactory, HasUuid;

    public const UPDATED_AT = null;

    protected $fillable = [
        'guest_id', 'type', 'source', 'points', 'batch_id', 'voucher_id', 'reservation_id', 'folio_id',
        'reverses_entry_id', 'performed_by', 'reason', 'shortfall_points', 'discount_usd',
        'idempotency_key', 'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'type' => LoyaltyEntryType::class,
            'source' => LoyaltyBatchSource::class,
            'points' => 'integer',
            'shortfall_points' => 'integer',
            'discount_usd' => 'decimal:2',
            'occurred_at' => 'datetime',
        ];
    }

    public function guest(): BelongsTo
    {
        return $this->belongsTo(Guest::class);
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(LoyaltyEarnBatch::class, 'batch_id');
    }

    public function voucher(): BelongsTo
    {
        return $this->belongsTo(LoyaltyVoucher::class, 'voucher_id');
    }

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class);
    }

    public function folio(): BelongsTo
    {
        return $this->belongsTo(Folio::class);
    }

    public function performer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by');
    }

    /** The entry this one reverses (clawback of an earn, refund of a redeem). */
    public function reversedEntry(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reverses_entry_id');
    }

    /** The entry that reversed this one; `reverses_entry_id` is unique, so at most one. */
    public function reversal(): HasOne
    {
        return $this->hasOne(self::class, 'reverses_entry_id');
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(LoyaltyAllocation::class, 'ledger_entry_id');
    }
}
