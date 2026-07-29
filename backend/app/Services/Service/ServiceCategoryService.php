<?php

namespace App\Services\Service;

use App\Base\BaseService;
use App\Filters\ServiceCategoryFilter;
use App\Models\ServiceCategory;
use Illuminate\Database\Eloquent\Builder;

class ServiceCategoryService extends BaseService
{
    protected string $model = ServiceCategory::class;
    protected ?string $filter = ServiceCategoryFilter::class;
    protected array $with = ['items'];

    protected function query(): Builder
    {
        return ServiceCategory::query()->with($this->with)->orderBy('sort_order');
    }
}
