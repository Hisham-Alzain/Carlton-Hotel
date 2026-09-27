<?php

namespace App\Services\Operations;

use App\Actions\Operations\AssignRequestAction;
use App\Actions\Operations\UpdateRequestStatusAction;
use App\Exceptions\ForbiddenException;
use App\Models\EventInquiry;
use App\Models\User;
use App\Support\OperationsQueueType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * The merged operations queue. Every per-type rule (model, permissions, open
 * statuses, eager loads) comes from the OperationsQueueType registry (D-12).
 */
class OperationsQueueService
{
    // A queue shows unresolved work, not history — closed items are excluded
    // before the bound below is even applied, so the cap only ever trims a
    // genuinely large *active* backlog. Bounded per-type fetch for the merged
    // queue — a true cross-table merge can't be paginated at the DB layer, so
    // each type is capped (3 × 500 at most) rather than pulled unbounded (see
    // P10_TICKETS.md; SQL UNION deferred, D-12).
    private const MERGE_FETCH_LIMIT = 500;

    public function __construct(
        private readonly AssignRequestAction $assignAction,
        private readonly UpdateRequestStatusAction $statusAction,
    ) {}

    public function index(User $user, int $page, int $perPage = 15): array
    {
        $items = collect();

        foreach (OperationsQueueType::all() as $type) {
            if ($user->can($type->viewPermission)) {
                $items = $items->concat(
                    $type->queueQuery()->latest()->orderByDesc('id')->limit(self::MERGE_FETCH_LIMIT)->get()
                );
            }
        }

        // sortByDesc is stable: rows with an equal created_at keep their
        // concatenation order — registry order (service_request, ticket,
        // housekeeping_task), then id descending inside one type — so the
        // order is identical across calls (HK-05).
        $sorted = $items->sortByDesc(fn ($item) => $item->created_at)->values();
        $page   = max(1, $page);

        $paginator = new LengthAwarePaginator(
            $sorted->forPage($page, $perPage)->values(),
            $sorted->count(),
            $perPage,
            $page,
            ['path' => LengthAwarePaginator::resolveCurrentPath()],
        );

        return ['data' => $paginator, 'code' => 200];
    }

    public function summary(User $user): array
    {
        $summary = [];

        foreach (OperationsQueueType::all() as $type) {
            if (! $user->can($type->viewPermission)) {
                continue;
            }

            $summary[$type->summaryKey] = $type->modelClass::selectRaw('status, count(*) as count')
                ->groupBy('status')->pluck('count', 'status');

            // Event inquiries have no queue arm; their counts ride with tickets (P10).
            if ($type->segment === 'tickets') {
                $summary['event_inquiries'] = EventInquiry::selectRaw('status, count(*) as count')
                    ->groupBy('status')->pluck('count', 'status');
            }
        }

        return ['data' => $summary, 'code' => 200];
    }

    public function assign(string $type, string $uuid, string $userUuid, User $actor): array
    {
        $this->assertCan($actor, $this->requiredPermission($type, 'assign'));
        $item   = $this->resolve($type, $uuid);
        $target = User::where('uuid', $userUuid)->firstOrFail();

        $result = $this->assignAction->handle($item, $target, $actor);

        return ['data' => $this->reload($type, $result['data']), 'code' => $result['code']];
    }

    public function updateStatus(string $type, string $uuid, string $status, User $actor, ?string $reason = null): array
    {
        $this->assertCan($actor, $this->requiredPermission($type, 'status'));
        $item = $this->resolve($type, $uuid);

        $result = $this->statusAction->handle($item, $status, $actor, $reason);

        return ['data' => $this->reload($type, $result['data']), 'code' => $result['code']];
    }

    private function resolve(string $type, string $uuid): Model
    {
        return OperationsQueueType::fromSegment($type)->modelClass::where('uuid', $uuid)->firstOrFail();
    }

    private function requiredPermission(string $type, string $operation): string
    {
        $entry = OperationsQueueType::fromSegment($type);

        return $operation === 'assign' ? $entry->assignPermission : $entry->statusPermission;
    }

    /** Re-read through the registry so the response carries room_number and the assignee without lazy loads. */
    private function reload(string $type, Model $item): Model
    {
        return OperationsQueueType::fromSegment($type)->baseQuery()->whereKey($item->getKey())->firstOrFail();
    }

    private function assertCan(User $actor, string $permission): void
    {
        if (! $actor->can($permission)) {
            throw new ForbiddenException(__('custom.errors.forbidden'));
        }
    }
}
