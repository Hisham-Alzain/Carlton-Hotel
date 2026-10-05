<?php

namespace App\Http\Requests\Loyalty;

use App\Base\BaseRequest;
use App\Enums\LoyaltyRewardType;
use App\Models\LoyaltyReward;
use App\Support\TranslatableRules;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Validation\Rule;

class UpdateLoyaltyRewardRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            ...TranslatableRules::sometimes('name', ['string', 'max:150']),
            ...TranslatableRules::optional('description', ['string', 'max:1000']),
            'type' => ['sometimes', 'required', Rule::enum(LoyaltyRewardType::class)],
            'points_cost' => ['sometimes', 'required', 'integer', 'min:1', 'max:100000000'],
            'discount_usd' => ['sometimes', 'nullable', 'decimal:0,2', 'min:0.01', 'max:99999.99'],
            'voucher_valid_days' => ['sometimes', 'required', 'integer', 'min:1', 'max:730'],
            'is_active' => ['boolean'],
            'sort_order' => ['integer', 'min:0'],
        ];
    }

    /**
     * The type/discount pairing is judged on the values the reward will have
     * after this update: the sent field, else the stored one (Q12). So changing
     * only `type` cannot leave a discount on a free night, or a voucher without one.
     *
     * @return list<callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->has('type') || $validator->errors()->has('discount_usd')) {
                    return;
                }

                $reward = $this->route('reward');
                $stored = $reward instanceof LoyaltyReward ? $reward : null;

                $type = $this->has('type')
                    ? LoyaltyRewardType::tryFrom((string) $this->input('type'))
                    : $stored?->type;
                $discount = $this->exists('discount_usd')
                    ? $this->input('discount_usd')
                    : $stored?->discount_usd;

                if ($type === null) {
                    return;
                }

                if ($type !== LoyaltyRewardType::DISCOUNT_VOUCHER && $discount !== null) {
                    $validator->errors()->add('discount_usd', __('custom.validation.loyalty_discount_usd_voucher_only'));
                }

                if ($type === LoyaltyRewardType::DISCOUNT_VOUCHER && $discount === null) {
                    $validator->errors()->add('discount_usd', __('custom.validation.required', ['attribute' => 'discount_usd']));
                }
            },
        ];
    }
}
