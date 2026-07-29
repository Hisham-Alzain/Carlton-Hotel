<?php

namespace App\Services\Service;

use App\Base\BaseService;
use App\Filters\ServiceItemFilter;
use App\Models\ServiceCategory;
use App\Models\ServiceItem;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class ServiceItemService extends BaseService
{
    protected string $model = ServiceItem::class;
    protected ?string $filter = ServiceItemFilter::class;
    protected array $with = ['category'];

    public function store(array $data): array
    {
        return parent::store($this->resolveCategory($data));
    }

    public function update(Model $model, array $data): array
    {
        return parent::update($model, $this->resolveCategory($data));
    }

    protected function query(): Builder
    {
        return ServiceItem::query()->with($this->with)->orderBy('sort_order');
    }

    private function resolveCategory(array $data): array
    {
        if (isset($data['service_category_uuid'])) {
            $data['service_category_id'] = ServiceCategory::where('uuid', $data['service_category_uuid'])->value('id');
            unset($data['service_category_uuid']);
        }
        return $data;
    }
}
