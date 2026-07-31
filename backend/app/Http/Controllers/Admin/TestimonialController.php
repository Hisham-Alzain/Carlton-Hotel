<?php

namespace App\Http\Controllers\Admin;

use App\Base\BaseController;
use App\Http\Requests\Cms\CreateTestimonialRequest;
use App\Http\Requests\Cms\UpdateTestimonialRequest;
use App\Http\Resources\Cms\TestimonialResource;
use App\Models\Testimonial;
use App\Services\Cms\TestimonialService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TestimonialController extends BaseController
{
    public function __construct(private readonly TestimonialService $service) {}

    public function index(Request $request): JsonResponse
    {
        return $this->paginatedSuccess($this->service->index($this->indexParams($request), perPage: $this->perPageParam($request))['data'], TestimonialResource::class, $request);
    }

    public function show(Testimonial $testimonial, Request $request): JsonResponse
    {
        $result = $this->service->show($testimonial);
        $result['data'] = new TestimonialResource($result['data']);
        return $this->respondFromService($result, request: $request);
    }

    public function store(CreateTestimonialRequest $request): JsonResponse
    {
        $result = $this->service->store($request->validated());
        $result['data'] = new TestimonialResource($result['data']);
        return $this->respondFromService($result, request: $request);
    }

    public function update(UpdateTestimonialRequest $request, Testimonial $testimonial): JsonResponse
    {
        $result = $this->service->update($testimonial, $request->validated());
        $result['data'] = new TestimonialResource($result['data']);
        return $this->respondFromService($result, request: $request);
    }

    public function destroy(Testimonial $testimonial, Request $request): JsonResponse
    {
        $this->service->destroy($testimonial);
        return $this->success(null, 'custom.messages.deleted', 204, $request);
    }

    public function trashed(Request $request): JsonResponse
    {
        return $this->paginatedSuccess($this->service->trashed($this->indexParams($request), perPage: $this->perPageParam($request))['data'], TestimonialResource::class, $request);
    }

    public function restore(Testimonial $testimonial, Request $request): JsonResponse
    {
        $result = $this->service->restore($testimonial);
        $result['data'] = new TestimonialResource($result['data']);
        return $this->respondFromService($result, 'custom.messages.restored', $request);
    }

    public function forceDestroy(Testimonial $testimonial, Request $request): JsonResponse
    {
        $this->service->forceDestroy($testimonial);
        return $this->success(null, 'custom.messages.deleted', 204, $request);
    }
}
