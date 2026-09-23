<?php

namespace App\Services\Auditing;

use App\Enums\AuditAction;
use App\Models\AuditLog;
use App\Models\TenantModel;
use App\Support\TenantContext;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;

class AuditService
{
    /** @var array<int, string> */
    private const SENSITIVE_FIELDS = [
        'password',
        'remember_token',
        'token',
        'token_hash',
        'auth_token',
        'public_key',
        'provider_signature_hash',
    ];

    public function __construct(private readonly TenantContext $tenantContext) {}

    public function created(TenantModel $model): void
    {
        $this->record($model, AuditAction::Create, ['after' => $model->getAttributes()]);
    }

    public function updated(TenantModel $model): void
    {
        $changes = Arr::except($model->getChanges(), ['updated_at']);

        if ($changes === []) {
            return;
        }

        $before = [];

        foreach (array_keys($changes) as $attribute) {
            $before[$attribute] = $model->getOriginal($attribute);
        }

        $this->record($model, AuditAction::Update, [
            'before' => $before,
            'after' => $changes,
        ]);
    }

    public function deleted(TenantModel $model): void
    {
        $this->record($model, AuditAction::Delete);
    }

    public function restored(TenantModel $model): void
    {
        $this->record($model, AuditAction::Restore);
    }

    /** @param array<string, mixed> $diff */
    private function record(TenantModel $model, AuditAction $action, array $diff = []): void
    {
        if ($this->tenantContext->id() === null) {
            return;
        }

        AuditLog::query()->create([
            'actor_id' => Auth::id(),
            'entity' => $model::class,
            'entity_id' => (string) $model->getKey(),
            'action' => $action,
            'diff' => $this->redact($diff),
            'ip_hash' => request()->ip() === null ? null : hash('sha256', request()->ip()),
            'user_agent' => mb_substr((string) request()->userAgent(), 0, 512),
        ]);
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    private function redact(array $values): array
    {
        foreach ($values as $key => $value) {
            if (in_array($key, self::SENSITIVE_FIELDS, true)) {
                $values[$key] = '[REDACTED]';
            } elseif (is_array($value)) {
                $values[$key] = $this->redact($value);
            }
        }

        return $values;
    }
}
