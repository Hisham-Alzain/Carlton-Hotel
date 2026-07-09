<?php

namespace App\Services\Cms;

use App\Base\BaseService;
use App\Models\RoomType;
use Illuminate\Database\Eloquent\Builder;

class RoomTypeService extends BaseService
{
    protected string $model = RoomType::class;
    protected array $with = ['images'];

    public function indexPublic(): array
    {
        $query = RoomType::query()
            ->with($this->with)
            ->where('is_active', true)
            ->orderBy('sort_order');
        return ['data' => $query->paginate($this->perPage), 'code' => 200];
    }

    protected function query(): Builder
    {
        return RoomType::query()->with($this->with)->orderBy('sort_order');
    }
}
