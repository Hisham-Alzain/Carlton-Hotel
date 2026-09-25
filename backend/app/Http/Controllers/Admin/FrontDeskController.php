<?php

namespace App\Http\Controllers\Admin;

use App\Base\BaseController;
use App\Http\Requests\Operations\ShowFrontDeskGridRequest;
use App\Http\Requests\Operations\ShowRoomBoardRequest;
use App\Services\Operations\FrontDeskService;
use Illuminate\Http\JsonResponse;

class FrontDeskController extends BaseController
{
    public function __construct(private readonly FrontDeskService $service) {}

    public function board(ShowRoomBoardRequest $request): JsonResponse
    {
        return $this->respondFromService($this->service->board($request->validated()), request: $request);
    }

    public function availabilityGrid(ShowFrontDeskGridRequest $request): JsonResponse
    {
        return $this->respondFromService($this->service->availabilityGrid($request->validated()), request: $request);
    }

    public function ratesGrid(ShowFrontDeskGridRequest $request): JsonResponse
    {
        return $this->respondFromService($this->service->ratesGrid($request->validated()), request: $request);
    }
}
