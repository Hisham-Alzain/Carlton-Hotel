<?php

namespace App\Models;

use App\Enums\FolioDisputeStatus;
use App\Traits\HasUuid;
use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One dispute raised on a folio line item, by the guest or by staff (D-09).
 * A dispute is a flag, never a money movement and never a check-out gate
 * (D-11, D-12). Exactly one of guest_id / user_id is set.
 */
class FolioItemDispute extends Model
{
    use HasFactory, HasUuid, LogsActivity;

    protected $fillable = [
        'folio_item_id', 'status', 'reason', 'guest_id', 'user_id',
        'resolved_by', 'resolved_at', 'resolution_note',
    ];

    protected $casts = [
        'status'      => FolioDisputeStatus::class,
        'resolved_at' => 'datetime',
    ];

    public function item(): BelongsTo         { return $this->belongsTo(FolioItem::class, 'folio_item_id'); }
    public function guest(): BelongsTo        { return $this->belongsTo(Guest::class); }
    public function raisedByUser(): BelongsTo { return $this->belongsTo(User::class, 'user_id'); }
    public function resolver(): BelongsTo     { return $this->belongsTo(User::class, 'resolved_by'); }

    /** Phase 9 read hook (D-16). */
    public function scopeOpen(Builder $query): Builder
    {
        return $query->where('status', FolioDisputeStatus::OPEN->value);
    }

    /** `staff`, `guest`, or null once the raiser's account was deleted (FA-5.07-2). */
    public function raisedBy(): ?string
    {
        return match (true) {
            $this->user_id !== null  => 'staff',
            $this->guest_id !== null => 'guest',
            default                  => null,
        };
    }
}
