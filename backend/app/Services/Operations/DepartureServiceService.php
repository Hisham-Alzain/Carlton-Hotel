<?php

namespace App\Services\Operations;

use App\Actions\Operations\UpdateRequestStatusAction;
use App\Actions\Service\UpdateServiceBookingStatusAction;
use App\Enums\BookableType;
use App\Enums\CheckOutMode;
use App\Enums\ServiceBookingStatus;
use App\Enums\ServiceRequestStatus;
use App\Exceptions\DepartureServiceReadonlyException;
use App\Exceptions\NotFoundException;
use App\Models\Reservation;
use App\Models\ServiceBooking;
use App\Models\ServiceRequest;
use App\Models\User;
use App\Support\DepartureServiceProjection;
use App\Support\HotelClock;
use Illuminate\Validation\ValidationException;

/**
 * Departure services (Phase 6, SVC-02/03; D-18..D-22). Reads are a
 * projection over bookings, requests and stays — read-only, no transaction.
 * A status change lands on the row's own source through that source's single
 * writer; express-checkout rows are never written.
 */
class DepartureServiceService
{
    private const SOURCE_TYPES = ['service_booking', 'service_request', 'reservation'];

    public function __construct(
        private readonly DepartureServiceProjection $projection,
        private readonly UpdateServiceBookingStatusAction $updateBookingStatus,
        private readonly UpdateRequestStatusAction $updateRequestStatus,
    ) {}

    /**
     * @param  list<string>  $kinds
     * @param  list<string>  $statuses
     */
    public function index(?string $date, array $kinds, array $statuses): array
    {
        return [
            'data' => $this->projection->build($date ?? HotelClock::today()->toDateString(), $kinds, $statuses),
            'code' => 200,
        ];
    }

    /**
     * Resolves the departure row by its bare source uuid (the `source_type`
     * hint, else booking → request → reservation), checks the status against
     * the source's own family and delegates. Returns the refreshed row.
     */
    public function updateStatus(string $uuid, string $status, ?string $sourceType, ?string $reason, User $actor): array
    {
        $source = $this->resolve($uuid, $sourceType);

        if ($source instanceof Reservation) {
            throw new DepartureServiceReadonlyException(__('custom.errors.departure_service_readonly'));
        }

        if ($source instanceof ServiceBooking) {
            $to = ServiceBookingStatus::tryFrom($status) ?? throw $this->wrongFamily();
            $this->updateBookingStatus->handle($source, $to, $actor, $reason);
        } else {
            ServiceRequestStatus::tryFrom($status) ?? throw $this->wrongFamily();
            $this->updateRequestStatus->handle($source, $status, $actor, $reason);
        }

        return ['data' => $this->projection->row($source->fresh()), 'code' => 200];
    }

    /** Only sources that can be departure rows resolve; anything else is 404 (FA-6.08-1). */
    private function resolve(string $uuid, ?string $sourceType): ServiceBooking|ServiceRequest|Reservation
    {
        foreach ($sourceType !== null ? [$sourceType] : self::SOURCE_TYPES as $type) {
            $found = match ($type) {
                'service_booking' => ServiceBooking::where('uuid', $uuid)
                    ->where('bookable_type', BookableType::TRANSFER->value)->first(),
                'service_request' => ServiceRequest::where('uuid', $uuid)
                    ->whereIn('type', DepartureServiceProjection::REQUEST_KINDS)->first(),
                'reservation'     => Reservation::where('uuid', $uuid)
                    ->where('check_out_mode', CheckOutMode::GUEST_EXPRESS->value)->first(),
                default           => null,
            };

            if ($found !== null) {
                return $found;
            }
        }

        throw new NotFoundException(__('custom.errors.not_found'));
    }

    private function wrongFamily(): ValidationException
    {
        return ValidationException::withMessages(['status' => __('custom.validation.in', ['attribute' => 'status'])]);
    }
}
