<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;

#[Fillable(['snapshot_date', 'metric', 'dimensions', 'value', 'metadata'])]
class AnalyticsSnapshot extends TenantModel
{
    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'snapshot_date' => 'date',
            'dimensions' => 'array',
            'value' => 'decimal:4',
            'metadata' => 'array',
        ];
    }
}
