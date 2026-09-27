<?php

namespace App\Models;

use App\Enums\Department;
use App\Enums\ServiceRequestPriority;
use App\Enums\ServiceRequestStatus;
use App\Traits\HasUuid;
use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ServiceRequest extends Model
{
    use HasFactory, HasUuid, LogsActivity;

    protected $fillable = [
        'guest_id', 'reservation_id', 'service_item_id', 'type', 'department',
        'status', 'priority', 'assigned_user_id', 'notes',
    ];

    protected $casts = [
        'status'     => ServiceRequestStatus::class,
        'priority'   => ServiceRequestPriority::class,
        'department' => Department::class,
    ];

    public function guest(): BelongsTo       { return $this->belongsTo(Guest::class); }
    public function reservation(): BelongsTo { return $this->belongsTo(Reservation::class); }
    public function assignedUser(): BelongsTo { return $this->belongsTo(User::class, 'assigned_user_id'); }

    /** Null for legacy free-string requests placed outside the catalog. */
    public function serviceItem(): BelongsTo { return $this->belongsTo(ServiceItem::class); }

    /** The request task created for a housekeeping-department request (Phase 6, D-10); at most one. */
    public function housekeepingTask(): HasOne { return $this->hasOne(HousekeepingTask::class); }

    /**
     * Adds a `room_number` column: the number of the room on the reservation's
     * first line (lowest reservation_rooms.id) that has a room, else null.
     * One correlated subselect instead of an eager chain; used by the
     * operations queue (Phase 6, D-14) and the staff request board (D-16).
     */
    public function scopeWithRoomNumber(Builder $query): Builder
    {
        if ($query->getQuery()->columns === null) {
            $query->select($query->getModel()->getTable() . '.*');
        }

        // withTrashed: a room retired after the stay still names where the guest was.
        return $query->addSelect(['room_number' => Room::withTrashed()
            ->select('rooms.number')
            ->join('reservation_rooms', 'reservation_rooms.room_id', '=', 'rooms.id')
            ->whereColumn('reservation_rooms.reservation_id', 'service_requests.reservation_id')
            ->orderBy('reservation_rooms.id')
            ->limit(1),
        ]);
    }
}
