<?php

namespace App\Models;

use App\Enums\StockLocationType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['branch_id', 'custodian_id', 'code', 'name', 'type', 'address', 'latitude', 'longitude', 'is_active'])]
class StockLocation extends TenantModel
{
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function custodian(): BelongsTo
    {
        return $this->belongsTo(User::class, 'custodian_id');
    }

    public function outgoingMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class, 'from_location_id');
    }

    public function incomingMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class, 'to_location_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'type' => StockLocationType::class,
            'address' => 'array',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'is_active' => 'boolean',
        ];
    }
}
