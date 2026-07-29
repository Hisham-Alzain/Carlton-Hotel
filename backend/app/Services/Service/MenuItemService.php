<?php

namespace App\Services\Service;

use App\Base\BaseService;
use App\Filters\MenuItemFilter;
use App\Models\DiningVenue;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class MenuItemService extends BaseService
{
    protected string $model = MenuItem::class;
    protected ?string $filter = MenuItemFilter::class;
    protected array $with = ['category', 'images'];

    public function store(array $data): array
    {
        return parent::store($this->resolveCategory($data));
    }

    public function update(Model $model, array $data): array
    {
        return parent::update($model, $this->resolveCategory($data));
    }

    /**
     * A restaurant's menu, optionally narrowed to one category slug ("starters",
     * "desserts", ...). Filtering on the slug rather than the category uuid keeps
     * the mobile filter chips stable across content edits.
     */
    public function menuForVenue(DiningVenue $venue, ?string $type = null): array
    {
        // Joined rather than whereHas() so the menu can be ordered by the
        // category's sort_order. Every column is table-qualified — both tables
        // carry `is_active`.
        $data = MenuItem::query()
            ->with($this->with)
            ->join('menu_categories', 'menu_items.menu_category_id', '=', 'menu_categories.id')
            ->where('menu_items.is_active', true)
            ->where('menu_categories.dining_venue_id', $venue->id)
            ->where('menu_categories.is_active', true)
            ->when($type !== null, fn (Builder $query) => $query->where('menu_categories.slug', $type))
            ->orderBy('menu_categories.sort_order')
            ->orderBy('menu_items.id')
            ->select('menu_items.*')
            ->paginate($this->perPage);

        return ['data' => $data, 'code' => 200];
    }

    protected function query(): Builder
    {
        return MenuItem::query()->with($this->with);
    }

    private function resolveCategory(array $data): array
    {
        if (isset($data['menu_category_uuid'])) {
            $data['menu_category_id'] = MenuCategory::where('uuid', $data['menu_category_uuid'])->value('id');
            unset($data['menu_category_uuid']);
        }
        return $data;
    }
}
