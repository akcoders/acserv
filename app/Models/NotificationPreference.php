<?php

namespace App\Models;

use App\Enums\NotificationChannel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'event', 'channel', 'is_enabled', 'quiet_starts_at', 'quiet_ends_at', 'timezone'])]
class NotificationPreference extends TenantModel
{
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'channel' => NotificationChannel::class,
            'is_enabled' => 'boolean',
        ];
    }
}
