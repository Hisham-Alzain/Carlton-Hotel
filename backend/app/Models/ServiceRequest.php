<?php

namespace App\Models;

use App\Traits\HasUuid;
use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServiceRequest extends Model
{
    use HasFactory, HasUuid, LogsActivity;

    const STATUS_NEW         = 'new';
    const STATUS_IN_PROGRESS = 'in_progress';
    const STATUS_COMPLETED   = 'completed';
    const STATUS_CANCELLED   = 'cancelled';

    const STATUSES = [self::STATUS_NEW, self::STATUS_IN_PROGRESS, self::STATUS_COMPLETED, self::STATUS_CANCELLED];

    const PRIORITY_LOW    = 'low';
    const PRIORITY_NORMAL = 'normal';
    const PRIORITY_HIGH   = 'high';

    const DEPARTMENT_KITCHEN      = 'kitchen';
    const DEPARTMENT_HOUSEKEEPING = 'housekeeping';
    const DEPARTMENT_CONCIERGE    = 'concierge';

    // type => department routing (mirrors P6's SubmitInquiryAction pattern)
    const TYPE_DEPARTMENTS = [
        'room_service' => self::DEPARTMENT_KITCHEN,
        'housekeeping' => self::DEPARTMENT_HOUSEKEEPING,
    ];

    protected $fillable = [
        'guest_id', 'reservation_id', 'type', 'department',
        'status', 'priority', 'assigned_user_id', 'notes',
    ];

    public function guest(): BelongsTo       { return $this->belongsTo(Guest::class); }
    public function reservation(): BelongsTo { return $this->belongsTo(Reservation::class); }
    public function assignedUser(): BelongsTo { return $this->belongsTo(User::class, 'assigned_user_id'); }
}
