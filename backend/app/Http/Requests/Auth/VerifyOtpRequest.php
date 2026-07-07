<?php
namespace App\Http\Requests\Auth;

use App\Base\BaseRequest;
use App\Support\NormalizesPhone;
use Illuminate\Validation\Validator;

class VerifyOtpRequest extends BaseRequest
{
    use NormalizesPhone;

    public function prepareForValidation(): void
    {
        if ($this->filled('phone')) {
            $normalized = $this->normalizePhone($this->input('phone'));
            if ($normalized) {
                $this->merge(['phone' => $normalized['e164'], 'phone_country' => $normalized['country']]);
            }
            // If normalization fails, leave phone as-is; the OTP lookup will not find a match
        }
        if ($this->filled('email')) {
            $this->merge(['email' => strtolower(trim($this->input('email')))]);
        }
    }

    public function rules(): array
    {
        return [
            'phone'   => 'nullable|string',
            'email'   => 'nullable|email',
            'code'    => 'required|string|size:6',
            'purpose' => 'required|in:login,register,booking_link',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            if (! $this->filled('phone') && ! $this->filled('email')) {
                $v->errors()->add('identity', __('custom.errors.identity_required'));
            }
        });
    }
}
