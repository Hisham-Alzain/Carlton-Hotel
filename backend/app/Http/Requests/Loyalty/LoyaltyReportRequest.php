<?php

namespace App\Http\Requests\Loyalty;

use App\Base\BaseRequest;
use Carbon\CarbonImmutable;
use Illuminate\Validation\Validator;

/**
 * Phase 10 (LOY-19, Q19): `date_from` / `date_to` both present or both absent,
 * strict real `Y-m-d`, `date_to >= date_from`, at most 366 days inclusive.
 * Absent means the hotel's today (the service). The pattern follows Phase 9's
 * period validation; no Phase 9 class is reused.
 */
class LoyaltyReportRequest extends BaseRequest
{
    public const MAX_DAYS = 366;

    public function rules(): array
    {
        return [
            'date_from' => ['bail', 'nullable', 'required_with:date_to', 'string', 'date_format:Y-m-d'],
            'date_to' => ['bail', 'nullable', 'required_with:date_from', 'string', 'date_format:Y-m-d'],
        ];
    }

    /**
     * Order and length are checked once both dates are valid strings:
     * `after_or_equal` would throw a TypeError on an array `date_from`.
     *
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $from = $this->input('date_from');
                $to = $this->input('date_to');
                if (! is_string($from) || ! is_string($to)) {
                    return;
                }

                if ($to < $from) {
                    $validator->errors()->add('date_to', __('custom.validation.after_or_equal', ['attribute' => 'date_to', 'date' => 'date_from']));

                    return;
                }

                // Calendar days on the local dates, so a DST day still counts as one.
                $days = (int) CarbonImmutable::parse($from)->diffInDays(CarbonImmutable::parse($to)) + 1;
                if ($days > self::MAX_DAYS) {
                    $validator->errors()->add('date_to', __('custom.validation.loyalty_report_period_too_long'));
                }
            },
        ];
    }
}
