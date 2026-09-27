<?php

namespace App\Actions\Folio;

use App\Enums\FolioDisputeStatus;
use App\Exceptions\FolioDisputeStateException;
use App\Models\Folio;
use App\Models\FolioItem;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Closes the item's open dispute as resolved or rejected (D-11). Never moves
 * money: a refund-worthy dispute is settled by posting a credit with
 * reverses_item_uuid. Locks the folio row only (D-15).
 */
class ResolveFolioDisputeAction
{
    public function handle(FolioItem $item, User $resolver, FolioDisputeStatus $outcome, string $note): array
    {
        if ($outcome === FolioDisputeStatus::OPEN) {
            throw new InvalidArgumentException('A dispute can only be closed as resolved or rejected.');
        }

        return DB::transaction(function () use ($item, $resolver, $outcome, $note) {
            Folio::whereKey($item->folio_id)->lockForUpdate()->firstOrFail();

            $open = $item->disputes()->open()->first();

            if ($open === null) {
                throw new FolioDisputeStateException(__('custom.errors.folio_dispute_state'), [
                    'item_uuid' => $item->uuid,
                    'status'    => $item->latestDispute()->first()?->status?->value,
                ]);
            }

            $open->update([
                'status'          => $outcome,
                'resolved_by'     => $resolver->id,
                'resolved_at'     => now(),
                'resolution_note' => $note,
            ]);

            return ['data' => $item->fresh(), 'code' => 200];
        });
    }
}
