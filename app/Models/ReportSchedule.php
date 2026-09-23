<?php

namespace App\Models;

use App\Enums\ReportFrequency;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'report_type', 'frequency', 'recipients', 'filters', 'is_active', 'next_run_at', 'last_run_at'])]
class ReportSchedule extends TenantModel
{
    public function generatedReports(): HasMany
    {
        return $this->hasMany(GeneratedReport::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'frequency' => ReportFrequency::class,
            'recipients' => 'array',
            'filters' => 'array',
            'is_active' => 'boolean',
            'next_run_at' => 'datetime',
            'last_run_at' => 'datetime',
        ];
    }
}
