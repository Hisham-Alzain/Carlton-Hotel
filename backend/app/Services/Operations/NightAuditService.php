<?php

namespace App\Services\Operations;

use App\Exceptions\NightAuditClosedException;
use App\Exceptions\NightAuditNotInitializedException;
use App\Models\NightAudit;
use App\Models\NightAuditState;
use App\Models\User;

/**
 * Night audit reads (Phase 9, D-02, D-13). Every audit endpoint answers with
 * the same payload: the business-date state plus the audit (or null).
 *
 * The audit's relations (`checks.actor`, `checks.blocker`, `blockers.actor`,
 * `blockers.check`, `opener`, `closer`) are loaded in a fixed three statements — checks,
 * blockers, and one users lookup for every actor — and set with
 * `setRelation()`, so the Resource's `whenLoaded()` guards hold and the read
 * budget does not depend on how many blockers exist or who acted (D-22).
 */
class NightAuditService
{
    /** The singleton state row, locked for the rest of the caller's transaction (D-11). */
    public function lockState(): ?NightAuditState
    {
        return NightAuditState::query()->where('singleton', 1)->lockForUpdate()->first();
    }

    /**
     * Mutation lock order (D-12): state → audit, then the caller locks the
     * child. A closed audit refuses every change before any other guard.
     *
     * @return array{0: NightAuditState, 1: NightAudit}
     *
     * @throws NightAuditNotInitializedException when no state row exists
     * @throws NightAuditClosedException
     */
    public function lockOpenAudit(int $auditId): array
    {
        $state = $this->lockState() ?? throw new NightAuditNotInitializedException('', ['requires' => 'date']);
        $audit = NightAudit::query()->whereKey($auditId)->lockForUpdate()->firstOrFail();

        if ($audit->isClosed()) {
            throw new NightAuditClosedException('', [
                'business_date' => $audit->business_date->toDateString(),
                'closed_at'     => $audit->closed_at?->toIso8601ZuluString(),
            ]);
        }

        return [$state, $audit];
    }

    public function findAudit(string $businessDate): ?NightAudit
    {
        return NightAudit::query()->whereDate('business_date', $businessDate)->first();
    }

    /** @return array{state: NightAuditState, audit: ?NightAudit} */
    public function payload(NightAuditState $state, ?NightAudit $audit): array
    {
        if ($audit !== null) {
            $this->loadRelations($audit);
        }

        return ['state' => $state, 'audit' => $audit];
    }

    /**
     * Readiness from the loaded children; no query (D-12).
     *
     * @return array{checks_pending: int, blockers_open: int, can_close: bool}
     */
    public function readiness(NightAudit $audit): array
    {
        return $audit->readiness();
    }

    private function loadRelations(NightAudit $audit): void
    {
        $checks   = $audit->checks()->get();
        $blockers = $audit->blockers()->get();

        $userIds = collect([$audit->opened_by, $audit->closed_by])
            ->merge($checks->pluck('acted_by'))
            ->merge($blockers->pluck('acted_by'))
            ->filter()
            ->unique()
            ->values()
            ->all();

        // Always one statement, even with no actor yet (whereIn [] compiles to 0 = 1).
        $users = User::query()->whereIn('id', $userIds)->get(['id', 'uuid', 'name'])->keyBy('id');

        $checksById      = $checks->keyBy('id');
        $blockersByCheck = $blockers->keyBy('night_audit_check_id');

        foreach ($checks as $check) {
            $check->setRelation('actor', $users->get($check->acted_by));
            $check->setRelation('blocker', $blockersByCheck->get($check->id));
        }

        foreach ($blockers as $blocker) {
            $blocker->setRelation('actor', $users->get($blocker->acted_by));
            $blocker->setRelation('check', $checksById->get($blocker->night_audit_check_id));
        }

        $audit->setRelation('checks', $checks);
        $audit->setRelation('blockers', $blockers);
        $audit->setRelation('opener', $users->get($audit->opened_by));
        $audit->setRelation('closer', $users->get($audit->closed_by));
    }
}
