<?php
namespace App\Services\Auth;

use App\Exceptions\ForbiddenException;
use App\Exceptions\UnauthorizedException;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\PersonalAccessToken;

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
                'permissions' => $user->effectivePermissionNames(),
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

    public function updateProfile(User $user, array $data): array
    {
        $user->update($data);
        $user->load('roles', 'permissions');
        return ['data' => $user, 'code' => 200];
    }

    public function changePassword(User $user, string $newPassword): array
    {
        $current = $user->currentAccessToken();

        DB::transaction(function () use ($user, $newPassword, $current) {
            // The `hashed` cast hashes the plain value on write.
            $user->update(['password' => $newPassword]);

            // Keep the session that made the change, sign out every other one.
            // A cookie session (TransientToken) or no token has no row to keep.
            if ($current instanceof PersonalAccessToken) {
                $user->tokens()->whereKeyNot($current->getKey())->delete();
            } else {
                $user->tokens()->delete();
            }
        });

        return ['data' => null, 'code' => 200];
    }
}
