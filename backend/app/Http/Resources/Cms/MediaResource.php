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
            // Whole locale map, like every other translatable field: the website
            // switches language client-side off a single fetch, and `alt` has to
            // switch with it. `[]` when the editor has not written one yet.
            'alt_text'   => $this->getTranslations('alt_text'),
            'title'      => $this->title,
            'mime_type'  => $this->mime_type,
            'size'       => $this->size,
            'sort_order' => $this->sort_order,
        ];
    }
}
