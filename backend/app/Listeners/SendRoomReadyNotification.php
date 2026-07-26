<?php

namespace App\Listeners;

use App\Enums\NotificationType;
use App\Events\RoomAssigned;
use App\Services\Notification\NotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;

class SendRoomReadyNotification implements ShouldQueue
{
    public function __construct(private readonly NotificationService $notifications) {}

    public function handle(RoomAssigned $event): void
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
