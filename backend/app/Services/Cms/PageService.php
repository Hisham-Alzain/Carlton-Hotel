<?php

namespace App\Services\Cms;

use App\Base\BaseService;
use App\Exceptions\NotFoundException;
use App\Models\Page;
use Illuminate\Database\Eloquent\Builder;

class PageService extends BaseService
{
    protected string $model = Page::class;
    protected array $with = [];

    public function findBySlug(string $slug): array
    {
        $page = Page::where('slug', $slug)->where('is_active', true)->first();
        if (!$page) {
            throw new NotFoundException();
        }
        return ['data' => $page, 'code' => 200];
    }

    protected function query(): Builder
    {
        return Page::query()->with($this->with)->orderBy('sort_order');
    }
}
