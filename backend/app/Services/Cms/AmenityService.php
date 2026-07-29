<?php

namespace App\Services\Cms;

use App\Base\BaseService;
use App\Filters\AmenityFilter;
use App\Models\Amenity;
use Illuminate\Database\Eloquent\Builder;

class AmenityService extends BaseService
{
    protected string $model = Amenity::class;
    protected ?string $filter = AmenityFilter::class;

    public function indexPublic(?int $perPage = null): array
    {
        $query = Amenity::query()->where('is_active', true)->orderBy('sort_order');
        return ['data' => $query->paginate($this->resolvePerPage($perPage)), 'code' => 200];
    }

    protected function query(): Builder
    {
        return Amenity::query()->orderBy('sort_order');
    }
}
