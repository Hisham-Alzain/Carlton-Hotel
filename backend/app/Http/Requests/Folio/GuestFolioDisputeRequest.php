<?php

namespace App\Http\Requests\Folio;

use App\Base\BaseRequest;
use App\Exceptions\NotFoundException;
use App\Models\FolioItem;

/**
 * PATCH /folio/items/{item}/dispute (D-10).
 *
 * Ownership is the item's folio reservation `guest_id` against the
 * authenticated guest, never the GuestEntitlement current-stay lookup (FA-06-1).
 * A foreign item answers exactly like an unknown uuid (404 `not_found`, Phase 4
 * precedent), and authorize() runs before validation, so a foreign item with
 * an invalid body is still a 404.
 */
class GuestFolioDisputeRequest extends BaseRequest
{
    public function authorize(): bool
    {
        $item  = $this->route('item');
        $guest = $this->user('guests');

        return $item instanceof FolioItem
            && $guest !== null
            && $item->folio?->reservation?->guest_id === $guest->id;
    }

    protected function failedAuthorization(): void
    {
        throw new NotFoundException();
    }

    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'max:500'],
        ];
    }
}
