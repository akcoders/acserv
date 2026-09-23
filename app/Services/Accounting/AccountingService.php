<?php

namespace App\Services\Accounting;

use App\Enums\DocumentStatus;
use App\Enums\PaymentStatus;
use App\Enums\PayoutStatus;
use App\Enums\StockMovementType;
use App\Models\AccountEntry;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PayoutCycle;
use App\Models\PurchaseOrder;
use App\Models\StockMovement;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AccountingService
{
    /** @param array<string, mixed> $data */
    public function record(array $data): AccountEntry
    {
        return DB::transaction(function () use ($data): AccountEntry {
            $purchaseOrderId = null;
            $category = $data['category'] ?? $data['type'];
            $description = $data['description'] ?? '';

            if ($data['type'] === 'PURCHASE_PAYMENT') {
                $order = PurchaseOrder::query()->lockForUpdate()->findOrFail($data['purchase_order_id']);

                if ($order->status !== 'RECEIVED') {
                    throw ValidationException::withMessages(['purchase_order_id' => 'Receive the purchase before recording payment.']);
                }

                $paid = (float) $order->payments()->sum('amount');

                if ((float) $data['amount'] > round((float) $order->grand_total - $paid, 2)) {
                    throw ValidationException::withMessages(['amount' => 'Payment exceeds the outstanding purchase balance.']);
                }

                $purchaseOrderId = $order->getKey();
                $category = 'INVENTORY_PURCHASE';
                $description = 'Vendor payment for '.$order->order_number;
            }

            return AccountEntry::query()->create([
                'purchase_order_id' => $purchaseOrderId,
                'entry_number' => 'ACC-'.now()->format('Ymd').'-'.Str::upper(Str::substr((string) Str::ulid(), -6)),
                'type' => $data['type'],
                'category' => $category,
                'description' => $description,
                'amount' => $data['amount'],
                'payment_method' => $data['payment_method'],
                'reference' => $data['reference'] ?? null,
                'entry_date' => $data['entry_date'],
            ]);
        });
    }

    /** @return array{sales: float, other_income: float, revenue: float, parts_cost: float, gross_profit: float, manual_expenses: float, payroll_cost: float, operating_expenses: float, net_profit: float, customer_receipts: float, vendor_payments: float, payroll_paid: float, cash_in: float, cash_out: float, cash_movement: float} */
    public function statement(string $from, string $to): array
    {
        $invoices = Invoice::query()
            ->whereDate('issued_on', '>=', $from)
            ->whereDate('issued_on', '<=', $to)
            ->whereNotIn('status', [DocumentStatus::Draft->value, DocumentStatus::Rejected->value, DocumentStatus::Cancelled->value]);
        $sales = round((float) (clone $invoices)->sum('subtotal') - (float) (clone $invoices)->sum('discount_total'), 2);
        $otherIncome = (float) AccountEntry::query()->where('type', 'OTHER_INCOME')->whereDate('entry_date', '>=', $from)->whereDate('entry_date', '<=', $to)->sum('amount');
        $manualExpenses = (float) AccountEntry::query()->where('type', 'EXPENSE')->whereDate('entry_date', '>=', $from)->whereDate('entry_date', '<=', $to)->sum('amount');
        $payrollCost = (float) PayoutCycle::query()
            ->whereIn('status', [PayoutStatus::Processed->value, PayoutStatus::Paid->value, PayoutStatus::Disputed->value])
            ->whereDate('ends_on', '>=', $from)->whereDate('ends_on', '<=', $to)
            ->sum('net_total');
        $operatingExpenses = round($manualExpenses + $payrollCost, 2);

        $movements = StockMovement::query()
            ->whereNotNull('job_id')
            ->whereBetween('moved_at', [$from.' 00:00:00', $to.' 23:59:59']);
        $consumedCost = (float) (clone $movements)->where('type', StockMovementType::Consumption->value)->sum(DB::raw('quantity * unit_cost'));
        $returnedCost = (float) (clone $movements)->where('type', StockMovementType::Return->value)->sum(DB::raw('quantity * unit_cost'));
        $partsCost = round($consumedCost - $returnedCost, 2);

        $customerReceipts = (float) Payment::query()
            ->whereIn('status', [PaymentStatus::Paid->value, PaymentStatus::Captured->value])
            ->whereBetween('paid_at', [$from.' 00:00:00', $to.' 23:59:59'])
            ->sum('amount');
        $vendorPayments = (float) AccountEntry::query()->where('type', 'PURCHASE_PAYMENT')->whereDate('entry_date', '>=', $from)->whereDate('entry_date', '<=', $to)->sum('amount');
        $payrollPaid = (float) PayoutCycle::query()->where('status', PayoutStatus::Paid->value)->whereBetween('paid_at', [$from.' 00:00:00', $to.' 23:59:59'])->sum('net_total');
        $revenue = round($sales + $otherIncome, 2);
        $grossProfit = round($sales - $partsCost, 2);
        $cashIn = round($customerReceipts + $otherIncome, 2);
        $cashOut = round($vendorPayments + $manualExpenses + $payrollPaid, 2);

        return [
            'sales' => $sales,
            'other_income' => $otherIncome,
            'revenue' => $revenue,
            'parts_cost' => $partsCost,
            'gross_profit' => $grossProfit,
            'manual_expenses' => $manualExpenses,
            'payroll_cost' => $payrollCost,
            'operating_expenses' => $operatingExpenses,
            'net_profit' => round($grossProfit + $otherIncome - $operatingExpenses, 2),
            'customer_receipts' => $customerReceipts,
            'vendor_payments' => $vendorPayments,
            'payroll_paid' => $payrollPaid,
            'cash_in' => $cashIn,
            'cash_out' => $cashOut,
            'cash_movement' => round($cashIn - $cashOut, 2),
        ];
    }
}
