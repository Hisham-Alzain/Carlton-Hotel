<?php

namespace App\Http\Controllers\Admin;

use App\Base\BaseController;
use App\Http\Requests\Housekeeping\AssignHousekeepingTaskRequest;
use App\Http\Requests\Housekeeping\CreateHousekeepingTaskRequest;
use App\Http\Requests\Housekeeping\UpdateHousekeepingTaskStatusRequest;
use App\Http\Resources\Housekeeping\HousekeepingTaskResource;
use App\Models\HousekeepingTask;
use App\Services\Housekeeping\HousekeepingTaskService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The housekeeping task board (Phase 6, D-05..D-08). On BaseController, not
 * the CRUD pair: store is find-or-create (201 / 200 with distinct messages),
 * and assign / status are verbs over the housekeeping single writers. No
 * update or destroy (D-08).
 */
class HousekeepingTaskController extends BaseController
{
    public function __construct(private readonly HousekeepingTaskService $service) {}

    public function index(Request $request): JsonResponse
    {
        return $this->paginatedSuccess(
            $this->service->index($this->indexParams($request), null, $this->perPageParam($request))['data'],
            HousekeepingTaskResource::class,
            $request,
        );
    }

    public function show(HousekeepingTask $task, Request $request): JsonResponse
    {
        $result = $this->service->show($task);
        $result['data'] = new HousekeepingTaskResource($result['data']);

        return $this->respondFromService($result, request: $request);
    }

    public function store(CreateHousekeepingTaskRequest $request): JsonResponse
    {
        $result = $this->service->store($request->validated(), $request->user('users'));

        // respondFromService() would swap a 201 for the generic "created" key;
        // D-08 wants distinct created / already-exists messages.
        return $this->success(
            new HousekeepingTaskResource($result['data']),
            $result['code'] === 201 ? 'custom.messages.housekeeping_task_created' : 'custom.messages.housekeeping_task_exists',
            $result['code'],
            $request,
        );
    }

    public function assign(HousekeepingTask $task, AssignHousekeepingTaskRequest $request): JsonResponse
    {
        $result = $this->service->assign($task, $request->validated('user_uuid'), $request->user('users'));
        $result['data'] = new HousekeepingTaskResource($result['data']);

        return $this->respondFromService($result, 'custom.messages.housekeeping_task_assigned', $request);
    }

    public function updateStatus(HousekeepingTask $task, UpdateHousekeepingTaskStatusRequest $request): JsonResponse
    {
        $result = $this->service->updateStatus(
            $task,
            $request->validated('status'),
            $request->validated('reason'),
            $request->user('users'),
        );
        $result['data'] = new HousekeepingTaskResource($result['data']);

        return $this->respondFromService($result, 'custom.messages.housekeeping_task_status_updated', $request);
    }
}
