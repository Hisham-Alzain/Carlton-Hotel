<?php

namespace App\Support;

use App\Enums\HousekeepingTaskStatus;
use App\Enums\ServiceRequestStatus;
use App\Enums\TicketStatus;
use App\Exceptions\NotFoundException;
use App\Models\HousekeepingTask;
use App\Models\ServiceRequest;
use App\Models\Ticket;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use LogicException;

/**
 * The operations-queue type registry (Phase 6, D-12): one entry per item type,
 * read by the queue service (resolve, permissions, index, summary), the status
 * request (which enum to validate against), the item resource and the
 * Firestore mirror (document id prefix). Adding a queue type means adding an
 * entry here, not another match arm in each of those places.
 *
 * Each type is fetched with its own bounded cap before the merge, so the
 * merged queue holds at most 3 × 500 rows and a type with more than 500 open
 * items is truncated (a SQL UNION / materialised queue is deferred).
 */
final class OperationsQueueType
{
    /**
     * @param class-string<Model>     $modelClass
     * @param class-string<\BackedEnum> $statusEnum
     */
    private function __construct(
        public readonly string $segment,
        public readonly string $itemType,
        public readonly string $modelClass,
        public readonly string $viewPermission,
        public readonly string $assignPermission,
        public readonly string $statusPermission,
        public readonly string $statusEnum,
        public readonly string $mirrorPrefix,
        public readonly string $summaryKey,
    ) {}

    /**
     * Registry order is also the tie-break order of the merged queue.
     *
     * @return list<self>
     */
    public static function all(): array
    {
        static $all = null;

        return $all ??= [
            new self('service-requests', 'service_request', ServiceRequest::class,
                'service_requests.view', 'service_requests.assign', 'service_requests.update',
                ServiceRequestStatus::class, 'service_request_', 'service_requests'),
            new self('tickets', 'ticket', Ticket::class,
                'tickets.view', 'tickets.assign', 'tickets.respond',
                TicketStatus::class, 'ticket_', 'tickets'),
            new self('housekeeping-tasks', 'housekeeping_task', HousekeepingTask::class,
                'housekeeping.view', 'housekeeping.assign', 'housekeeping.update',
                HousekeepingTaskStatus::class, 'housekeeping_task_', 'housekeeping_tasks'),
        ];
    }

    public static function tryFromSegment(string $segment): ?self
    {
        foreach (self::all() as $type) {
            if ($type->segment === $segment) {
                return $type;
            }
        }

        return null;
    }

    public static function fromSegment(string $segment): self
    {
        return self::tryFromSegment($segment) ?? throw new NotFoundException(__('custom.errors.not_found'));
    }

    public static function forModel(Model $item): self
    {
        foreach (self::all() as $type) {
            if ($item instanceof $type->modelClass) {
                return $type;
            }
        }

        throw new LogicException('Not an operations queue item: ' . $item::class);
    }

    /** @return list<string> status values that keep an item on the queue */
    public function openStatuses(): array
    {
        $cases = $this->statusEnum === HousekeepingTaskStatus::class
            ? HousekeepingTaskStatus::open()
            : $this->statusEnum::active();

        return array_map(static fn (\BackedEnum $s) => $s->value, $cases);
    }

    /** Everything the item resource and the mirror read, loaded up front. */
    public function baseQuery(): Builder
    {
        return match ($this->modelClass) {
            ServiceRequest::class   => ServiceRequest::query()->withRoomNumber()->with('assignedUser'),
            HousekeepingTask::class => HousekeepingTask::query()->with(['assignedUser', 'room']),
            // Ticket::room() includes trashed rooms, so a deleted room keeps its number (D-05).
            Ticket::class           => Ticket::query()->with(['assignedUser', 'room']),
            default                 => $this->modelClass::query()->with('assignedUser'),
        };
    }

    public function queueQuery(): Builder
    {
        return $this->baseQuery()->whereIn('status', $this->openStatuses());
    }

    /**
     * Enforced targets for tasks and (Phase 7, D-06/D-24) tickets —
     * `TicketStatus::allowedTargets()`, `assigned` excluded because only
     * assign/claim/escalate reach it. Service requests remain advisory
     * "every other value" (FA-6.05-1).
     *
     * @return list<string>
     */
    public function allowedStatuses(Model $item): array
    {
        return array_map(static fn (\BackedEnum $s) => $s->value, $item->status->allowedTargets());
    }

    /** Never lazy-loads: tasks and tickets need `room` loaded, requests the `room_number` subselect. */
    public function roomNumber(Model $item): ?string
    {
        return match (true) {
            $item instanceof HousekeepingTask => $item->relationLoaded('room') ? $item->room?->number : null,
            $item instanceof Ticket           => $item->relationLoaded('room') ? $item->room?->number : null,
            $item instanceof ServiceRequest   => $item->getAttribute('room_number') !== null ? (string) $item->getAttribute('room_number') : null,
            default                           => null,
        };
    }
}
