<?php
namespace App\Http\Requests\Staff;

use App\Base\BaseRequest;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Validator;

class AssignPermissionsRequest extends BaseRequest
{
    public function authorize(): bool
    {
        // Authorization checked here so it fires before validation,
        // ensuring 403 is returned before 422 on empty/invalid body.
        $user = $this->route('user');
        return Gate::allows('assignPermissions', $user instanceof User ? $user : User::class);
    }

    public function rules(): array
    {
        return [
            'grant'   => 'sometimes|array',
            'grant.*' => 'string|distinct|exists:permissions,name',
            'revoke'   => 'sometimes|array',
            'revoke.*' => 'string|distinct|exists:permissions,name',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            $grant  = $this->input('grant', []);
            $revoke = $this->input('revoke', []);

            // At least one must be present
            if (empty($grant) && empty($revoke)) {
                $v->errors()->add('grant', __('custom.validation.required'));
                return;
            }

            // No overlap
            $conflict = array_intersect((array) $grant, (array) $revoke);
            if (! empty($conflict)) {
                $v->errors()->add('grant', __('custom.validation.grant_revoke_conflict'));
            }
        });
    }
}
