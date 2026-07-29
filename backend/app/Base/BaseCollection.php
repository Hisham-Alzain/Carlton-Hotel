<?php

namespace App\Base;

use Illuminate\Http\Resources\Json\ResourceCollection;

class BaseCollection extends ResourceCollection
{
    /**
     * Never let Laravel *guess* the resource class.
     *
     * `ResourceCollection::collects()` falls back to
     * `Str::replaceLast('Collection', 'Resource', static::class)`, which for
     * this class resolves to `App\Base\BaseResource` — an abstract class. Every
     * paginator handed here that was not already mapped into resources died
     * with "Cannot instantiate abstract class App\Base\BaseResource". Returning
     * `$this->collects` verbatim means an unset value passes the models through
     * untouched, and a caller that wants resources says so explicitly.
     */
    protected function collects(): ?string
    {
        return $this->collects;
    }

    public function toArray($request): array
    {
        return [
            'items' => $this->collection,
            'meta'  => [
                'current_page' => $this->resource->currentPage(),
                'per_page'     => $this->resource->perPage(),
                'total'        => $this->resource->total(),
                'last_page'    => $this->resource->lastPage(),
            ],
        ];
    }
}
