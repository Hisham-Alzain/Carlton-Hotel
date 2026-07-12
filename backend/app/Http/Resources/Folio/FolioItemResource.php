<?php

namespace App\Http\Resources\Folio;

use App\Base\BaseResource;
use Illuminate\Http\Request;

class FolioItemResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid'        => $this->uuid,
            'description' => $this->description,
            'amount_usd'  => $this->amount_usd,
            'source_type' => $this->source_type,
        ];
    }
}
