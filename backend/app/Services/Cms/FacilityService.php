<?php

namespace App\Services\Cms;

use App\Base\BaseService;
use App\Models\Facility;
use Illuminate\Database\Eloquent\Builder;

class FacilityService extends BaseService
{
    protected string $model = Facility::class;
    protected array $with = ['images'];

    public function indexPublic(): array
    {
        $query = Facility::query()
            ->with($this->with)
            ->where('is_active', true)
            ->orderBy('sort_order');
        return ['data' => $query->paginate($this->perPage), 'code' => 200];
    }

    protected function query(): Builder
    {
        return Facility::query()->with($this->with)->orderBy('sort_order');
    }
}
