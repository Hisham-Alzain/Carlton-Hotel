<?php
namespace App\Http\Requests\Auth;

use App\Base\BaseRequest;
use App\Support\NormalizesPhone;
use Illuminate\Validation\Validator;

class RequestOtpRequest extends BaseRequest
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
            // If normalization fails, leave phone as-is; withValidator will catch coherence
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
            'channel' => 'required|in:sms,whatsapp,email',
            'purpose' => 'required|in:login,register',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            if (! $this->filled('phone') && ! $this->filled('email')) {
                $v->errors()->add('identity', __('custom.errors.identity_required'));
            }
            // channel ↔ identifier coherence
            $channel = $this->input('channel');
            if (in_array($channel, ['sms', 'whatsapp']) && ! $this->filled('phone')) {
                $v->errors()->add('phone', __('custom.errors.identity_required'));
            }
            if ($channel === 'email' && ! $this->filled('email')) {
                $v->errors()->add('email', __('custom.errors.identity_required'));
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
