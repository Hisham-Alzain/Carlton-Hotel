<?php

namespace App\Http\Resources\Cms;

use App\Base\BaseResource;
use Illuminate\Http\Request;

class MediaResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid'       => $this->uuid,
            'url'        => $this->url,
            'file_name'  => $this->file_name,
            'mime_type'  => $this->mime_type,
            'size'       => $this->size,
            'sort_order' => $this->sort_order,
        ];
    }
}
