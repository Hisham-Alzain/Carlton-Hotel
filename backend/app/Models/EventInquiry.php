<?php

namespace App\Models;

use App\Enums\Department;
use App\Enums\EventDepositStatus;
use App\Enums\EventInquiryStatus;
use App\Enums\EventType;
use App\Traits\HasUuid;
use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;

/**
 * An event RFP lead.
 *
 * `notes` is the guest's own brief and is never written by staff routes;
 * staff write `staff_notes` (Phase 8, D-01). `deposit_status` /
 * `deposit_paid_at` are current state over the `payments` ledger, written
 * only by `RecordEventDepositAction` (D-05, D-15).
 *
 * This model must never join `Relation::morphMap()`: the payments it owns are
 * written with the FQCN `payable_type` and the deposit replay lookup depends
 * on that staying stable (D-15, PR-8).
 */
class EventInquiry extends Model
{
    use HasFactory, HasUuid, LogsActivity;

    protected $fillable = [
        'guest_id', 'event_space_id', 'assigned_user_id',
        'name', 'email', 'phone', 'company',
        'event_type', 'event_date', 'expected_guests',
        'budget_usd', 'notes', 'status', 'department',
        'staff_notes', 'deposit_status', 'deposit_paid_at',
    ];

    protected $casts = [
        'status'          => EventInquiryStatus::class,
        'department'      => Department::class,
        'event_type'      => EventType::class,
        'event_date'      => 'date',
        'budget_usd'      => 'decimal:2',
        'deposit_status'  => EventDepositStatus::class,
        'deposit_paid_at' => 'datetime',
    ];

    /** Model-level default so a fresh `new EventInquiry` matches the column default. */
    protected $attributes = [
        'deposit_status' => 'unpaid',
    ];

    public function guest(): BelongsTo       { return $this->belongsTo(Guest::class); }
    public function eventSpace(): BelongsTo  { return $this->belongsTo(EventSpace::class); }
    public function assignedUser(): BelongsTo { return $this->belongsTo(User::class, 'assigned_user_id'); }
    public function requirements(): HasMany  { return $this->hasMany(EventRequirement::class); }

    /** Lazily created staff checklist rows (D-02); `deposit` never has one. */
    public function checklistItems(): HasMany
    {
        return $this->hasMany(EventInquiryChecklistItem::class);
    }

    /** In this phase every payment on an inquiry is its deposit (D-05). */
    public function payments(): MorphMany
    {
        return $this->morphMany(Payment::class, 'payable');
    }

    /** The latest completed deposit payment, if any. */
    public function depositPayment(): MorphOne
    {
        // The constraint goes inside ofMany(): an outer where() would be applied
        // after the max(id) pick and could hide a completed payment.
        return $this->morphOne(Payment::class, 'payable')
            ->ofMany(['id' => 'max'], fn ($query) => $query->where('status', 'completed'));
    }
}
