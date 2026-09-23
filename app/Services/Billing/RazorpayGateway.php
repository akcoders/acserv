<?php

namespace App\Services\Billing;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class RazorpayGateway implements PaymentGateway
{
    /** @return array<string, mixed> */
    public function createOrder(string $receipt, float $amount, string $currency = 'INR'): array
    {
        [$keyId, $keySecret] = $this->credentials();

        return Http::withBasicAuth($keyId, $keySecret)
            ->connectTimeout(3)
            ->timeout(10)
            ->post('https://api.razorpay.com/v1/orders', [
                'amount' => (int) round($amount * 100),
                'currency' => $currency,
                'receipt' => $receipt,
            ])
            ->throw()
            ->json();
    }

    public function verifySignature(string $orderId, string $paymentId, string $signature): bool
    {
        [, $keySecret] = $this->credentials();
        $expected = hash_hmac('sha256', $orderId.'|'.$paymentId, $keySecret);

        return hash_equals($expected, $signature);
    }

    /** @return array{0: string, 1: string} */
    private function credentials(): array
    {
        $keyId = config('services.razorpay.key_id');
        $keySecret = config('services.razorpay.key_secret');

        if (! is_string($keyId) || $keyId === '' || ! is_string($keySecret) || $keySecret === '') {
            throw new RuntimeException('Razorpay is not configured.');
        }

        return [$keyId, $keySecret];
    }
}
