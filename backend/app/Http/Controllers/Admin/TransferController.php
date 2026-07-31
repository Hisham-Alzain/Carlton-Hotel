<?php

namespace App\Http\Controllers\Admin;

use App\Base\BaseCRUDController;
use App\Base\BaseService;
use App\Http\Requests\Service\StoreTransferRequest;
use App\Http\Resources\Service\TransferResource;
use App\Models\Transfer;
use App\Services\Service\TransferService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TransferController extends BaseCRUDController
{
    protected ?string $resource = TransferResource::class;

    public function __construct(private readonly TransferService $service) {}

    protected function service(): BaseService
    {
        return $this->service;
    }

    public function show(Transfer $transfer, Request $request): JsonResponse
    {
        return $this->showResponse($transfer, $request);
    }

    public function store(StoreTransferRequest $request): JsonResponse
    {
        return $this->storeResponse($request);
    }

    public function update(StoreTransferRequest $request, Transfer $transfer): JsonResponse
    {
        return $this->updateResponse($request, $transfer);
    }

    public function destroy(Transfer $transfer, Request $request): JsonResponse
    {
        return $this->destroyResponse($transfer, $request);
    }
}
