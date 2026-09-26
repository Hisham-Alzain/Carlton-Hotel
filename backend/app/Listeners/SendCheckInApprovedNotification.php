<?php

namespace App\Listeners;

use App\Enums\NotificationType;
use App\Events\CheckInApproved;
use App\Services\Notification\NotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;

class SendCheckInApprovedNotification implements ShouldQueue
{
    public function __construct(private readonly NotificationService $notifications) {}

    /**
     * Tells the guest their key is ready in the app (D-14). The key itself is
     * never read here: it must not reach a push, a guest_notifications row or
     * the Firestore mirror. Auto-discovered from the type hint.
     */
    public function handle(CheckInApproved $event): void
    {
        $guest = $event->reservation->guest;
        if (! $guest) {
            return;
        }

        $this->notifications->pushToGuest(
            $guest,
            NotificationType::CHECK_IN_APPROVED,
            __('custom.notifications.check_in_approved.title'),
            __('custom.notifications.check_in_approved.body'),
            ['reservation_uuid' => $event->reservation->uuid],
        );
    }
}
