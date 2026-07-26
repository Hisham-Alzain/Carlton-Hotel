<?php

namespace App\Services\Cms;

use App\Base\BaseService;
use App\Models\HomeSlider;
use Illuminate\Database\Eloquent\Builder;

class HomeSliderService extends BaseService
{
    protected string $model = HomeSlider::class;
    protected array $with = ['images'];

    public function indexPublic(): array
    {
        $query = HomeSlider::query()
            ->with($this->with)
            ->where('is_active', true)
            ->orderBy('sort_order');
        return ['data' => $query->paginate($this->perPage), 'code' => 200];
    }

    protected function query(): Builder
    {
        return HomeSlider::query()->with($this->with)->orderBy('sort_order');
    }
}
