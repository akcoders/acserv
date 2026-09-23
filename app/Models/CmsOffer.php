<?php

namespace App\Models;

use App\Enums\ContentStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;

#[Fillable(['title', 'code', 'body', 'discount_type', 'discount_value', 'starts_on', 'ends_on', 'status', 'published_at'])]
class CmsOffer extends TenantModel
{
    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'discount_value' => 'decimal:2',
            'starts_on' => 'date',
            'ends_on' => 'date',
            'status' => ContentStatus::class,
            'published_at' => 'datetime',
        ];
    }
}
