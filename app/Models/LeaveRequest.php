<?php

namespace App\Models;

use App\Enums\LeaveStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'reviewed_by', 'leave_type', 'starts_on', 'ends_on', 'days', 'reason', 'status', 'review_notes', 'reviewed_at'])]
class LeaveRequest extends TenantModel
{
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'ends_on' => 'date',
            'days' => 'decimal:2',
            'status' => LeaveStatus::class,
            'reviewed_at' => 'datetime',
        ];
    }
}
