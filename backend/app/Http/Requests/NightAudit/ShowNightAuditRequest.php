<?php

namespace App\Http\Requests\NightAudit;

use App\Base\BaseRequest;

/**
 * Phase 9 (D-01, D-03): optional business date. `date_format:Y-m-d` already
 * round-trips the value (`2026-02-30` fails), so only real dates pass.
 * Access is gated by route middleware (`reports.view|night_audit.manage`).
 */
class ShowNightAuditRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'date' => ['sometimes', 'date_format:Y-m-d'],
        ];
    }
}
