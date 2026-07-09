<?php

namespace App\Services\Booking;

use App\Actions\Booking\AssignRoomAction;
use App\Actions\Booking\CancelReservationAction;
use App\Actions\Booking\ConfirmReservationAction;
use App\Actions\Booking\CreateReservationAction;
use App\Actions\Auth\RequestOtpAction;
use App\Adapters\DirectAdapter;
use App\Exceptions\HoldExpiredException;
use App\Exceptions\NotFoundException;
use App\Models\Guest;
use Illuminate\Support\Facades\DB;
use App\Models\OtpCode;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\RoomType;

class ReservationService
{
    protected array $with = ['rooms.roomType', 'rooms.room', 'guest', 'promoCode'];

    public function __construct(
        private readonly CreateReservationAction  $create,
        private readonly ConfirmReservationAction $confirm,
        private readonly CancelReservationAction  $cancel,
        private readonly AssignRoomAction         $assignRoom,
        private readonly RequestOtpAction         $requestOtp,
    ) {}

    // Authenticated path (app-only): one step
    public function store(Guest $guest, array $data): array
    {
        $roomTypeId = RoomType::where('uuid', $data['room_type_uuid'])->value('id');
        return $this->create->handle($guest, array_merge($data, ['room_type_id' => $roomTypeId]), new DirectAdapter());
    }

    // Public path step 1: create unverified guest + pending_verification reservation + send OTP
    public function storeAsGuest(array $data): array
    {
        $isPhone   = isset($data['phone']);
        $identifier = $isPhone ? $data['phone'] : $data['email'];

        // Find or create unverified guest
        $guest = $isPhone
            ? Guest::byPhone($identifier)->first()
            : Guest::byEmail($identifier)->first();

        if (! $guest) {
            $guest = Guest::create(array_filter([
                'phone'        => $data['phone'] ?? null,
                'phone_country'=> $data['phone_country'] ?? null,
                'email'        => $data['email'] ?? null,
                'first_name'   => $data['first_name'],
                'last_name'    => $data['last_name'],
            ]));
        }

        $roomTypeId = RoomType::where('uuid', $data['room_type_uuid'])->value('id');
        $holdExpiry = now()->addMinutes(5); // OTP TTL = hold TTL

        $result = $this->create->handle($guest, [
            'room_type_id'  => $roomTypeId,
            'check_in'      => $data['check_in'],
            'check_out'     => $data['check_out'],
            'payment_method'=> $data['payment_method'] ?? Reservation::PAYMENT_ON_ARRIVAL,
            'status'        => Reservation::STATUS_PENDING_VERIFICATION,
            'hold_expires_at' => $holdExpiry,
            'last_name'     => $data['last_name'],
        ], new DirectAdapter());

        $reservation = $result['data'];

        // Send OTP — reuses P1 rules (1/min, 5/hr) with booking_verification purpose
        $channel = $isPhone ? OtpCode::CHANNEL_SMS : OtpCode::CHANNEL_EMAIL;
        $this->requestOtp->handle($identifier, $channel, OtpCode::PURPOSE_BOOKING_VERIFICATION);

        $masked = strlen($identifier) > 4
            ? substr($identifier, 0, 4) . str_repeat('*', strlen($identifier) - 4)
            : '****';

        return [
            'data' => [
                'reservation_uuid'  => $reservation->uuid,
                'identifier_masked' => $masked,
                'channel'           => $channel,
            ],
            'code' => 200,
        ];
    }

    // Public path step 2: verify OTP → activate reservation → issue token
    public function verifyGuestBooking(string $reservationUuid, Guest $guest, Reservation $reservation): array
    {
        if ($reservation->isHoldExpired()) {
            throw new HoldExpiredException(__('custom.errors.hold_expired'));
        }

        $token = DB::transaction(function () use ($reservation, $guest) {
            $reservation->update(['status' => Reservation::STATUS_PENDING, 'hold_expires_at' => null]);
            return $guest->createToken('guest')->plainTextToken;
        });

        $reservation->refresh()->load($this->with);

        return [
            'data' => ['reservation' => $reservation, 'guest' => $guest, 'token' => $token],
            'code' => 200,
        ];
    }

    public function show(Reservation $reservation): array
    {
        $reservation->loadMissing($this->with);
        return ['data' => $reservation, 'code' => 200];
    }

    public function index(Guest $guest): array
    {
        $data = Reservation::where('guest_id', $guest->id)
            ->with($this->with)
            ->orderByDesc('created_at')
            ->paginate(15);
        return ['data' => $data, 'code' => 200];
    }

    public function adminIndex(): array
    {
        $data = Reservation::with($this->with)->orderByDesc('created_at')->paginate(15);
        return ['data' => $data, 'code' => 200];
    }

    public function confirm(Reservation $reservation): array
    {
        return $this->confirm->handle($reservation);
    }

    public function cancel(Reservation $reservation): array
    {
        return $this->cancel->handle($reservation);
    }

    public function assignRoom(Reservation $reservation, Room $room): array
    {
        return $this->assignRoom->handle($reservation, $room);
    }
}
