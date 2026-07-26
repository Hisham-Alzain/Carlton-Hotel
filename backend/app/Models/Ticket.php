<?php

namespace App\Models;

use App\Enums\Department;
use App\Enums\ServiceRequestPriority;
use App\Enums\TicketCategory;
use App\Enums\TicketSource;
use App\Enums\TicketStatus;
use App\Traits\HasUuid;
use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Ticket extends Model
{
    use HasFactory, HasUuid, LogsActivity;

    protected $fillable = [
        'guest_id', 'chatbot_session_id', 'conversation_id', 'subject', 'category',
        'status', 'priority', 'department', 'source', 'assigned_user_id',
    ];

    protected $casts = [
        'status'     => TicketStatus::class,
        'category'   => TicketCategory::class,
        'department' => Department::class,
        'source'     => TicketSource::class,
    ];

    public function guest(): BelongsTo        { return $this->belongsTo(Guest::class); }
    public function conversation(): BelongsTo { return $this->belongsTo(Conversation::class); }
    public function assignedUser(): BelongsTo { return $this->belongsTo(User::class, 'assigned_user_id'); }

    // Normalizes the 1-3 int scale to ServiceRequest's low/normal/high vocabulary
    // so the merged ops queue exposes one consistent `priority` type for both.
    public function priorityLabel(): ServiceRequestPriority
    {
        return ServiceRequestPriority::fromTicketScale((int) $this->priority);
    }
}
