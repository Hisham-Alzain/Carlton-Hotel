<?php

namespace App\Actions\Service;

use App\Enums\BookableType;
use App\Enums\ServiceBookingStatus;
use App\Exceptions\NoAvailabilityException;
use App\Models\DiningVenue;
use App\Models\Guest;
use App\Models\Reservation;
use App\Models\RestaurantTable;
use App\Models\ServiceBooking;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Books a table at a restaurant for a party.
 *
 * The guest picks a venue, date, time and party size — never a specific table.
 * The smallest table that seats the party and is free for the seating window is
 * assigned, so large tables stay available for large parties.
 */
class ReserveTableAction
{
    /** A seating occupies its table for this long. */
    private const SEATING_MINUTES = 120;

    public function handle(Guest $guest, Reservation $reservation, DiningVenue $venue, array $data): array
    {
        $scheduledAt = Carbon::parse("{$data['date']} {$data['time']}");
        $guestCount  = (int) $data['guest_count'];

        $booking = DB::transaction(function () use ($guest, $reservation, $venue, $data, $scheduledAt, $guestCount) {
            $table = $this->findFreeTable($venue, $scheduledAt, $guestCount);

            if (! $table) {
                throw new NoAvailabilityException(__('custom.errors.no_table_available'));
            }

            return ServiceBooking::create([
                'guest_id'       => $guest->id,
                'reservation_id' => $reservation->id,
                'bookable_type'  => BookableType::RESTAURANT_TABLE->value,
                'bookable_id'    => $table->id,
                'scheduled_at'   => $scheduledAt,
                'guest_count'    => $guestCount,
                'status'         => ServiceBookingStatus::PENDING,
                'notes'          => $data['special_request'] ?? null,
            ]);
        });

        return ['data' => $booking->load('bookable'), 'code' => 201];
    }

    private function findFreeTable(DiningVenue $venue, Carbon $scheduledAt, int $guestCount): ?RestaurantTable
    {
        $windowStart = $scheduledAt->copy()->subMinutes(self::SEATING_MINUTES);
        $windowEnd   = $scheduledAt->copy()->addMinutes(self::SEATING_MINUTES);

        return RestaurantTable::query()
            ->where('dining_venue_id', $venue->id)
            ->where('is_active', true)
            ->where('capacity', '>=', $guestCount)
            ->whereNotExists(function ($query) use ($windowStart, $windowEnd) {
                $query->select(DB::raw(1))
                    ->from('service_bookings')
                    ->whereColumn('service_bookings.bookable_id', 'restaurant_tables.id')
                    ->where('service_bookings.bookable_type', BookableType::RESTAURANT_TABLE->value)
                    ->whereIn('service_bookings.status', ServiceBookingStatus::blockingSeating())
                    // Strict bounds: a seating that ends exactly as another starts
                    // does not collide.
                    ->where('service_bookings.scheduled_at', '>', $windowStart)
                    ->where('service_bookings.scheduled_at', '<', $windowEnd);
            })
            // Smallest table that fits, so six-tops stay free for six-tops.
            ->orderBy('capacity')
            ->lockForUpdate()
            ->first();
    }
}
