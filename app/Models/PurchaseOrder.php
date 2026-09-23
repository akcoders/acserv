<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['vendor_id', 'stock_location_id', 'order_number', 'status', 'supplier_invoice_number', 'ordered_on', 'due_on', 'received_at', 'subtotal', 'tax_total', 'grand_total', 'notes'])]
class PurchaseOrder extends TenantModel
{
    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function stockLocation(): BelongsTo
    {
        return $this->belongsTo(StockLocation::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(PurchaseOrderLine::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(AccountEntry::class)->where('type', 'PURCHASE_PAYMENT');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'ordered_on' => 'date',
            'due_on' => 'date',
            'received_at' => 'datetime',
            'subtotal' => 'decimal:2',
            'tax_total' => 'decimal:2',
            'grand_total' => 'decimal:2',
        ];
    }
}
