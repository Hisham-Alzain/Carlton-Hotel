<?php

namespace App\Http\Requests\Reports;

use App\Base\BaseRequest;
use Carbon\CarbonImmutable;
use Illuminate\Validation\Validator;

/**
 * Phase 9 (D-16): `date_from` / `date_to` both present or both absent,
 * strict real `Y-m-d` (date_format round-trips), `date_to >= date_from`,
 * at most 31 days inclusive. Absent → the hotel's today (controller).
 */
class ReportDashboardRequest extends BaseRequest
{
    public const MAX_DAYS = 31;

    public function rules(): array
    {
        return [
            'date_from' => ['bail', 'required_with:date_to', 'string', 'date_format:Y-m-d'],
            'date_to'   => ['bail', 'required_with:date_from', 'string', 'date_format:Y-m-d'],
        ];
    }

    /**
     * Order and length are checked here, once both dates are valid: Laravel's
     * `after_or_equal:date_from` throws a TypeError when `date_from` is an array.
     */

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $from = $this->input('date_from');
                $to   = $this->input('date_to');
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
                    $validator->errors()->add('date_to', __('custom.validation.report_period_too_long'));
                }
            },
        ];
    }
}
