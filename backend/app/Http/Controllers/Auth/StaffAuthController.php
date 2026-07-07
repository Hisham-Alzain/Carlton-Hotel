<?php
namespace App\Http\Controllers\Auth;

use App\Base\BaseController;
use App\Http\Requests\Auth\StaffLoginRequest;
use App\Http\Resources\UserResource;
use App\Services\Auth\AuthStaffService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StaffAuthController extends BaseController
{
    public function __construct(private readonly AuthStaffService $service) {}

    public function login(StaffLoginRequest $request): JsonResponse
    {
        $result = $this->service->login($request->validated('email'), $request->validated('password'));
        return $this->success([
            'user'        => new UserResource($result['data']['user']),
            'token'       => $result['data']['token'],
            'permissions' => $result['data']['permissions'],
        ], 'custom.auth.logged_in', 200, $request);
    }

    public function logout(Request $request): JsonResponse
    {
        $this->service->logout($request->user('users'));
        return $this->success(null, 'custom.auth.logged_out', 200, $request);
    }

    public function me(Request $request): JsonResponse
    {
        $result = $this->service->me($request->user('users'));
        return $this->success(new UserResource($result['data']), 'custom.messages.success', 200, $request);
    }
}
