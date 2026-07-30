<?php

namespace App\Http\Requests\Service;

use App\Base\BaseRequest;
use App\Enums\Department;
use App\Enums\ServiceCategoryKind;
use App\Support\TranslatableRules;
use Illuminate\Validation\Rule;

class StoreServiceCategoryRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'code'           => ['required', 'string', 'max:50', Rule::unique('service_categories', 'code')],
            ...TranslatableRules::for('name', ['string', 'max:255']),
            ...TranslatableRules::optional('description', ['string']),
            'kind'           => ['required', Rule::enum(ServiceCategoryKind::class)],
            // Only requestable kinds route to a department.
            'department'     => ['nullable', 'required_if:kind,catalog,direct', Rule::enum(Department::class)],
            'link_target'    => ['nullable', 'required_if:kind,link', 'string', 'max:30'],
            'icon'           => ['nullable', 'string', 'max:50'],
            'is_active'      => ['boolean'],
            'sort_order'     => ['integer', 'min:0'],
        ];
    }
}
