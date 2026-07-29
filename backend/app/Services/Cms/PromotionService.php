<?php

namespace App\Services\Cms;

use App\Base\BaseService;
use App\Filters\PromotionFilter;
use App\Models\Promotion;
use Illuminate\Database\Eloquent\Builder;

class PromotionService extends BaseService
{
    protected string $model = Promotion::class;
    protected ?string $filter = PromotionFilter::class;
    protected array $with = ['images'];

    public function indexPublic(?int $perPage = null): array
    {
        $query = Promotion::query()
            ->with($this->with)
            ->where('is_active', true)
            ->orderBy('sort_order');
        return ['data' => $query->paginate($this->resolvePerPage($perPage)), 'code' => 200];
    }

    protected function query(): Builder
    {
        return Promotion::query()->with($this->with)->orderBy('sort_order');
    }
}
