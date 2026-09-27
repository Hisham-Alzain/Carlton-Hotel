<?php

namespace App\Services\Operations;

use App\Base\BaseFilter;
use App\Base\BaseService;
use App\Filters\ServiceRequestFilter;
use App\Models\ServiceRequest;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * The staff service-request board (Phase 6, SVC-01, D-15..D-17).
 *
 * Read-only: no transaction, no lock. Writes go through the operations-queue
 * verbs (`PATCH /operations/queue/service-requests/{uuid}/assign|status`).
 *
 * One page costs at most 7 queries (D-16): the paginator's count, the page
 * itself (carrying `room_number` as a subselect) and the five eager loads
 * below.
 */
class ServiceRequestBoardService extends BaseService
{
    protected string $model = ServiceRequest::class;

    protected ?string $filter = ServiceRequestFilter::class;

    protected array $with = ['guest', 'reservation', 'serviceItem', 'assignedUser', 'housekeepingTask'];

    /** Default order: newest first, id descending as the tiebreak. An explicit `sort` replaces it. */
    public function index(array $params = [], ?BaseFilter $filter = null, ?int $perPage = null): array
    {
        $query = $this->boardQuery()
            ->orderByDesc('service_requests.created_at')
            ->orderByDesc('service_requests.id');

        ($filter ?? $this->makeFilter($params))?->apply($query);

        return ['data' => $query->paginate($this->resolvePerPage($perPage)), 'code' => 200];
    }

    /** Re-read through the board query so the row carries `room_number`. */
    public function show(Model $model): array
    {
        return ['data' => $this->boardQuery()->whereKey($model->getKey())->firstOrFail(), 'code' => 200];
    }

    private function boardQuery(): Builder
    {
        return ServiceRequest::query()->withRoomNumber()->with($this->with);
    }
}
