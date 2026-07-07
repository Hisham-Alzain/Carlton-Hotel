<?php

namespace App\Base;

use Illuminate\Http\Resources\Json\JsonResource;

abstract class BaseResource extends JsonResource
{
    // Subclasses implement toArray(). Never query inside.
    protected function uuid(): ?string
    {
        return $this->resource->uuid ?? null;
    }
}
