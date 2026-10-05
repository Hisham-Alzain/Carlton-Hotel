<?php

namespace App\Http\Requests\Loyalty;

use App\Base\BaseRequest;
use App\Http\Requests\Concerns\ReadsIdempotencyKey;

/**
 * POST /loyalty/rewards/{reward}/redeem (LOY-13). The body is empty: the reward
 * is the route-bound model and the `Idempotency-Key` header is REQUIRED (merged
 * in by ReadsIdempotencyKey). The caller is always the token's own guest.
 */
class RedeemLoyaltyRewardRequest extends BaseRequest
{
    use ReadsIdempotencyKey;

    public function rules(): array
    {
        return [
            'idempotency_key' => ['required', 'string', 'max:64'],
        ];
    }

    public function messages(): array
    {
        return array_merge(parent::messages(), $this->idempotencyKeyMessages());
    }
}
