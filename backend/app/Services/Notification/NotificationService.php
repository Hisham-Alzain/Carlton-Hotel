<?php

namespace App\Services\Notification;

use App\Contracts\FirebaseServiceInterface;
use App\Models\Guest;
use App\Models\GuestNotification;

class NotificationService
{
    public function __construct(private readonly FirebaseServiceInterface $firebase) {}

    /**
     * Not DB-transactional on purpose: the FCM call is external and must not
     * roll back the notification record if it fails — the record should
     * persist as a queryable fact either way (see notifyDepartment below).
     *
     * @param array<string, mixed> $data
     */
    public function pushToGuest(Guest $guest, string $type, string $title, string $body, array $data = []): GuestNotification
    {
        $notification = GuestNotification::create([
            'guest_id' => $guest->id,
            'type'     => $type,
            'title'    => $title,
            'body'     => $body,
            'data'     => $data,
        ]);

        $tokens = $guest->deviceTokens()->pluck('token')->all();
        if (! empty($tokens)) {
            $this->firebase->sendPush($tokens, $title, $body, $data);
            $notification->update(['sent_at' => now()]);
        }

        return $notification->fresh();
    }

    /**
     * Records a department-addressed notification. No push channel exists for
     * staff (§3.6 — the dashboard is web, live-updated via Firestore, not FCM);
     * this is a persisted, queryable fact. Not yet read by anything — P10's
     * OperationsQueueService doesn't query guest_notifications; a future
     * dashboard extension or P12 reporting can read this table directly.
     *
     * @param array<string, mixed> $data
     */
    public function notifyDepartment(string $department, string $type, string $title, string $body, array $data = []): GuestNotification
    {
        return GuestNotification::create([
            'department' => $department,
            'type'       => $type,
            'title'      => $title,
            'body'       => $body,
            'data'       => $data,
            'sent_at'    => now(),
        ]);
    }
}
