<?php

namespace App\Models;

use App\Enums\BookingChannel;
use App\Enums\BookingStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['customer_id', 'customer_user_id', 'asset_id', 'branch_id', 'booking_number', 'channel', 'status', 'service_type', 'complaint', 'preferred_start_at', 'preferred_end_at', 'service_address', 'latitude', 'longitude', 'notes'])]
class Booking extends TenantModel
{
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function customerUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_user_id');
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function jobs(): HasMany
    {
        return $this->hasMany(Job::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'channel' => BookingChannel::class,
            'status' => BookingStatus::class,
            'preferred_start_at' => 'datetime',
            'preferred_end_at' => 'datetime',
            'service_address' => 'array',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
        ];
    }
}
