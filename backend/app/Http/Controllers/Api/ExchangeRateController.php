<?php

namespace App\Http\Controllers\Api;

use App\Base\BaseController;
use App\Http\Resources\Currency\ExchangeRateBoardResource;
use App\Services\Currency\ExchangeRateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * GET /api/public/exchange-rates (Phase 9.1, D-15, D-16, D-18).
 *
 * Display-only: the apps convert a USD amount as `usd × rate` rounded to
 * `display_decimals`; nothing is ever charged, stored or settled in SYP/TRY.
 * Reads no query string. Who set a rate and its note stay staff-only. No
 * server cache; the response may be reused for 5 minutes by app or CDN.
 */
class ExchangeRateController extends BaseController
{
    public function __construct(private readonly ExchangeRateService $service) {}

    public function index(Request $request): JsonResponse
    {
        $result = $this->service->board(false);

        return $this->success((new ExchangeRateBoardResource($result['data']))->resolve($request), 'custom.messages.success', 200, $request)
            ->header('Cache-Control', 'public, max-age=300');
    }
}
