<?php

namespace App\Http\Controllers\Admin;

use App\Base\BaseCRUDController;
use App\Base\BaseService;
use App\Base\HandlesRecycleBin;
use App\Http\Requests\Cms\CreateTestimonialRequest;
use App\Http\Requests\Cms\UpdateTestimonialRequest;
use App\Http\Resources\Cms\TestimonialResource;
use App\Models\Testimonial;
use App\Services\Cms\TestimonialService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TestimonialController extends BaseCRUDController
{
    use HandlesRecycleBin;

    protected ?string $resource = TestimonialResource::class;

    public function __construct(private readonly TestimonialService $service) {}

    protected function service(): BaseService
    {
        return $this->service;
    }

    public function show(Testimonial $testimonial, Request $request): JsonResponse
    {
        return $this->showResponse($testimonial, $request);
    }

    public function store(CreateTestimonialRequest $request): JsonResponse
    {
        return $this->storeResponse($request);
    }

    public function update(UpdateTestimonialRequest $request, Testimonial $testimonial): JsonResponse
    {
        return $this->updateResponse($request, $testimonial);
    }

    public function destroy(Testimonial $testimonial, Request $request): JsonResponse
    {
        return $this->destroyResponse($testimonial, $request);
    }

    public function restore(Testimonial $testimonial, Request $request): JsonResponse
    {
        return $this->restoreResponse($testimonial, $request);
    }

    public function forceDestroy(Testimonial $testimonial, Request $request): JsonResponse
    {
        return $this->forceDestroyResponse($testimonial, $request);
    }
}
