<?php

namespace App\Services\Security;

use App\Models\SecurityEvent;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Http\Request;

class SecurityEventRecorder
{
    public function __construct(private readonly TenantContext $tenantContext) {}

    /** @param array<string, mixed> $metadata */
    public function record(User $user, Request $request, string $eventType, string $severity = 'INFO', array $metadata = []): void
    {
        $previousTenant = $this->tenantContext->id();
        $this->tenantContext->set($user->tenant_id);

        try {
            SecurityEvent::query()->create([
                'user_id' => $user->getKey(),
                'event_type' => $eventType,
                'severity' => $severity,
                'ip_hash' => $request->ip() === null ? null : hash_hmac('sha256', $request->ip(), (string) config('app.key')),
                'user_agent' => mb_substr((string) $request->userAgent(), 0, 512),
                'metadata' => $metadata,
                'occurred_at' => now(),
            ]);
        } finally {
            if ($previousTenant === null) {
                $this->tenantContext->clear();
            } else {
                $this->tenantContext->set($previousTenant);
            }
        }
    }
}
