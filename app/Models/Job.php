<?php

namespace App\Models;

use App\Enums\JobStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['booking_id', 'customer_id', 'asset_id', 'branch_id', 'job_number', 'status', 'priority', 'service_type', 'description', 'resolution', 'service_address', 'scheduled_at', 'estimated_minutes', 'reached_at', 'inspected_at', 'authorized_at', 'started_at', 'completed_at', 'verified_at', 'closed_at', 'cancelled_at', 'inspection_remark', 'completion_remark', 'service_cost', 'service_tax_rate', 'start_latitude', 'start_longitude', 'end_latitude', 'end_longitude', 'prework_signature_path', 'customer_signature_path'])]
class Job extends TenantModel
{
    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(JobAssignment::class);
    }

    public function evidence(): HasMany
    {
        return $this->hasMany(JobEvidence::class);
    }

    public function checklistItems(): HasMany
    {
        return $this->hasMany(JobChecklistItem::class);
    }

    public function partConsumptions(): HasMany
    {
        return $this->hasMany(JobPartConsumption::class);
    }

    public function invoice(): HasOne
    {
        return $this->hasOne(Invoice::class);
    }

    public function feedback(): HasOne
    {
        return $this->hasOne(Feedback::class);
    }

    public function paymentCollections(): HasMany
    {
        return $this->hasMany(JobPaymentCollection::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'status' => JobStatus::class,
            'service_address' => 'array',
            'scheduled_at' => 'datetime',
            'reached_at' => 'datetime',
            'inspected_at' => 'datetime',
            'authorized_at' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'verified_at' => 'datetime',
            'closed_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'start_latitude' => 'decimal:7',
            'start_longitude' => 'decimal:7',
            'end_latitude' => 'decimal:7',
            'end_longitude' => 'decimal:7',
            'service_cost' => 'decimal:2',
            'service_tax_rate' => 'decimal:2',
        ];
    }
}
