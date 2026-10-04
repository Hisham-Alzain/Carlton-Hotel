<?php

namespace App\Actions\NightAudit;

use App\Enums\NightAuditBlockerStatus;
use App\Enums\NightAuditCheckStatus;
use App\Enums\NightAuditCheckType;
use App\Enums\NightAuditStatus;
use App\Exceptions\ForbiddenException;
use App\Exceptions\NightAuditDateInFutureException;
use App\Exceptions\NightAuditDateMismatchException;
use App\Exceptions\NightAuditNotInitializedException;
use App\Models\NightAudit;
use App\Models\NightAuditBlocker;
use App\Models\NightAuditCheck;
use App\Models\NightAuditState;
use App\Models\User;
use App\Services\Operations\NightAuditService;
use App\Support\HotelClock;
use App\Support\NightAuditEvaluator;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Lazy, idempotent night-audit creation (Phase 9, D-03, D-04, D-09, D-11).
 *
 * Under the singleton state row lock:
 *  - no state yet → initialization (date required; only a `night_audit.manage`
 *    holder may choose the accounting start date; never a future date);
 *  - an existing audit for the target date is returned as is (history);
 *  - otherwise only the current business date may be created, and only once
 *    the hotel's today has reached it. The clock bounds creation; it never
 *    picks the date.
 *
 * Creation evaluates the five categories once and persists the snapshot: the
 * audit, exactly five checks, and one open blocker per non-empty blocking
 * check. Re-opening never re-evaluates. Source rows are only read.
 *
 * Backstop: the unique `night_audits.business_date`. A unique violation (that
 * class only) is recovered by re-reading the existing audit once, outside the
 * failed transaction; every other database error propagates.
 */
class OpenNightAuditAction
{
    public function __construct(
        private readonly NightAuditService $service,
        private readonly NightAuditEvaluator $evaluator,
    ) {}

    /** @return array{data: array{state: NightAuditState, audit: ?NightAudit}, code: int} */
    public function handle(?string $date, bool $canInitialize, User $actor): array
    {
        try {
            [$state, $audit] = DB::transaction(fn () => $this->open($date, $canInitialize, $actor));
        } catch (UniqueConstraintViolationException $e) {
            [$state, $audit] = DB::transaction(function () use ($date, $e) {
                $state = $this->service->lockState();
                $audit = $state ? $this->findAudit($date ?? $state->current_business_date->toDateString()) : null;

                if ($audit === null) {
                    throw $e;
                }

                return [$state, $audit];
            });
        }

        return ['data' => $this->service->payload($state, $audit), 'code' => 200];
    }

    /** Test seam for the unique-violation recovery (D-11). */
    protected function findAudit(string $businessDate): ?NightAudit
    {
        return $this->service->findAudit($businessDate);
    }

    /** @return array{0: NightAuditState, 1: ?NightAudit} */
    private function open(?string $date, bool $canInitialize, User $actor): array
    {
        $state = $this->service->lockState() ?? $this->initialize($date, $canInitialize);

        $current = $state->current_business_date->toDateString();
        $target  = $date ?? $current;

        $existing = $this->findAudit($target);
        if ($existing !== null) {
            return [$state, $existing];
        }

        if ($target !== $current) {
            throw new NightAuditDateMismatchException('', [
                'requested_date'        => $target,
                'current_business_date' => $current,
            ]);
        }

        // Closing D at 23:30 and reloading is not an error: D + 1 exists only tomorrow.
        if ($target > HotelClock::today()->toDateString()) {
            return [$state, null];
        }

        return [$state, $this->create($target, $actor)];
    }

    private function initialize(?string $date, bool $canInitialize): NightAuditState
    {
        if ($date === null) {
            throw new NightAuditNotInitializedException('', ['requires' => 'date']);
        }

        if (! $canInitialize) {
            throw new ForbiddenException('', ['reason' => 'night_audit_not_initialized']);
        }

        $today = HotelClock::today()->toDateString();
        if ($date > $today) {
            throw new NightAuditDateInFutureException('', [
                'requested_date' => $date,
                'hotel_today'    => $today,
            ]);
        }

        $now = now();
        NightAuditState::query()->insertOrIgnore([
            'singleton'             => 1,
            'current_business_date' => $date,
            'last_closed_date'      => null,
            'created_at'            => $now,
            'updated_at'            => $now,
        ]);

        return $this->service->lockState();
    }

    private function create(string $businessDate, User $actor): NightAudit
    {
        $audit = NightAudit::create([
            'business_date'  => $businessDate,
            'status'         => NightAuditStatus::OPEN,
            'snapshot_basis' => NightAudit::SNAPSHOT_BASIS,
            'evaluated_at'   => now(),
            'opened_by'      => $actor->id,
        ]);

        $results = $this->evaluator->evaluate($businessDate);
        $now     = now();
        $rows    = [];

        foreach (NightAuditCheckType::cases() as $type) {
            $result = $results[$type->value];
            $rows[] = [
                'uuid'               => (string) Str::uuid(),
                'night_audit_id'     => $audit->id,
                'type'               => $type->value,
                'blocking'           => $type->isBlocking(),
                'status'             => ($result['issue_count'] > 0 ? NightAuditCheckStatus::PENDING : NightAuditCheckStatus::PASSED)->value,
                'issue_count'        => $result['issue_count'],
                'evidence'           => json_encode($result['evidence'], JSON_THROW_ON_ERROR),
                'evidence_truncated' => $result['evidence_truncated'],
                'created_at'         => $now,
                'updated_at'         => $now,
            ];
        }

        NightAuditCheck::query()->insert($rows);
        $this->insertBlockers($audit, $now);

        return $audit;
    }

    /**
     * One blocker per pending blocking check, in a single INSERT … SELECT so
     * the statement count is the same with 0, 1 or 2 blockers (D-22). Each
     * blocking type gets a uuid generated here, picked by a CASE on the type.
     */
    private function insertBlockers(NightAudit $audit, \DateTimeInterface $now): void
    {
        $case     = 'CASE type';
        $bindings = [];
        foreach (NightAuditCheckType::cases() as $type) {
            if ($type->isBlocking()) {
                $case .= ' WHEN ? THEN ?';
                array_push($bindings, $type->value, (string) Str::uuid());
            }
        }
        $case .= ' END';

        $timestamp = $now->format('Y-m-d H:i:s');

        NightAuditBlocker::query()->insertUsing(
            ['uuid', 'night_audit_id', 'night_audit_check_id', 'status', 'created_at', 'updated_at'],
            NightAuditCheck::query()
                ->where('night_audit_id', $audit->id)
                ->where('blocking', true)
                ->where('status', NightAuditCheckStatus::PENDING)
                ->orderBy('id')
                ->selectRaw(
                    "{$case}, night_audit_id, id, ?, ?, ?",
                    [...$bindings, NightAuditBlockerStatus::OPEN->value, $timestamp, $timestamp],
                )
                ->toBase(),
        );
    }
}
