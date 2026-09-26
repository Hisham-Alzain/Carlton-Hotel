<?php

namespace App\Http\Requests\Booking;

use App\Base\BaseRequest;
use Illuminate\Validation\Rule;

class CheckOutReservationRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            // Overrides an open folio; only honoured for callers holding
            // folios.settle (the controller answers 403 otherwise, D-07).
            'force'  => ['sometimes', 'boolean'],
            'reason' => ['nullable', Rule::requiredIf(fn () => $this->boolean('force')), 'string', 'max:255'],
        ];
    }
}
