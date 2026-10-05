<?php

namespace App\Actions\Loyalty;

use App\Actions\Booking\QuoteReservationAction;
use App\Models\Guest;
use App\Models\RoomType;
use App\Support\LoyaltyMath;

/**
 * Side-effect-free booking preview (LOY-15): the quote plus the loyalty
 * discount, priced by the same calculator the booking uses (Pitfall 6).
 *
 * Writes nothing and takes no lock. It does not check availability either: the
 * booking itself may still answer `no_availability`.
 */
class PreviewLoyaltyAction
{
    public function __construct(
        private readonly QuoteReservationAction $quoteReservation,
        private readonly PriceLoyaltyRedemptionAction $priceRedemption,
    ) {}

    /**
     * @param  array{room_type_uuid: string, check_in: string, check_out: string, promo_code?: ?string, loyalty_points?: ?int, voucher_code?: ?string}  $data
     * @return array{data: array<string, mixed>, code: int}
     */
    public function handle(Guest $guest, array $data): array
    {
        $roomType = RoomType::query()->where('uuid', $data['room_type_uuid'])->firstOrFail();

        $quote = $this->quoteReservation->handle(
            $roomType,
            $data['check_in'],
            $data['check_out'],
            $data['promo_code'] ?? null,
        );

        $priced = $this->priceRedemption->handle($guest, $quote, $data)['data'];

        $priced['quote'] = [
            'nights' => $quote['nights'],
            'daily_rate_usd' => LoyaltyMath::fromQuote($quote['daily_rate_usd']),
            'subtotal_usd' => LoyaltyMath::fromQuote($quote['subtotal_usd']),
            'promo_discount_usd' => LoyaltyMath::fromQuote($quote['discount_usd']),
            'total_usd' => LoyaltyMath::fromQuote($quote['total_usd']),
        ];

        return ['data' => $priced, 'code' => 200];
    }
}
