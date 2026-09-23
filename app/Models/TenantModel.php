<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Services\Auditing\AuditService;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

abstract class TenantModel extends Model
{
    use BelongsToTenant, HasFactory, HasUlids, SoftDeletes;

    protected static function booted(): void
    {
        static::created(fn (TenantModel $model) => app(AuditService::class)->created($model));
        static::updated(fn (TenantModel $model) => app(AuditService::class)->updated($model));
        static::deleted(fn (TenantModel $model) => app(AuditService::class)->deleted($model));
        static::restored(fn (TenantModel $model) => app(AuditService::class)->restored($model));
    }
}
