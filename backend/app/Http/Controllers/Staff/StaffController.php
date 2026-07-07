<?php
namespace App\Http\Controllers\Staff;

use App\Actions\Staff\AssignPermissionsAction;
use App\Actions\Staff\CreateStaffAction;
use App\Base\BaseController;
use App\Http\Requests\Staff\AssignPermissionsRequest;
use App\Http\Requests\Staff\CreateStaffRequest;
use App\Http\Requests\Staff\UpdateStaffRequest;
use App\Http\Resources\StaffResource;
use App\Models\User;
use App\Services\StaffService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StaffController extends BaseController
{
    public function __construct(
        private readonly StaffService            $staffService,
        private readonly CreateStaffAction       $createAction,
        private readonly AssignPermissionsAction $assignAction,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', User::class);
        $result = $this->staffService->index();
        // Map paginator items through StaffResource
        $paginator = $result['data'];
        $paginator->getCollection()->transform(fn ($u) => new StaffResource($u));
        return $this->respondFromService(['data' => $paginator, 'code' => 200], request: $request);
    }

    public function show(User $user, Request $request): JsonResponse
    {
        $this->authorize('view', $user);
        $result = $this->staffService->show($user);
        return $this->success(new StaffResource($result['data']), 'custom.messages.success', 200, $request);
    }

    public function store(CreateStaffRequest $request): JsonResponse
    {
        $this->authorize('create', User::class);
        $result = $this->createAction->handle($request->validated());
        return $this->success(new StaffResource($result['data']), 'custom.messages.staff_created', 201, $request);
    }

    public function update(UpdateStaffRequest $request, User $user): JsonResponse
    {
        $this->authorize('update', $user);
        $result = $this->staffService->update($user, $request->validated());
        return $this->success(new StaffResource($result['data']), 'custom.messages.staff_updated', 200, $request);
    }

    public function assignPermissions(AssignPermissionsRequest $request, User $user): JsonResponse
    {
        $this->authorize('assignPermissions', $user);
        $result = $this->assignAction->handle(
            $request->user('users'),
            $user,
            $request->validated('grant', []),
            $request->validated('revoke', []),
        );
        return $this->success(new StaffResource($result['data']), 'custom.messages.permissions_updated', 200, $request);
    }

    public function deactivate(User $user, Request $request): JsonResponse
    {
        $this->authorize('deactivate', $user);
        $result = $this->staffService->deactivate($user);
        return $this->success(new StaffResource($result['data']), 'custom.messages.staff_deactivated', 200, $request);
    }
}
