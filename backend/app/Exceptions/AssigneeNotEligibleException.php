<?php

namespace App\Exceptions;

/**
 * The named assignee is inactive, not a staff account, or lacks the queue
 * type's work permission (Phase 7, D-09). Context: {user_uuid, required_permission}.
 */
class AssigneeNotEligibleException extends DomainException
{
    public function errorCode(): string { return 'assignee_not_eligible'; }
    public function statusCode(): int   { return 422; }
}
