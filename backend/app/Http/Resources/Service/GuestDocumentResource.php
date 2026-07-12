<?php

namespace App\Http\Resources\Service;

use App\Base\BaseResource;
use Illuminate\Http\Request;

class GuestDocumentResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'type' => $this->type,
        ];
    }
}
