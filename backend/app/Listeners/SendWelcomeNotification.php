<?php

namespace App\Listeners;

use App\Events\GuestConnected;
use App\Models\GuestNotification;
use App\Services\Notification\NotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;

class SendWelcomeNotification implements ShouldQueue
{
    public function __construct(private readonly NotificationService $notifications) {}

    public function handle(GuestConnected $event): void
    {
        $this->notifications->pushToGuest(
            $event->guest,
            GuestNotification::TYPE_WELCOME,
            __('custom.notifications.welcome_title'),
            __('custom.notifications.welcome_body'),
        );
    }
}
