<?php

namespace App\Http\Controllers\Admin;

use App\Base\BaseController;
use App\Http\Requests\Cms\CreateFaqRequest;
use App\Http\Requests\Cms\UpdateFaqRequest;
use App\Http\Resources\Cms\FaqResource;
use App\Models\Faq;
use App\Services\Cms\FaqService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FaqController extends BaseController
{
    public function __construct(private readonly FaqService $service) {}

    public function index(Request $request): JsonResponse
    {
        return $this->paginatedSuccess($this->service->index($this->indexParams($request), perPage: $this->perPageParam($request))['data'], FaqResource::class, $request);
    }

    public function show(Faq $faq, Request $request): JsonResponse
    {
        $result = $this->service->show($faq);
        $result['data'] = new FaqResource($result['data']);
        return $this->respondFromService($result, request: $request);
    }

    public function store(CreateFaqRequest $request): JsonResponse
    {
        $result = $this->service->store($request->validated());
        $result['data'] = new FaqResource($result['data']);
        return $this->respondFromService($result, request: $request);
    }

    public function update(UpdateFaqRequest $request, Faq $faq): JsonResponse
    {
        $result = $this->service->update($faq, $request->validated());
        $result['data'] = new FaqResource($result['data']);
        return $this->respondFromService($result, request: $request);
    }

    public function destroy(Faq $faq, Request $request): JsonResponse
    {
        $this->service->destroy($faq);
        return $this->success(null, 'custom.messages.deleted', 204, $request);
    }
}
