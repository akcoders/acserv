<?php

namespace App\Models;

use App\Enums\WarrantyType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['asset_id', 'customer_id', 'job_id', 'type', 'provider', 'policy_number', 'starts_on', 'ends_on', 'status', 'coverage_terms'])]
class Warranty extends TenantModel
{
    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function job(): BelongsTo
    {
        return $this->belongsTo(Job::class);
    }

    public function claims(): HasMany
    {
        return $this->hasMany(WarrantyClaim::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'type' => WarrantyType::class,
            'starts_on' => 'date',
            'ends_on' => 'date',
            'coverage_terms' => 'array',
        ];
    }
}
