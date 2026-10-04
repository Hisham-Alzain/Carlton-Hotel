<?php

namespace App\Actions\NightAudit;

use App\Enums\NightAuditCheckStatus;
use App\Exceptions\NightAuditItemResolvedException;
use App\Models\NightAuditCheck;
use App\Models\User;
use App\Services\Operations\NightAuditService;
use Illuminate\Support\Facades\DB;

/**
 * AUDIT-02 (Phase 9, D-06, D-12): staff record a pending check as `resolved`
 * (verified fixed in the source) or `overridden` (exception accepted), with a
 * mandatory note. The actor is the authenticated user; the time is now (UTC).
 *
 * Locks state → audit → check. A closed audit is refused first; then a check
 * that is no longer pending. The check's blocker is never touched, and no
 * source row (folio, room, ticket, reservation) is written.
 */
class ResolveNightAuditCheckAction
{
    public function __construct(private readonly NightAuditService $service) {}

    public function handle(NightAuditCheck $check, string $status, string $note, User $actor): array
    {
        return DB::transaction(function () use ($check, $status, $note, $actor) {
            [$state, $audit] = $this->service->lockOpenAudit($check->night_audit_id);

            $locked = NightAuditCheck::query()->whereKey($check->getKey())->lockForUpdate()->firstOrFail();

            if ($locked->status !== NightAuditCheckStatus::PENDING) {
                throw new NightAuditItemResolvedException('', ['item' => 'check', 'status' => $locked->status->value]);
            }

            $locked->update([
                'status'   => NightAuditCheckStatus::from($status),
                'note'     => $note,
                'acted_by' => $actor->id,
                'acted_at' => now(),
            ]);

            return ['data' => $this->service->payload($state, $audit), 'code' => 200];
        });
    }
}
