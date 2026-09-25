<?php

namespace App\Http\Requests\Auth;

use App\Base\BaseRequest;
use Illuminate\Validation\Rule;

class UpdateStaffProfileRequest extends BaseRequest
{
    public function rules(): array
    {
        // Null-safe on purpose: the localization scan builds every FormRequest
        // with no authenticated user and calls rules().
        $user = $this->user('users');

        // Strict comparison, no lower-casing (mirrors UpdateStaffRequest): a
        // case-only change is still a change and needs the current password.
        $emailChanging = $this->filled('email') && $this->input('email') !== $user?->email;

        return [
            'name'             => ['required_without:email', 'string', 'max:255'],
            'email'            => ['required_without:name', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user?->id)],
            'current_password' => [$emailChanging ? 'required' : 'sometimes', 'string', 'current_password:users'],
        ];
    }
}
