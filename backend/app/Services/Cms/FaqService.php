<?php

namespace App\Services\Cms;

use App\Base\BaseService;
use App\Filters\FaqFilter;
use App\Models\Faq;
use Illuminate\Database\Eloquent\Builder;

class FaqService extends BaseService
{
    protected string $model = Faq::class;
    protected ?string $filter = FaqFilter::class;

    /**
     * Public read: published rows in editor order. Unfilterable on purpose —
     * the website asks for the section, not for a query.
     */
    public function indexPublic(?int $perPage = null): array
    {
        $query = Faq::query()
            ->where('is_active', true)
            ->orderBy('sort_order');

        return ['data' => $query->paginate($this->resolvePerPage($perPage)), 'code' => 200];
    }

    protected function query(): Builder
    {
        return Faq::query()->orderBy('sort_order');
    }
}
