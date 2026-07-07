<?php
namespace App\Services;

use App\Base\BaseService;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

class StaffService extends BaseService
{
    protected string $model = User::class;
    protected array $with   = ['roles', 'permissions'];

    public function createFromPreset(array $data): array
    {
        $user = DB::transaction(function () use ($data) {
            $user = User::create([
                'name'      => $data['name'],
                'email'     => $data['email'],
                'password'  => $data['password'], // cast 'hashed' on model
                'type'      => 'staff',
                'is_active' => true,
            ]);
            $user->assignRole($data['role']);
            return $user;
        });

        $user->loadMissing($this->with);
        return ['data' => $user, 'code' => 201];
    }

    public function update(Model $model, array $data): array
    {
        // Only name + email allowed — strip anything else
        $allowed = array_intersect_key($data, array_flip(['name', 'email']));

        DB::transaction(fn () => $model->update($allowed));
        $model->refresh()->loadMissing($this->with);
        return ['data' => $model, 'code' => 200];
    }

    public function rolePresets(): array
    {
        // Bounded reference list (5 presets) — get() acceptable, not paginated
        $roles = Role::where('guard_name', 'users')->with('permissions')->get();
        return ['data' => $roles, 'code' => 200];
    }

    public function deactivate(User $user): array
    {
        DB::transaction(function () use ($user) {
            $user->update(['is_active' => false]);
            $user->tokens()->delete(); // revoke all sessions
        });
        $user->refresh()->loadMissing($this->with);
        return ['data' => $user, 'code' => 200];
    }
}
