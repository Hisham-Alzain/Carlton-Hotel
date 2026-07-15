<?php

namespace App\Listeners;

use App\Events\InquirySubmitted;
use App\Models\GuestNotification;
use App\Services\Notification\NotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;

class NotifyDepartmentOnInquiry implements ShouldQueue
{
    public function __construct(private readonly NotificationService $notifications) {}

    public function handle(InquirySubmitted $event): void
    {
        $inquiry = $event->inquiry;

        $this->notifications->notifyDepartment(
            $inquiry->department,
            GuestNotification::TYPE_INQUIRY_ROUTED,
            __('custom.notifications.inquiry_routed_title'),
            __('custom.notifications.inquiry_routed_body', ['name' => $inquiry->name]),
            ['inquiry_uuid' => $inquiry->uuid],
        );
    }
}
