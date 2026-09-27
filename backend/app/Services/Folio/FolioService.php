<?php

namespace App\Services\Folio;

use App\Actions\Folio\ApproveFolioAction;
use App\Actions\Folio\GenerateFolioAction;
use App\Actions\Folio\PostFolioItemAction;
use App\Actions\Folio\RaiseFolioDisputeAction;
use App\Actions\Folio\RecordFolioPaymentAction;
use App\Actions\Folio\ResolveFolioDisputeAction;
use App\Actions\Folio\SettleFolioAction;
use App\Enums\FolioDisputeStatus;
use App\Exceptions\FolioMissingException;
use App\Exceptions\NotFoundException;
use App\Models\Folio;
use App\Models\FolioItem;
use App\Models\Guest;
use App\Models\Reservation;
use App\Models\User;
use App\Support\FolioLedger;
use App\Support\GuestEntitlement;

class FolioService
{
    public function __construct(
        private readonly GenerateFolioAction $generate,
        private readonly ApproveFolioAction  $approve,
        private readonly SettleFolioAction   $settle,
        private readonly PostFolioItemAction $postItem,
        private readonly RecordFolioPaymentAction $recordPayment,
        private readonly RaiseFolioDisputeAction $raiseDispute,
        private readonly ResolveFolioDisputeAction $resolveDispute,
    ) {}

    public function myFolio(Guest $guest): array
    {
        $reservation = GuestEntitlement::currentReservation($guest);

        return $this->displayed($this->generate->handle($reservation));
    }

    public function approveMyFolio(Guest $guest): array
    {
        $reservation = GuestEntitlement::currentReservation($guest);

        return $this->displayed($this->approve->handle($reservation));
    }

    /**
     * Staff folio read (D-01): a pure read for any reservation status. Never
     * generates: a reservation without a folio is a 404 `folio_missing`, and
     * the dashboard then calls the generate route.
     */
    public function adminShow(Reservation $reservation): array
    {
        // The open-dispute count rides in this first select (D-12), keeping the read at 6 queries.
        $folio = Folio::where('reservation_id', $reservation->id)->withCount('openDisputes')->first();

        if ($folio === null) {
            throw new FolioMissingException(__('custom.errors.folio_missing'), [
                'reservation_uuid'   => $reservation->uuid,
                'reservation_status' => $reservation->status->value,
            ]);
        }

        return ['data' => $this->display($folio, $reservation), 'code' => 200];
    }

    public function adminGenerate(Reservation $reservation): array
    {
        return $this->displayed($this->generate->handle($reservation));
    }

    public function adminSettle(Folio $folio, array $data, User $recorder): array
    {
        $amount = $data['amount_usd'] ?? null;

        // Keeps `payment_recorded` so the controller can pick the message (D-14).
        return $this->displayed($this->settle->handle(
            $folio,
            $data['method'] ?? null,
            $amount === null ? null : FolioLedger::normalize($amount),
            $recorder,
            $data['note'] ?? null,
        ));
    }

    /** Phase 5 (D-05): post a charge or credit; 201 new, 200 Idempotency-Key replay. */
    public function adminPostItem(Folio $folio, array $data, User $poster): array
    {
        return $this->displayed($this->postItem->handle($folio, $poster, $data));
    }

    /** Phase 5 (D-13): record a folio payment; 201 new, 200 Idempotency-Key replay. */
    public function adminRecordPayment(Folio $folio, array $data, User $recorder): array
    {
        return $this->displayed($this->recordPayment->handle($folio, $recorder, $data, $data['idempotency_key']));
    }

    /** Phase 5 (D-10): the guest disputes an item of their own folio (ownership checked in the request). */
    public function guestDispute(Guest $guest, FolioItem $item, string $reason): array
    {
        return $this->displayedItem($this->raiseDispute->handle($item, $guest, $reason));
    }

    /**
     * Phase 5 (D-11): staff raise, resolve or reject a line-item dispute. The
     * route's scoped binding already 404s an item of another folio; this check
     * is the explicit fallback behind it.
     */
    public function adminDispute(Folio $folio, FolioItem $item, array $data, User $staff): array
    {
        if ($item->folio_id !== $folio->id) {
            throw new NotFoundException();
        }

        $result = match ($data['action']) {
            'raise'   => $this->raiseDispute->handle($item, $staff, $data['reason']),
            'resolve' => $this->resolveDispute->handle($item, $staff, FolioDisputeStatus::RESOLVED, $data['note']),
            'reject'  => $this->resolveDispute->handle($item, $staff, FolioDisputeStatus::REJECTED, $data['note']),
        };

        return $this->displayedItem($result);
    }

    /** The item display relations FolioItemResource reads (same graph as a folio's items). */
    private function displayedItem(array $result): array
    {
        $result['data']->load(['latestDispute', 'postedBy:id,uuid,name', 'reversesItem:id,uuid']);

        return $result;
    }

    private function displayed(array $result): array
    {
        $result['data'] = $this->display($result['data']);

        return $result;
    }

    /**
     * The one eager-load graph behind every FolioResource (D-02). Payment
     * recorders are not loaded (PaymentResource omits `recorded_by` then) to
     * hold the D-03 read bound of 6 queries (folio, items, postedBy, reversesItem,
     * latestDispute, ledgerPayments).
     */
    private function display(Folio $folio, ?Reservation $reservation = null): Folio
    {
        $folio->load([
            'items' => fn ($query) => $query->orderBy('id'),
            'items.postedBy:id,uuid,name',
            'items.reversesItem:id,uuid',
            'items.latestDispute',
        ]);

        $folio->setRelation('ledgerPayments', $folio->ledgerPayments()->orderBy('created_at')->orderBy('id')->get());

        if ($reservation !== null) {
            $folio->setRelation('reservation', $reservation);
        }

        if (! array_key_exists('open_disputes_count', $folio->getAttributes())) {
            $folio->loadCount('openDisputes');
        }

        return $folio;
    }
}
