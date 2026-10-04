<?php

namespace App\Http\Requests\NightAudit;

use App\Base\BaseRequest;

/**
 * Phase 9 (D-01, D-06, D-12): a trimmed note is mandatory; `status` is
 * optional and, if sent, must be `resolved` — blockers have no override.
 */
class ResolveNightAuditBlockerRequest extends BaseRequest
{
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('note'))) {
            $this->merge(['note' => trim($this->input('note'))]);
        }
    }

    public function rules(): array
    {
        return [
            'status' => ['sometimes', 'string', 'in:resolved'],
            'note'   => ['required', 'string', 'max:1000'],
        ];
    }
}
