<?php

namespace App\Services\Events;

use App\Actions\Events\SubmitInquiryAction;
use App\Exceptions\ReservationStateException;
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

    public function updateStatus(EventInquiry $inquiry, string $status): array
    {
        $allowed = [
            EventInquiry::STATUS_NEW       => [EventInquiry::STATUS_IN_REVIEW, EventInquiry::STATUS_CANCELLED],
            EventInquiry::STATUS_IN_REVIEW => [EventInquiry::STATUS_QUOTED, EventInquiry::STATUS_CANCELLED],
            EventInquiry::STATUS_QUOTED    => [EventInquiry::STATUS_CONFIRMED, EventInquiry::STATUS_CANCELLED],
            EventInquiry::STATUS_CONFIRMED => [EventInquiry::STATUS_CANCELLED],
            EventInquiry::STATUS_CANCELLED => [],
        ];

        if (!in_array($status, $allowed[$inquiry->status] ?? [])) {
            throw new ReservationStateException(__('custom.errors.inquiry_state'));
        }

        $inquiry->update(['status' => $status]);
        return ['data' => $inquiry->fresh()->load(['requirements', 'assignedUser']), 'code' => 200];
    }

    public function assign(EventInquiry $inquiry, User $user): array
    {
        $inquiry->update([
            'assigned_user_id' => $user->id,
            'status'           => $inquiry->status === EventInquiry::STATUS_NEW
                ? EventInquiry::STATUS_IN_REVIEW
                : $inquiry->status,
        ]);
        return ['data' => $inquiry->fresh()->load(['requirements', 'assignedUser']), 'code' => 200];
    }
}
