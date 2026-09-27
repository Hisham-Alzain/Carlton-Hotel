<?php

namespace App\Actions\Folio;

use App\Actions\Payment\RecordCashPaymentAction;
use App\Enums\FolioStatus;
use App\Exceptions\FolioOverpaymentException;
use App\Exceptions\FolioSettledException;
use App\Models\Folio;
use App\Models\Payment;
use App\Models\User;
use App\Support\FolioLedger;
use App\Support\IdempotentWrite;
use Illuminate\Support\Facades\DB;

/**
 * Records a manual payment against a folio (D-13), replay-safe (D-08).
 *
 * Under the folio row lock only (D-15): the Idempotency-Key replay is checked
 * first, then the settled guard, then the overpayment check against
 * balanceDueUsd() (reservation deposits count, D-03; a zero-balance folio
 * therefore refuses any payment). The payment that brings the balance to 0.00
 * or below settles the folio and logs `folio.auto_settled`.
 *
 * Pre-departure money goes through the reservation-level deposit route; this
 * route is the departure desk (auto-settle closes the folio; there is no
 * reopen).
 */
class RecordFolioPaymentAction
{
    public function __construct(private readonly RecordCashPaymentAction $recordCashPayment) {}

    public function handle(Folio $folio, User $recorder, array $data, string $key): array
    {
        return DB::transaction(function () use ($folio, $recorder, $data, $key) {
            $locked = Folio::whereKey($folio->id)->lockForUpdate()->firstOrFail();
            $method = $data['method'] instanceof \BackedEnum ? $data['method']->value : (string) $data['method'];
            $amount = FolioLedger::normalize($data['amount_usd']);
            $note   = $data['note'] ?? null;

            [, $replayed] = IdempotentWrite::run(
                $key,
                fn () => Payment::where('payable_type', $locked->getMorphClass())
                    ->where('payable_id', $locked->id)
                    ->where('idempotency_key', $key)
                    ->first(),
                // recorded_by is part of the payload: the same key from another desk is a conflict (D-08).
                fn (Payment $payment) => $payment->method === $method
                    && bccomp(FolioLedger::normalize($payment->amount_usd), $amount, 2) === 0
                    && $payment->note === $note
                    && (int) $payment->recorded_by === (int) $recorder->id,
                fn () => $this->record($locked, $recorder, $method, $amount, $note, $key),
            );

            return ['data' => $locked->fresh(), 'code' => $replayed ? 200 : 201];
        });
    }

    private function record(Folio $locked, User $recorder, string $method, string $amount, ?string $note, string $key): Payment
    {
        if ($locked->status === FolioStatus::SETTLED) {
            throw new FolioSettledException(__('custom.errors.folio_settled'), [
                'folio_uuid' => $locked->uuid,
                'settled_at' => $locked->settled_at?->toIso8601String(),
            ]);
        }

        $balance = $locked->balanceDueUsd();

        if (bccomp($amount, $balance, 2) === 1) {
            throw new FolioOverpaymentException(__('custom.errors.folio_overpayment'), [
                'balance_due_usd' => $balance,
                'amount_usd'      => $amount,
            ]);
        }

        $payment = $this->recordCashPayment->handle($locked, $method, $amount, $recorder, $note, idempotencyKey: $key)['data'];

        if (bccomp($locked->balanceDueUsd(), '0', 2) <= 0) {
            $locked->update(['status' => FolioStatus::SETTLED, 'settled_at' => now()]);

            activity()
                ->performedOn($locked)
                ->causedBy($recorder)
                ->withProperties(['folio_uuid' => $locked->uuid, 'paid_usd' => $locked->paidUsd()])
                ->log('folio.auto_settled');
        }

        return $payment;
    }
}
