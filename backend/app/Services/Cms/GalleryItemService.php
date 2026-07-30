<?php

namespace App\Services\Cms;

use App\Base\BaseService;
use App\Filters\GalleryItemFilter;
use App\Models\GalleryCategory;
use App\Models\GalleryItem;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class GalleryItemService extends BaseService
{
    protected string $model = GalleryItem::class;
    protected ?string $filter = GalleryItemFilter::class;
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
     * Public read: published photographs in published chips, ordered chip-first
     * so the website's "All" tab reads as the editor arranged it. Unfilterable on
     * purpose — the site fetches once and filters its chips client-side.
     *
     * The category's own `is_active` is checked as well as the item's: the site
     * groups strictly by chip, so a photograph in a hidden chip has nowhere to
     * render and would only be a leaked draft.
     */
    public function indexPublic(?int $perPage = null): array
    {
        $data = GalleryItem::query()
            ->with($this->with)
            ->join('gallery_categories', 'gallery_items.gallery_category_id', '=', 'gallery_categories.id')
            // Every predicate is table-qualified — both tables carry `is_active`
            // and `sort_order`.
            ->where('gallery_items.is_active', true)
            ->where('gallery_categories.is_active', true)
            ->orderBy('gallery_categories.sort_order')
            ->orderBy('gallery_items.sort_order')
            ->orderBy('gallery_items.id')
            ->select('gallery_items.*')
            ->paginate($this->resolvePerPage($perPage));

        return ['data' => $data, 'code' => 200];
    }

    protected function query(): Builder
    {
        return GalleryItem::query()->with($this->with)->orderBy('sort_order');
    }

    private function resolveCategory(array $data): array
    {
        if (isset($data['gallery_category_uuid'])) {
            $data['gallery_category_id'] = GalleryCategory::where('uuid', $data['gallery_category_uuid'])->value('id');
            unset($data['gallery_category_uuid']);
        }

        return $data;
    }
}
