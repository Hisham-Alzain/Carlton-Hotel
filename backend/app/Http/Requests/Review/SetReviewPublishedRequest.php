<?php

namespace App\Http\Requests\Review;

use App\Base\BaseRequest;

class SetReviewPublishedRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'is_published' => ['required', 'boolean'],
        ];
    }
}
