<?php
namespace App\Actions\Staff;

use App\Exceptions\ForbiddenException;
use App\Models\User;
use App\Services\PermissionAssignmentService;

class AssignPermissionsAction
{
    public function __construct(
        private readonly PermissionAssignmentService $permissionService
    ) {}

    public function handle(User $actor, User $target, array $grant, array $revoke): array
    {
        // Guard rail 2: super_admin is untouchable (defensive — policy already blocks this)
        if ($target->isSuperAdmin()) {
            throw new ForbiddenException(__('custom.errors.superadmin_immutable'));
        }

        // Guard rail 1: actor cannot grant permissions they don't hold (no escalation)
        if (! $actor->isSuperAdmin()) {
            $held = $this->permissionService->heldBy($actor);
            foreach ($grant as $name) {
                if (! $held->contains($name)) {
                    throw new ForbiddenException(__('custom.errors.escalation_blocked'));
                }
            }
        }

        return $this->permissionService->apply($target, $grant, $revoke);
    }
}
