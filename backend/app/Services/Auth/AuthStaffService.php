<?php
namespace App\Services\Auth;

use App\Exceptions\ForbiddenException;
use App\Exceptions\UnauthorizedException;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class AuthStaffService
{
    public function login(string $email, string $password): array
    {
        $user = User::where('email', $email)->first();

        if (! $user || ! Hash::check($password, $user->password)) {
            throw new UnauthorizedException(__('custom.errors.credentials_invalid'));
        }

        if (! $user->is_active) {
            throw new ForbiddenException(__('custom.errors.account_inactive'));
        }

        $user->load('roles', 'permissions');
        $token = $user->createToken('staff')->plainTextToken;

        return [
            'data' => [
                'user'        => $user,
                'token'       => $token,
                'permissions' => $user->getAllPermissions()->pluck('name'),
            ],
            'code' => 200,
        ];
    }

    public function logout(User $user): array
    {
        $token = $user->currentAccessToken();
        if ($token) {
            $token->delete();
        } else {
            // Fallback: delete all tokens for this user (safe — one active session)
            $user->tokens()->delete();
        }
        return ['data' => null, 'code' => 200];
    }

    public function me(User $user): array
    {
        $user->load('roles', 'permissions');
        return ['data' => $user, 'code' => 200];
    }
}
