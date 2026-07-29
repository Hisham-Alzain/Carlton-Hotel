<?php

namespace App\Services\Cms;

use App\Base\BaseService;
use App\Filters\DiningVenueFilter;
use App\Models\DiningVenue;
use Illuminate\Database\Eloquent\Builder;

class DiningVenueService extends BaseService
{
    protected string $model = DiningVenue::class;
    protected ?string $filter = DiningVenueFilter::class;
    protected array $with = ['images'];

    public function indexPublic(?int $perPage = null): array
    {
        $query = DiningVenue::query()
            ->with($this->with)
            ->where('is_active', true)
            ->orderBy('sort_order');
        return ['data' => $query->paginate($this->resolvePerPage($perPage)), 'code' => 200];
    }

    protected function query(): Builder
    {
        return DiningVenue::query()->with($this->with)->orderBy('sort_order');
    }
}
