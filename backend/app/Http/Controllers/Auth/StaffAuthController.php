<?php
namespace App\Http\Controllers\Auth;

use App\Base\BaseController;
use App\Http\Requests\Auth\ChangePasswordRequest;
use App\Http\Requests\Auth\StaffLoginRequest;
use App\Http\Requests\Auth\UpdateStaffProfileRequest;
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

    // Settings screen read: the same UserResource and shape as me().
    public function profile(Request $request): JsonResponse
    {
        $result = $this->service->me($request->user('users'));
        return $this->success(new UserResource($result['data']), 'custom.messages.success', 200, $request);
    }

    // Self-service edit of name and email only; current_password never reaches the model.
    public function updateProfile(UpdateStaffProfileRequest $request): JsonResponse
    {
        $result = $this->service->updateProfile($request->user('users'), $request->safe()->only(['name', 'email']));
        return $this->success(new UserResource($result['data']), 'custom.messages.profile_updated', 200, $request);
    }

    // Changes the caller's password and signs out every other session of the account.
    public function changePassword(ChangePasswordRequest $request): JsonResponse
    {
        $this->service->changePassword($request->user('users'), $request->validated('password'));
        return $this->success(null, 'custom.messages.password_changed', 200, $request);
    }
}
