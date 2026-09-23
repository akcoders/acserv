<?php

namespace App\Models;

use App\Enums\ReportStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['report_schedule_id', 'requested_by', 'report_type', 'status', 'period_starts_on', 'period_ends_on', 'parameters', 'disk', 'path', 'mime_type', 'error_message', 'completed_at'])]
class GeneratedReport extends TenantModel
{
    public function schedule(): BelongsTo
    {
        return $this->belongsTo(ReportSchedule::class, 'report_schedule_id');
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'status' => ReportStatus::class,
            'period_starts_on' => 'date',
            'period_ends_on' => 'date',
            'parameters' => 'array',
            'completed_at' => 'datetime',
        ];
    }
}
