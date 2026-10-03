<?php

namespace App\Models;

use App\Enums\EventChecklistItem;
use App\Traits\HasUuid;
use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One ticked (or once-ticked) checklist item of an event inquiry (Phase 8,
 * D-02). Created lazily on the first tick; un-ticking nulls `completed_at`
 * and `completed_by` and the activity log keeps the history. Written only by
 * `ToggleEventChecklistItemAction`. The derived `deposit` item never has a row.
 */
class EventInquiryChecklistItem extends Model
{
    use HasFactory, HasUuid, LogsActivity;

    protected $fillable = ['event_inquiry_id', 'item', 'completed_at', 'completed_by'];

    protected $casts = [
        'item'         => EventChecklistItem::class,
        'completed_at' => 'datetime',
    ];

    public function inquiry(): BelongsTo     { return $this->belongsTo(EventInquiry::class, 'event_inquiry_id'); }
    public function completedBy(): BelongsTo { return $this->belongsTo(User::class, 'completed_by'); }
}
