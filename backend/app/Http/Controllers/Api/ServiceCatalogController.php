<?php

namespace App\Http\Controllers\Api;

use App\Base\BaseController;
use App\Http\Resources\Service\ServiceCategoryResource;
use App\Services\Service\ServiceCatalogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ServiceCatalogController extends BaseController
{
    public function __construct(private readonly ServiceCatalogService $service) {}

    public function index(Request $request): JsonResponse
    {
        $result = $this->service->index();
        $result['data'] = ServiceCategoryResource::collection($result['data']);

        return $this->respondFromService($result, request: $request);
    }
}
