<?php

namespace App\Http\Controllers\Api;

use App\Base\BaseController;
use App\Exceptions\NotFoundException;
use App\Http\Requests\Service\MenuFilterRequest;
use App\Http\Resources\Service\MenuCategoryResource;
use App\Http\Resources\Service\MenuItemResource;
use App\Models\DiningVenue;
use App\Services\Service\MenuCategoryService;
use App\Services\Service\MenuItemService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MenuController extends BaseController
{
    public function __construct(
        private readonly MenuItemService     $items,
        private readonly MenuCategoryService $categories,
    ) {}

    /** Filter chips for a restaurant's menu. */
    public function categories(DiningVenue $diningVenue, Request $request): JsonResponse
    {
        $this->assertActive($diningVenue);

        $result = $this->categories->indexForVenue($diningVenue);
        $result['data'] = MenuCategoryResource::collection($result['data']);

        return $this->respondFromService($result, request: $request);
    }

    public function index(MenuFilterRequest $request, DiningVenue $diningVenue): JsonResponse
    {
        $this->assertActive($diningVenue);

        return $this->paginatedSuccess(
            $this->items->menuForVenue($diningVenue, $request->validated('type'))['data'],
            MenuItemResource::class,
            $request,
        );
    }

    private function assertActive(DiningVenue $venue): void
    {
        if (! $venue->is_active) {
            throw new NotFoundException();
        }
    }
}
