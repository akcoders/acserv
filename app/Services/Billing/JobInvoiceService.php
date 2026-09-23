<?php

namespace App\Services\Billing;

use App\Enums\JobStatus;
use App\Models\Invoice;
use App\Models\Job;
use App\Models\TaxProfile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class JobInvoiceService
{
    public function __construct(private readonly BillingService $billing) {}

    public function estimatedTotal(Job $job): float
    {
        $job->loadMissing('partConsumptions');
        $subtotal = (float) $job->service_cost;
        $tax = round($subtotal * (float) $job->service_tax_rate / 100, 2);

        foreach ($job->partConsumptions as $consumption) {
            $quantity = max(0, round((float) $consumption->quantity - (float) $consumption->returned_quantity, 3));
            $base = $quantity * (float) $consumption->unit_price;
            $subtotal += $base;
            $tax += round($base * (float) $consumption->tax_rate / 100, 2);
        }

        return round($subtotal + $tax, 2);
    }

    public function generate(Job $job): Invoice
    {
        return DB::transaction(function () use ($job): Invoice {
            $lockedJob = Job::query()->lockForUpdate()->findOrFail($job->getKey());
            $existingInvoice = $lockedJob->invoice()->first();

            if ($existingInvoice !== null) {
                return $existingInvoice->load('lines');
            }

            if ($lockedJob->status !== JobStatus::Completed) {
                throw ValidationException::withMessages([
                    'status' => 'Finish the job before generating its invoice.',
                ]);
            }

            $lockedJob->load(['customer', 'partConsumptions.inventoryItem.taxProfile']);
            $taxProfile = TaxProfile::query()->where('is_default', true)->first();
            $lines = [];
            $serviceCost = (float) $lockedJob->service_cost;

            $lines[] = [
                'description' => $lockedJob->service_type,
                'hsn_code' => $taxProfile?->sac_code,
                'quantity' => 1,
                'unit_price' => $serviceCost,
                'discount' => 0,
                'tax_rate' => (float) $lockedJob->service_tax_rate,
            ];

            foreach ($lockedJob->partConsumptions as $consumption) {
                $billableQuantity = round((float) $consumption->quantity - (float) $consumption->returned_quantity, 3);

                if ($billableQuantity <= 0) {
                    continue;
                }

                $item = $consumption->inventoryItem;
                $lines[] = [
                    'inventory_item_id' => $item->getKey(),
                    'description' => $item->name,
                    'hsn_code' => $item->hsn_code ?: $item->taxProfile?->hsn_code,
                    'quantity' => $billableQuantity,
                    'unit_price' => (float) $consumption->unit_price,
                    'discount' => 0,
                    'tax_rate' => (float) $consumption->tax_rate,
                ];
            }

            return $this->billing->createInvoice([
                'job_id' => $lockedJob->getKey(),
                'customer_id' => $lockedJob->customer_id,
                'tax_profile_id' => $taxProfile?->getKey(),
                'supplier_gstin' => $taxProfile?->gstin,
                'billing_address' => $lockedJob->customer->billing_address ?: $lockedJob->customer->service_address,
                'issued_on' => today()->toDateString(),
                'due_on' => today()->addDays(7)->toDateString(),
                'notes' => $lockedJob->resolution,
                'lines' => $lines,
            ]);
        });
    }
}
