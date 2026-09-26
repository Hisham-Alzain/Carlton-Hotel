<?php

namespace App\Actions\Guest;

use App\Models\Guest;
use App\Models\GuestNote;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Append a staff note to a guest (D-07). Append-only: there is no edit or
 * delete counterpart. Writes no activity entry of any kind (D-06) — the body
 * is free text about a person and stays out of the audit table.
 */
class AddGuestNoteAction
{
    public function handle(Guest $guest, User $author, string $body): array
    {
        return DB::transaction(function () use ($guest, $author, $body) {
            $note = GuestNote::create([
                'guest_id' => $guest->id,
                'user_id'  => $author->id,
                'body'     => $body,
            ]);

            return ['data' => $note->load('author'), 'code' => 201];
        });
    }
}
