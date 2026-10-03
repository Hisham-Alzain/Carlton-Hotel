<?php

namespace App\Http\Controllers\Api;

use App\Base\BasePublicIndexController;
use App\Base\BaseService;
use App\Exceptions\NotFoundException;
use App\Http\Resources\Cms\DiningVenueResource;
use App\Models\DiningVenue;
use App\Services\Cms\DiningVenueService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class DiningVenueController extends BasePublicIndexController
{
    protected ?string $resource = DiningVenueResource::class;

    public function __construct(private readonly DiningVenueService $service) {}

    protected function service(): BaseService
    {
        return $this->service;
    }

    public function show(DiningVenue $diningVenue, Request $request): JsonResponse
    {
        if (! $diningVenue->is_active) {
            throw new NotFoundException();
        }

        return $this->showResponse($diningVenue, $request);
    }

    /**
     * DINING-02 (Phase 8, D-11, D-26): the menu file's URL — 200 `{url, file_name,
     * mime_type, size, updated_at}`, 204 with an empty body (the one documented
     * envelope exception) when the venue has none, 404 for an inactive venue
     * (parity with `show`). A URL, not a stream: the app opens it externally.
     */
    public function menuDownload(DiningVenue $diningVenue, Request $request): JsonResponse|Response
    {
        if (! $diningVenue->is_active) {
            throw new NotFoundException();
        }

        $menu = $this->service->menuFile($diningVenue)['data'];

        if ($menu === null) {
            return response()->noContent();
        }

        return $this->success([
            'url'        => $menu->url,
            'file_name'  => $menu->file_name,
            'mime_type'  => $menu->mime_type,
            'size'       => $menu->size,
            'updated_at' => $menu->updated_at?->toIso8601String(),
        ], request: $request);
    }
}
