<?php

namespace App\Models;

use App\Enums\ReminderStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['customer_id', 'asset_id', 'warranty_id', 'amc_contract_id', 'type', 'due_on', 'notify_on', 'status', 'last_attempted_at', 'sent_at', 'metadata'])]
class ServiceReminder extends TenantModel
{
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function warranty(): BelongsTo
    {
        return $this->belongsTo(Warranty::class);
    }

    public function amcContract(): BelongsTo
    {
        return $this->belongsTo(AmcContract::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'due_on' => 'date',
            'notify_on' => 'date',
            'status' => ReminderStatus::class,
            'last_attempted_at' => 'datetime',
            'sent_at' => 'datetime',
            'metadata' => 'array',
        ];
    }
}
