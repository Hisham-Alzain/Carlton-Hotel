<?php

namespace App\Http\Controllers\Api;

use App\Base\BaseController;
use App\Http\Requests\Folio\GuestFolioDisputeRequest;
use App\Http\Resources\Folio\FolioItemResource;
use App\Http\Resources\Folio\FolioResource;
use App\Models\FolioItem;
use App\Services\Folio\FolioService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FolioController extends BaseController
{
    public function __construct(private readonly FolioService $service) {}

    public function show(Request $request): JsonResponse
    {
        $result = $this->service->myFolio($request->user('guests'));
        $result['data'] = new FolioResource($result['data']);
        return $this->respondFromService($result, request: $request);
    }

    public function approve(Request $request): JsonResponse
    {
        $result = $this->service->approveMyFolio($request->user('guests'));
        $result['data'] = new FolioResource($result['data']);
        return $this->respondFromService($result, 'custom.messages.folio_approved', $request);
    }

    /** Phase 5 (D-10): dispute an item of your own folio; anything else is 404. */
    public function dispute(GuestFolioDisputeRequest $request, FolioItem $item): JsonResponse
    {
        $result = $this->service->guestDispute($request->user('guests'), $item, $request->validated('reason'));

        return $this->success(new FolioItemResource($result['data']), 'custom.messages.folio_dispute_raised', $result['code'], $request);
    }
}
