<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One immutable row per accepted housekeeping task status change (Phase 6,
 * D-03). Written only by the housekeeping single writers
 * (`App\Actions\Housekeeping\*`), in the same transaction as the change.
 * Twin of RoomStatusHistory: no uuid, no activity log, no `updated_at`, plain
 * string statuses.
 */
class HousekeepingTaskStatusHistory extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'housekeeping_task_status_history';

    protected $fillable = [
        'housekeeping_task_id',
        'from_status',
        'to_status',
        'changed_by',
        'reason',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function task(): BelongsTo
    {
        return $this->belongsTo(HousekeepingTask::class, 'housekeeping_task_id');
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
