<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['job_id', 'label', 'is_required', 'sort_order', 'completed_at', 'completed_by'])]
class JobChecklistItem extends TenantModel
{
    public function job(): BelongsTo
    {
        return $this->belongsTo(Job::class);
    }

    public function completedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'is_required' => 'boolean',
            'completed_at' => 'datetime',
        ];
    }
}
