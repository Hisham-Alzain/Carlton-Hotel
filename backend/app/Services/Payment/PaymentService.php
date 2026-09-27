<?php

namespace App\Services\Payment;

use App\Actions\Payment\RecordCashPaymentAction;
use App\Enums\FolioStatus;
use App\Exceptions\FolioSettledException;
use App\Models\Folio;
use App\Models\Reservation;
use App\Models\User;
use App\Support\FolioLedger;
use Illuminate\Support\Facades\DB;

class PaymentService
{
    public function __construct(private readonly RecordCashPaymentAction $action) {}

    /**
     * Reservation-level payment (pre-departure deposits belong here; they count
     * toward the folio balance, D-03).
     *
     * D-14 guard: once the folio is settled no reservation-level money is
     * taken; reservation then folio lock order (D-15), the order every other
     * booking path uses. The amount is a decimal string (D-13).
     */
    public function settleReservation(Reservation $reservation, array $data, User $recorder): array
    {
        return DB::transaction(function () use ($reservation, $data, $recorder) {
            Reservation::whereKey($reservation->getKey())->lockForUpdate()->first();

            $folio = Folio::where('reservation_id', $reservation->id)->lockForUpdate()->first();

            if ($folio !== null && $folio->status === FolioStatus::SETTLED) {
                throw new FolioSettledException(__('custom.errors.folio_settled'), [
                    'folio_uuid' => $folio->uuid,
                    'settled_at' => $folio->settled_at?->toIso8601String(),
                ]);
            }

            $result = $this->action->handle(
                $reservation,
                $data['method'],
                // The legacy request validates `numeric` (not decimal:0,2), so
                // "1e3" and "10.555" can arrive here; fromNumeric keeps them exact.
                FolioLedger::fromNumeric($data['amount_usd']),
                $recorder,
                $data['note'] ?? null,
            );

            $result['data']->load('recorder');

            return $result;
        });
    }
}
