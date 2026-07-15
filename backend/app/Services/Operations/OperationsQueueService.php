<?php

namespace App\Services\Operations;

use App\Actions\Operations\AssignRequestAction;
use App\Actions\Operations\UpdateRequestStatusAction;
use App\Exceptions\ForbiddenException;
use App\Exceptions\NotFoundException;
use App\Models\EventInquiry;
use App\Models\ServiceRequest;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;

class OperationsQueueService
{
    // A queue shows unresolved work, not history — completed/cancelled and
    // resolved/closed items are excluded before the bound below is even
    // applied, so the cap only ever trims a genuinely large *active* backlog.
    private const SERVICE_REQUEST_ACTIVE_STATUSES = [ServiceRequest::STATUS_NEW, ServiceRequest::STATUS_IN_PROGRESS];
    private const TICKET_ACTIVE_STATUSES = [Ticket::STATUS_OPEN, Ticket::STATUS_ASSIGNED];

    // Bounded per-table fetch for the merged queue — a true cross-table merge
    // can't be paginated at the DB layer, so each side is capped rather than
    // pulled unbounded (see P10_TICKETS.md).
    private const MERGE_FETCH_LIMIT = 500;

    public function __construct(
        private readonly AssignRequestAction $assignAction,
        private readonly UpdateRequestStatusAction $statusAction,
    ) {}

    public function index(User $user, int $page, int $perPage = 15): array
    {
        $items = collect();

        if ($user->can('service_requests.view')) {
            $items = $items->concat(
                ServiceRequest::with('assignedUser')
                    ->whereIn('status', self::SERVICE_REQUEST_ACTIVE_STATUSES)
                    ->latest()->limit(self::MERGE_FETCH_LIMIT)->get()
            );
        }
        if ($user->can('tickets.view')) {
            $items = $items->concat(
                Ticket::with('assignedUser')
                    ->whereIn('status', self::TICKET_ACTIVE_STATUSES)
                    ->latest()->limit(self::MERGE_FETCH_LIMIT)->get()
            );
        }

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

        if ($user->can('service_requests.view')) {
            $summary['service_requests'] = ServiceRequest::selectRaw('status, count(*) as count')
                ->groupBy('status')->pluck('count', 'status');
        }
        if ($user->can('tickets.view')) {
            $summary['tickets'] = Ticket::selectRaw('status, count(*) as count')
                ->groupBy('status')->pluck('count', 'status');
            $summary['event_inquiries'] = EventInquiry::selectRaw('status, count(*) as count')
                ->groupBy('status')->pluck('count', 'status');
        }

        return ['data' => $summary, 'code' => 200];
    }

    public function assign(string $type, string $uuid, string $userUuid, User $actor): array
    {
        $this->assertCan($actor, $type, 'assign');
        $item = $this->resolve($type, $uuid);
        $target = User::where('uuid', $userUuid)->firstOrFail();

        return $this->assignAction->handle($item, $target);
    }

    public function updateStatus(string $type, string $uuid, string $status, User $actor): array
    {
        $this->assertCan($actor, $type, 'status');
        $item = $this->resolve($type, $uuid);

        return $this->statusAction->handle($item, $status);
    }

    private function resolve(string $type, string $uuid): ServiceRequest|Ticket
    {
        return match ($type) {
            'service-requests' => ServiceRequest::where('uuid', $uuid)->firstOrFail(),
            'tickets'           => Ticket::where('uuid', $uuid)->firstOrFail(),
            default             => throw new NotFoundException(__('custom.errors.not_found')),
        };
    }

    private function requiredPermission(string $type, string $operation): string
    {
        return match (true) {
            $type === 'service-requests' && $operation === 'assign' => 'service_requests.assign',
            $type === 'service-requests' && $operation === 'status' => 'service_requests.update',
            $type === 'tickets' && $operation === 'assign'          => 'tickets.assign',
            $type === 'tickets' && $operation === 'status'          => 'tickets.respond',
            default => throw new NotFoundException(__('custom.errors.not_found')),
        };
    }

    private function assertCan(User $actor, string $type, string $operation): void
    {
        if (! $actor->can($this->requiredPermission($type, $operation))) {
            throw new ForbiddenException(__('custom.errors.forbidden'));
        }
    }
}
