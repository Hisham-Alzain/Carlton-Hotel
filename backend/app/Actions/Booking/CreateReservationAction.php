<?php

namespace App\Actions\Booking;

use App\Actions\Loyalty\ApplyLoyaltyToReservationAction;
use App\Actions\Loyalty\PriceLoyaltyRedemptionAction;
use App\Contracts\ChannelAdapterInterface;
use App\Enums\ReservationStatus;
use App\Exceptions\IdempotencyConflictException;
use App\Exceptions\NoAvailabilityException;
use App\Exceptions\OccupancyExceededException;
use App\Models\Guest;
use App\Models\LoyaltyReservationApplication;
use App\Models\PromoCode;
use App\Models\Reservation;
use App\Models\RoomType;
use BackedEnum;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use LogicException;

class CreateReservationAction
{
    /** Phase 10: +loyaltyApplication.voucher so the response carries what was applied. */
    private const RELATIONS = ['rooms.roomType', 'rooms.room', 'guest', 'promoCode', 'loyaltyApplication.voucher'];

    public function __construct(
        private readonly CheckAvailabilityAction $checkAvailability,
        private readonly QuoteReservationAction  $quote,
        private readonly PriceLoyaltyRedemptionAction $priceRedemption,
        private readonly ApplyLoyaltyToReservationAction $applyLoyalty,
    ) {}

    /**
     * Identity-agnostic: caller resolves the Guest; this action handles lock + inventory.
     *
     * Phase 10: booking lock order room_type → guest → batches/voucher (M-6). The
     * guest row is locked only when `loyalty_points` or `voucher_code` is present
     * (only the authenticated guest path sets them, Q17); every other booking is
     * unchanged. `idempotency_key` then makes the request replay-safe (Q6).
     */
    public function handle(Guest $guest, array $data, ChannelAdapterInterface $channel): array
    {
        return DB::transaction(function () use ($guest, $data, $channel) {
            // Pessimistic lock on the room_type row — channel-blind serialization point.
            $roomType = RoomType::where('id', $data['room_type_id'])
                ->lockForUpdate()
                ->firstOrFail();

            $usesLoyalty = ($data['loyalty_points'] ?? null) !== null || ($data['voucher_code'] ?? null) !== null;
            $lockedGuest = null;

            if ($usesLoyalty) {
                $lockedGuest = Guest::whereKey($guest->id)->lockForUpdate()->firstOrFail();

                // A retry of an already-booked request is answered before any availability check.
                if ($replayed = $this->loyaltyReplay($lockedGuest, $data)) {
                    return ['data' => $replayed, 'code' => 200];
                }
            }

            // The one place the party-size defaults live. Checked against the locked
            // room_type row and before availability: an over-full party is a 422,
            // not a 409.
            $adults   = (int) ($data['adults'] ?? 1);
            $children = (int) ($data['children'] ?? 0);

            if ($adults + $children > (int) $roomType->max_occupancy) {
                throw new OccupancyExceededException(
                    __('custom.errors.occupancy_exceeded', ['max' => (int) $roomType->max_occupancy]),
                    ['max_occupancy' => (int) $roomType->max_occupancy, 'requested' => $adults + $children],
                );
            }

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

            // The same calculator the preview uses (Pitfall 6): the total is net of the discount.
            $redemption = $usesLoyalty
                ? $this->priceRedemption->handle($lockedGuest, $pricing, $data)['data']
                : null;

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
                'total_usd'       => $redemption['net_total_usd'] ?? $pricing['total_usd'],
                'promo_code_id'   => $pricing['promo_code_id'],
                'adults'          => $adults,
                'children'        => $children,
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

            if ($redemption !== null) {
                $this->applyLoyalty->handle(
                    $reservation,
                    $lockedGuest,
                    $redemption,
                    $data['idempotency_key'] ?? throw new LogicException('Loyalty booking requires an idempotency key.'),
                );
            }

            $reservation->load(self::RELATIONS);

            return ['data' => $reservation, 'code' => 201];
        });
    }

    /**
     * The reservation a previous request with the same Idempotency-Key created,
     * when this request is identical to it; null when the key is new.
     *
     * Compares the stored facts rather than a request hash (FA-10.11-1).
     *
     * @throws IdempotencyConflictException the key was used for a different request
     */
    private function loyaltyReplay(Guest $lockedGuest, array $data): ?Reservation
    {
        $key = $data['idempotency_key'] ?? null;

        $application = $key === null ? null : LoyaltyReservationApplication::query()
            ->where('guest_id', $lockedGuest->id)
            ->where('idempotency_key', $key)
            ->with(['reservation.rooms', 'voucher'])
            ->first();

        if ($application === null) {
            return null;
        }

        if (! $this->matchesStoredRequest($application, $data)) {
            throw new IdempotencyConflictException(__('custom.errors.idempotency_conflict'), ['idempotency_key' => $key]);
        }

        return $application->reservation->load(self::RELATIONS);
    }

    private function matchesStoredRequest(LoyaltyReservationApplication $application, array $data): bool
    {
        $reservation = $application->reservation;
        $promoCode = $data['promo_code'] ?? null;
        $payment = $data['payment_method'];

        return (int) $reservation->rooms->sortBy('id')->first()?->room_type_id === (int) $data['room_type_id']
            && $reservation->check_in->toDateString() === Carbon::parse($data['check_in'])->toDateString()
            && $reservation->check_out->toDateString() === Carbon::parse($data['check_out'])->toDateString()
            && $reservation->payment_method->value === ($payment instanceof BackedEnum ? $payment->value : $payment)
            && (int) $reservation->promo_code_id === (int) ($promoCode ? PromoCode::where('code', $promoCode)->value('id') : 0)
            && (int) $reservation->adults === (int) ($data['adults'] ?? 1)
            && (int) $reservation->children === (int) ($data['children'] ?? 0)
            && $application->points_redeemed === (int) ($data['loyalty_points'] ?? 0)
            && $application->voucher?->code === $this->normaliseVoucherCode($data['voucher_code'] ?? null);
    }

    /** Same normalisation as PriceLoyaltyRedemptionAction: upper-case, no whitespace. */
    private function normaliseVoucherCode(?string $code): ?string
    {
        $normalised = strtoupper((string) preg_replace('/\s+/', '', (string) $code));

        return $normalised === '' ? null : $normalised;
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
