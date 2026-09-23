<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['tax_profile_id', 'sku', 'name', 'description', 'hsn_code', 'unit', 'unit_cost', 'sale_price', 'reorder_level', 'track_serials', 'is_active'])]
class InventoryItem extends TenantModel
{
    public function taxProfile(): BelongsTo
    {
        return $this->belongsTo(TaxProfile::class);
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    public function incomingMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class)->whereNotNull('to_location_id');
    }

    public function outgoingMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class)->whereNotNull('from_location_id');
    }

    public function partConsumptions(): HasMany
    {
        return $this->hasMany(JobPartConsumption::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'unit_cost' => 'decimal:2',
            'sale_price' => 'decimal:2',
            'reorder_level' => 'decimal:3',
            'track_serials' => 'boolean',
            'is_active' => 'boolean',
        ];
    }
}
