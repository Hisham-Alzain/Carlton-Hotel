<?php

namespace App\Models;

use App\Traits\HasUuid;
use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * One signed line of a folio (D-04). `amount_usd` is the line total: positive
 * for generated and manual charges, negative for credits. `source_type` stays a
 * plain string column (see FolioItemSource for the values); it is not cast
 * because existing callers compare it with strings.
 */
class FolioItem extends Model
{
    use HasFactory, HasUuid, LogsActivity;

    protected $fillable = [
        'folio_id', 'description', 'quantity', 'unit_price_usd', 'amount_usd',
        'source_type', 'source_id', 'source_line',
        'posted_by', 'reason', 'reverses_item_id', 'idempotency_key',
    ];

    protected $casts = [
        'amount_usd'     => 'decimal:2',
        'unit_price_usd' => 'decimal:2',
        'quantity'       => 'integer',
        'source_line'    => 'integer',
    ];

    public function folio(): BelongsTo { return $this->belongsTo(Folio::class); }

    public function postedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'posted_by');
    }

    /** The row this credit reverses (credits only). */
    public function reversesItem(): BelongsTo
    {
        return $this->belongsTo(FolioItem::class, 'reverses_item_id');
    }

    /** Credits that reverse this row. */
    public function reversals(): HasMany
    {
        return $this->hasMany(FolioItem::class, 'reverses_item_id');
    }

    /** Every dispute raised on this line, oldest first by id (D-09). */
    public function disputes(): HasMany
    {
        return $this->hasMany(FolioItemDispute::class);
    }

    /** The newest dispute: the one the item resource shows ("latest wins", D-10). */
    public function latestDispute(): HasOne
    {
        return $this->hasOne(FolioItemDispute::class)->latestOfMany();
    }

    /**
     * Adds the flags isFrozen() reads, so the reconcile loop runs no query per row.
     */
    public function scopeWithLedgerReferences(Builder $query): Builder
    {
        return $query->withExists(['reversals as is_reversed', 'disputes as is_disputed']);
    }

    /**
     * A generated row that is referenced by a credit or carries any dispute,
     * whatever its status, is frozen: regeneration never deletes, reprices or
     * re-describes it (D-06, D-09).
     */
    public function isFrozen(): bool
    {
        $reversed = array_key_exists('is_reversed', $this->attributes)
            ? (bool) $this->attributes['is_reversed']
            : $this->reversals()->exists();

        if ($reversed) {
            return true;
        }

        return array_key_exists('is_disputed', $this->attributes)
            ? (bool) $this->attributes['is_disputed']
            : $this->disputes()->exists();
    }
}
