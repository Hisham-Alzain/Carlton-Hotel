<?php

namespace App\Base;

use Illuminate\Database\Eloquent\Builder;

class BaseFilter
{
    public function __construct(
        private readonly array $filters,
        private readonly array $allowed = []
    ) {}

    public function apply(Builder $query): Builder
    {
        foreach ($this->allowed as $field) {
            if (! isset($this->filters[$field]) || ! is_array($this->filters[$field])) {
                continue;
            }
            foreach ($this->filters[$field] as $op => $value) {
                match ($op) {
                    'eq'    => $query->where($field, '=', $value),
                    'like'  => $query->where($field, 'like', "%{$value}%"),
                    'gte'   => $query->where($field, '>=', $value),
                    'lte'   => $query->where($field, '<=', $value),
                    'in'    => $query->whereIn($field, is_array($value) ? $value : explode(',', $value)),
                    default => null,
                };
            }
        }
        return $query;
    }
}
