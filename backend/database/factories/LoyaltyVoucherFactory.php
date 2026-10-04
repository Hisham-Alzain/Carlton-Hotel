<?php

namespace Database\Factories;

use App\Enums\LoyaltyRewardType;
use App\Enums\LoyaltyVoucherStatus;
use App\Models\Guest;
use App\Models\LoyaltyVoucher;
use App\Models\Reservation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LoyaltyVoucher>
 */
class LoyaltyVoucherFactory extends Factory
{
    /** Crockford base32: no I, L, O or U, so a code survives being read aloud. */
    private const CODE_ALPHABET = '0123456789ABCDEFGHJKMNPQRSTVWXYZ';

    protected $model = LoyaltyVoucher::class;

    public function definition(): array
    {
        return [
            'code' => 'LOY-'.$this->randomCode(8),
            'guest_id' => Guest::factory(),
            'loyalty_reward_id' => null,
            'type' => LoyaltyRewardType::DISCOUNT_VOUCHER,
            'reward_name' => ['en' => '25 USD discount', 'ar' => 'خصم 25 دولار'],
            'value_usd' => '25.00',
            'points_spent' => 2500,
            'status' => LoyaltyVoucherStatus::ACTIVE,
            'expires_at' => now()->addDays(90),
            'reservation_id' => null,
            'used_at' => null,
        ];
    }

    public function used(Reservation $reservation): static
    {
        return $this->state([
            'status' => LoyaltyVoucherStatus::USED,
            'reservation_id' => $reservation->id,
            'used_at' => now(),
        ]);
    }

    public function expired(): static
    {
        return $this->state([
            'status' => LoyaltyVoucherStatus::EXPIRED,
            'expires_at' => now()->subDay(),
        ]);
    }

    private function randomCode(int $length): string
    {
        $code = '';
        for ($i = 0; $i < $length; $i++) {
            $code .= self::CODE_ALPHABET[random_int(0, strlen(self::CODE_ALPHABET) - 1)];
        }

        return $code;
    }
}
