<?php

namespace App\Services\Events;

use App\Actions\Events\SubmitInquiryAction;
use App\Enums\EventInquiryStatus;
use App\Exceptions\InquiryStateException;
use App\Models\EventInquiry;
use App\Models\User;

class EventInquiryService
{
    public function __construct(private readonly SubmitInquiryAction $action) {}

    public function submit(array $data, ?int $guestId = null): array
    {
        return $this->action->handle($data, $guestId);
    }

    public function adminIndex(): array
    {
        return ['data' => EventInquiry::with(['requirements', 'assignedUser'])
            ->latest()
            ->paginate(20), 'code' => 200];
    }

    public function show(EventInquiry $inquiry): array
    {
        return ['data' => $inquiry->load(['requirements', 'assignedUser', 'guest', 'eventSpace']), 'code' => 200];
    }

    public function updateStatus(EventInquiry $inquiry, EventInquiryStatus|string $status): array
    {
        $status = $status instanceof EventInquiryStatus ? $status : EventInquiryStatus::from($status);

        // Enum cases cannot key a PHP array, so the transition table is a match.
        $allowed = match ($inquiry->status) {
            EventInquiryStatus::NEW       => [EventInquiryStatus::IN_REVIEW, EventInquiryStatus::CANCELLED],
            EventInquiryStatus::IN_REVIEW => [EventInquiryStatus::QUOTED, EventInquiryStatus::CANCELLED],
            EventInquiryStatus::QUOTED    => [EventInquiryStatus::CONFIRMED, EventInquiryStatus::CANCELLED],
            EventInquiryStatus::CONFIRMED => [EventInquiryStatus::CANCELLED],
            EventInquiryStatus::CANCELLED => [],
        };

        if (! in_array($status, $allowed, true)) {
            throw new InquiryStateException(__('custom.errors.inquiry_state'));
        }

        $inquiry->update(['status' => $status]);
        return ['data' => $inquiry->fresh()->load(['requirements', 'assignedUser']), 'code' => 200];
    }

    public function assign(EventInquiry $inquiry, User $user): array
    {
        $inquiry->update([
            'assigned_user_id' => $user->id,
            'status'           => $inquiry->status === EventInquiryStatus::NEW
                ? EventInquiryStatus::IN_REVIEW
                : $inquiry->status,
        ]);
        return ['data' => $inquiry->fresh()->load(['requirements', 'assignedUser']), 'code' => 200];
    }
}
