<?php
namespace App\Http\Controllers\Staff;

use App\Base\BaseController;
use App\Http\Resources\RolePresetResource;
use App\Models\User;
use App\Services\StaffService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RoleController extends BaseController
{
    public function __construct(private readonly StaffService $staffService) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', User::class);
        $result = $this->staffService->rolePresets();
        return $this->success(
            RolePresetResource::collection($result['data']),
            'custom.messages.success',
            200,
            $request
        );
    }
}
