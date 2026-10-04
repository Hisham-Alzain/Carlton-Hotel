<?php

namespace App\Http\Requests\NightAudit;

use App\Base\BaseRequest;

/**
 * Phase 9 (D-01, D-12): `resolved` (fixed in the source) or `overridden`
 * (exception accepted); a trimmed note is mandatory for both. Only these two
 * keys are read — client actor/snapshot fields are ignored.
 */
class ResolveNightAuditCheckRequest extends BaseRequest
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
            'status' => ['required', 'string', 'in:resolved,overridden'],
            'note'   => ['required', 'string', 'max:1000'],
        ];
    }
}
