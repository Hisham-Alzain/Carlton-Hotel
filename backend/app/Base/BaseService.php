<?php

namespace App\Base;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

abstract class BaseService
{
    protected string $model;

    /** Filter class instantiated from the query params handed in by the controller. */
    protected ?string $filter = null;

    protected array $with = [];

    /** Page size used when the client does not ask for one. */
    protected int $perPage = 15;

    /**
     * Ceiling on a client-supplied `per_page`. 100 covers the public website's
     * "give me the whole collection" request (`per_page: 100` in the site's
     * contentService) while keeping any single page — and its eager loads —
     * bounded. Larger values are clamped, not rejected.
     */
    protected int $maxPerPage = 100;

    /**
     * @param  array<string, mixed>  $params   Query-string params from the controller.
     * @param  BaseFilter|null       $filter   Explicit filter, overriding `$this->filter`.
     * @param  int|null              $perPage  Client-requested page size, or null for the default.
     */
    public function index(array $params = [], ?BaseFilter $filter = null, ?int $perPage = null): array
    {
        $query = $this->query();

        $filter ??= $this->makeFilter($params);
        $filter?->apply($query);

        return ['data' => $query->paginate($this->resolvePerPage($perPage)), 'code' => 200];
    }

    public function show(Model $model): array
    {
        $model->loadMissing($this->with);
        return ['data' => $model, 'code' => 200];
    }

    public function store(array $data): array
    {
        $model = DB::transaction(fn () => ($this->model)::create($data));
        $model->loadMissing($this->with);
        return ['data' => $model, 'code' => 201];
    }

    public function update(Model $model, array $data): array
    {
        DB::transaction(fn () => $model->update($data));
        $model->refresh()->loadMissing($this->with);
        return ['data' => $model, 'code' => 200];
    }

    public function destroy(Model $model): array
    {
        DB::transaction(fn () => $model->delete());
        return ['data' => null, 'code' => 204];
    }

    protected function query(): Builder
    {
        return ($this->model)::query()->with($this->with);
    }

    /**
     * @param  array<string, mixed>  $params
     */
    protected function makeFilter(array $params): ?BaseFilter
    {
        return $this->filter === null ? null : new ($this->filter)($params);
    }

    /**
     * Clamp the client's page size into `[1, $maxPerPage]`, falling back to the
     * service default when it is absent, non-numeric or non-positive.
     */
    protected function resolvePerPage(?int $perPage): int
    {
        if ($perPage === null || $perPage < 1) {
            return $this->perPage;
        }

        return min($perPage, $this->maxPerPage);
    }
}
