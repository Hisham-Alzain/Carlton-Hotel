<?php

namespace App\Actions\Folio;

use App\Actions\Payment\RecordCashPaymentAction;
use App\Enums\FolioStatus;
use App\Exceptions\FolioSettledException;
use App\Models\Folio;
use App\Models\User;
use App\Support\FolioLedger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Closes a folio (POST /cms/folios/{folio}/settle), under the folio row lock.
 *
 * Phase 5 (D-14):
 *  - an already-settled folio answers `folio_settled` (was `reservation_state`);
 *  - when nothing is due (balanceDueUsd() <= 0.00, e.g. a prepaid stay whose
 *    reservation deposit covers the total) the folio closes WITHOUT a Payment
 *    row, even if an amount was sent, and logs `folio.settled_no_payment`;
 *  - otherwise an amount is required and is recorded exactly as before
 *    (tightening it to the exact balance is deferred), then the folio closes.
 *
 * The result carries `payment_recorded` so the controller can pick the message.
 */
class SettleFolioAction
{
    public function __construct(private readonly RecordCashPaymentAction $recordCashPayment) {}

    public function handle(Folio $folio, ?string $method, ?string $amount, User $recorder, ?string $notes = null): array
    {
        return DB::transaction(function () use ($folio, $method, $amount, $recorder, $notes) {
            $locked = Folio::whereKey($folio->id)->lockForUpdate()->firstOrFail();

            if ($locked->status === FolioStatus::SETTLED) {
                throw new FolioSettledException(__('custom.errors.folio_settled'), [
                    'folio_uuid' => $locked->uuid,
                    'settled_at' => $locked->settled_at?->toIso8601String(),
                ]);
            }

            $balance = $locked->balanceDueUsd();

            if (bccomp($balance, '0', 2) <= 0) {
                $locked->update(['status' => FolioStatus::SETTLED, 'settled_at' => now()]);

                activity()
                    ->performedOn($locked)
                    ->causedBy($recorder)
                    ->withProperties([
                        'folio_uuid'      => $locked->uuid,
                        'balance_due_usd' => $balance,
                        'paid_usd'        => $locked->paidUsd(),
                    ])
                    ->log('folio.settled_no_payment');

                return ['data' => $locked->fresh(), 'code' => 200, 'payment_recorded' => false];
            }

            // Money is due: an amount is required. Raised under the lock, so a
            // charge posted between validation and settle is seen (FA-5.06-1).
            if ($amount === null || $method === null) {
                throw ValidationException::withMessages([
                    'amount_usd' => [__('custom.validation.required', ['attribute' => __('custom.attributes.amount_usd')])],
                ]);
            }

            $this->recordCashPayment->handle($locked, $method, FolioLedger::normalize($amount), $recorder, $notes);

            $locked->update(['status' => FolioStatus::SETTLED, 'settled_at' => now()]);

            return ['data' => $locked->fresh(), 'code' => 200, 'payment_recorded' => true];
        });
    }
}
