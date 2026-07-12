<?php

namespace App\Http\Controllers\Api;

use App\Base\BaseController;
use App\Http\Resources\Folio\FolioResource;
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
}
