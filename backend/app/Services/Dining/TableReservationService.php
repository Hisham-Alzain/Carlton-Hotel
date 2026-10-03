<?php

namespace App\Services\Dining;

use App\Base\BaseService;
use App\Enums\BookableType;
use App\Filters\TableReservationFilter;
use App\Models\RestaurantTable;
use App\Models\ServiceBooking;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Restaurant table reservations for staff (Phase 8, DINING-01, D-23). Read-only:
 * these are `service_bookings` rows with `bookable_type = restaurant_table`.
 *
 * Eager loads are fixed so the list costs six queries (D-30): count, rows,
 * tables, their venues (trashed included, so a deleted venue still renders),
 * guests and reservations. A hard-deleted table leaves `bookable` null (PR-4).
 */
class TableReservationService extends BaseService
{
    protected string $model = ServiceBooking::class;

    protected ?string $filter = TableReservationFilter::class;

    protected int $perPage = 50;

    protected function query(): Builder
    {
        return ServiceBooking::query()
            ->where('bookable_type', BookableType::RESTAURANT_TABLE->value)
            ->with([
                'bookable' => fn (MorphTo $morphTo) => $morphTo->morphWith([
                    RestaurantTable::class => ['diningVenue' => fn ($q) => $q->withTrashed()],
                ]),
                'guest:id,uuid,name,first_name,last_name',
                'reservation:id,uuid,booking_code',
            ])
            ->orderBy('scheduled_at')
            ->orderBy('id');
    }
}
