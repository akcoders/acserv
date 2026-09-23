<?php

namespace App\Services\Purchasing;

use App\Enums\StockMovementType;
use App\Models\InventoryItem;
use App\Models\PurchaseOrder;
use App\Models\StockLocation;
use App\Models\Vendor;
use App\Services\Inventory\StockService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PurchaseService
{
    public function __construct(private readonly StockService $stock) {}

    /** @param array<string, mixed> $data */
    public function create(array $data): PurchaseOrder
    {
        return DB::transaction(function () use ($data): PurchaseOrder {
            $vendor = Vendor::query()->where('is_active', true)->findOrFail($data['vendor_id']);
            StockLocation::query()->where('is_active', true)->findOrFail($data['stock_location_id']);

            $order = PurchaseOrder::query()->create([
                'vendor_id' => $vendor->getKey(),
                'stock_location_id' => $data['stock_location_id'],
                'order_number' => 'PO-'.now()->format('Ymd').'-'.Str::upper(Str::substr((string) Str::ulid(), -6)),
                'status' => 'ORDERED',
                'supplier_invoice_number' => $data['supplier_invoice_number'] ?? null,
                'ordered_on' => $data['ordered_on'],
                'due_on' => Carbon::parse($data['ordered_on'])->addDays($vendor->payment_terms_days)->toDateString(),
                'notes' => $data['notes'] ?? null,
            ]);

            $subtotal = 0.0;
            $taxTotal = 0.0;

            foreach ($data['lines'] as $index => $line) {
                InventoryItem::query()->where('is_active', true)->findOrFail($line['inventory_item_id']);
                $lineSubtotal = round((float) $line['quantity'] * (float) $line['unit_cost'], 2);
                $lineTax = round($lineSubtotal * (float) ($line['tax_rate'] ?? 0) / 100, 2);
                $order->lines()->create([
                    'inventory_item_id' => $line['inventory_item_id'],
                    'quantity' => $line['quantity'],
                    'unit_cost' => $line['unit_cost'],
                    'tax_rate' => $line['tax_rate'] ?? 0,
                    'subtotal' => $lineSubtotal,
                    'tax_amount' => $lineTax,
                    'line_total' => $lineSubtotal + $lineTax,
                    'sort_order' => $index,
                ]);
                $subtotal += $lineSubtotal;
                $taxTotal += $lineTax;
            }

            $order->update([
                'subtotal' => round($subtotal, 2),
                'tax_total' => round($taxTotal, 2),
                'grand_total' => round($subtotal + $taxTotal, 2),
            ]);

            return $order->load(['vendor', 'stockLocation', 'lines.inventoryItem']);
        });
    }

    public function receive(PurchaseOrder $order, ?string $supplierInvoiceNumber = null): PurchaseOrder
    {
        return DB::transaction(function () use ($order, $supplierInvoiceNumber): PurchaseOrder {
            $locked = PurchaseOrder::query()->with(['lines.inventoryItem', 'stockLocation'])->lockForUpdate()->findOrFail($order->getKey());

            if ($locked->status !== 'ORDERED') {
                throw ValidationException::withMessages(['purchase_order' => 'Only an ordered purchase can be received.']);
            }

            foreach ($locked->lines as $line) {
                $this->stock->move([
                    'inventory_item_id' => $line->inventory_item_id,
                    'to_location_id' => $locked->stock_location_id,
                    'type' => StockMovementType::Purchase->value,
                    'quantity' => $line->quantity,
                    'unit_cost' => $line->unit_cost,
                    'reference' => $locked->order_number,
                    'idempotency_key' => 'purchase:'.$line->getKey(),
                ]);
                $line->inventoryItem->update(['unit_cost' => $line->unit_cost]);
            }

            $locked->update([
                'status' => 'RECEIVED',
                'received_at' => now(),
                'supplier_invoice_number' => $supplierInvoiceNumber ?: $locked->supplier_invoice_number,
            ]);

            return $locked->refresh();
        });
    }
}
