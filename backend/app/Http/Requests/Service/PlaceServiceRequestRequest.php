<?php

namespace App\Http\Requests\Service;

use App\Base\BaseRequest;
use App\Enums\ServiceRequestPriority;
use Illuminate\Validation\Rule;

class PlaceServiceRequestRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            // The catalog path: "the id of the service" the guest tapped.
            'service_item_uuid' => [
                'nullable',
                'string',
                Rule::exists('service_items', 'uuid')->where('is_active', true),
            ],
            // Legacy free-string path, kept so existing app builds keep working.
            'type'     => ['required_without:service_item_uuid', 'string', 'max:255'],
            'priority' => ['nullable', Rule::enum(ServiceRequestPriority::class)],
            // "Optional special instructions".
            'notes'    => ['nullable', 'string', 'max:1000'],
        ];
    }
}
