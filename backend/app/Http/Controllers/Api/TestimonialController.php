<?php

namespace App\Http\Controllers\Api;

use App\Base\BaseController;
use App\Http\Resources\Cms\TestimonialResource;
use App\Services\Cms\TestimonialService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TestimonialController extends BaseController
{
    public function __construct(private readonly TestimonialService $service) {}

    /**
     * The website renders testimonials as one section, so there is no public
     * `show` — no route needs a single quote by uuid. Add one only when a page
     * actually deep-links to it.
     */
    public function index(Request $request): JsonResponse
    {
        return $this->paginatedSuccess($this->service->indexPublic($this->perPageParam($request))['data'], TestimonialResource::class, $request);
    }
}
