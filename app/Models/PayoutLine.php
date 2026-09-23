<?php

namespace App\Models;

use App\Enums\PayoutStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['payout_cycle_id', 'user_id', 'job_count', 'worked_hours', 'base_amount', 'incentive_amount', 'penalty_amount', 'deduction_amount', 'net_amount', 'details', 'status'])]
class PayoutLine extends TenantModel
{
    public function cycle(): BelongsTo
    {
        return $this->belongsTo(PayoutCycle::class, 'payout_cycle_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function disputes(): HasMany
    {
        return $this->hasMany(PayoutDispute::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'worked_hours' => 'decimal:2',
            'base_amount' => 'decimal:2',
            'incentive_amount' => 'decimal:2',
            'penalty_amount' => 'decimal:2',
            'deduction_amount' => 'decimal:2',
            'net_amount' => 'decimal:2',
            'details' => 'array',
            'status' => PayoutStatus::class,
        ];
    }
}
