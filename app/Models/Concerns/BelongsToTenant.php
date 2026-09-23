<?php

namespace App\Models\Concerns;

use App\Models\Scopes\TenantScope;
use App\Models\Tenant;
use App\Support\TenantContext;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;
use LogicException;

trait BelongsToTenant
{
    protected static function bootBelongsToTenant(): void
    {
        static::addGlobalScope(app(TenantScope::class));

        static::creating(function (self $model): void {
            if ($model->getAttribute('tenant_id') === null) {
                $tenantId = app(TenantContext::class)->id();

                if ($tenantId === null) {
                    throw new LogicException('A tenant context is required to create this record.');
                }

                $model->setAttribute('tenant_id', $tenantId);
            }

            if ($model->tracksTenantActors() && $model->getAttribute('created_by') === null && Auth::id() !== null) {
                $model->setAttribute('created_by', Auth::id());
            }

            if ($model->tracksTenantActors() && $model->getAttribute('updated_by') === null && Auth::id() !== null) {
                $model->setAttribute('updated_by', Auth::id());
            }
        });

        static::updating(function (self $model): void {
            if ($model->tracksTenantActors() && Auth::id() !== null) {
                $model->setAttribute('updated_by', Auth::id());
            }
        });
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    protected function tracksTenantActors(): bool
    {
        return true;
    }
}
