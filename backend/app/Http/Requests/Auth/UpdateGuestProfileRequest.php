<?php

namespace App\Http\Requests\Auth;

use App\Base\BaseRequest;
use App\Support\NormalizesPhone;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateGuestProfileRequest extends BaseRequest
{
    use NormalizesPhone;

    public function prepareForValidation(): void
    {
        if ($this->filled('phone')) {
            $normalized = $this->normalizePhone($this->input('phone'));
            if ($normalized) {
                $this->merge([
                    'phone'         => $normalized['e164'],
                    'phone_country' => $normalized['country'],
                ]);
            }
        }
        if ($this->filled('email')) {
            $this->merge(['email' => strtolower(trim($this->input('email')))]);
        }
    }

    public function rules(): array
    {
        $guestId = $this->user('guests')?->id;

        return [
            'first_name'       => ['sometimes', 'required', 'string', 'max:255'],
            'last_name'        => ['sometimes', 'required', 'string', 'max:255'],
            'phone'            => ['sometimes', 'nullable', 'string', Rule::unique('guests', 'phone')->ignore($guestId)],
            'email'            => ['sometimes', 'nullable', 'email', Rule::unique('guests', 'email')->ignore($guestId)],
            'preferred_locale' => ['sometimes', 'in:en,ar'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            // A phone that survived prepareForValidation unchanged failed E.164
            // normalization — reject it rather than storing an unroutable number.
            if ($this->filled('phone') && ! str_starts_with($this->input('phone'), '+')) {
                $v->errors()->add('phone', __('custom.validation.phone_invalid'));
            }
        });
    }

    public function messages(): array
    {
        return array_merge(parent::messages(), [
            'phone.string' => __('custom.validation.phone_invalid'),
        ]);
    }
}
