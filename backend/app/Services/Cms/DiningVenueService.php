<?php

namespace App\Services\Cms;

use App\Base\BaseService;
use App\Exceptions\NotFoundException;
use App\Filters\DiningVenueFilter;
use App\Models\DiningVenue;
use App\Models\Media;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

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

    /** The venue's menu file or null — one query (Phase 8, D-26). */
    public function menuFile(DiningVenue $venue): array
    {
        return ['data' => $venue->menuFile()->first(), 'code' => 200];
    }

    /**
     * Remove the venue's menu file (Phase 8, D-11). None → `not_found`. The file
     * is purged after commit unless another row still names it.
     */
    public function removeMenuFile(DiningVenue $venue): array
    {
        DB::transaction(function () use ($venue): void {
            DiningVenue::whereKey($venue->getKey())->lockForUpdate()->firstOrFail();

            $menus = Media::query()
                ->where('mediable_type', $venue->getMorphClass())
                ->where('mediable_id', $venue->getKey())
                ->where('collection', 'menu')
                ->get();

            if ($menus->isEmpty()) {
                throw new NotFoundException(__('custom.errors.not_found'));
            }

            // Historical duplicates must not reappear after removing the latest file.
            // Delete models individually so each file's after-commit purge runs.
            $menus->each->delete();
        });

        return ['data' => null, 'code' => 200];
    }

    protected function query(): Builder
    {
        return DiningVenue::query()->with($this->with)->orderBy('sort_order');
    }
}
