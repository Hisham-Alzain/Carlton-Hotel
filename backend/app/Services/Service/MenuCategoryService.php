<?php

namespace App\Services\Service;

use App\Base\BaseService;
use App\Models\DiningVenue;
use App\Models\MenuCategory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class MenuCategoryService extends BaseService
{
    protected string $model = MenuCategory::class;
    protected array $with = ['venue'];

    public function store(array $data): array
    {
        return parent::store($this->resolveVenue($data));
    }

    public function update(Model $model, array $data): array
    {
        return parent::update($model, $this->resolveVenue($data));
    }

    /** The filter chips above a restaurant's menu. */
    public function indexForVenue(DiningVenue $venue): array
    {
        $data = MenuCategory::query()
            ->with($this->with)
            ->where('dining_venue_id', $venue->id)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        return ['data' => $data, 'code' => 200];
    }

    protected function query(): Builder
    {
        return MenuCategory::query()->with($this->with)->orderBy('sort_order');
    }

    private function resolveVenue(array $data): array
    {
        if (isset($data['dining_venue_uuid'])) {
            $data['dining_venue_id'] = DiningVenue::where('uuid', $data['dining_venue_uuid'])->value('id');
            unset($data['dining_venue_uuid']);
        }
        return $data;
    }
}
