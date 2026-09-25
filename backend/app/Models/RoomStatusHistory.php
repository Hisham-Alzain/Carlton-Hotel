<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One immutable row per accepted housekeeping status change (Phase 2, D-03).
 *
 * Written only by `App\Actions\Cms\UpdateRoomStatusAction`. The row is itself
 * the audit record, so it carries no activity-log, uuid or soft-delete trait,
 * and no `updated_at`. `from_status` / `to_status` stay plain strings so the
 * history remains readable if the enum changes later.
 */
class RoomStatusHistory extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'room_status_history';

    protected $fillable = [
        'room_id',
        'from_status',
        'to_status',
        'changed_by',
        'reason',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
