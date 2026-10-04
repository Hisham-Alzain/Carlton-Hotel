<?php

namespace App\Http\Controllers\Admin;

use App\Base\BaseController;
use App\Http\Requests\Currency\StoreExchangeRateRequest;
use App\Http\Resources\Currency\ExchangeRateBoardResource;
use App\Http\Resources\Currency\ExchangeRateResource;
use App\Services\Currency\ExchangeRateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Staff maintenance of display exchange rates (Phase 9.1, D-15..D-19), gated
 * by `pricing.edit` in the routes. On BaseController: the board is a bounded
 * object, not a paginator, and the store is append-only.
 */
class ExchangeRateController extends BaseController
{
    public function __construct(private readonly ExchangeRateService $service) {}

    public function index(Request $request): JsonResponse
    {
        $result = $this->service->board(true);

        return $this->success((new ExchangeRateBoardResource($result['data']))->resolve($request), 'custom.messages.success', 200, $request);
    }

    public function history(Request $request): JsonResponse
    {
        $result = $this->service->history($this->indexParams($request), $this->perPageParam($request));

        return $this->paginatedSuccess($result['data'], ExchangeRateResource::class, $request);
    }

    public function store(StoreExchangeRateRequest $request): JsonResponse
    {
        $result = $this->service->record($request->validated(), $request->user());

        return $this->success(new ExchangeRateResource($result['data']), 'custom.messages.exchange_rate_recorded', $result['code'], $request);
    }
}
