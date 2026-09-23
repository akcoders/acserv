<?php

namespace App\Models;

use App\Enums\AuditAction;
use App\Models\Concerns\BelongsToTenant;
use Database\Factories\AuditLogFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['actor_id', 'entity', 'entity_id', 'action', 'diff', 'ip_hash', 'user_agent'])]
class AuditLog extends Model
{
    /** @use HasFactory<AuditLogFactory> */
    use BelongsToTenant, HasFactory, HasUlids;

    public const UPDATED_AT = null;

    protected function tracksTenantActors(): bool
    {
        return false;
    }

    protected function casts(): array
    {
        return [
            'action' => AuditAction::class,
            'diff' => 'array',
        ];
    }
}
