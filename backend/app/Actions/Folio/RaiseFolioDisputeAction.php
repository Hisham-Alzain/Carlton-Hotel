<?php

namespace App\Actions\Folio;

use App\Enums\FolioDisputeStatus;
use App\Exceptions\FolioItemDisputeOpenException;
use App\Models\Folio;
use App\Models\FolioItem;
use App\Models\Guest;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Raises a dispute on a folio line item, for the guest (D-10) or staff (D-11).
 *
 * Locks the folio row only (D-10, D-15). One open dispute per item (D-09),
 * checked under that lock; a new one may be raised once the previous one is
 * resolved or rejected. Allowed on open and settled folios. A dispute never
 * moves money (D-11) and never gates check-out (D-12).
 */
class RaiseFolioDisputeAction
{
    public function handle(FolioItem $item, Guest|User $raiser, string $reason): array
    {
        return DB::transaction(function () use ($item, $raiser, $reason) {
            Folio::whereKey($item->folio_id)->lockForUpdate()->firstOrFail();

            $open = $item->disputes()->open()->first();

            if ($open !== null) {
                throw new FolioItemDisputeOpenException(__('custom.errors.folio_item_dispute_open'), [
                    'item_uuid'    => $item->uuid,
                    'dispute_uuid' => $open->uuid,
                ]);
            }

            // Exactly one raiser column is set (D-09).
            $item->disputes()->create([
                'status'   => FolioDisputeStatus::OPEN,
                'reason'   => $reason,
                'guest_id' => $raiser instanceof Guest ? $raiser->id : null,
                'user_id'  => $raiser instanceof User ? $raiser->id : null,
            ]);

            return ['data' => $item->fresh(), 'code' => 200];
        });
    }
}
