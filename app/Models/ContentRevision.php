<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['content_type', 'content_id', 'version', 'snapshot', 'authored_by'])]
class ContentRevision extends TenantModel
{
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'authored_by');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'snapshot' => 'array',
        ];
    }
}
