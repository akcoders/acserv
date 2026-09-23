<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'period_starts_on', 'period_ends_on', 'completed_jobs', 'average_rating', 'rework_percentage', 'sla_percentage', 'attendance_percentage', 'score', 'metrics', 'computed_at'])]
class TechnicianScorecard extends TenantModel
{
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'period_starts_on' => 'date',
            'period_ends_on' => 'date',
            'average_rating' => 'decimal:2',
            'rework_percentage' => 'decimal:2',
            'sla_percentage' => 'decimal:2',
            'attendance_percentage' => 'decimal:2',
            'score' => 'decimal:2',
            'metrics' => 'array',
            'computed_at' => 'datetime',
        ];
    }
}
