<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['customer_id', 'asset_id', 'contract_number', 'starts_on', 'ends_on', 'included_visits', 'used_visits', 'amount', 'status', 'terms'])]
class AmcContract extends TenantModel
{
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'ends_on' => 'date',
            'amount' => 'decimal:2',
            'terms' => 'array',
        ];
    }
}
