<?php

namespace App\Models;

use App\Enums\ContentStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['og_media_id', 'slug', 'title', 'excerpt', 'body', 'template', 'status', 'meta_title', 'meta_description', 'meta_keywords', 'version', 'published_at'])]
class CmsPage extends TenantModel
{
    public function ogMedia(): BelongsTo
    {
        return $this->belongsTo(MediaAsset::class, 'og_media_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'status' => ContentStatus::class,
            'meta_keywords' => 'array',
            'published_at' => 'datetime',
        ];
    }
}
