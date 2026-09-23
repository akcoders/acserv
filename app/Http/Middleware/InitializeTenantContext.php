<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Support\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class InitializeTenantContext
{
    public function __construct(private readonly TenantContext $tenantContext) {}

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        abort_unless($user instanceof User && $user->tenant_id !== null, 403);

        $this->tenantContext->set($user->tenant_id);

        try {
            return $next($request);
        } finally {
            $this->tenantContext->clear();
        }
    }
}
