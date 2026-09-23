<?php

namespace App\Models;

use App\Enums\PayoutStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['cycle_number', 'type', 'starts_on', 'ends_on', 'status', 'gross_total', 'deduction_total', 'net_total', 'processed_at', 'paid_at'])]
class PayoutCycle extends TenantModel
{
    public function lines(): HasMany
    {
        return $this->hasMany(PayoutLine::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'ends_on' => 'date',
            'status' => PayoutStatus::class,
            'gross_total' => 'decimal:2',
            'deduction_total' => 'decimal:2',
            'net_total' => 'decimal:2',
            'processed_at' => 'datetime',
            'paid_at' => 'datetime',
        ];
    }
}
