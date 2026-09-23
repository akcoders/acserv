<?php

namespace App\Services\Billing;

interface PaymentGateway
{
    /** @return array<string, mixed> */
    public function createOrder(string $receipt, float $amount, string $currency = 'INR'): array;

    public function verifySignature(string $orderId, string $paymentId, string $signature): bool;
}
