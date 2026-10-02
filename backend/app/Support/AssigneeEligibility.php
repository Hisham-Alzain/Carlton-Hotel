<?php

namespace App\Support;

use App\Exceptions\AssigneeNotEligibleException;
use App\Models\User;

/**
 * Who may be handed operations-queue work (Phase 7, D-09): an active account
 * of type staff or super_admin that can perform the queue type's work
 * permission (super admins pass through Gate::before).
 *
 * Callers pass `OperationsQueueType::…->statusPermission`
 * (service_requests.update | tickets.respond | housekeeping.update). Claim
 * skips it: the claimer's own work permission was already checked (D-22).
 */
final class AssigneeEligibility
{
    private const ASSIGNABLE_TYPES = ['staff', 'super_admin'];

    public static function assert(User $target, string $workPermission): void
    {
        if ($target->is_active
            && in_array($target->type, self::ASSIGNABLE_TYPES, true)
            && $target->can($workPermission)) {
            return;
        }

        throw new AssigneeNotEligibleException(
            __('custom.errors.assignee_not_eligible'),
            ['user_uuid' => $target->uuid, 'required_permission' => $workPermission],
        );
    }
}
