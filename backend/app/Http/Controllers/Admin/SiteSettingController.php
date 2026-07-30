<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Cms\UpsertSiteSettingsAction;
use App\Base\BaseController;
use App\Http\Requests\Cms\UpsertSiteSettingsRequest;
use App\Http\Resources\Cms\SiteSettingResource;
use App\Services\Cms\SiteSettingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SiteSettingController extends BaseController
{
    public function __construct(private readonly SiteSettingService $service) {}

    /**
     * Every setting, bucketed by group:
     *
     *     data: { contact: [ {uuid, group, key, value, type, is_active}, … ], … }
     *
     * Not paginated — see `SiteSettingService::grouped()` for why that is a
     * deliberate exception rather than a missed convention.
     */
    public function index(Request $request): JsonResponse
    {
        return $this->success($this->groupedPayload(), 'custom.messages.success', 200, $request);
    }

    /**
     * Bulk upsert. The whole payload lands or none of it does — the atomicity
     * lives in `UpsertSiteSettingsAction`, not here.
     *
     * Responds with the full grouped set rather than an echo of the written
     * rows, so the CMS form re-renders from the authoritative state it just
     * produced instead of merging a partial response into stale local state.
     */
    public function update(UpsertSiteSettingsRequest $request, UpsertSiteSettingsAction $action): JsonResponse
    {
        $action->handle($request->validated()['settings']);

        return $this->success($this->groupedPayload(), 'custom.messages.success', 200, $request);
    }

    /**
     * @return array<string, \Illuminate\Http\Resources\Json\AnonymousResourceCollection>
     */
    private function groupedPayload(): array
    {
        return array_map(
            static fn ($settings) => SiteSettingResource::collection($settings),
            $this->service->grouped(),
        );
    }
}
