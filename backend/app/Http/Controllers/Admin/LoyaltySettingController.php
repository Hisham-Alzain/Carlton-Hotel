<?php

namespace App\Http\Controllers\Admin;

use App\Base\BaseController;
use App\Http\Requests\Loyalty\UpdateLoyaltySettingsRequest;
use App\Http\Resources\Loyalty\LoyaltySettingsResource;
use App\Services\Loyalty\LoyaltySettingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LoyaltySettingController extends BaseController
{
    public function __construct(private readonly LoyaltySettingService $service) {}

    /** Phase 10 (LOY-02): loyalty.view|loyalty.manage; never writes, nulls and defaults when unconfigured. */
    public function show(Request $request): JsonResponse
    {
        $result = $this->service->show();

        return $this->success(new LoyaltySettingsResource($result['data']), 'custom.messages.success', $result['code'], $request);
    }

    /** Phase 10 (LOY-01): loyalty.manage; present keys only. */
    public function update(UpdateLoyaltySettingsRequest $request): JsonResponse
    {
        $result = $this->service->update($request->validated(), $request->user());

        return $this->success(new LoyaltySettingsResource($result['data']), 'custom.messages.loyalty_settings_updated', $result['code'], $request);
    }
}
