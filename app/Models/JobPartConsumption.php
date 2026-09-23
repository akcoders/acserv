<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['job_id', 'inventory_item_id', 'stock_location_id', 'consumed_by', 'quantity', 'returned_quantity', 'unit_price', 'tax_rate', 'consumed_at'])]
class JobPartConsumption extends TenantModel
{
    public function job(): BelongsTo
    {
        return $this->belongsTo(Job::class);
    }

    public function inventoryItem(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class);
    }

    public function stockLocation(): BelongsTo
    {
        return $this->belongsTo(StockLocation::class);
    }

    public function consumer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'consumed_by');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
            'returned_quantity' => 'decimal:3',
            'unit_price' => 'decimal:2',
            'tax_rate' => 'decimal:2',
            'consumed_at' => 'datetime',
        ];
    }
}
