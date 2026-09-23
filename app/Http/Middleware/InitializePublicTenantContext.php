<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use App\Support\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class InitializePublicTenantContext
{
    public function __construct(private readonly TenantContext $tenantContext) {}

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $tenant = Tenant::query()
            ->where('slug', config('acserv.public_tenant_slug'))
            ->where('status', 'ACTIVE')
            ->firstOrFail();

        $this->tenantContext->set($tenant->getKey());

        try {
            return $next($request);
        } finally {
            $this->tenantContext->clear();
        }
    }
}
