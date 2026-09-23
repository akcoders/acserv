<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['code', 'name', 'contact_name', 'email', 'phone', 'gstin', 'address', 'payment_terms_days', 'is_active'])]
class Vendor extends TenantModel
{
    public function purchaseOrders(): HasMany
    {
        return $this->hasMany(PurchaseOrder::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'payment_terms_days' => 'integer'];
    }
}
