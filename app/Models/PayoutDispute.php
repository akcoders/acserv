<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['payout_line_id', 'user_id', 'resolved_by', 'status', 'reason', 'resolution', 'resolved_at'])]
class PayoutDispute extends TenantModel
{
    public function payoutLine(): BelongsTo
    {
        return $this->belongsTo(PayoutLine::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'resolved_at' => 'datetime',
        ];
    }
}
