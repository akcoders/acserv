<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['quotation_id', 'inventory_item_id', 'description', 'hsn_code', 'quantity', 'unit_price', 'discount', 'tax_rate', 'line_total', 'sort_order'])]
class QuotationLine extends TenantModel
{
    public function quotation(): BelongsTo
    {
        return $this->belongsTo(Quotation::class);
    }

    public function inventoryItem(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
            'unit_price' => 'decimal:2',
            'discount' => 'decimal:2',
            'tax_rate' => 'decimal:2',
            'line_total' => 'decimal:2',
        ];
    }
}
