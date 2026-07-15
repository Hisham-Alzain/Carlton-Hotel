<?php

namespace App\Services\Notification;

use App\Actions\Notification\RegisterDeviceTokenAction;
use App\Models\Guest;

class DeviceTokenService
{
    public function __construct(private readonly RegisterDeviceTokenAction $action) {}

    public function register(Guest $guest, array $data): array
    {
        return $this->action->handle($guest, $data['token'], $data['platform']);
    }
}
