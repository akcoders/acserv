<?php

namespace App\Models;

use App\Enums\CollectionStatus;
use App\Enums\PaymentMode;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['job_id', 'technician_id', 'invoice_id', 'payment_id', 'mode', 'status', 'amount', 'reference', 'proof_path', 'submitted_at', 'reviewed_by', 'reviewed_at', 'rejection_reason'])]
class JobPaymentCollection extends TenantModel
{
    public function job(): BelongsTo
    {
        return $this->belongsTo(Job::class);
    }

    public function technician(): BelongsTo
    {
        return $this->belongsTo(User::class, 'technician_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'mode' => PaymentMode::class,
            'status' => CollectionStatus::class,
            'amount' => 'decimal:2',
            'submitted_at' => 'datetime',
            'reviewed_at' => 'datetime',
        ];
    }
}
