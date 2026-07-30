<?php

namespace App\Http\Requests\Cms;

use App\Base\BaseRequest;
use App\Enums\BedType;
use App\Enums\RoomView;
use App\Models\RoomType;
use App\Support\TranslatableRules;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Validation\Rule;

class UpdateRoomTypeRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            ...TranslatableRules::sometimes('name', ['string', 'max:255']),
            ...TranslatableRules::sometimes('description', ['string']),
            // Omit the key to leave the existing pivot untouched; send [] to clear.
            'amenities'              => ['nullable', 'array'],
            'amenities.*.uuid'       => ['required', 'string', 'exists:amenities,uuid'],
            'amenities.*.is_highlight' => ['boolean'],
            'amenities.*.sort_order' => ['integer', 'min:0'],
            'view_type'              => ['nullable', Rule::enum(RoomView::class)],
            'bed_types'              => ['nullable', 'array'],
            'bed_types.*'            => [Rule::enum(BedType::class)],
            // `gte:base_occupancy` is NOT usable here — see withValidator().
            'base_occupancy'         => ['sometimes', 'integer', 'min:1', 'max:20'],
            'max_occupancy'          => ['sometimes', 'integer', 'min:1', 'max:20'],
            'size_sqm'               => ['nullable', 'numeric', 'min:1'],
            'base_price_usd'         => ['sometimes', 'numeric', 'min:0'],
            'cancellation_hours'     => ['integer', 'min:0', 'max:8760'],
            'is_active'              => ['boolean'],
            'sort_order'             => ['integer', 'min:0'],
        ];
    }

    /**
     * `max_occupancy >= base_occupancy`, compared against the row as it will be
     * AFTER the update rather than against the payload alone.
     *
     * `CreateRoomTypeRequest` states this as `gte:base_occupancy`, and copying
     * that rule here would enforce it only when both fields happen to be in the
     * same request. Laravel's `gte` resolves its other operand from the request
     * data: on `PUT {"max_occupancy": 1}` against a row whose `base_occupancy`
     * is 4, `base_occupancy` is absent, the comparison has nothing to compare
     * against and the rule passes. The invariant the create request enforces is
     * then silently gone on the one verb that can still break it — a room type
     * that sleeps at most one but is booked for four.
     *
     * So each side falls back to the persisted value when the payload does not
     * carry it, and the check runs whenever at least one of the two is sent. The
     * error is attached to `max_occupancy` in both directions: whichever field
     * moved, it is the pair that is wrong, and `max_occupancy` is the one the
     * create request already reports.
     *
     * Reported through `custom.validation.gte` so the message is the same
     * sentence, in the caller's language, that a create rejection produces.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->hasAny(['base_occupancy', 'max_occupancy'])) {
                return; // one of them is not an integer yet; comparing is noise
            }

            $sentBase = $this->has('base_occupancy');
            $sentMax  = $this->has('max_occupancy');

            if (! $sentBase && ! $sentMax) {
                return; // the stored pair is already valid; nothing moved
            }

            $roomType = $this->route('roomType');

            $base = $sentBase
                ? $this->integer('base_occupancy')
                : ($roomType instanceof RoomType ? (int) $roomType->base_occupancy : null);

            $max = $sentMax
                ? $this->integer('max_occupancy')
                : ($roomType instanceof RoomType ? (int) $roomType->max_occupancy : null);

            if ($base === null || $max === null) {
                return; // no bound model (not a real route) — nothing to compare
            }

            if ($max < $base) {
                $validator->errors()->add(
                    'max_occupancy',
                    __('custom.validation.gte', ['attribute' => 'max_occupancy', 'value' => $base]),
                );
            }
        });
    }
}
