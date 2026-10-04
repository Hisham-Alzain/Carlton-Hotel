<?php

namespace App\Http\Requests\Currency;

use App\Base\BaseRequest;
use App\Support\CurrencyConfig;
use Illuminate\Validation\Rule;

/**
 * POST /api/cms/exchange-rates (Phase 9.1, D-15). `rate` = units of the
 * currency per 1 USD: up to 14 integer and 6 fractional digits (DECIMAL 20,6),
 * strictly positive. Validated as a string so no float ever touches it.
 */
class StoreExchangeRateRequest extends BaseRequest
{
    /** 1..14 integer digits, optional 1..6 decimals, and not zero. */
    private const RATE_PATTERN = '/^(?!0+(?:\.0+)?$)\d{1,14}(?:\.\d{1,6})?$/';

    public function prepareForValidation(): void
    {
        if (is_string($this->input('currency'))) {
            $this->merge(['currency' => strtoupper(trim($this->input('currency')))]);
        }

        // A JSON number arrives as int/float; validate its canonical string.
        $rate = $this->input('rate');
        if (is_int($rate) || is_float($rate)) {
            $this->merge(['rate' => (string) $rate]);
        }
    }

    public function rules(): array
    {
        return [
            // CurrencyConfig never lists the base, so USD is rejected here too.
            'currency' => ['required', 'string', Rule::in(CurrencyConfig::codes())],
            'rate' => ['required', 'string', 'regex:'.self::RATE_PATTERN],
            'note' => ['nullable', 'string', 'max:255'],
            'confirm_large_change' => ['sometimes', 'boolean'],
        ];
    }

    public function attributes(): array
    {
        return [
            'currency' => __('custom.attributes.currency'),
            'rate' => __('custom.attributes.rate'),
            'confirm_large_change' => __('custom.attributes.confirm_large_change'),
        ];
    }
}
