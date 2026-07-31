<?php

namespace App\Http\Controllers\Admin;

use App\Base\BaseCRUDController;
use App\Base\BaseService;
use App\Base\HandlesRecycleBin;
use App\Http\Requests\Cms\CreateFaqRequest;
use App\Http\Requests\Cms\UpdateFaqRequest;
use App\Http\Resources\Cms\FaqResource;
use App\Models\Faq;
use App\Services\Cms\FaqService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FaqController extends BaseCRUDController
{
    use HandlesRecycleBin;

    protected ?string $resource = FaqResource::class;

    public function __construct(private readonly FaqService $service) {}

    protected function service(): BaseService
    {
        return $this->service;
    }

    public function show(Faq $faq, Request $request): JsonResponse
    {
        return $this->showResponse($faq, $request);
    }

    public function store(CreateFaqRequest $request): JsonResponse
    {
        return $this->storeResponse($request);
    }

    public function update(UpdateFaqRequest $request, Faq $faq): JsonResponse
    {
        return $this->updateResponse($request, $faq);
    }

    public function destroy(Faq $faq, Request $request): JsonResponse
    {
        return $this->destroyResponse($faq, $request);
    }

    public function restore(Faq $faq, Request $request): JsonResponse
    {
        return $this->restoreResponse($faq, $request);
    }

    public function forceDestroy(Faq $faq, Request $request): JsonResponse
    {
        return $this->forceDestroyResponse($faq, $request);
    }
}
