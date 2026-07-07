<?php

namespace App\Base;

use Illuminate\Foundation\Http\FormRequest;

abstract class BaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function messages(): array
    {
        return [
            'required' => __('custom.validation.required', ['attribute' => ':attribute']),
            'string'   => __('custom.validation.string',   ['attribute' => ':attribute']),
            'email'    => __('custom.validation.email',    ['attribute' => ':attribute']),
            'max'      => __('custom.validation.max',      ['attribute' => ':attribute', 'max' => ':max']),
            'min'      => __('custom.validation.min',      ['attribute' => ':attribute', 'min' => ':min']),
            'unique'   => __('custom.validation.unique',   ['attribute' => ':attribute']),
            'exists'   => __('custom.validation.exists',   ['attribute' => ':attribute']),
            'distinct' => __('custom.validation.distinct', ['attribute' => ':attribute']),
        ];
    }
}
