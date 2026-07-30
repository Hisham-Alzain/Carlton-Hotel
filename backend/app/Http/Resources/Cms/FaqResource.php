<?php

namespace App\Http\Resources\Cms;

use App\Base\BaseResource;
use Illuminate\Http\Request;

class FaqResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid'       => $this->uuid,
            'category'   => $this->category,
            // Whole locale maps, not the negotiated locale: the website
            // switches language client-side off a single fetch.
            'question'   => $this->getTranslations('question'),
            'answer'     => $this->getTranslations('answer'),
            'is_active'  => $this->is_active,
            'sort_order' => $this->sort_order,
        ];
    }
}
