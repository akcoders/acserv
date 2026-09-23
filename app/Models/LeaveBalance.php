<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'year', 'leave_type', 'opening_days', 'accrued_days', 'used_days'])]
class LeaveBalance extends TenantModel
{
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'opening_days' => 'decimal:2',
            'accrued_days' => 'decimal:2',
            'used_days' => 'decimal:2',
        ];
    }
}
