<?php

namespace App\Actions\Payment;

use App\Contracts\PaymentGatewayInterface;
use App\Exceptions\PaymentFailedException;
use App\Models\Payment;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class RecordCashPaymentAction
{
    public function __construct(private readonly PaymentGatewayInterface $gateway) {}

    public function handle(
        Model $payable,
        string $method,
        float $amount,
        User $recorder,
        ?string $note = null,
    ): array {
        return DB::transaction(function () use ($payable, $method, $amount, $recorder, $note) {
            $result = $this->gateway->charge($method, $amount, [
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
            ]);

            // Transition pending reservation to confirmed on payment
            if ($payable instanceof Reservation
                && $payable->status === Reservation::STATUS_PENDING) {
                $payable->update(['status' => Reservation::STATUS_CONFIRMED]);
            }

            return ['data' => $payment, 'code' => 200];
        });
    }
}
