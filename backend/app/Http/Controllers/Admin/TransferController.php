<?php

namespace App\Http\Controllers\Admin;

use App\Base\BaseController;
use App\Http\Requests\Service\StoreTransferRequest;
use App\Http\Resources\Service\TransferResource;
use App\Models\Transfer;
use App\Services\Service\TransferService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TransferController extends BaseController
{
    public function __construct(private readonly TransferService $service) {}

    public function index(Request $request): JsonResponse
    {
        return $this->paginatedSuccess($this->service->index()['data'], TransferResource::class, $request);
    }

    public function show(Transfer $transfer, Request $request): JsonResponse
    {
        $result = $this->service->show($transfer);
        $result['data'] = new TransferResource($result['data']);
        return $this->respondFromService($result, request: $request);
    }

    public function store(StoreTransferRequest $request): JsonResponse
    {
        $result = $this->service->store($request->validated());
        $result['data'] = new TransferResource($result['data']);
        return $this->respondFromService($result, request: $request);
    }

    public function update(StoreTransferRequest $request, Transfer $transfer): JsonResponse
    {
        $result = $this->service->update($transfer, $request->validated());
        $result['data'] = new TransferResource($result['data']);
        return $this->respondFromService($result, request: $request);
    }

    public function destroy(Transfer $transfer, Request $request): JsonResponse
    {
        $this->service->destroy($transfer);
        return $this->success(null, 'custom.messages.deleted', 204, $request);
    }
}
