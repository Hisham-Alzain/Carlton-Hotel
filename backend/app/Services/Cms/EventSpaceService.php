<?php

namespace App\Services\Cms;

use App\Base\BaseService;
use App\Models\EventSpace;
use Illuminate\Database\Eloquent\Builder;

class EventSpaceService extends BaseService
{
    protected string $model = EventSpace::class;
    protected array $with = ['images'];

    public function indexPublic(): array
    {
        $query = EventSpace::query()
            ->with($this->with)
            ->where('is_active', true)
            ->orderBy('sort_order');
        return ['data' => $query->paginate($this->perPage), 'code' => 200];
    }

    protected function query(): Builder
    {
        return EventSpace::query()->with($this->with)->orderBy('sort_order');
    }
}
