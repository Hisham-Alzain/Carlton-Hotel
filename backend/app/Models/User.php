<?php

namespace App\Models;

use App\Traits\HasUuid;
use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\PermissionRegistrar;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, HasRoles, HasUuid, LogsActivity, Notifiable;

    protected $fillable = ['name', 'email', 'password', 'type', 'is_active'];
    protected $hidden   = ['password', 'remember_token'];
    protected $guard_name = 'users';

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password'          => 'hashed',
            'is_active'         => 'boolean',
        ];
    }

    public function isSuperAdmin(): bool
    {
        return $this->type === 'super_admin';
    }

    /**
     * Every permission name this user can actually exercise.
     *
     * A super admin holds no permission rows: AppServiceProvider's
     * `Gate::before` hook short-circuits every check for them, so
     * `getAllPermissions()` returns an empty collection while the API
     * allows everything. Mirroring the bypass here keeps what a client
     * is told in sync with what the API will permit. Reads come from
     * Spatie's permission cache, so this adds no query per request.
     *
     * @return Collection<int, string>
     */
    public function effectivePermissionNames(): Collection
    {
        if ($this->isSuperAdmin()) {
            return app(PermissionRegistrar::class)
                ->getPermissions(['guard_name' => $this->guard_name])
                ->pluck('name')
                ->values();
        }

        return $this->getAllPermissions()->pluck('name')->values();
    }
}
