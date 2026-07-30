<?php

namespace App\Http\Requests\Service;

use App\Base\BaseRequest;
use App\Enums\Department;
use App\Enums\ServiceCategoryKind;
use App\Support\TranslatableRules;
use Illuminate\Validation\Rule;

/**
 * A partial update of a guest-service category.
 *
 * `update()` used to reuse the create request, which is where the
 * `Rule::unique(...)->ignore($this->route('serviceCategory'))` in that class came
 * from — on create there is no route model, so the ignore was dead code there and
 * load-bearing only on this verb. It lives here now, where it means something:
 * without it a category cannot be saved without changing its own `code`.
 *
 * `required_if:kind,catalog,direct` is kept and still keyed off `kind`. On a
 * partial update it fires only when the payload actually sets `kind` — which is
 * the correct reading: a request that does not touch `kind` cannot make the row's
 * existing kind newly department-less, because it does not clear `department`
 * either (`sometimes`). Sending `kind` without the `department` it now needs is
 * still a 422.
 */
class UpdateServiceCategoryRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'code'        => [
                'sometimes',
                'string',
                'max:50',
                Rule::unique('service_categories', 'code')->ignore($this->route('serviceCategory')?->id),
            ],
            ...TranslatableRules::sometimes('name', ['string', 'max:255']),
            ...TranslatableRules::optional('description', ['string']),
            'kind'        => ['sometimes', Rule::enum(ServiceCategoryKind::class)],
            'department'  => ['sometimes', 'nullable', 'required_if:kind,catalog,direct', Rule::enum(Department::class)],
            'link_target' => ['sometimes', 'nullable', 'required_if:kind,link', 'string', 'max:30'],
            'icon'        => ['sometimes', 'nullable', 'string', 'max:50'],
            'is_active'   => ['sometimes', 'boolean'],
            'sort_order'  => ['sometimes', 'integer', 'min:0'],
        ];
    }
}
