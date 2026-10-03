<?php

namespace App\Services\Events;

use App\Actions\Events\SubmitInquiryAction;
use App\Enums\EventInquiryStatus;
use App\Exceptions\InquiryStateException;
use App\Models\EventInquiry;
use App\Models\User;

/**
 * Legacy service (not on BaseService — kept as is in Phase 8, PR-6). The
 * checklist and deposit writers are actions; reads and the notes writer live here.
 */
class EventInquiryService
{
    /** Everything `EventInquiryDetailResource` reads (D-29, budget D-30). */
    public const DETAIL_RELATIONS = [
        'requirements', 'assignedUser', 'guest', 'eventSpace',
        'checklistItems.completedBy', 'depositPayment.recorder',
    ];

    public function __construct(private readonly SubmitInquiryAction $action) {}

    public function submit(array $data, ?int $guestId = null): array
    {
        return $this->action->handle($data, $guestId);
    }

    public function adminIndex(): array
    {
        return ['data' => EventInquiry::with(['requirements', 'assignedUser'])
            ->withCount(['checklistItems as checklist_done_count' => fn ($q) => $q->whereNotNull('completed_at')])
            ->latest()
            ->paginate(20), 'code' => 200];
    }

    public function show(EventInquiry $inquiry): array
    {
        return ['data' => $inquiry->load(self::DETAIL_RELATIONS), 'code' => 200];
    }

    public function updateStatus(EventInquiry $inquiry, EventInquiryStatus|string $status): array
    {
        $status  = $status instanceof EventInquiryStatus ? $status : EventInquiryStatus::from($status);
        $allowed = $this->allowedTargets($inquiry->status);

        if (! in_array($status, $allowed, true)) {
            // Phase 8 (D-21): additive context, same code.
            throw new InquiryStateException(__('custom.errors.inquiry_state'), [
                'status'  => $inquiry->status->value,
                'allowed' => array_map(fn (EventInquiryStatus $s) => $s->value, $allowed),
            ]);
        }

        $inquiry->update(['status' => $status]);
        return ['data' => $inquiry->fresh(self::DETAIL_RELATIONS), 'code' => 200];
    }

    public function assign(EventInquiry $inquiry, User $user): array
    {
        $inquiry->update([
            'assigned_user_id' => $user->id,
            'status'           => $inquiry->status === EventInquiryStatus::NEW
                ? EventInquiryStatus::IN_REVIEW
                : $inquiry->status,
        ]);
        return ['data' => $inquiry->fresh(self::DETAIL_RELATIONS), 'code' => 200];
    }

    /**
     * Internal staff notes (D-01, D-20). Allowed in every status, including
     * cancelled. Plain single-column update: no lock and no concurrency token,
     * so the last write wins (If-Match deferred). The guest's `notes` is never touched.
     */
    public function updateStaffNotes(EventInquiry $inquiry, ?string $notes): array
    {
        $inquiry->update(['staff_notes' => $notes]);

        return ['data' => $inquiry->fresh(self::DETAIL_RELATIONS), 'code' => 200];
    }

    /** @return list<EventInquiryStatus> Enum cases cannot key a PHP array, so the table is a match. */
    private function allowedTargets(EventInquiryStatus $from): array
    {
        return match ($from) {
            EventInquiryStatus::NEW       => [EventInquiryStatus::IN_REVIEW, EventInquiryStatus::CANCELLED],
            EventInquiryStatus::IN_REVIEW => [EventInquiryStatus::QUOTED, EventInquiryStatus::CANCELLED],
            EventInquiryStatus::QUOTED    => [EventInquiryStatus::CONFIRMED, EventInquiryStatus::CANCELLED],
            EventInquiryStatus::CONFIRMED => [EventInquiryStatus::CANCELLED],
            EventInquiryStatus::CANCELLED => [],
        };
    }
}
