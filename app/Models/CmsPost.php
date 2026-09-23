<?php

namespace App\Models;

use App\Enums\ContentStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['author_id', 'featured_media_id', 'slug', 'title', 'excerpt', 'body', 'category', 'tags', 'status', 'meta_title', 'meta_description', 'version', 'published_at'])]
class CmsPost extends TenantModel
{
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function featuredMedia(): BelongsTo
    {
        return $this->belongsTo(MediaAsset::class, 'featured_media_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'tags' => 'array',
            'status' => ContentStatus::class,
            'published_at' => 'datetime',
        ];
    }
}
