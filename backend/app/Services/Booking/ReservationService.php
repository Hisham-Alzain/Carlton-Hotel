<?php

namespace App\Services\Booking;

use App\Actions\Booking\AssignRoomAction;
use App\Actions\Booking\CancelReservationAction;
use App\Actions\Booking\CheckAvailabilityAction;
use App\Actions\Booking\CheckInReservationAction;
use App\Actions\Booking\CheckOutReservationAction;
use App\Actions\Booking\ConfirmReservationAction;
use App\Actions\Booking\CreateReservationAction;
use App\Actions\Booking\UpdateReservationNotesAction;
use App\Actions\Auth\RequestOtpAction;
use App\Adapters\DirectAdapter;
use App\Adapters\WalkInAdapter;
use App\Enums\CheckOutMode;
use App\Enums\OtpChannel;
use App\Enums\OtpPurpose;
use App\Enums\PaymentMethod;
use App\Enums\ReservationSource;
use App\Enums\ReservationStatus;
use App\Exceptions\HoldExpiredException;
use App\Exceptions\NotFoundException;
use App\Exceptions\ReservationStateException;
use App\Filters\ReservationFilter;
use App\Models\Guest;
use Illuminate\Support\Facades\DB;
use App\Models\OtpCode;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\User;

class ReservationService
{
    protected array $with = ['rooms.roomType', 'rooms.room', 'guest', 'promoCode'];

    public function __construct(
        private readonly CreateReservationAction  $create,
        private readonly ConfirmReservationAction $confirm,
        private readonly CancelReservationAction  $cancel,
        private readonly AssignRoomAction         $assignRoom,
        private readonly RequestOtpAction         $requestOtp,
        private readonly UpdateReservationNotesAction $updateNotes,
        private readonly CheckAvailabilityAction   $availability,
        private readonly CheckInReservationAction  $checkIn,
        private readonly CheckOutReservationAction $checkOut,
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
            'payment_method'=> $data['payment_method'] ?? PaymentMethod::ON_ARRIVAL,
            'status'        => ReservationStatus::PENDING_VERIFICATION,
            'hold_expires_at' => $holdExpiry,
            'last_name'     => $data['last_name'],
        ], new DirectAdapter());

        $reservation = $result['data'];

        // Send OTP — reuses P1 rules (1/min, 5/hr) with booking_verification purpose
        $channel = $isPhone ? OtpChannel::SMS : OtpChannel::EMAIL;
        $this->requestOtp->handle($identifier, $channel, OtpPurpose::BOOKING_VERIFICATION);

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
            $reservation->update(['status' => ReservationStatus::PENDING, 'hold_expires_at' => null]);
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

    /**
     * Staff list, newest first, 15 per page. `$params` is the query string
     * (`indexParams`), narrowed by `ReservationFilter`: `status` and
     * `folio_status` (D-10).
     */
    public function adminIndex(array $params = []): array
    {
        $query = Reservation::with($this->with)->orderByDesc('created_at');

        (new ReservationFilter($params))->apply($query);

        return ['data' => $query->paginate(15), 'code' => 200];
    }

    /**
     * Reception creating a booking for a guest at the desk or on the phone.
     *
     * Skips the OTP the public flow requires — staff hold `reservations.create`
     * and have the guest in front of them — so the reservation is live
     * immediately (confirmed by default) with no hold to expire. Room inventory,
     * pricing and promo handling are the same CreateReservationAction the guest
     * paths use; only the identity resolution and the channel differ.
     */
    public function adminStore(array $data): array
    {
        $guest      = $this->resolveGuestForStaffBooking($data);
        $roomTypeId = RoomType::where('uuid', $data['room_type_uuid'])->value('id');

        return $this->create->handle($guest, [
            'room_type_id'   => $roomTypeId,
            'check_in'       => $data['check_in'],
            'check_out'      => $data['check_out'],
            'payment_method' => $data['payment_method'],
            'promo_code'     => $data['promo_code'] ?? null,
            'status'         => isset($data['status'])
                ? ReservationStatus::from($data['status'])
                : ReservationStatus::CONFIRMED,
            'source'         => isset($data['source'])
                ? ReservationSource::from($data['source'])
                : null,
        ], new WalkInAdapter());
    }

    /**
     * An existing guest by uuid, otherwise the one already on file under the
     * given phone/email, otherwise a new unverified record.
     *
     * A guest created here has no verified contact — they never proved they own
     * the number. They claim the booking in the app through
     * POST /auth/guest/link-booking-code, which is what does the verifying.
     */
    private function resolveGuestForStaffBooking(array $data): Guest
    {
        if (! empty($data['guest_uuid'])) {
            return Guest::where('uuid', $data['guest_uuid'])
                ->firstOr(fn () => throw new NotFoundException());
        }

        $guest = null;

        if (! empty($data['phone'])) {
            $guest = Guest::byPhone($data['phone'])->first();
        }

        if (! $guest && ! empty($data['email'])) {
            $guest = Guest::byEmail($data['email'])->first();
        }

        return $guest ?? Guest::create(array_filter([
            'phone'         => $data['phone']         ?? null,
            'phone_country' => $data['phone_country'] ?? null,
            'email'         => $data['email']         ?? null,
            'first_name'    => $data['first_name'],
            'last_name'     => $data['last_name'],
            'name'          => trim($data['first_name'] . ' ' . $data['last_name']),
        ]));
    }

    public function confirm(Reservation $reservation): array
    {
        return $this->confirm->handle($reservation);
    }

    public function cancel(Reservation $reservation): array
    {
        return $this->cancel->handle($reservation);
    }

    public function assignRoom(Reservation $reservation, ?string $roomUuid = null): array
    {
        $room = $roomUuid ? Room::where('uuid', $roomUuid)->firstOrFail() : null;

        return $this->assignRoom->handle($reservation, $room);
    }

    public function updateNotes(Reservation $reservation, ?string $notes): array
    {
        return $this->updateNotes->handle($reservation, $notes);
    }

    /**
     * The desk's pick list (D-12): rooms of the reservation's first room type
     * that are active, not in maintenance and free for its dates (the shared
     * D-04 predicate), the assigned room first and flagged, the rest by number.
     *
     * Exactly three queries and no writes or locks: Q1 the first line's type
     * and assigned room in one join, Q2/Q3 inside `freeRoomsFor()`.
     */
    public function availableRooms(Reservation $reservation): array
    {
        $type = RoomType::withTrashed()
            ->select([
                'room_types.id',
                'room_types.uuid',
                'room_types.name',
                'reservation_rooms.room_id as assigned_room_id',
            ])
            ->join('reservation_rooms', 'reservation_rooms.room_type_id', '=', 'room_types.id')
            ->where('reservation_rooms.reservation_id', $reservation->id)
            ->orderBy('reservation_rooms.id')
            ->first();

        if (! $type) {
            throw new ReservationStateException(__('custom.errors.reservation_state'));
        }

        $assignedId = $type->assigned_room_id !== null ? (int) $type->assigned_room_id : null;

        $items = $this->availability->freeRoomsFor($reservation, (int) $type->id)
            ->sortBy(fn (Room $room) => (int) $room->id === $assignedId ? 0 : 1)
            ->values()
            ->map(fn (Room $room) => [
                'uuid'                => $room->uuid,
                'number'              => $room->number,
                'floor'               => $room->floor,
                'housekeeping_status' => $room->status->value,
                'assigned'            => (int) $room->id === $assignedId,
            ])
            ->all();

        return [
            'data' => [
                'room_type' => [
                    'uuid' => $type->uuid,
                    'name' => $type->getTranslations('name'),
                ],
                'check_in'  => $reservation->check_in->toDateString(),
                'check_out' => $reservation->check_out->toDateString(),
                'items'     => $items,
            ],
            'code' => 200,
        ];
    }

    public function checkIn(Reservation $reservation, ?string $roomUuid, User $actor, bool $earlyCheckIn, ?string $reason): array
    {
        $room = $roomUuid ? Room::where('uuid', $roomUuid)->firstOrFail() : null;

        return $this->checkIn->handle($reservation, $room, $actor, $earlyCheckIn, $reason);
    }

    public function checkOut(Reservation $reservation, CheckOutMode $mode, User $actor, ?string $reason): array
    {
        return $this->checkOut->handle($reservation, $mode, $actor, $reason);
    }
}
