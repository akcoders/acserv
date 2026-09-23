<?php

namespace App\Http\Middleware;

use App\Enums\Role;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureRole
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();
        $allowedRoles = array_filter(array_map(
            fn (string $role): ?Role => Role::tryFrom(mb_strtoupper($role)),
            $roles,
        ));

        abort_unless(
            $user instanceof User && $user->role instanceof Role && in_array($user->role, $allowedRoles, true),
            403,
        );

        return $next($request);
    }
}
