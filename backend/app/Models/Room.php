<?php

namespace App\Models;

use App\Enums\RoomStatus;
use App\Models\ReservationRoom;
use App\Traits\HasUuid;
use App\Traits\LogsActivity;
use App\Traits\PurgesMedia;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * `status` is the housekeeping status only (Phase 2, D-01); occupancy is derived
 * from reservations. `status_changed_at` / `status_changed_by` are a
 * denormalised copy of the latest `room_status_history` row, kept out of
 * `$fillable`: their sync owner is `App\Actions\Cms\UpdateRoomStatusAction`.
 */
class Room extends Model
{
    use HasFactory, HasUuid, LogsActivity, PurgesMedia, SoftDeletes;

    protected $fillable = [
        'room_type_id',
        'number',
        'floor',
        'status',
        'is_active',
    ];

    protected $casts = [
        'status'            => RoomStatus::class,
        'is_active'         => 'boolean',
        'status_changed_at' => 'datetime',
    ];

    public function roomType(): BelongsTo
    {
        return $this->belongsTo(RoomType::class);
    }

    public function images(): MorphMany
    {
        return $this->morphMany(Media::class, 'mediable')->orderBy('sort_order');
    }

    public function reservationRooms(): HasMany
    {
        return $this->hasMany(ReservationRoom::class);
    }

    public function statusChangedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'status_changed_by');
    }

    public function statusHistory(): HasMany
    {
        return $this->hasMany(RoomStatusHistory::class);
    }
}
