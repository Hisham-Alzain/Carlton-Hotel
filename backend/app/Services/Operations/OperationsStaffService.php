<?php

namespace App\Services\Operations;

use App\Models\User;
use App\Support\OperationsQueueType;
use Illuminate\Database\Eloquent\Builder;
use Spatie\Permission\Models\Role;

/**
 * The assignable-staff directory behind `GET /operations/staff` (Phase 7,
 * OPS-02, D-23, council A5).
 *
 * Rows are active users of type staff or super_admin, ordered by name, capped
 * at 200 (`truncated` says more matched; `count` is the number returned,
 * FA-7.10-1). At most 4 queries: the role-existence check, users, roles
 * eager load (plus Spatie's cached permission reads).
 *
 * - `type` (registry segment) and `permission` (one of the registry work
 *   permissions) both narrow to holders of that permission, directly or via a
 *   role; both apply when both are given (FA-7.10-2). Super admins match these
 *   filters (Gate::before parity).
 * - `department` = users holding the role named like the Department value;
 *   super admins without that role are excluded. `sales` and `maintenance`
 *   have no role and return an empty list (the Spatie scope throws on an
 *   unknown role, so it is never called with one — RESEARCH Pitfall 6).
 * - `departments[]` on each row derives from role names, so renaming a role
 *   silently changes both the filter and the response (A5).
 */
class OperationsStaffService
{
    public const LIMIT = 200;

    /** @param array{type?: ?string, permission?: ?string, department?: ?string, search?: ?string} $filters */
    public function index(array $filters): array
    {
        $query = User::query()
            ->select(['id', 'uuid', 'name', 'type'])
            ->where('is_active', true)
            ->whereIn('type', ['staff', 'super_admin'])
            ->with('roles:id,name');

        foreach ($this->permissionsFor($filters) as $permission) {
            $query->where(fn (Builder $w) => $w->permission($permission)->orWhere('type', 'super_admin'));
        }

        if (! empty($filters['department'])) {
            $this->applyDepartment($query, $filters['department']);
        }

        if (! empty($filters['search'])) {
            // Same escaping as BaseFilter::orWhereLikeInsensitive: `!` escape,
            // lowered on both sides so MySQL and SQLite agree.
            $term = str_replace(['!', '%', '_'], ['!!', '!%', '!_'], mb_strtolower($filters['search']));
            $query->whereRaw("lower(name) like ? escape '!'", ['%' . $term . '%']);
        }

        $rows      = $query->orderBy('name')->orderBy('id')->limit(self::LIMIT + 1)->get();
        $truncated = $rows->count() > self::LIMIT;
        $items     = $rows->take(self::LIMIT)->values();

        return [
            'data' => ['items' => $items, 'meta' => ['count' => $items->count(), 'truncated' => $truncated]],
            'code' => 200,
        ];
    }

    /** @return list<string> */
    private function permissionsFor(array $filters): array
    {
        $permissions = [];

        if (! empty($filters['type'])) {
            $permissions[] = OperationsQueueType::fromSegment($filters['type'])->statusPermission;
        }

        if (! empty($filters['permission'])) {
            $permissions[] = $filters['permission'];
        }

        return array_values(array_unique($permissions));
    }

    private function applyDepartment(Builder $query, string $department): void
    {
        if (! Role::where('name', $department)->where('guard_name', 'users')->exists()) {
            $query->whereRaw('1 = 0');

            return;
        }

        $query->role($department, 'users');
    }
}
