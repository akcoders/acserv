<?php

namespace App\Models;

use App\Enums\NotificationChannel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['event', 'channel', 'locale', 'subject', 'body', 'variables', 'is_active'])]
class NotificationTemplate extends TenantModel
{
    public function logs(): HasMany
    {
        return $this->hasMany(NotificationLog::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'channel' => NotificationChannel::class,
            'variables' => 'array',
            'is_active' => 'boolean',
        ];
    }
}
