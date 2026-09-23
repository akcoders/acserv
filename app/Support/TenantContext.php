<?php

namespace App\Support;

class TenantContext
{
    private ?string $tenantId = null;

    public function set(string $tenantId): void
    {
        $this->tenantId = $tenantId;
    }

    public function clear(): void
    {
        $this->tenantId = null;
    }

    public function hasTenant(): bool
    {
        return $this->tenantId !== null;
    }

    public function id(): ?string
    {
        return $this->tenantId;
    }
}
