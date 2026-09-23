<?php

use App\Enums\Role;
use App\Http\Middleware\EnsureRole;
use App\Http\Middleware\InitializePublicTenantContext;
use App\Http\Middleware\InitializeTenantContext;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->append(SecurityHeaders::class);
        $middleware->preventRequestForgery(except: ['payments/razorpay/webhook']);
        $middleware->redirectGuestsTo(fn (Request $request): string => route('login'));
        $middleware->redirectUsersTo(fn (Request $request): string => match ($request->user()?->role) {
            Role::Technician => route('technician.dashboard'),
            Role::Customer => route('customer.dashboard'),
            default => route('admin.dashboard'),
        });
        $middleware->alias([
            'tenant' => InitializeTenantContext::class,
            'public.tenant' => InitializePublicTenantContext::class,
            'role' => EnsureRole::class,
        ]);
        $middleware->prependToPriorityList(
            SubstituteBindings::class,
            InitializeTenantContext::class,
        );
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
