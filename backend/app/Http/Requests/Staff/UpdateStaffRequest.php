<?php
namespace App\Http\Requests\Staff;

use App\Base\BaseRequest;
use Illuminate\Validation\Rule;

class UpdateStaffRequest extends BaseRequest
{
    public function rules(): array
    {
        $userId = $this->route('user')?->id;

        return [
            'name'  => 'sometimes|string|max:255',
            'email' => [
                'sometimes', 'email', 'max:255',
                Rule::unique('users', 'email')->ignore($userId),
            ],
            // type, password, is_active explicitly excluded
        ];
    }
}
