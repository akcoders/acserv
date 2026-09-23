<?php

namespace App\Models;

use App\Enums\ClaimStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['warranty_id', 'job_id', 'claim_number', 'status', 'issue', 'decision_notes', 'approved_amount', 'submitted_at', 'decided_at'])]
class WarrantyClaim extends TenantModel
{
    public function warranty(): BelongsTo
    {
        return $this->belongsTo(Warranty::class);
    }

    public function job(): BelongsTo
    {
        return $this->belongsTo(Job::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'status' => ClaimStatus::class,
            'approved_amount' => 'decimal:2',
            'submitted_at' => 'datetime',
            'decided_at' => 'datetime',
        ];
    }
}
