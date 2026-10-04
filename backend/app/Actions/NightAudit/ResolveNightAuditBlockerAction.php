<?php

namespace App\Actions\NightAudit;

use App\Enums\NightAuditBlockerStatus;
use App\Exceptions\NightAuditItemResolvedException;
use App\Models\NightAuditBlocker;
use App\Models\User;
use App\Services\Operations\NightAuditService;
use Illuminate\Support\Facades\DB;

/**
 * AUDIT-03 (Phase 9, D-06, D-12): resolve a close blocker with a mandatory
 * note. An attestation, not a live re-check (express / forced check-outs
 * legitimately leave open folios for days); blockers have no override.
 *
 * Locks state → audit → blocker. Closed audit first, then already resolved.
 * The blocker's check is never touched; no source row is written.
 */
class ResolveNightAuditBlockerAction
{
    public function __construct(private readonly NightAuditService $service) {}

    public function handle(NightAuditBlocker $blocker, string $note, User $actor): array
    {
        return DB::transaction(function () use ($blocker, $note, $actor) {
            [$state, $audit] = $this->service->lockOpenAudit($blocker->night_audit_id);

            $locked = NightAuditBlocker::query()->whereKey($blocker->getKey())->lockForUpdate()->firstOrFail();

            if ($locked->status !== NightAuditBlockerStatus::OPEN) {
                throw new NightAuditItemResolvedException('', ['item' => 'blocker', 'status' => $locked->status->value]);
            }

            $locked->update([
                'status'   => NightAuditBlockerStatus::RESOLVED,
                'note'     => $note,
                'acted_by' => $actor->id,
                'acted_at' => now(),
            ]);

            return ['data' => $this->service->payload($state, $audit), 'code' => 200];
        });
    }
}
