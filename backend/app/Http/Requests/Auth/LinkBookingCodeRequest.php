<?php
namespace App\Http\Requests\Auth;

use App\Base\BaseRequest;
use App\Support\NormalizesPhone;
use Illuminate\Validation\Validator;

class LinkBookingCodeRequest extends BaseRequest
{
    use NormalizesPhone;

    public function prepareForValidation(): void
    {
        if ($this->filled('booking_code')) {
            $this->merge(['booking_code' => strtoupper(trim($this->input('booking_code')))]);
        }
        if ($this->filled('phone')) {
            $normalized = $this->normalizePhone($this->input('phone'));
            $this->merge(['phone' => $normalized ? $normalized['e164'] : null]);
        }
    }

    public function rules(): array
    {
        return [
            'booking_code' => ['required', 'string', 'regex:/^CARL-[0-9A-HJ-NP-TV-Z]{8}$/'],
            'last_name'    => 'nullable|string',
            'phone'        => 'nullable|string',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            if (! $this->filled('last_name') && ! $this->filled('phone')) {
                $v->errors()->add('booking_code', __('custom.errors.booking_link_failed'));
            }
        });
    }
}
