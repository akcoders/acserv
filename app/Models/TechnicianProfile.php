<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['user_id', 'branch_id', 'skills', 'service_zones', 'is_available', 'home_latitude', 'home_longitude', 'work_starts_at', 'work_ends_at', 'max_daily_jobs'])]
class TechnicianProfile extends TenantModel
{
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(JobAssignment::class, 'technician_id', 'user_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'skills' => 'array',
            'service_zones' => 'array',
            'is_available' => 'boolean',
            'home_latitude' => 'decimal:7',
            'home_longitude' => 'decimal:7',
        ];
    }
}
