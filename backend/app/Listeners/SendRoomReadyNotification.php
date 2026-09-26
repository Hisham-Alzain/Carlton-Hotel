<?php

namespace App\Listeners;

use App\Enums\NotificationType;
use App\Events\GuestCheckedIn;
use App\Events\RoomAssigned;
use App\Services\Notification\NotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;

class SendRoomReadyNotification implements ShouldQueue
{
    public function __construct(private readonly NotificationService $notifications) {}

    /**
     * Fires when staff check the guest in (GuestCheckedIn) and when a
     * checked-in guest is moved to another room (RoomAssigned), never on a
     * pre-arrival assignment (D-05). Auto-discovered from this union type.
     */
    public function handle(RoomAssigned|GuestCheckedIn $event): void
    {
        $guest = $event->reservation->guest;
        if (! $guest) {
            return;
        }

        $this->notifications->pushToGuest(
            $guest,
            NotificationType::ROOM_READY,
            __('custom.notifications.room_ready_title'),
            __('custom.notifications.room_ready_body'),
            ['reservation_uuid' => $event->reservation->uuid],
        );
    }
}
