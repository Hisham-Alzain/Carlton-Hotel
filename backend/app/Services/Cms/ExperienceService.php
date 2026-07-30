<?php

namespace App\Services\Cms;

use App\Base\BaseService;
use App\Filters\ExperienceFilter;
use App\Models\Experience;
use Illuminate\Database\Eloquent\Builder;

class ExperienceService extends BaseService
{
    protected string $model = Experience::class;
    protected ?string $filter = ExperienceFilter::class;
    protected array $with = ['images'];

    /**
     * Public read: published rows only, in editor order. Unfilterable on
     * purpose — the website asks for the section and filters its own category
     * chips client-side off a single fetch.
     */
    public function indexPublic(?int $perPage = null): array
    {
        $query = Experience::query()
            ->with($this->with)
            ->where('is_active', true)
            ->orderBy('sort_order');

        return ['data' => $query->paginate($this->resolvePerPage($perPage)), 'code' => 200];
    }

    protected function query(): Builder
    {
        return Experience::query()->with($this->with)->orderBy('sort_order');
    }
}
