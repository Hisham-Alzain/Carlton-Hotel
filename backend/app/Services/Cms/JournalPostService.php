<?php

namespace App\Services\Cms;

use App\Base\BaseService;
use App\Filters\JournalPostFilter;
use App\Models\JournalPost;
use Illuminate\Database\Eloquent\Builder;

class JournalPostService extends BaseService
{
    protected string $model = JournalPost::class;
    protected ?string $filter = JournalPostFilter::class;
    protected array $with = ['images'];

    /**
     * Public read: published rows only, newest first.
     *
     * Two things this deliberately does NOT do:
     *
     * 1. It does not compare `published_on` to `now()`. The date is editorial
     *    metadata, not a schedule — a future-dated active post is returned, and
     *    `JournalPostTest::test_future_dated_active_post_is_still_returned_publicly`
     *    fails if that ever changes.
     * 2. It takes no filter. The website asks for the Journal, not for a query,
     *    so a stale or hostile query string cannot reach this builder and
     *    surface drafts.
     */
    public function indexPublic(?int $perPage = null): array
    {
        $query = JournalPost::query()
            ->with($this->with)
            ->where('is_active', true)
            ->orderByDesc('published_on')
            // Tiebreakers, not secondary sorts: several posts share a date, and
            // without a deterministic order the same row can appear on two
            // pages (or on neither) as the client walks the pagination.
            ->orderBy('sort_order')
            ->orderBy('id');

        return ['data' => $query->paginate($this->resolvePerPage($perPage)), 'code' => 200];
    }

    protected function query(): Builder
    {
        return JournalPost::query()
            ->with($this->with)
            ->orderByDesc('published_on')
            ->orderBy('sort_order');
    }
}
