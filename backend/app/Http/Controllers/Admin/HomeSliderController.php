<?php

namespace App\Http\Controllers\Admin;

use App\Base\BaseCRUDController;
use App\Base\BaseService;
use App\Base\HandlesRecycleBin;
use App\Http\Requests\Cms\CreateHomeSliderRequest;
use App\Http\Requests\Cms\UpdateHomeSliderRequest;
use App\Http\Resources\Cms\HomeSliderResource;
use App\Models\HomeSlider;
use App\Services\Cms\HomeSliderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HomeSliderController extends BaseCRUDController
{
    use HandlesRecycleBin;

    protected ?string $resource = HomeSliderResource::class;

    public function __construct(private readonly HomeSliderService $service) {}

    protected function service(): BaseService
    {
        return $this->service;
    }

    public function show(HomeSlider $homeSlider, Request $request): JsonResponse
    {
        return $this->showResponse($homeSlider, $request);
    }

    public function store(CreateHomeSliderRequest $request): JsonResponse
    {
        return $this->storeResponse($request);
    }

    public function update(UpdateHomeSliderRequest $request, HomeSlider $homeSlider): JsonResponse
    {
        return $this->updateResponse($request, $homeSlider);
    }

    public function destroy(HomeSlider $homeSlider, Request $request): JsonResponse
    {
        return $this->destroyResponse($homeSlider, $request);
    }

    public function restore(HomeSlider $homeSlider, Request $request): JsonResponse
    {
        return $this->restoreResponse($homeSlider, $request);
    }

    public function forceDestroy(HomeSlider $homeSlider, Request $request): JsonResponse
    {
        return $this->forceDestroyResponse($homeSlider, $request);
    }
}
