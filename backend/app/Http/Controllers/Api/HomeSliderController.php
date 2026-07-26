<?php

namespace App\Http\Controllers\Api;

use App\Base\BaseController;
use App\Http\Resources\Cms\HomeSliderResource;
use App\Services\Cms\HomeSliderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HomeSliderController extends BaseController
{
    public function __construct(private readonly HomeSliderService $service) {}

    public function index(Request $request): JsonResponse
    {
        return $this->paginatedSuccess($this->service->indexPublic()['data'], HomeSliderResource::class, $request);
    }
}
