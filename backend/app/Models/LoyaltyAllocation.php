<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * How many points a ledger entry took from, or gave back to, one earn batch
 * (Phase 10, M-9). Pure join data: no uuid, no timestamps, no audit - the
 * ledger entry it hangs off is the audit record.
 */
class LoyaltyAllocation extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = ['ledger_entry_id', 'batch_id', 'points'];

    protected function casts(): array
    {
        return ['points' => 'integer'];
    }

    public function entry(): BelongsTo
    {
        return $this->belongsTo(LoyaltyLedgerEntry::class, 'ledger_entry_id');
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(LoyaltyEarnBatch::class, 'batch_id');
    }
}
