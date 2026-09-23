<?php

namespace App\Services\Billing;

use App\Enums\DocumentStatus;
use App\Enums\PaymentStatus;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Quotation;
use App\Models\User;
use App\Models\WorkOrder;
use App\Services\Notifications\NotificationDispatcher;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class BillingService
{
    public function __construct(private readonly NotificationDispatcher $notifications) {}

    /** @param array<string, mixed> $data */
    public function createQuotation(array $data): Quotation
    {
        return DB::transaction(function () use ($data): Quotation {
            $lines = $data['lines'];
            unset($data['lines']);
            $quotation = Quotation::query()->create([
                ...$data,
                'quotation_number' => 'QUO-'.now()->format('Ymd').'-'.Str::upper(Str::substr((string) Str::ulid(), -6)),
                'status' => DocumentStatus::Sent,
            ]);
            $totals = $this->storeLines($quotation, $lines);
            $quotation->update($totals);

            return $quotation->load('lines');
        });
    }

    public function acceptQuotation(Quotation $quotation, User $actor): WorkOrder
    {
        return DB::transaction(function () use ($quotation, $actor): WorkOrder {
            $locked = Quotation::query()->with('lines')->lockForUpdate()->findOrFail($quotation->getKey());

            if ($locked->workOrder()->exists()) {
                return $locked->workOrder()->firstOrFail();
            }

            if (! in_array($locked->status, [DocumentStatus::Draft, DocumentStatus::Sent], true)) {
                throw ValidationException::withMessages(['quotation' => 'Only an open quotation can be accepted.']);
            }

            $locked->update(['status' => DocumentStatus::Accepted, 'accepted_at' => now()]);

            return WorkOrder::query()->create([
                'quotation_id' => $locked->getKey(),
                'job_id' => $locked->job_id,
                'customer_id' => $locked->customer_id,
                'work_order_number' => 'WO-'.now()->format('Ymd').'-'.Str::upper(Str::substr((string) Str::ulid(), -6)),
                'status' => DocumentStatus::Approved,
                'approved_at' => now(),
                'approved_by' => $actor->getKey(),
                'scope' => $locked->lines->pluck('description')->join("\n"),
                'notes' => $locked->notes,
            ]);
        });
    }

    /** @param array<string, mixed> $data */
    public function createInvoice(array $data): Invoice
    {
        return DB::transaction(function () use ($data): Invoice {
            $lines = $data['lines'];
            unset($data['lines']);

            $invoice = Invoice::query()->create([
                ...$data,
                'invoice_number' => 'INV-'.now()->format('Ymd').'-'.Str::upper(Str::substr((string) Str::ulid(), -6)),
                'status' => DocumentStatus::Sent,
                'currency' => 'INR',
            ]);

            $totals = $this->storeLines($invoice, $lines);
            $invoice->update([
                ...$totals,
                'paid_total' => 0,
                'balance_due' => $totals['grand_total'],
            ]);

            return $invoice->load('lines');
        });
    }

    public function createInvoiceFromWorkOrder(WorkOrder $workOrder): Invoice
    {
        $workOrder->load(['quotation.lines']);

        if ($workOrder->quotation === null) {
            throw ValidationException::withMessages(['work_order' => 'This work order is not linked to a quotation.']);
        }

        if ($workOrder->invoices()->exists()) {
            throw ValidationException::withMessages(['work_order' => 'An invoice already exists for this work order.']);
        }

        return $this->createInvoice([
            'job_id' => $workOrder->job_id,
            'customer_id' => $workOrder->customer_id,
            'work_order_id' => $workOrder->getKey(),
            'issued_on' => today()->toDateString(),
            'due_on' => today()->addDays(7)->toDateString(),
            'notes' => $workOrder->notes,
            'lines' => $workOrder->quotation->lines->map(fn ($line): array => [
                'inventory_item_id' => $line->inventory_item_id,
                'description' => $line->description,
                'hsn_code' => $line->hsn_code,
                'quantity' => $line->quantity,
                'unit_price' => $line->unit_price,
                'discount' => $line->discount,
                'tax_rate' => $line->tax_rate,
            ])->all(),
        ]);
    }

    /** @param array<string, mixed> $data */
    public function recordPayment(array $data): Payment
    {
        $payment = DB::transaction(function () use ($data): Payment {
            $invoice = Invoice::query()->lockForUpdate()->findOrFail($data['invoice_id']);
            $amount = (float) $data['amount'];

            if ($amount > (float) $invoice->balance_due) {
                throw ValidationException::withMessages(['amount' => 'Payment cannot exceed the invoice balance.']);
            }

            $signature = $data['provider_signature'] ?? null;
            unset($data['provider_signature']);
            $payment = Payment::query()->create([
                ...$data,
                'payment_number' => 'PAY-'.now()->format('Ymd').'-'.Str::upper(Str::substr((string) Str::ulid(), -6)),
                'status' => PaymentStatus::Paid,
                'currency' => 'INR',
                'provider_signature_hash' => $signature !== null
                    ? hash('sha256', $signature)
                    : null,
                'paid_at' => now(),
            ]);

            $paidTotal = round((float) $invoice->paid_total + $amount, 2);
            $balanceDue = max(0, round((float) $invoice->grand_total - $paidTotal, 2));
            $invoice->update([
                'paid_total' => $paidTotal,
                'balance_due' => $balanceDue,
                'status' => $balanceDue === 0.0 ? DocumentStatus::Paid : DocumentStatus::PartiallyPaid,
            ]);

            return $payment;
        });

        $payment->load('invoice.customer.user');
        $recipient = $payment->invoice->customer->user;

        if ($recipient !== null) {
            $this->notifications->queue('invoice.paid', $recipient, [
                'invoice_number' => $payment->invoice->invoice_number,
            ]);
        }

        return $payment;
    }

    public function settleGatewayPayment(Payment $payment, string $providerPaymentId, ?string $providerSignature = null): Payment
    {
        return DB::transaction(function () use ($payment, $providerPaymentId, $providerSignature): Payment {
            $lockedPayment = Payment::query()->lockForUpdate()->findOrFail($payment->getKey());

            if ($lockedPayment->status === PaymentStatus::Paid) {
                return $lockedPayment;
            }

            $invoice = Invoice::query()->lockForUpdate()->findOrFail($lockedPayment->invoice_id);
            $lockedPayment->update([
                'status' => PaymentStatus::Paid,
                'provider_payment_id' => $providerPaymentId,
                'provider_signature_hash' => $providerSignature === null ? $lockedPayment->provider_signature_hash : hash('sha256', $providerSignature),
                'paid_at' => now(),
            ]);
            $paidTotal = round((float) $invoice->paid_total + (float) $lockedPayment->amount, 2);
            $balanceDue = max(0, round((float) $invoice->grand_total - $paidTotal, 2));
            $invoice->update([
                'paid_total' => $paidTotal,
                'balance_due' => $balanceDue,
                'status' => $balanceDue === 0.0 ? DocumentStatus::Paid : DocumentStatus::PartiallyPaid,
            ]);

            return $lockedPayment->refresh();
        });
    }

    /**
     * @param  array<int, array<string, mixed>>  $lines
     * @return array{subtotal: float, discount_total: float, tax_total: float, grand_total: float}
     */
    private function storeLines(Invoice|Quotation $document, array $lines): array
    {
        $subtotal = 0.0;
        $discountTotal = 0.0;
        $taxTotal = 0.0;

        foreach ($lines as $sortOrder => $line) {
            $quantity = (float) $line['quantity'];
            $unitPrice = (float) $line['unit_price'];
            $discount = (float) ($line['discount'] ?? 0);
            $taxRate = (float) ($line['tax_rate'] ?? 0);
            $base = max(0, ($quantity * $unitPrice) - $discount);
            $tax = round($base * $taxRate / 100, 2);
            $lineTotal = round($base + $tax, 2);
            $document->lines()->create([
                ...$line,
                'discount' => $discount,
                'tax_rate' => $taxRate,
                'line_total' => $lineTotal,
                'sort_order' => $sortOrder,
            ]);
            $subtotal += $quantity * $unitPrice;
            $discountTotal += $discount;
            $taxTotal += $tax;
        }

        return [
            'subtotal' => round($subtotal, 2),
            'discount_total' => round($discountTotal, 2),
            'tax_total' => round($taxTotal, 2),
            'grand_total' => round($subtotal - $discountTotal + $taxTotal, 2),
        ];
    }
}
