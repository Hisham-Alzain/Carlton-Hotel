<?php

namespace App\Models;

use App\Traits\HasUuid;
use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A display exchange rate (Phase 9.1, D-14): `rate` units of `currency` per
 * 1 USD. Append-only — a change is a new row, never an update — so the table
 * is its own history. Only the create is ever logged.
 */
class ExchangeRate extends Model
{
    use HasFactory, HasUuid, LogsActivity;

    protected $fillable = ['currency', 'rate', 'note', 'set_by'];

    protected function casts(): array
    {
        return [
            // A string with 6 decimals: no float drift on the wire (D-16).
            'rate' => 'decimal:6',
        ];
    }

    public function setBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'set_by');
    }

    /**
     * The newest row of each given currency: `id IN (MAX(id) … GROUP BY currency)`,
     * served by the (currency, id) index.
     *
     * @param  list<string>  $codes
     */
    public function scopeLatestPerCurrency(Builder $query, array $codes): Builder
    {
        return $query->whereIn('id', fn ($sub) => $sub
            ->selectRaw('MAX(id)')
            ->from('exchange_rates')
            ->whereIn('currency', $codes)
            ->groupBy('currency'));
    }
}
