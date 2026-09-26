<?php

namespace App\Http\Requests\Booking;

use App\Base\BaseRequest;
use App\Models\Reservation;

class SubmitOnlineCheckInRequest extends BaseRequest
{
    /**
     * Owner-only (D-10). Someone else's reservation is 403 `forbidden` here,
     * deliberately not the receipt routes' historical 404. Route-model binding
     * runs first, so an unknown uuid is still 404.
     */
    public function authorize(): bool
    {
        $reservation = $this->route('reservation');
        $guest       = $this->user('guests');

        return $reservation instanceof Reservation
            && $guest !== null
            && $reservation->guest_id === $guest->id;
    }

    public function rules(): array
    {
        return [
            // Hotel-local wall time, exactly HH:MM (`7:30` and `18:30:00` fail).
            'arrival_time' => ['required', 'date_format:H:i'],
        ];
    }
}
