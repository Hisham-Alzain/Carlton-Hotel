<?php
namespace App\Http\Requests\Staff;

use App\Base\BaseRequest;
use Illuminate\Support\Facades\Gate;

class CreateStaffRequest extends BaseRequest
{
    public function authorize(): bool
    {
        // Authorization checked here so it fires before validation,
        // ensuring 403 is returned before 422 on missing fields.
        return Gate::allows('create', \App\Models\User::class);
    }

    public function rules(): array
    {
        return [
            'name'     => 'required|string|max:255',
            'email'    => 'required|email|max:255|unique:users,email',
            'password' => 'required|string|min:8',
            'role'     => 'required|string|exists:roles,name',
        ];
    }
}
