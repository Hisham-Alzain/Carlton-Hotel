<?php

namespace App\Http\Resources\Dining;

use App\Base\BaseResource;
use App\Models\RestaurantTable;
use App\Support\HotelClock;
use Illuminate\Http\Request;

/**
 * One restaurant table reservation for staff (Phase 8, D-25). `scheduled_at`
 * is the UTC instant; `local_date` / `local_time` are the hotel-local slot.
 * Null-safe for a hard-deleted table (PR-4). No `allowed_statuses`: there is
 * no staff verb this phase. Reads loaded relations only.
 */
class TableReservationResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        $local = $this->scheduled_at?->copy()->setTimezone(HotelClock::timezone());
        $table = $this->bookable instanceof RestaurantTable ? $this->bookable : null;
        $venue = $table?->diningVenue;
        $guest = $this->guest;

        return [
            'uuid'            => $this->uuid,
            'status'          => $this->status,
            'scheduled_at'    => $this->scheduled_at?->toIso8601String(),
            'local_date'      => $local?->toDateString(),
            'local_time'      => $local?->format('H:i'),
            'guest_count'     => $this->guest_count,
            'special_request' => $this->notes,
            'venue'           => $venue ? [
                'uuid' => $venue->uuid,
                'name' => $venue->getTranslation('name', app()->getLocale()),
            ] : null,
            'table'           => $table ? [
                'uuid'         => $table->uuid,
                'table_number' => $table->table_number,
                'capacity'     => $table->capacity,
            ] : null,
            // PR-3: `name`, else first + last, else null.
            'guest'           => $guest ? [
                'uuid' => $guest->uuid,
                'name' => $guest->name ?: (trim("{$guest->first_name} {$guest->last_name}") ?: null),
            ] : null,
            'reservation'     => $this->reservation ? [
                'uuid'         => $this->reservation->uuid,
                'booking_code' => $this->reservation->booking_code,
            ] : null,
            'created_at'      => $this->created_at?->toIso8601String(),
        ];
    }
}
