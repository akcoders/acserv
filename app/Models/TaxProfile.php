<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'gstin', 'hsn_code', 'sac_code', 'tax_rate', 'is_default'])]
class TaxProfile extends TenantModel
{
    public function inventoryItems(): HasMany
    {
        return $this->hasMany(InventoryItem::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'tax_rate' => 'decimal:2',
            'is_default' => 'boolean',
        ];
    }
}
