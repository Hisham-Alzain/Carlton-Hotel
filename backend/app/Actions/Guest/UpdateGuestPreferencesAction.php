<?php

namespace App\Actions\Guest;

use App\Models\Guest;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Merge-write a guest's preferences (D-09): only keys present in `$data` are
 * written, an explicit null clears, and `preferences_updated_at` moves on
 * every successful call.
 */
class UpdateGuestPreferencesAction
{
    private const FIELDS = [
        'bed_type'         => 'bed_type',
        'pillow_type'      => 'pillow_type',
        'floor_preference' => 'floor_preference',
        'other'            => 'preferences_other',
    ];

    /**
     * `$actor` is part of the D-09 signature but is not written anywhere: the
     * automatic activity entry takes its causer from the route's guard (the
     * guest on the guest route, the staff user on the staff route), so this
     * action never sets a causer itself.
     */
    public function handle(Guest $guest, array $data, ?User $actor = null): array
    {
        $write = [];

        foreach (self::FIELDS as $input => $column) {
            if (array_key_exists($input, $data)) {
                $write[$column] = $data[$input];
            }
        }

        $write['preferences_updated_at'] = now();

        DB::transaction(fn () => $guest->fill($write)->save());

        return ['data' => $guest->refresh(), 'code' => 200];
    }
}
