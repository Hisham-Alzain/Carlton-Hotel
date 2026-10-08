<?php

namespace App\Actions\Loyalty;

use App\Enums\LoyaltyApplicationStatus;
use App\Enums\LoyaltyVoucherStatus;
use App\Models\Folio;
use App\Models\Guest;
use App\Models\LoyaltyLedgerEntry;
use App\Models\LoyaltyReservationApplication;
use App\Models\LoyaltyVoucher;
use App\Models\Reservation;
use App\Support\HotelClock;
use App\Support\LoyaltyLedger;
use Illuminate\Support\Facades\DB;

/**
 * Undoes every loyalty effect of a cancelled reservation (Phase 10, LOY-17, Q1,
 * Q2, Q3, Q13, M-3, M-6, M-9).
 *
 * CALLER CONTRACT: call inside the cancel transaction with the reservation row
 * already locked and re-checked (CancelReservationAction, M-3). Lock order is
 * reservation (caller) -> folio -> guest -> application / batches / voucher
 * (M-6), the same as a booking and a settlement, so none of them deadlock.
 *
 * Order inside one cancel: spent points are refunded and the voucher restored
 * first, then the folio earnings are clawed back, so the balance is never
 * negative. Spent earnings never fail the cancel (Q2): the clawback floors at
 * zero and the part it could not take back is logged as
 * `loyalty.clawback_shortfall` on the guest.
 *
 * Idempotent: only an `applied` application is reversed (and flipped to
 * `reversed`), and the ledger keys `refund:{id}` / `clawback:{id}` are unique,
 * so a second call writes nothing, not even a second shortfall log.
 *
 * Phase 10 gap (LOY-23): for an account deleted after this booking (9.1),
 * whatever the reversal gives back (refunded points, a restored voucher) is
 * forfeited again in the same transaction, so a deleted account never regains
 * a balance. The check reads the guest this action already locked, so a guest
 * that is not deleted costs no extra query.
 */
class ReverseLoyaltyForReservationAction
{
    public function __construct(
        private readonly LoyaltyLedger $ledger,
        private readonly ReverseLoyaltyForFolioAction $reverseFolio,
        private readonly ForfeitLoyaltyBalanceAction $forfeit,
    ) {}

    /**
     * @return array{data: array{refund_entry: ?LoyaltyLedgerEntry, clawback_entries: list<LoyaltyLedgerEntry>, voucher_restored: bool}, code: int}
     */
    public function handle(Reservation $lockedReservation): array
    {
        $result = ['refund_entry' => null, 'clawback_entries' => [], 'voucher_restored' => false];

        if ($lockedReservation->guest_id === null) {
            return ['data' => $result, 'code' => 200];
        }

        return DB::transaction(function () use ($lockedReservation, $result): array {
            $folio = Folio::query()->where('reservation_id', $lockedReservation->id)->lockForUpdate()->first();
            $guest = Guest::query()->whereKey($lockedReservation->guest_id)->lockForUpdate()->firstOrFail();
            $application = LoyaltyReservationApplication::query()
                ->where('reservation_id', $lockedReservation->id)
                ->lockForUpdate()
                ->first();

            if ($application?->status === LoyaltyApplicationStatus::APPLIED) {
                $result['refund_entry'] = $this->refundPoints($application);
                $result['voucher_restored'] = $this->restoreVoucher($application, $lockedReservation);
                $application->update(['status' => LoyaltyApplicationStatus::REVERSED, 'reversed_at' => now()]);
            }

            if ($folio !== null) {
                $result['clawback_entries'] = $this->reverseFolio->handle($folio)['data'];
                $this->logShortfall($guest, $lockedReservation, $result['clawback_entries']);
            }

            if ($guest->isDeleted()) {
                $this->forfeit->handle($guest);
            }

            return ['data' => $result, 'code' => 200];
        });
    }

    private function refundPoints(LoyaltyReservationApplication $application): ?LoyaltyLedgerEntry
    {
        if ($application->redeem_entry_id === null) {
            return null;
        }

        return $this->ledger->refund(LoyaltyLedgerEntry::query()->findOrFail($application->redeem_entry_id));
    }

    /**
     * Q13, M-9: the voucher goes back to `active` with its reservation link
     * cleared. One already past its expiry gets the grace period, counted in
     * hotel-local days like the issue expiry.
     */
    private function restoreVoucher(LoyaltyReservationApplication $application, Reservation $reservation): bool
    {
        if ($application->voucher_id === null) {
            return false;
        }

        $voucher = LoyaltyVoucher::query()->whereKey($application->voucher_id)->lockForUpdate()->first();

        if ($voucher === null
            || $voucher->status !== LoyaltyVoucherStatus::USED
            || (int) $voucher->reservation_id !== (int) $reservation->id) {
            return false;
        }

        $attributes = ['status' => LoyaltyVoucherStatus::ACTIVE, 'reservation_id' => null, 'used_at' => null];

        if (! $voucher->expires_at->gt(now())) {
            $attributes['expires_at'] = HotelClock::today()
                ->addDays((int) config('loyalty.restored_voucher_grace_days'))
                ->endOfDay()
                ->utc();
        }

        $voucher->update($attributes);

        return true;
    }

    /** @param  list<LoyaltyLedgerEntry>  $clawbacks */
    private function logShortfall(Guest $guest, Reservation $reservation, array $clawbacks): void
    {
        // Only entries written by this call: a replayed clawback was logged when it was written.
        $shortfall = (int) collect($clawbacks)
            ->filter(fn (LoyaltyLedgerEntry $entry): bool => $entry->wasRecentlyCreated)
            ->sum('shortfall_points');

        if ($shortfall > 0) {
            activity()
                ->performedOn($guest)
                ->withProperties(['reservation_uuid' => $reservation->uuid, 'shortfall_points' => $shortfall])
                ->log('loyalty.clawback_shortfall');
        }
    }
}
