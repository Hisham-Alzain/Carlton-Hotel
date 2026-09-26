<?php

namespace App\Http\Requests\Guest;

use App\Base\BaseRequest;
use App\Enums\BedType;
use App\Enums\FloorPreference;
use App\Enums\PillowType;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Shared by `PATCH /auth/guest/preferences` and `PATCH /guests/{guest}/preferences`
 * (D-09). PATCH semantics: a present key is written, an explicit null clears it,
 * an absent key is left alone. Empty strings arrive as null through the global
 * ConvertEmptyStringsToNull middleware.
 */
class UpdateGuestPreferencesRequest extends BaseRequest
{
    private const KEYS = ['bed_type', 'pillow_type', 'floor_preference', 'other'];

    public function rules(): array
    {
        return [
            // EXTRA is an inventory-only bed, never a guest preference (D-08).
            'bed_type'         => ['sometimes', 'nullable', Rule::enum(BedType::class)->except([BedType::EXTRA])],
            'pillow_type'      => ['sometimes', 'nullable', Rule::enum(PillowType::class)],
            'floor_preference' => ['sometimes', 'nullable', Rule::enum(FloorPreference::class)],
            'other'            => ['sometimes', 'nullable', 'string', 'max:500'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            // A body carrying none of the four keys would only bump the
            // timestamp — almost certainly a client bug, so it is refused.
            if (! $this->hasAny(self::KEYS)) {
                $v->errors()->add('preferences', __('custom.validation.required', ['attribute' => 'preferences']));
            }
        });
    }
}
