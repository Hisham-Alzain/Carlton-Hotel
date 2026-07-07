<?php
namespace App\Http\Controllers\Staff;

use App\Base\BaseController;
use App\Http\Resources\PermissionResource;
use App\Models\User;
use App\Services\PermissionAssignmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PermissionController extends BaseController
{
    public function __construct(private readonly PermissionAssignmentService $service) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', User::class);
        $result   = $this->service->groupedPermissions();
        $resources = collect($result['data'])->map(fn ($g) => new PermissionResource($g));
        return $this->success($resources, 'custom.messages.success', 200, $request);
    }
}
