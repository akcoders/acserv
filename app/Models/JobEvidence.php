<?php

namespace App\Models;

use App\Enums\EvidenceType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['job_id', 'job_assignment_id', 'uploaded_by', 'type', 'disk', 'path', 'mime_type', 'size_bytes', 'sha256', 'latitude', 'longitude', 'accuracy_metres', 'captured_at', 'device_id', 'is_mock_location', 'metadata'])]
class JobEvidence extends TenantModel
{
    public function job(): BelongsTo
    {
        return $this->belongsTo(Job::class);
    }

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(JobAssignment::class, 'job_assignment_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'type' => EvidenceType::class,
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'accuracy_metres' => 'decimal:2',
            'captured_at' => 'datetime',
            'is_mock_location' => 'boolean',
            'metadata' => 'array',
        ];
    }
}
