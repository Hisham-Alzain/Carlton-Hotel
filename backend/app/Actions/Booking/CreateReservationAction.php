<?php

namespace App\Actions\Booking;

use App\Contracts\ChannelAdapterInterface;
use App\Enums\ReservationStatus;
use App\Exceptions\NoAvailabilityException;
use App\Models\Guest;
use App\Models\Reservation;
use App\Models\RoomType;
use Illuminate\Support\Facades\DB;

class CreateReservationAction
{
    public function __construct(
        private readonly CheckAvailabilityAction $checkAvailability,
        private readonly QuoteReservationAction  $quote,
    ) {}

    // Identity-agnostic: caller resolves the Guest; this action handles lock + inventory.
    public function handle(Guest $guest, array $data, ChannelAdapterInterface $channel): array
    {
        return DB::transaction(function () use ($guest, $data, $channel) {
            // Pessimistic lock on the room_type row — channel-blind serialization point.
            $roomType = RoomType::where('id', $data['room_type_id'])
                ->lockForUpdate()
                ->firstOrFail();

            // A specific room is reserved now, not at check-in, so the guest can
            // be told "Room 801" the moment the booking is made. Picking the
            // room *is* the availability check — if none is free, none is free.
            $room = $this->checkAvailability->findFreeRoom(
                $roomType->id,
                $data['check_in'],
                $data['check_out'],
            );

            if (! $room) {
                throw new NoAvailabilityException(__('custom.errors.no_availability'));
            }

            $pricing = $this->quote->handle(
                $roomType,
                $data['check_in'],
                $data['check_out'],
                $data['promo_code'] ?? null,
            );

            $bookingCode = $this->generateBookingCode();

            $reservation = Reservation::create([
                'guest_id'        => $guest->id,
                'booking_code'    => $bookingCode,
                'source'          => $data['source']          ?? $channel->source(),
                'external_ref'    => $data['external_ref']    ?? null,
                'external_channel'=> $data['external_channel']?? null,
                'check_in'        => $data['check_in'],
                'check_out'       => $data['check_out'],
                'status'          => $data['status']          ?? ReservationStatus::PENDING,
                'hold_expires_at' => $data['hold_expires_at'] ?? null,
                'payment_method'  => $data['payment_method'],
                'total_usd'       => $pricing['total_usd'],
                'promo_code_id'   => $pricing['promo_code_id'],
            ]);

            // Snapshot the pre-promo subtotal per room; promo discount lives at reservation level
            $reservation->rooms()->create([
                'room_type_id' => $roomType->id,
                'room_id'      => $room->id,
                'price_usd'    => $pricing['subtotal_usd'],
            ]);

            // Increment promo usage inside the transaction
            if ($pricing['promo_code_id']) {
                \App\Models\PromoCode::where('id', $pricing['promo_code_id'])->increment('used_count');
            }

            $reservation->load(['rooms.roomType', 'rooms.room', 'guest', 'promoCode']);

            return ['data' => $reservation, 'code' => 201];
        });
    }

    private function generateBookingCode(): string
    {
        $alphabet = '0123456789ABCDEFGHJKMNPQRSTVWXYZ';
        do {
            $code = 'CARL-';
            for ($i = 0; $i < 8; $i++) {
                $code .= $alphabet[random_int(0, 31)];
            }
        } while (Reservation::where('booking_code', $code)->exists());
        return $code;
    }
}
