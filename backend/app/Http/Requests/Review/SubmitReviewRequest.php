<?php

namespace App\Http\Requests\Review;

use App\Base\BaseRequest;

class SubmitReviewRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'rating'  => ['required', 'integer', 'min:1', 'max:5'],
            'comment' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
