<?php

namespace App\Http\Resources\Loyalty;

use App\Base\BaseResource;
use Illuminate\Http\Request;

class LoyaltyRewardResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        $description = $this->getTranslations('description');

        return [
            'uuid' => $this->uuid,
            // Whole locale maps, not the negotiated locale: clients switch language off one fetch.
            'name' => $this->getTranslations('name'),
            'description' => $description === [] ? null : $description,
            'type' => $this->type->value,
            'type_label' => $this->type->label(),
            'points_cost' => $this->points_cost,
            'discount_usd' => $this->discount_usd,
            'voucher_valid_days' => $this->voucher_valid_days,
            'is_active' => $this->is_active,
            'sort_order' => $this->sort_order,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            'deleted_at' => $this->deleted_at?->toISOString(),
        ];
    }
}
