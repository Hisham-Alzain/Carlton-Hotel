<?php

namespace App\Http\Resources\Loyalty;

use App\Base\BaseResource;
use App\Models\Guest;
use App\Models\User;
use Illuminate\Http\Request;

/**
 * A guest's loyalty balance (Q16). Wraps the array built by
 * LoyaltyAccountService::account(), so it never queries. There are no tier
 * fields. `guest` is added for a staff caller only; the guest route never
 * echoes the caller's own identity back.
 *
 * @property array<string, mixed> $resource
 */
class LoyaltyAccountResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        $account = $this->resource;
        $guest = $account['guest'] ?? null;

        return [
            'available_points' => $account['available_points'],
            'expiring_soon_points' => $account['expiring_soon_points'],
            'expiring_soon_window_days' => $account['expiring_soon_window_days'],
            'next_expiry_at' => $account['next_expiry_at']?->toIso8601String(),
            'lifetime_earned_points' => $account['lifetime_earned_points'],
            'lifetime_redeemed_points' => $account['lifetime_redeemed_points'],
            'program' => $account['program'],
            'redeem_value_usd' => $account['redeem_value_usd'],
            'min_redeem_points' => $account['min_redeem_points'],
            'max_redeem_percent' => $account['max_redeem_percent'],
            'guest' => $this->when(
                $request->user() instanceof User && $guest instanceof Guest,
                fn () => ['uuid' => $guest->uuid, 'name' => $guest->name],
            ),
        ];
    }
}
