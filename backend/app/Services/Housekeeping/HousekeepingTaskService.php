<?php

namespace App\Services\Housekeeping;

use App\Actions\Housekeeping\AssignHousekeepingTaskAction;
use App\Actions\Housekeeping\CreateHousekeepingTaskAction;
use App\Actions\Housekeeping\UpdateHousekeepingTaskStatusAction;
use App\Base\BaseFilter;
use App\Base\BaseService;
use App\Enums\HousekeepingTaskStatus;
use App\Enums\HousekeepingTaskType;
use App\Enums\ServiceRequestPriority;
use App\Filters\HousekeepingTaskFilter;
use App\Models\HousekeepingTask;
use App\Models\Room;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * The housekeeping task board (Phase 6, HK-01..HK-04, D-05, D-08).
 *
 * Reads are plain board queries; every write delegates to a housekeeping
 * single writer (create, assign, status), so the lock order, history rows
 * and events live in exactly one place each.
 */
class HousekeepingTaskService extends BaseService
{
    protected string $model = HousekeepingTask::class;

    protected ?string $filter = HousekeepingTaskFilter::class;

    protected array $with = ['room', 'reservation', 'assignedUser', 'serviceRequest'];

    /** History rows returned by show(). */
    private const HISTORY_LIMIT = 10;

    public function __construct(
        private readonly CreateHousekeepingTaskAction $createTask,
        private readonly AssignHousekeepingTaskAction $assignTask,
        private readonly UpdateHousekeepingTaskStatusAction $updateTaskStatus,
    ) {}

    /**
     * Default order: due_at ascending with undated tasks last, id ascending as
     * the tiebreak (stable across calls). An explicit `sort` replaces it.
     */
    public function index(array $params = [], ?BaseFilter $filter = null, ?int $perPage = null): array
    {
        $query = $this->query()
            ->orderByRaw('due_at is null')
            ->orderBy('due_at')
            ->orderBy('id');

        ($filter ?? $this->makeFilter($params))?->apply($query);

        return ['data' => $query->paginate($this->resolvePerPage($perPage)), 'code' => 200];
    }

    public function show(Model $task): array
    {
        $task->loadMissing($this->with);
        $task->load(['history' => fn ($q) => $q->with('changedBy')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(self::HISTORY_LIMIT)]);

        return ['data' => $task, 'code' => 200];
    }

    /**
     * Manual creation (D-08) via the single creator: 201 when created, 200 with the
     * existing open task of that type for the room.
     *
     * @param  array{room_uuid: string, type: string, due_at?: ?string, priority?: ?string, notes?: ?string}  $data
     */
    public function store(array $data, ?User $actor = null): array
    {
        $room = Room::where('uuid', $data['room_uuid'])->firstOrFail();

        $result = $this->createTask->ensureOpen($room, HousekeepingTaskType::from($data['type']), [
            'priority' => ServiceRequestPriority::tryFrom((string) ($data['priority'] ?? '')) ?? ServiceRequestPriority::NORMAL,
            'due_at'   => isset($data['due_at']) ? CarbonImmutable::parse($data['due_at'])->utc() : null,
            'notes'    => $data['notes'] ?? null,
            'reason'   => 'manual',
        ], $actor);

        $result['data']->loadMissing($this->with);

        return $result;
    }

    public function assign(HousekeepingTask $task, string $userUuid, ?User $actor): array
    {
        $assignee = User::where('uuid', $userUuid)->firstOrFail();

        $result = $this->assignTask->handle($task, $assignee, $actor);
        $result['data']->loadMissing($this->with);

        return $result;
    }

    public function updateStatus(HousekeepingTask $task, string $status, ?string $reason, ?User $actor): array
    {
        $result = $this->updateTaskStatus->handle($task, HousekeepingTaskStatus::from($status), $reason, $actor);
        $result['data']->loadMissing($this->with);

        return $result;
    }
}
