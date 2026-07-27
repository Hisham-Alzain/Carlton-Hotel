<?php

namespace App\Http\Resources\Service;

use App\Base\BaseResource;
use Illuminate\Http\Request;

class ServiceCategoryResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid'        => $this->uuid,
            'code'        => $this->code,
            // Tells the client which of the four behaviors to render:
            // catalog → item picker, direct → request default_item_uuid straight
            // away, link → navigate to link_target, toggle → render a switch.
            'kind'        => $this->kind,
            'name'        => $this->getTranslations('name'),
            'description' => $this->getTranslations('description'),
            'icon'        => $this->icon,
            'link_target' => $this->link_target,
            'department'  => $this->department,
            'sort_order'  => $this->sort_order,
            'is_active'   => $this->is_active,
            'default_item_uuid' => $this->whenLoaded('defaultItem', fn () => $this->defaultItem?->uuid),
            'items'       => ServiceItemResource::collection($this->whenLoaded('visibleItems')),
        ];
    }
}
