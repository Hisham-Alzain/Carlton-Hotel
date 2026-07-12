<?php

namespace App\Services\Service;

use App\Base\BaseService;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use Illuminate\Database\Eloquent\Model;

class MenuItemService extends BaseService
{
    protected string $model = MenuItem::class;
    protected array $with = ['category'];

    public function store(array $data): array
    {
        $data = $this->resolveCategory($data);
        return parent::store($data);
    }

    public function update(Model $model, array $data): array
    {
        $data = $this->resolveCategory($data);
        return parent::update($model, $data);
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
