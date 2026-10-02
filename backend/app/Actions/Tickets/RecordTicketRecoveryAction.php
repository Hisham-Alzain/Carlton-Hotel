<?php

namespace App\Actions\Tickets;

use App\Enums\FolioItemSource;
use App\Enums\TicketActionType;
use App\Enums\TicketRecoveryType;
use App\Enums\TicketStatus;
use App\Exceptions\TicketClosedException;
use App\Exceptions\TicketRecoveryFolioInvalidException;
use App\Models\FolioItem;
use App\Models\Ticket;
use App\Models\TicketAction;
use App\Models\TicketRecovery;
use App\Models\User;
use App\Support\FolioLedger;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

/**
 * The service-recovery writer (Phase 7, D-14, D-15; council A7, A8). Record-only.
 *
 * Money never moves here: the folio stays single-writer (credit floors, the
 * bcmath ledger, idempotency, folios.post). The dashboard first posts the
 * credit through `POST /cms/folios/{folio}/line-items`, then links it here by
 * `folio_item_uuid`. A `folio_credit` link is checked in this order:
 * no_stay (ticket has neither reservation nor guest) → not_credit → other_stay
 * (the item's folio is not on the ticket's reservation or, for a guest-only
 * ticket, not on one of that guest's reservations) → already_linked. Each is
 * 422 `ticket_recovery_folio_invalid {folio_item_uuid, reason}`. The unique
 * `folio_item_id` index is the idempotency guard: a lost race is caught as
 * `UniqueConstraintViolationException` and reported as already_linked, and
 * the recovery action row rolls back with it.
 *
 * `amount_usd` is a snapshot of the credit's absolute value at link time (A8);
 * only folio_credit rows are ledger-backed (A7), other types carry an
 * optional informational amount. Only the ticket row is locked — folios and
 * folio items are read, never written or locked — so the ticket lock stays a
 * leaf. No TicketChanged: the queue row is unchanged (D-21).
 *
 * `$data`: type (TicketRecoveryType), description, amount_usd (?string),
 * folio_item (?FolioItem).
 */
class RecordTicketRecoveryAction
{
    public function handle(Ticket $ticket, array $data, User $actor): array
    {
        return DB::transaction(function () use ($ticket, $data, $actor) {
            $locked = Ticket::whereKey($ticket->getKey())->lockForUpdate()->firstOrFail();

            if ($locked->status === TicketStatus::CLOSED) {
                throw new TicketClosedException(__('custom.errors.ticket_closed'), ['status' => $locked->status->value]);
            }

            /** @var TicketRecoveryType $type */
            $type = $data['type'];
            $item = null;

            if ($type === TicketRecoveryType::FOLIO_CREDIT) {
                $item = $data['folio_item'] ?? throw new InvalidArgumentException('A folio_credit recovery needs a folio item.');
                $this->assertLinkable($locked, $item);
                $amount = $this->creditAmount($item, $data['amount_usd'] ?? null);
            } else {
                $amount = isset($data['amount_usd']) ? FolioLedger::normalize($data['amount_usd']) : null;
            }

            $action = TicketAction::create([
                'ticket_id' => $locked->id,
                'user_id'   => $actor->getKey(),
                'type'      => TicketActionType::RECOVERY,
            ]);

            try {
                TicketRecovery::create([
                    'ticket_action_id' => $action->id,
                    'type'             => $type,
                    'amount_usd'       => $amount,
                    'description'      => $data['description'],
                    'folio_item_id'    => $item?->getKey(),
                ]);
            } catch (UniqueConstraintViolationException) {
                throw $this->invalid($item, 'already_linked');
            }

            return ['data' => $locked, 'code' => 201];
        }, 3);
    }

    private function assertLinkable(Ticket $ticket, FolioItem $item): void
    {
        if ($ticket->reservation_id === null && $ticket->guest_id === null) {
            throw $this->invalid($item, 'no_stay');
        }

        $source = $item->source_type instanceof FolioItemSource ? $item->source_type : FolioItemSource::tryFrom((string) $item->source_type);

        if ($source !== FolioItemSource::CREDIT) {
            throw $this->invalid($item, 'not_credit');
        }

        $reservation = $item->folio?->reservation;

        $sameStay = $ticket->reservation_id !== null
            ? $item->folio?->reservation_id === $ticket->reservation_id
            : $reservation !== null && $reservation->guest_id === $ticket->guest_id;

        if (! $sameStay) {
            throw $this->invalid($item, 'other_stay');
        }

        if (TicketRecovery::where('folio_item_id', $item->getKey())->exists()) {
            throw $this->invalid($item, 'already_linked');
        }
    }

    /** The credit's absolute value; a body amount must equal it (compared as 2dp strings, never floats). */
    private function creditAmount(FolioItem $item, ?string $bodyAmount): string
    {
        $amount = ltrim(FolioLedger::normalize((string) $item->amount_usd), '-');

        if ($bodyAmount !== null && FolioLedger::normalize($bodyAmount) !== $amount) {
            throw ValidationException::withMessages([
                'amount_usd' => [__('custom.validation.ticket_recovery_amount_mismatch')],
            ]);
        }

        return $amount;
    }

    private function invalid(FolioItem $item, string $reason): TicketRecoveryFolioInvalidException
    {
        return new TicketRecoveryFolioInvalidException(
            __('custom.errors.ticket_recovery_folio_invalid'),
            ['folio_item_uuid' => $item->uuid, 'reason' => $reason],
        );
    }
}
