<?php

namespace App\Actions\NightAudit;

use App\Enums\NightAuditStatus;
use App\Exceptions\NightAuditDateMismatchException;
use App\Exceptions\NightAuditNotInitializedException;
use App\Exceptions\NightAuditNotReadyException;
use App\Models\NightAudit;
use App\Models\User;
use App\Services\Operations\NightAuditService;
use App\Support\HotelClock;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * AUDIT-04 (Phase 9, D-12): close the business date.
 *
 * Locks state → audit. An already closed audit answers with the unchanged
 * record (idempotent repeat; nothing moves). The audit must be for the
 * current business date (defence in depth). Readiness is computed from the
 * children read under the audit lock: every check terminal and every blocker
 * resolved. Closing stamps closer/time and advances the state exactly one
 * calendar day in the hotel timezone (DST-safe: date arithmetic, not 24 h).
 * There is no reopen and nothing is read from the request body.
 */
class CloseNightAuditAction
{
    public function __construct(private readonly NightAuditService $service) {}

    public function handle(NightAudit $audit, User $actor): array
    {
        return DB::transaction(function () use ($audit, $actor) {
            $state  = $this->service->lockState() ?? throw new NightAuditNotInitializedException('', ['requires' => 'date']);
            $locked = NightAudit::query()->whereKey($audit->getKey())->lockForUpdate()->firstOrFail();

            if ($locked->isClosed()) {
                return ['data' => $this->service->payload($state, $locked), 'code' => 200];
            }

            $date    = $locked->business_date->toDateString();
            $current = $state->current_business_date->toDateString();

            if ($date !== $current) {
                throw new NightAuditDateMismatchException('', [
                    'requested_date'        => $date,
                    'current_business_date' => $current,
                ]);
            }

            $payload   = $this->service->payload($state, $locked);
            $readiness = $locked->readiness();

            if (! $readiness['can_close']) {
                throw new NightAuditNotReadyException('', [
                    'checks_pending' => $readiness['checks_pending'],
                    'blockers_open'  => $readiness['blockers_open'],
                ]);
            }

            $locked->update([
                'status'    => NightAuditStatus::CLOSED,
                'closed_by' => $actor->id,
                'closed_at' => now(),
            ]);
            $locked->setRelation('closer', $actor);

            $state->update([
                'last_closed_date'      => $date,
                'current_business_date' => CarbonImmutable::createFromFormat('!Y-m-d', $date, HotelClock::timezone())
                    ->addDay()
                    ->toDateString(),
            ]);

            return ['data' => $payload, 'code' => 200];
        });
    }
}
