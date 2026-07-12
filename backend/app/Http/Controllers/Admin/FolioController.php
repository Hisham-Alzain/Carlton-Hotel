<?php

namespace App\Http\Controllers\Admin;

use App\Base\BaseController;
use App\Http\Requests\Folio\SettleFolioRequest;
use App\Http\Resources\Folio\FolioResource;
use App\Models\Folio;
use App\Models\Reservation;
use App\Services\Folio\FolioService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FolioController extends BaseController
{
    public function __construct(private readonly FolioService $service) {}

    public function generate(Reservation $reservation, Request $request): JsonResponse
    {
        $result = $this->service->adminGenerate($reservation);
        $result['data'] = new FolioResource($result['data']);
        return $this->respondFromService($result, 'custom.messages.folio_generated', $request);
    }

    public function settle(SettleFolioRequest $request, Folio $folio): JsonResponse
    {
        $result = $this->service->adminSettle($folio, $request->validated(), $request->user());
        $result['data'] = new FolioResource($result['data']);
        return $this->respondFromService($result, 'custom.messages.folio_settled', $request);
    }
}
