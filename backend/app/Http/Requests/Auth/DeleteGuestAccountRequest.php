<?php

namespace App\Http\Requests\Auth;

use App\Base\BaseRequest;

/**
 * DELETE /auth/guest/me (Phase 9.1, D-06). `confirm: true` guards against an
 * accidental or replayed bare DELETE; the identity comes only from the token.
 */
class DeleteGuestAccountRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'confirm' => ['required', 'accepted'],
        ];
    }

    public function attributes(): array
    {
        return ['confirm' => __('custom.attributes.confirm')];
    }
}
