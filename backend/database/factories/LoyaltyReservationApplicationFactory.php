<?php

namespace Database\Factories;

use App\Enums\LoyaltyApplicationStatus;
use App\Models\LoyaltyReservationApplication;
use App\Models\Reservation;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<LoyaltyReservationApplication>
 */
class LoyaltyReservationApplicationFactory extends Factory
{
    protected $model = LoyaltyReservationApplication::class;

    public function definition(): array
    {
        return [
            'reservation_id' => Reservation::factory(),
            // The application belongs to the reservation's own guest.
            'guest_id' => fn (array $attributes) => Reservation::query()->whereKey($attributes['reservation_id'])->value('guest_id'),
            'idempotency_key' => 'factory-'.Str::random(16),
            'redeem_entry_id' => null,
            'points_redeemed' => 0,
            'points_discount_usd' => '0.00',
            'voucher_id' => null,
            'voucher_discount_usd' => '0.00',
            'status' => LoyaltyApplicationStatus::APPLIED,
            'reversed_at' => null,
        ];
    }
}
