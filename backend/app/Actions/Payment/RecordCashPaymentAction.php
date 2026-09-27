<?php

namespace App\Actions\Payment;

use App\Contracts\PaymentGatewayInterface;
use App\Enums\ReservationStatus;
use App\Exceptions\PaymentFailedException;
use App\Models\Payment;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Records a manual (cash / on-arrival) payment against any payable.
 *
 * `$amount` is a validated decimal string (Phase 5, D-13); it is converted to a
 * float only for PaymentGatewayInterface::charge().
 */
class RecordCashPaymentAction
{
    public function __construct(private readonly PaymentGatewayInterface $gateway) {}

    public function handle(
        Model $payable,
        string $method,
        string $amount,
        User $recorder,
        ?string $note = null,
        ?string $idempotencyKey = null,
    ): array {
        return DB::transaction(function () use ($payable, $method, $amount, $recorder, $note, $idempotencyKey) {
            // The one float boundary: the gateway interface keeps float (consultant
            // ruling 1). Every comparison and sum happens in bcmath before this call.
            $result = $this->gateway->charge($method, (float) $amount, [
                'payable_type' => get_class($payable),
                'payable_id'   => $payable->id,
            ]);

            if ($result['status'] !== 'completed') {
                throw new PaymentFailedException(__('custom.errors.payment_failed'));
            }

            $payment = Payment::create([
                'payable_type' => get_class($payable),
                'payable_id'   => $payable->id,
                'method'       => $method,
                'amount_usd'   => $amount,
                'recorded_by'  => $recorder->id,
                'note'         => $note,
                'status'       => $result['status'],
                // Written in the same insert so the unique (payable, key) index is the backstop (D-13).
                'idempotency_key' => $idempotencyKey,
            ]);

            // Transition pending reservation to confirmed on payment
            if ($payable instanceof Reservation
                && $payable->status === ReservationStatus::PENDING) {
                $payable->update(['status' => ReservationStatus::CONFIRMED]);
            }

            return ['data' => $payment, 'code' => 200];
        });
    }
}
