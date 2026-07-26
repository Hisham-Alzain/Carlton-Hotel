<?php

namespace App\Http\Controllers\Api;

use App\Base\BaseController;
use App\Http\Resources\Cms\AmenityResource;
use App\Services\Cms\AmenityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AmenityController extends BaseController
{
    public function __construct(private readonly AmenityService $service) {}

    public function index(Request $request): JsonResponse
    {
        return $this->paginatedSuccess($this->service->indexPublic()['data'], AmenityResource::class, $request);
    }
}
