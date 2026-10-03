<?php

namespace App\Actions\Events;

use App\Enums\EventChecklistItem;
use App\Enums\EventInquiryStatus;
use App\Exceptions\EventChecklistItemDerivedException;
use App\Exceptions\InquiryStateException;
use App\Models\EventInquiry;
use App\Models\EventInquiryChecklistItem;
use App\Models\User;
use App\Services\Events\EventInquiryService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * The only writer of event checklist rows (Phase 8, D-02, D-09, D-19).
 *
 * `done` is explicit, never a blind toggle, so a retried request cannot undo
 * itself: setting the current state again is a no-op that writes nothing, and
 * un-ticking an item that has no row creates none. Under the inquiry lock:
 * a cancelled inquiry is refused (`inquiry_state` with context), the derived
 * `deposit` item is refused (`event_checklist_item_derived`, D-04), then the
 * row is created lazily (unique `(event_inquiry_id, item)` as the race
 * backstop) and stamped with the actor and time, or cleared.
 */
class ToggleEventChecklistItemAction
{
    public function handle(EventInquiry $inquiry, EventChecklistItem $item, bool $done, User $actor): array
    {
        return DB::transaction(function () use ($inquiry, $item, $done, $actor) {
            $locked = EventInquiry::whereKey($inquiry->getKey())->lockForUpdate()->firstOrFail();

            if ($locked->status === EventInquiryStatus::CANCELLED) {
                throw new InquiryStateException(__('custom.errors.inquiry_state'), [
                    'status'  => $locked->status->value,
                    'allowed' => array_values(array_diff(EventInquiryStatus::values(), [EventInquiryStatus::CANCELLED->value])),
                ]);
            }

            if ($item->isDerived()) {
                throw new EventChecklistItemDerivedException(
                    __('custom.errors.event_checklist_item_derived'),
                    ['item' => $item->value],
                );
            }

            $row = $this->find($locked, $item);

            if (($row?->completed_at !== null) !== $done) {
                $row ??= $this->create($locked, $item);

                $row->update([
                    'completed_at' => $done ? now() : null,
                    'completed_by' => $done ? $actor->id : null,
                ]);
            }

            // The locked row is current (no inquiry column changed): load, don't re-read (D-30).
            return ['data' => $locked->load(EventInquiryService::DETAIL_RELATIONS), 'code' => 200];
        }, 3);
    }

    private function find(EventInquiry $inquiry, EventChecklistItem $item): ?EventInquiryChecklistItem
    {
        return $inquiry->checklistItems()->where('item', $item->value)->first();
    }

    /** Insert in a savepoint; a writer that lost the race reads the winner (unique backstop). */
    private function create(EventInquiry $inquiry, EventChecklistItem $item): EventInquiryChecklistItem
    {
        try {
            return DB::transaction(fn () => $inquiry->checklistItems()->create(['item' => $item]));
        } catch (UniqueConstraintViolationException $e) {
            return $this->find($inquiry, $item) ?? throw $e;
        }
    }
}
