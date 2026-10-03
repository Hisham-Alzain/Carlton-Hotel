<?php

namespace App\Http\Resources\Events;

use App\Enums\EventChecklistItem;
use App\Enums\EventDepositStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Event inquiry, staff list shape (and, via `forGuest()`, the guest's own
 * submit receipt).
 *
 * Phase 8 (D-29) adds staff-side keys additively: `staff_notes`, deposit state
 * and checklist progress. `notes` keeps its meaning — the guest's brief (D-01).
 * Every value comes from columns or loaded relations; nothing here queries.
 */
class EventInquiryResource extends JsonResource
{
    /** False for the public submit response: staff-internal keys stay out of it. */
    protected bool $staffView = true;

    public static function forGuest(mixed $resource): static
    {
        $instance            = new static($resource);
        $instance->staffView = false;

        return $instance;
    }

    public function toArray(Request $request): array
    {
        $data = [
            'uuid'            => $this->uuid,
            'name'            => $this->name,
            'email'           => $this->email,
            'phone'           => $this->phone,
            'company'         => $this->company,
            'event_type'      => $this->event_type,
            'event_date'      => $this->event_date?->toDateString(),
            'expected_guests' => $this->expected_guests,
            'budget_usd'      => $this->budget_usd,
            'notes'           => $this->notes,
            'status'          => $this->status,
            'department'      => $this->department,
            'assigned_to'     => $this->whenLoaded('assignedUser', fn () => $this->assignedUser?->uuid),
            'requirements'    => $this->whenLoaded('requirements', fn () => EventRequirementResource::collection($this->requirements)),
            'created_at'      => $this->created_at?->toIso8601String(),
        ];

        if (! $this->staffView) {
            return $data;
        }

        return $data + [
            'staff_notes'          => $this->staff_notes,
            'deposit_status'       => $this->depositStatus()->value,
            'deposit_paid_at'      => $this->deposit_paid_at?->toIso8601String(),
            'checklist_done_count' => $this->checklistDoneCount(),
            'checklist_total'      => count(EventChecklistItem::cases()),
        ];
    }

    protected function depositStatus(): EventDepositStatus
    {
        return $this->deposit_status ?? EventDepositStatus::UNPAID;
    }

    /**
     * Stored ticks (from the list's `withCount`, else the loaded rows) plus the
     * derived deposit tick when paid (D-04, FA-8.03-1). Never queries.
     */
    protected function checklistDoneCount(): int
    {
        $stored = match (true) {
            isset($this->checklist_done_count)              => (int) $this->checklist_done_count,
            $this->relationLoaded('checklistItems')         => $this->checklistItems->whereNotNull('completed_at')->count(),
            default                                         => 0,
        };

        return $stored + ($this->depositStatus() === EventDepositStatus::PAID ? 1 : 0);
    }
}
