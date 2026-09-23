<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Services\Billing\BillingService;
use App\Support\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentWebhookController extends Controller
{
    public function __invoke(Request $request, BillingService $billing, TenantContext $tenantContext): JsonResponse
    {
        $secret = config('services.razorpay.webhook_secret');
        abort_unless(is_string($secret) && $secret !== '', 503);
        $signature = (string) $request->header('X-Razorpay-Signature');
        abort_unless(hash_equals(hash_hmac('sha256', $request->getContent(), $secret), $signature), 401);

        if ($request->string('event')->toString() !== 'payment.captured') {
            return response()->json(['message' => 'Event ignored.']);
        }

        $providerOrderId = $request->input('payload.payment.entity.order_id');
        $providerPaymentId = $request->input('payload.payment.entity.id');
        abort_unless(is_string($providerOrderId) && is_string($providerPaymentId), 422);

        $payment = Payment::withoutGlobalScopes()->where('provider_order_id', $providerOrderId)->firstOrFail();
        $tenantContext->set($payment->tenant_id);

        try {
            $billing->settleGatewayPayment($payment, $providerPaymentId);
        } finally {
            $tenantContext->clear();
        }

        return response()->json(['message' => 'Payment captured.']);
    }
}
