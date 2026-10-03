<?php

namespace App\Http\Requests\Dining;

use App\Base\BaseRequest;

/**
 * POST /cms/dining-venues/{diningVenue}/menu-file (Phase 8, D-11, D-27). PDF or
 * image, 10 MB. UploadMediaRequest stays image-only on purpose.
 */
class UploadMenuFileRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'file'  => ['required', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:10240'],
            'title' => ['nullable', 'string', 'max:255'],
        ];
    }
}
