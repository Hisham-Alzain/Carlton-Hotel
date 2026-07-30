<?php

namespace App\Http\Controllers\Api;

use App\Base\BaseController;
use App\Http\Resources\Cms\FaqResource;
use App\Services\Cms\FaqService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FaqController extends BaseController
{
    public function __construct(private readonly FaqService $service) {}

    /**
     * The website renders the FAQ as one accordion, so there is no public
     * `show` — no page deep-links to a single question.
     */
    public function index(Request $request): JsonResponse
    {
        return $this->paginatedSuccess($this->service->indexPublic($this->perPageParam($request))['data'], FaqResource::class, $request);
    }
}
