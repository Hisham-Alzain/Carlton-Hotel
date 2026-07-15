<?php

namespace App\Actions\Notification;

use App\Events\GuestConnected;
use App\Models\DeviceToken;
use App\Models\Guest;
use Illuminate\Support\Facades\DB;

class RegisterDeviceTokenAction
{
    public function handle(Guest $guest, string $token, string $platform): array
    {
        [$deviceToken, $isFirstEverForGuest] = DB::transaction(function () use ($guest, $token, $platform) {
            // Locks the guest row so two concurrent registrations for the same guest
            // serialize — otherwise both could see zero prior tokens and both fire
            // the welcome notification.
            Guest::whereKey($guest->id)->lockForUpdate()->first();

            $isFirstEverForGuest = ! DeviceToken::where('guest_id', $guest->id)->exists();

            $deviceToken = DeviceToken::updateOrCreate(
                ['token' => $token],
                ['guest_id' => $guest->id, 'platform' => $platform, 'last_used_at' => now()],
            );

            return [$deviceToken, $isFirstEverForGuest];
        });

        if ($isFirstEverForGuest) {
            event(new GuestConnected($guest));
        }

        return ['data' => $deviceToken, 'code' => 201];
    }
}
