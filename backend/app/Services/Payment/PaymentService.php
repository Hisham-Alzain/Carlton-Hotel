<?php

namespace App\Services\Payment;

use App\Actions\Payment\RecordCashPaymentAction;
use App\Models\Reservation;
use App\Models\User;

class PaymentService
{
    public function __construct(private readonly RecordCashPaymentAction $action) {}

    public function settleReservation(Reservation $reservation, array $data, User $recorder): array
    {
        $result = $this->action->handle(
            $reservation,
            $data['method'],
            (float) $data['amount_usd'],
            $recorder,
            $data['note'] ?? null,
        );

        $result['data']->load('recorder');
        return $result;
    }
}
