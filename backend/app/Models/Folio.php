<?php

namespace App\Models;

use App\Enums\FolioDisputeStatus;
use App\Enums\FolioStatus;
use App\Support\FolioLedger;
use App\Traits\HasUuid;
use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Folio extends Model
{
    use HasFactory, HasUuid, LogsActivity;

    protected $fillable = [
        'reservation_id', 'status', 'subtotal_usd', 'total_usd',
        'approved_by_guest_at', 'settled_at',
    ];

    protected $casts = [
        'status'               => FolioStatus::class,
        'subtotal_usd'         => 'decimal:2',
        'total_usd'            => 'decimal:2',
        'approved_by_guest_at' => 'datetime',
        'settled_at'           => 'datetime',
    ];

    public function reservation(): BelongsTo { return $this->belongsTo(Reservation::class); }
    public function items(): HasMany         { return $this->hasMany(FolioItem::class); }
    public function payments(): MorphMany     { return $this->morphMany(Payment::class, 'payable'); }

    /** Every dispute on any item of this folio (D-09). */
    public function disputes(): HasManyThrough
    {
        return $this->hasManyThrough(FolioItemDispute::class, FolioItem::class);
    }

    /** Phase 9 read hook (D-12, D-16): the open disputes, the ones flagged to the desk. */
    public function openDisputes(): HasManyThrough
    {
        return $this->disputes()->where('folio_item_disputes.status', FolioDisputeStatus::OPEN->value);
    }

    /** Phase 9 read hook (D-16): folios still open. */
    public function scopeUnsettled(Builder $query): Builder
    {
        return $query->where('status', FolioStatus::OPEN->value);
    }

    /** Phase 9 read hook (D-12, D-16): folios with at least one open dispute. A flag, never a gate. */
    public function scopeWithOpenDisputes(Builder $query): Builder
    {
        return $query->whereHas('openDisputes');
    }

    /** Phase 9 read hook (D-16): open disputes across this folio's items (a fresh count query). */
    public function openDisputesCount(): int
    {
        return $this->openDisputes()->count();
    }

    /**
     * Every payment that counts toward this folio: those whose payable is the
     * folio OR its reservation, so reservation-level deposits count (D-03).
     *
     * The OR is one grouped where, so any status filter a caller chains is
     * ANDed with the whole group. No status filter here: FolioLedger::paid()
     * counts completed rows only, and the folio response lists every row.
     *
     * Refunds are not subtracted yet; the refund phase must revisit this.
     */
    public function ledgerPayments(): Builder
    {
        $folioType       = $this->getMorphClass();
        $reservationType = (new Reservation())->getMorphClass();

        return Payment::query()->where(function (Builder $query) use ($folioType, $reservationType) {
            $query->where(fn (Builder $q) => $q->where('payable_type', $folioType)->where('payable_id', $this->id))
                ->orWhere(fn (Builder $q) => $q->where('payable_type', $reservationType)->where('payable_id', $this->reservation_id));
        });
    }

    /** Completed ledger payments, as an exact 2dp string (always a fresh query). */
    public function paidUsd(): string
    {
        return FolioLedger::paid($this->ledgerPayments()->get(['amount_usd', 'status']));
    }

    /** total_usd minus paidUsd(): signed, never clamped (D-03). */
    public function balanceDueUsd(): string
    {
        return FolioLedger::balance((string) $this->total_usd, $this->paidUsd());
    }

    /**
     * Recompute subtotal_usd and total_usd from the stored rows (D-07).
     *
     * The caller holds the folio row lock. The rows are re-read and folded in
     * bcmath rather than summed in SQL, because SQLite returns a REAL for SUM()
     * (e.g. 19.999999999999996) that bcmath would truncate. total equals
     * subtotal until tax/service lines exist. save() is a no-op when nothing
     * changed, so an unchanged refresh writes no activity row.
     */
    public function recalculateTotals(): string
    {
        $total = FolioLedger::sum($this->items()->pluck('amount_usd'));

        $this->forceFill(['subtotal_usd' => $total, 'total_usd' => $total])->save();

        return $total;
    }
}
