<?php

namespace App\Contracts;

interface PaymentGatewayInterface
{
    public function charge(string $method, float $amount, array $context = []): array;
}
