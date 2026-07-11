<?php

namespace App\Models;

use App\Traits\HasUuid;
use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EventInquiry extends Model
{
    use HasFactory, HasUuid, LogsActivity;

    const STATUS_NEW       = 'new';
    const STATUS_IN_REVIEW = 'in_review';
    const STATUS_QUOTED    = 'quoted';
    const STATUS_CONFIRMED = 'confirmed';
    const STATUS_CANCELLED = 'cancelled';

    const DEPARTMENT_EVENTS = 'events';
    const DEPARTMENT_SALES  = 'sales';

    // Event types that route to the sales department
    const SALES_EVENT_TYPES = ['corporate', 'conference', 'product_launch'];

    protected $fillable = [
        'guest_id', 'event_space_id', 'assigned_user_id',
        'name', 'email', 'phone', 'company',
        'event_type', 'event_date', 'expected_guests',
        'budget_usd', 'notes', 'status', 'department',
    ];

    protected $casts = [
        'event_date' => 'date',
        'budget_usd' => 'decimal:2',
    ];

    public function guest(): BelongsTo       { return $this->belongsTo(Guest::class); }
    public function eventSpace(): BelongsTo  { return $this->belongsTo(EventSpace::class); }
    public function assignedUser(): BelongsTo { return $this->belongsTo(User::class, 'assigned_user_id'); }
    public function requirements(): HasMany  { return $this->hasMany(EventRequirement::class); }
}
