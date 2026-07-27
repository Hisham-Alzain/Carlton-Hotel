<?php

namespace App\Http\Controllers\Api;

use App\Base\BaseController;
use App\Http\Resources\Service\PoolCabanaResource;
use App\Http\Resources\Service\RestaurantTableResource;
use App\Http\Resources\Service\SpaServiceResource;
use App\Http\Resources\Service\TransferResource;
use App\Models\DiningVenue;
use App\Models\PoolCabana;
use App\Models\RestaurantTable;
use App\Models\SpaService;
use App\Models\Transfer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Public reads over the things a guest can book with POST /service-bookings.
 *
 * Without these the catalog was write-only from the app's point of view: the
 * endpoint needs a `bookable_uuid` and there was no way to discover one.
 */
class BookableController extends BaseController
{
    public function spaServices(Request $request): JsonResponse
    {
        return $this->activeList(SpaService::query(), SpaServiceResource::class, $request);
    }

    public function poolCabanas(Request $request): JsonResponse
    {
        return $this->activeList(PoolCabana::query(), PoolCabanaResource::class, $request);
    }

    public function transfers(Request $request): JsonResponse
    {
        return $this->activeList(Transfer::query(), TransferResource::class, $request);
    }

    /**
     * Tables at one venue. Reserving through
     * POST /dining-venues/{uuid}/table-reservations is preferred — it picks the
     * table for the party size — but the raw list is exposed for clients that
     * want to show a floor plan.
     */
    public function restaurantTables(DiningVenue $diningVenue, Request $request): JsonResponse
    {
        return $this->activeList(
            RestaurantTable::query()->where('dining_venue_id', $diningVenue->id)->orderBy('table_number'),
            RestaurantTableResource::class,
            $request,
        );
    }

    private function activeList(Builder $query, string $resource, Request $request): JsonResponse
    {
        return $this->paginatedSuccess(
            $query->where('is_active', true)->paginate($request->integer('per_page', 15)),
            $resource,
            $request,
        );
    }
}
