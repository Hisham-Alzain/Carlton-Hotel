<?php

namespace App\Http\Requests\Loyalty;

use App\Base\BaseRequest;
use App\Enums\LoyaltyRewardType;
use App\Support\TranslatableRules;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Validation\Rule;

class CreateLoyaltyRewardRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            ...TranslatableRules::for('name', ['string', 'max:150']),
            ...TranslatableRules::optional('description', ['string', 'max:1000']),
            'type' => ['required', Rule::enum(LoyaltyRewardType::class)],
            'points_cost' => ['required', 'integer', 'min:1', 'max:100000000'],
            'discount_usd' => ['nullable', 'required_if:type,'.LoyaltyRewardType::DISCOUNT_VOUCHER->value, 'decimal:0,2', 'min:0.01', 'max:99999.99'],
            'voucher_valid_days' => ['required', 'integer', 'min:1', 'max:730'],
            'is_active' => ['boolean'],
            'sort_order' => ['integer', 'min:0'],
        ];
    }

    /**
     * A discount amount belongs to a discount voucher only (Q12). Judged after
     * the field rules so a bad `type` reports itself instead of this.
     *
     * @return list<callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $type = LoyaltyRewardType::tryFrom((string) $this->input('type'));

                if ($type !== null
                    && $type !== LoyaltyRewardType::DISCOUNT_VOUCHER
                    && $this->input('discount_usd') !== null
                    && ! $validator->errors()->has('discount_usd')) {
                    $validator->errors()->add('discount_usd', __('custom.validation.loyalty_discount_usd_voucher_only'));
                }
            },
        ];
    }
}
