<?php

namespace App\Actions\Auth;

use App\Exceptions\VerifiedContactImmutableException;
use App\Models\Guest;
use Illuminate\Support\Facades\DB;

/**
 * Completes or edits a guest's profile after OTP sign-in.
 *
 * Names are freely editable. A phone or email may be *filled in* when the guest
 * does not have one yet — that is the "create profile" step after signing in
 * with the other channel. Replacing a contact the guest already verified is
 * refused here: it would move the login identifier without proving ownership of
 * the new one, so it has to go back through the OTP flow.
 */
class UpdateGuestProfileAction
{
    public function handle(Guest $guest, array $data): array
    {
        foreach (['phone' => 'phone_verified_at', 'email' => 'email_verified_at'] as $field => $verifiedAt) {
            if (! array_key_exists($field, $data) || $data[$field] === null) {
                continue;
            }
            if ($data[$field] === $guest->{$field}) {
                continue;
            }
            if ($guest->{$verifiedAt} !== null) {
                throw new VerifiedContactImmutableException(
                    __('custom.errors.verified_contact_immutable'),
                    ['field' => $field],
                );
            }
        }

        DB::transaction(function () use ($guest, $data) {
            $guest->fill(array_filter([
                'first_name'       => $data['first_name']       ?? null,
                'last_name'        => $data['last_name']        ?? null,
                'phone'            => $data['phone']            ?? null,
                'phone_country'    => $data['phone_country']    ?? null,
                'email'            => $data['email']            ?? null,
                'preferred_locale' => $data['preferred_locale'] ?? null,
            ], fn ($value) => $value !== null));

            // `name` is the legacy single-field display name other modules read.
            if ($guest->isDirty(['first_name', 'last_name'])) {
                $guest->name = trim("{$guest->first_name} {$guest->last_name}") ?: $guest->name;
            }

            $guest->save();
        });

        $guest->refresh()->load('activeReservations');

        return ['data' => $guest, 'code' => 200];
    }
}
