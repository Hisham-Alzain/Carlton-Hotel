<?php

namespace App\Base;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

abstract class BaseService
{
    protected string $model;
    protected array $with = [];
    protected int $perPage = 15;

    public function index(array $filters = [], ?BaseFilter $filter = null): array
    {
        $query = $this->query();
        if ($filter) {
            $filter->apply($query);
        }
        return ['data' => $query->paginate($this->perPage), 'code' => 200];
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
}
