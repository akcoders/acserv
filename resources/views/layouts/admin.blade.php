<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#10243d">
    <link rel="manifest" href="/manifest.webmanifest">
    <title>@yield('title', __('app.name'))</title>
    @vite('resources/js/app.js')
    @stack('head')
</head>
<body class="admin-body">
    <div class="app-shell d-lg-flex">
        <aside class="app-sidebar offcanvas-lg offcanvas-start text-white flex-shrink-0" tabindex="-1" id="adminSidebar" aria-labelledby="adminSidebarTitle">
            <div class="offcanvas-body d-flex flex-column p-3 p-lg-4">
                <div class="d-flex align-items-center justify-content-between mb-4">
                    <a class="admin-brand d-flex align-items-center gap-3 text-white text-decoration-none" href="{{ route('admin.dashboard') }}">
                        <span class="admin-brand-mark"><i class="bi bi-snow2" aria-hidden="true"></i></span>
                        <span><strong class="d-block lh-sm" id="adminSidebarTitle">{{ __('app.name') }}</strong><small class="text-white-50">Service ERP</small></span>
                    </a>
                    <button type="button" class="btn-close btn-close-white d-lg-none" data-bs-dismiss="offcanvas" data-bs-target="#adminSidebar" aria-label="Close menu"></button>
                </div>
                <div class="admin-workspace-chip mb-4"><span class="admin-workspace-icon"><i class="bi bi-buildings" aria-hidden="true"></i></span><span class="min-width-0"><small class="d-block text-white-50">Workspace</small><strong class="d-block text-truncate">{{ auth()->user()->tenant?->name ?? __('app.name') }}</strong></span><i class="bi bi-chevron-down ms-auto small text-white-50" aria-hidden="true"></i></div>
                <nav class="nav nav-pills flex-column gap-1 admin-nav" aria-label="Admin navigation">
                    <span class="admin-nav-label">Overview</span>
                    <a class="nav-link {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}" href="{{ route('admin.dashboard') }}"><i class="bi bi-grid-1x2" aria-hidden="true"></i>Dashboard</a>
                    @if (auth()->user()->role->canManageJobs())
                        <span class="admin-nav-label mt-3">Operations</span>
                        <a class="nav-link {{ request()->routeIs('admin.bookings.*') ? 'active' : '' }}" href="{{ route('admin.bookings.index') }}"><i class="bi bi-calendar2-check" aria-hidden="true"></i>Bookings</a>
                        <a class="nav-link {{ request()->routeIs('admin.jobs.*') ? 'active' : '' }}" href="{{ route('admin.jobs.index') }}"><i class="bi bi-kanban" aria-hidden="true"></i>Job pipeline</a>
                    @endif
                    @if (auth()->user()->role->canManageInventory() || auth()->user()->role->canManageBilling())
                        <span class="admin-nav-label mt-3">Commerce</span>
                    @endif
                    @if (auth()->user()->role->canManageInventory())
                        <a class="nav-link {{ request()->routeIs('admin.inventory.*') ? 'active' : '' }}" href="{{ route('admin.inventory.index') }}"><i class="bi bi-box-seam" aria-hidden="true"></i>Inventory</a>
                        <a class="nav-link {{ request()->routeIs('admin.purchases.*', 'admin.vendors.*') ? 'active' : '' }}" href="{{ route('admin.purchases.index') }}"><i class="bi bi-bag-check" aria-hidden="true"></i>Purchases</a>
                    @endif
                    @if (auth()->user()->role->canManageBilling())
                        <a class="nav-link {{ request()->routeIs('admin.billing.*', 'admin.invoices.*', 'admin.payments.*') ? 'active' : '' }}" href="{{ route('admin.billing.index') }}"><i class="bi bi-receipt-cutoff" aria-hidden="true"></i>Billing</a>
                        <a class="nav-link {{ request()->routeIs('admin.accounts.*', 'admin.account-entries.*') ? 'active' : '' }}" href="{{ route('admin.accounts.index') }}"><i class="bi bi-wallet2" aria-hidden="true"></i>Accounts &amp; P/L</a>
                        <a class="nav-link {{ request()->routeIs('admin.warranties.*') ? 'active' : '' }}" href="{{ route('admin.warranties.index') }}"><i class="bi bi-patch-check" aria-hidden="true"></i>Warranty</a>
                        <a class="nav-link {{ request()->routeIs('admin.analytics.*') ? 'active' : '' }}" href="{{ route('admin.analytics.index') }}"><i class="bi bi-bar-chart-line" aria-hidden="true"></i>Analytics</a>
                    @endif
                    @if (auth()->user()->role->canManageWorkforce() || auth()->user()->role->canManageContent())
                        <span class="admin-nav-label mt-3">Management</span>
                    @endif
                    @if (auth()->user()->role->canManageWorkforce())
                        <a class="nav-link {{ request()->routeIs('admin.workforce.*') ? 'active' : '' }}" href="{{ route('admin.workforce.index') }}"><i class="bi bi-people" aria-hidden="true"></i>Workforce</a>
                        <a class="nav-link {{ request()->routeIs('admin.notifications.*') ? 'active' : '' }}" href="{{ route('admin.notifications.index') }}"><i class="bi bi-bell" aria-hidden="true"></i>Notifications</a>
                        <a class="nav-link {{ request()->routeIs('admin.feedback.*') ? 'active' : '' }}" href="{{ route('admin.feedback.index') }}"><i class="bi bi-chat-heart" aria-hidden="true"></i>Customer feedback</a>
                    @endif
                    @if (auth()->user()->role->canManageContent())
                        <a class="nav-link {{ request()->routeIs('admin.cms.*') ? 'active' : '' }}" href="{{ route('admin.cms.index') }}"><i class="bi bi-layout-text-window" aria-hidden="true"></i>Website CMS</a>
                    @endif
                    @if (auth()->user()->role === \App\Enums\Role::Owner && auth()->user()->tenant?->slug === config('acserv.public_tenant_slug'))
                        <span class="admin-nav-label mt-3">System</span>
                        <a class="nav-link {{ request()->routeIs('admin.system-updates.*') ? 'active' : '' }}" href="{{ route('admin.system-updates.index') }}"><i class="bi bi-cloud-arrow-up" aria-hidden="true"></i>Application updates</a>
                    @endif
                </nav>
                <div class="admin-sidebar-footer mt-auto pt-4">
                    <div class="d-flex align-items-center gap-3 mb-3"><span class="admin-avatar">{{ str(auth()->user()->first_name)->substr(0, 1)->upper() }}</span><span class="min-width-0"><strong class="d-block text-truncate">{{ auth()->user()->name }}</strong><small class="d-block text-white-50">{{ str(auth()->user()->role->value)->headline() }}</small></span></div>
                    <form method="POST" action="{{ route('logout') }}" data-ajax>@csrf<button class="btn btn-outline-light w-100" type="submit"><i class="bi bi-box-arrow-right me-2" aria-hidden="true"></i>Sign out</button></form>
                </div>
            </div>
        </aside>
        <div class="admin-main flex-grow-1 min-width-0">
            <header class="admin-topbar d-flex align-items-center justify-content-between gap-3 px-3 px-lg-5">
                <div class="d-flex align-items-center gap-3"><button class="btn btn-outline-secondary d-lg-none" type="button" data-bs-toggle="offcanvas" data-bs-target="#adminSidebar" aria-controls="adminSidebar" aria-label="Open menu"><i class="bi bi-list fs-5" aria-hidden="true"></i></button><span class="admin-topbar-kicker">Service operations / {{ auth()->user()->tenant?->name ?? __('app.name') }}</span></div>
                <div class="d-flex align-items-center gap-3"><span class="small text-secondary d-none d-md-inline">{{ now()->format('l, d M Y') }}</span><span class="admin-topbar-avatar" title="{{ auth()->user()->name }}">{{ str(auth()->user()->first_name)->substr(0, 1)->upper() }}</span></div>
            </header>
            <main class="admin-content p-3 p-lg-5">
                @yield('content')
            </main>
        </div>
    </div>
    @stack('scripts')
</body>
</html>
