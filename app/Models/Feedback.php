<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['customer_id', 'job_id', 'rating', 'comment', 'status', 'escalated_at', 'resolved_by', 'resolved_at', 'resolution'])]
class Feedback extends TenantModel
{
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function job(): BelongsTo
    {
        return $this->belongsTo(Job::class);
    }

    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'escalated_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }
}
