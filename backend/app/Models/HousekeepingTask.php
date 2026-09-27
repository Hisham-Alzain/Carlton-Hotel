<?php

namespace App\Models;

use App\Enums\HousekeepingTaskStatus;
use App\Enums\HousekeepingTaskType;
use App\Enums\ServiceRequestPriority;
use App\Traits\HasUuid;
use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A unit of housekeeping work on one room (Phase 6, D-01).
 *
 * Created only by `CreateHousekeepingTaskAction::ensureOpen()`; status and
 * assignee change only through the housekeeping single writers.
 *
 * `dedupe_key` and `service_request_id` are deliberately not fillable:
 *  - the dedupe key is derived by the saving hook below and nowhere else
 *    (D-02: no action, listener, command, service or request writes it);
 *  - the service request is attached with `serviceRequest()->associate()`
 *    (consultant override: a plain unique FK instead of a morph).
 */
class HousekeepingTask extends Model
{
    use HasFactory, HasUuid, LogsActivity;

    protected $fillable = [
        'room_id',
        'reservation_id',
        'type',
        'status',
        'priority',
        'assigned_user_id',
        'due_at',
        'started_at',
        'completed_at',
        'completed_by',
        'created_by',
        'notes',
    ];

    protected $attributes = [
        'status'   => 'pending',
        'priority' => 'normal',
    ];

    protected $casts = [
        'type'         => HousekeepingTaskType::class,
        'status'       => HousekeepingTaskStatus::class,
        'priority'     => ServiceRequestPriority::class,
        'due_at'       => 'datetime',
        'started_at'   => 'datetime',
        'completed_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        // D-02: the only writer of the dedupe column. Every save re-derives it,
        // so closing a task frees its key and a caller-supplied value is ignored.
        static::saving(function (HousekeepingTask $task) {
            $task->dedupe_key = $task->derivedDedupeKey();
        });
    }

    /**
     * `{room_id}:{type}` while a turnover, stayover or inspection task is open;
     * NULL for request tasks and for every closed task.
     */
    public function derivedDedupeKey(): ?string
    {
        $type   = $this->type instanceof HousekeepingTaskType ? $this->type : HousekeepingTaskType::tryFrom((string) $this->type);
        $status = $this->status instanceof HousekeepingTaskStatus ? $this->status : HousekeepingTaskStatus::tryFrom((string) $this->status);

        if ($type === null || $status === null || ! $type->isDeduped() || ! $status->isOpen() || $this->room_id === null) {
            return null;
        }

        return "{$this->room_id}:{$type->value}";
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereIn('status', array_map(
            static fn (HousekeepingTaskStatus $s) => $s->value,
            HousekeepingTaskStatus::open(),
        ));
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class)->withTrashed();
    }

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class);
    }

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    public function completedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** Set for request tasks only (consultant override: plain FK, no morph). */
    public function serviceRequest(): BelongsTo
    {
        return $this->belongsTo(ServiceRequest::class);
    }

    public function history(): HasMany
    {
        return $this->hasMany(HousekeepingTaskStatusHistory::class);
    }
}
