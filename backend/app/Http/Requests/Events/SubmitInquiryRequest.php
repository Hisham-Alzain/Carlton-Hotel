<?php

namespace App\Http\Requests\Events;

use App\Base\BaseRequest;
use App\Support\NormalizesPhone;

class SubmitInquiryRequest extends BaseRequest
{
    use NormalizesPhone;

    public function prepareForValidation(): void
    {
        if ($this->filled('phone')) {
            $normalized = $this->normalizePhone($this->input('phone'));
            $this->merge(['phone' => $normalized ? $normalized['e164'] : null]);
        }
    }

    public function rules(): array
    {
        return [
            'name'                   => ['required', 'string', 'max:255'],
            'email'                  => ['required', 'email', 'max:255'],
            'phone'                  => ['nullable', 'string'],
            'company'                => ['nullable', 'string', 'max:255'],
            'event_type'             => ['required', 'string', 'in:wedding,corporate,conference,gala,birthday,product_launch,other'],
            'event_date'             => ['nullable', 'date', 'after:today'],
            'expected_guests'        => ['nullable', 'integer', 'min:1'],
            'budget_usd'             => ['nullable', 'numeric', 'min:0'],
            'notes'                  => ['nullable', 'string', 'max:5000'],
            'requirements'           => ['nullable', 'array'],
            'requirements.*.type'    => ['required', 'string', 'max:255'],
            'requirements.*.notes'   => ['nullable', 'string', 'max:1000'],
        ];
    }
}
