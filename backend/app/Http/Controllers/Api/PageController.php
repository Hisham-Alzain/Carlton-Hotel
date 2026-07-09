<?php

namespace App\Http\Controllers\Api;

use App\Base\BaseController;
use App\Http\Resources\Cms\PageResource;
use App\Services\Cms\PageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PageController extends BaseController
{
    public function __construct(private readonly PageService $service) {}

    public function show(string $slug, Request $request): JsonResponse
    {
        $result = $this->service->findBySlug($slug);
        $result['data'] = new PageResource($result['data']);
        return $this->respondFromService($result, request: $request);
    }
}
