<?php

namespace App\Http\Controllers\Api;

use App\Base\BasePublicIndexController;
use App\Base\BaseService;
use App\Exceptions\NotFoundException;
use App\Http\Resources\Cms\RoomTypeResource;
use App\Models\RoomType;
use App\Services\Cms\RoomTypeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RoomTypeController extends BasePublicIndexController
{
    protected ?string $resource = RoomTypeResource::class;

    public function __construct(private readonly RoomTypeService $service) {}

    protected function service(): BaseService
    {
        return $this->service;
    }

    public function show(RoomType $roomType, Request $request): JsonResponse
    {
        if (! $roomType->is_active) {
            throw new NotFoundException();
        }

        return $this->showResponse($roomType, $request);
    }
}
