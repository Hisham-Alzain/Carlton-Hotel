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
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Spatie\Activitylog\Support\LogOptions;

/**
 * A support ticket. Carries current state only; its history lives in the
 * `ticket_actions` timeline (Phase 7, D-01/A8). Writes go through the ticket
 * single writers in `App\Actions\Tickets`.
 */
class Ticket extends Model
{
    use HasFactory, HasUuid, LogsActivity;

    /**
     * Set by TicketService::show() when the ticket has more timeline rows than
     * the loaded newest 200 (PR-3). Presentation only; never persisted.
     */
    public bool $actionsTruncated = false;

    protected $fillable = [
        'guest_id', 'chatbot_session_id', 'conversation_id', 'subject', 'description', 'category',
        'status', 'priority', 'department', 'source', 'assigned_user_id',
        'reservation_id', 'room_id', 'created_by', 'resolved_at', 'closed_at', 'escalation_level',
    ];

    protected $casts = [
        'status'           => TicketStatus::class,
        'category'         => TicketCategory::class,
        'department'       => Department::class,
        'source'           => TicketSource::class,
        'resolved_at'      => 'datetime',
        'closed_at'        => 'datetime',
        'escalation_level' => 'integer',
    ];

    /** Guest complaint text stays out of activity_log diffs (council A6). */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->logExcept(['description']);
    }

    public function guest(): BelongsTo        { return $this->belongsTo(Guest::class); }
    public function conversation(): BelongsTo { return $this->belongsTo(Conversation::class); }
    public function assignedUser(): BelongsTo { return $this->belongsTo(User::class, 'assigned_user_id'); }
    public function reservation(): BelongsTo  { return $this->belongsTo(Reservation::class); }
    public function room(): BelongsTo         { return $this->belongsTo(Room::class)->withTrashed(); }
    public function createdBy(): BelongsTo    { return $this->belongsTo(User::class, 'created_by'); }
    public function actions(): HasMany        { return $this->hasMany(TicketAction::class); }

    public function recoveries(): HasManyThrough
    {
        return $this->hasManyThrough(TicketRecovery::class, TicketAction::class, 'ticket_id', 'ticket_action_id');
    }

    // Normalizes the 1-3 int scale to ServiceRequest's low/normal/high vocabulary
    // so the merged ops queue exposes one consistent `priority` type for both.
    public function priorityLabel(): ServiceRequestPriority
    {
        return ServiceRequestPriority::fromTicketScale((int) $this->priority);
    }
}
