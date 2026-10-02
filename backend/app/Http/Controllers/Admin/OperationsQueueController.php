<?php

namespace App\Http\Controllers\Admin;

use App\Base\BaseController;
use App\Http\Requests\Operations\AssignRequestRequest;
use App\Http\Requests\Operations\UpdateRequestStatusRequest;
use App\Http\Resources\Operations\OperationsQueueItemResource;
use App\Services\Operations\OperationsQueueService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OperationsQueueController extends BaseController
{
    public function __construct(private readonly OperationsQueueService $service) {}

    public function index(Request $request): JsonResponse
    {
        $result = $this->service->index($request->user('users'), (int) $request->query('page', 1));
        return $this->paginatedSuccess($result['data'], OperationsQueueItemResource::class, $request);
    }

    public function summary(Request $request): JsonResponse
    {
        return $this->respondFromService($this->service->summary($request->user('users')), request: $request);
    }

    public function assign(string $type, string $uuid, AssignRequestRequest $request): JsonResponse
    {
        $result = $this->service->assign($type, $uuid, $request->validated('user_uuid'), $request->user('users'));
        $result['data'] = new OperationsQueueItemResource($result['data']);
        return $this->respondFromService($result, request: $request);
    }

    public function claim(string $type, string $uuid, Request $request): JsonResponse
    {
        $result = $this->service->claim($type, $uuid, $request->user('users'));

        return $this->success(
            new OperationsQueueItemResource($result['data']),
            $result['claimed'] ? 'custom.messages.queue_item_claimed' : 'custom.messages.queue_item_already_yours',
            200,
            $request,
        );
    }

    public function updateStatus(string $type, string $uuid, UpdateRequestStatusRequest $request): JsonResponse
    {
        $result = $this->service->updateStatus(
            $type,
            $uuid,
            $request->validated('status'),
            $request->user('users'),
            $request->validated('reason'),
        );
        $result['data'] = new OperationsQueueItemResource($result['data']);
        return $this->respondFromService($result, request: $request);
    }
}
