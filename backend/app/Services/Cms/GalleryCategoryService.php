<?php

namespace App\Services\Cms;

use App\Base\BaseService;
use App\Filters\GalleryCategoryFilter;
use App\Models\GalleryCategory;
use Illuminate\Database\Eloquent\Builder;

class GalleryCategoryService extends BaseService
{
    protected string $model = GalleryCategory::class;
    protected ?string $filter = GalleryCategoryFilter::class;

    /**
     * Public read: published chips in editor order. Unfilterable on purpose —
     * the website asks for the chip row, not for a query.
     */
    public function indexPublic(?int $perPage = null): array
    {
        $query = GalleryCategory::query()
            ->where('is_active', true)
            ->orderBy('sort_order');

        return ['data' => $query->paginate($this->resolvePerPage($perPage)), 'code' => 200];
    }

    protected function query(): Builder
    {
        return GalleryCategory::query()->orderBy('sort_order');
    }
}
