<?php

namespace App\Services\Loyalty;

use App\Base\BaseService;
use App\Filters\LoyaltyRewardFilter;
use App\Models\LoyaltyReward;
use Illuminate\Database\Eloquent\Builder;

/**
 * Staff-managed rewards catalog (Phase 10, LOY-11) and the guest listing of it
 * (LOY-12). Rewards need no program settings, so nothing here reads them.
 */
class LoyaltyRewardService extends BaseService
{
    protected string $model = LoyaltyReward::class;

    protected ?string $filter = LoyaltyRewardFilter::class;

    /**
     * New rewards are visible and sorted first unless the editor says otherwise;
     * applied here so the response does not echo `null` for omitted columns.
     */
    public function store(array $data): array
    {
        return parent::store($data + ['is_active' => true, 'sort_order' => 0]);
    }

    /**
     * Guest catalog: active rewards only (soft-deleted ones drop out through the
     * model scope), unfilterable on purpose.
     */
    public function indexPublic(?int $perPage = null): array
    {
        $query = $this->query()->where('is_active', true);

        return ['data' => $query->paginate($this->resolvePerPage($perPage)), 'code' => 200];
    }

    protected function query(): Builder
    {
        return LoyaltyReward::query()->orderBy('sort_order')->orderBy('id');
    }
}
