<?php

namespace App\Payments;

use App\Contracts\PaymentGatewayInterface;

class ManualDriver implements PaymentGatewayInterface
{
    public function charge(string $method, float $amount, array $context = []): array
    {
        return ['reference' => null, 'status' => 'completed'];
    }
}
