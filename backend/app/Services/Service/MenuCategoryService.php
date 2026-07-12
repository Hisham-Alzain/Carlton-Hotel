<?php

namespace App\Services\Service;

use App\Base\BaseService;
use App\Models\MenuCategory;
use Illuminate\Database\Eloquent\Builder;

class MenuCategoryService extends BaseService
{
    protected string $model = MenuCategory::class;

    protected function query(): Builder
    {
        return MenuCategory::query()->orderBy('sort_order');
    }
}
