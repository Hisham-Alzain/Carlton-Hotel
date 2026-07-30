<?php

namespace App\Services\Cms;

use App\Base\BaseService;
use App\Filters\TestimonialFilter;
use App\Models\Testimonial;
use Illuminate\Database\Eloquent\Builder;

class TestimonialService extends BaseService
{
    protected string $model = Testimonial::class;
    protected ?string $filter = TestimonialFilter::class;
    protected array $with = ['images'];

    /**
     * Public read: published rows only, in editor order. Unfilterable on
     * purpose — the website asks for the section, not for a query.
     */
    public function indexPublic(?int $perPage = null): array
    {
        $query = Testimonial::query()
            ->with($this->with)
            ->where('is_active', true)
            ->orderBy('sort_order');

        return ['data' => $query->paginate($this->resolvePerPage($perPage)), 'code' => 200];
    }

    protected function query(): Builder
    {
        return Testimonial::query()->with($this->with)->orderBy('sort_order');
    }
}
