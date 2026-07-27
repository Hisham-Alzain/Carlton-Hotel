<?php

namespace App\Models;

use App\Enums\Department;
use App\Enums\ServiceRequestPriority;
use App\Enums\ServiceRequestStatus;
use App\Traits\HasUuid;
use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
}
