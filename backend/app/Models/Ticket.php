<?php

namespace App\Models;

use App\Traits\HasUuid;
use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Ticket extends Model
{
    use HasFactory, HasUuid, LogsActivity;

    const STATUS_OPEN     = 'open';
    const STATUS_ASSIGNED = 'assigned';
    const STATUS_RESOLVED = 'resolved';
    const STATUS_CLOSED   = 'closed';

    const STATUSES = [self::STATUS_OPEN, self::STATUS_ASSIGNED, self::STATUS_RESOLVED, self::STATUS_CLOSED];

    const CATEGORY_INQUIRY      = 'inquiry';
    const CATEGORY_COMPLAINT    = 'complaint';
    const CATEGORY_BOOKING_HELP = 'booking_help';
    const CATEGORY_MAINTENANCE  = 'maintenance';
    const CATEGORY_OTHER        = 'other';

    const SOURCE_CHATBOT = 'chatbot';

    const DEPARTMENT_CONCIERGE    = 'concierge';
    const DEPARTMENT_HOUSEKEEPING = 'housekeeping';
    const DEPARTMENT_RECEPTION    = 'reception';

    // category => department routing (mirrors ServiceRequest::TYPE_DEPARTMENTS)
    const CATEGORY_DEPARTMENTS = [
        self::CATEGORY_COMPLAINT   => self::DEPARTMENT_CONCIERGE,
        self::CATEGORY_MAINTENANCE => self::DEPARTMENT_HOUSEKEEPING,
        self::CATEGORY_BOOKING_HELP=> self::DEPARTMENT_RECEPTION,
    ];

    protected $fillable = [
        'guest_id', 'chatbot_session_id', 'conversation_id', 'subject', 'category',
        'status', 'priority', 'department', 'source', 'assigned_user_id',
    ];

    public function guest(): BelongsTo        { return $this->belongsTo(Guest::class); }
    public function conversation(): BelongsTo { return $this->belongsTo(Conversation::class); }
    public function assignedUser(): BelongsTo { return $this->belongsTo(User::class, 'assigned_user_id'); }

    // Normalizes the 1-3 int scale to ServiceRequest's low/normal/high vocabulary
    // so the merged ops queue exposes one consistent `priority` type for both.
    public function priorityLabel(): string
    {
        return match (true) {
            $this->priority <= 1 => 'low',
            $this->priority >= 3 => 'high',
            default => 'normal',
        };
    }
}
