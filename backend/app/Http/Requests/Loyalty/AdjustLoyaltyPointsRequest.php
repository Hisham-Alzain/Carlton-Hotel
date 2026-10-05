<?php

namespace App\Http\Requests\Loyalty;

use App\Base\BaseRequest;
use App\Http\Requests\Concerns\ReadsIdempotencyKey;

/**
 * POST /cms/loyalty/guests/{guest}/adjustments (LOY-05, Q20). `points` is a
 * signed integer; zero and the magnitude cap are domain errors raised by
 * AdjustLoyaltyPointsAction (loyalty_adjustment_invalid), not field rules, so
 * that error code is reachable and non-HTTP callers are guarded too. The
 * `Idempotency-Key` header is REQUIRED (merged in by ReadsIdempotencyKey).
 * Access is gated by route middleware (permission:loyalty.adjust).
 */
class AdjustLoyaltyPointsRequest extends BaseRequest
{
    use ReadsIdempotencyKey;

    public function rules(): array
    {
        return [
            'points' => ['required', 'integer'],
            'reason' => ['required', 'string', 'min:3', 'max:500'],
            'idempotency_key' => ['required', 'string', 'max:64'],
        ];
    }

    public function messages(): array
    {
        return array_merge(parent::messages(), $this->idempotencyKeyMessages());
    }
}
