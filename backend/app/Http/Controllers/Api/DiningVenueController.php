<?php

namespace App\Http\Controllers\Api;

use App\Base\BasePublicIndexController;
use App\Base\BaseService;
use App\Exceptions\NotFoundException;
use App\Http\Resources\Cms\DiningVenueResource;
use App\Models\DiningVenue;
use App\Services\Cms\DiningVenueService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DiningVenueController extends BasePublicIndexController
{
    protected ?string $resource = DiningVenueResource::class;

    public function __construct(private readonly DiningVenueService $service) {}

    protected function service(): BaseService
    {
        return $this->service;
    }

    public function show(DiningVenue $diningVenue, Request $request): JsonResponse
    {
        if (! $diningVenue->is_active) {
            throw new NotFoundException();
        }

        return $this->showResponse($diningVenue, $request);
    }
}
