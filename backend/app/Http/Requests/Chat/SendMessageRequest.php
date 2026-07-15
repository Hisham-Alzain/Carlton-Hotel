<?php

namespace App\Http\Requests\Chat;

use App\Base\BaseRequest;
use Illuminate\Validation\Validator;

class SendMessageRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'body'            => ['nullable', 'string', 'max:2000'],
            'attachment'      => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if (! $this->filled('body') && ! $this->hasFile('attachment')) {
                $validator->errors()->add('body', __('custom.validation.required', ['attribute' => 'body']));
            }
        });
    }
}
