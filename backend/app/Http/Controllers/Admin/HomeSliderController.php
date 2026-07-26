<?php

namespace App\Http\Controllers\Admin;

use App\Base\BaseController;
use App\Http\Requests\Cms\CreateHomeSliderRequest;
use App\Http\Requests\Cms\UpdateHomeSliderRequest;
use App\Http\Resources\Cms\HomeSliderResource;
use App\Models\HomeSlider;
use App\Services\Cms\HomeSliderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HomeSliderController extends BaseController
{
    public function __construct(private readonly HomeSliderService $service) {}

    public function index(Request $request): JsonResponse
    {
        return $this->paginatedSuccess($this->service->index()['data'], HomeSliderResource::class, $request);
    }

    public function show(HomeSlider $homeSlider, Request $request): JsonResponse
    {
        $result = $this->service->show($homeSlider);
        $result['data'] = new HomeSliderResource($result['data']);
        return $this->respondFromService($result, request: $request);
    }

    public function store(CreateHomeSliderRequest $request): JsonResponse
    {
        $result = $this->service->store($request->validated());
        $result['data'] = new HomeSliderResource($result['data']);
        return $this->respondFromService($result, request: $request);
    }

    public function update(UpdateHomeSliderRequest $request, HomeSlider $homeSlider): JsonResponse
    {
        $result = $this->service->update($homeSlider, $request->validated());
        $result['data'] = new HomeSliderResource($result['data']);
        return $this->respondFromService($result, request: $request);
    }

    public function destroy(HomeSlider $homeSlider, Request $request): JsonResponse
    {
        $this->service->destroy($homeSlider);
        return $this->success(null, 'custom.messages.deleted', 204, $request);
    }
}
