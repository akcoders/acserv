<?php

namespace App\Models;

use App\Enums\ContentStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['media_asset_id', 'slug', 'title', 'summary', 'body', 'starting_price', 'duration_minutes', 'status', 'sort_order', 'published_at'])]
class CmsService extends TenantModel
{
    public function mediaAsset(): BelongsTo
    {
        return $this->belongsTo(MediaAsset::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'starting_price' => 'decimal:2',
            'status' => ContentStatus::class,
            'published_at' => 'datetime',
        ];
    }
}
