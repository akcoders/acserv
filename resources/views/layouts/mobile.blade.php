<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#0b2342">
    <meta name="push-public-key" content="{{ config('services.push.public_key') }}">
    <meta name="push-subscription-url" content="{{ route('push-subscriptions.store') }}">
    <link rel="manifest" href="/manifest.webmanifest">
    <title>@yield('title', __('app.name'))</title>
    @vite('resources/js/app.js')
    @stack('head')
</head>
<body class="mobile-app-body">
    @php($isTechnicianPortal = auth()->user()?->role === \App\Enums\Role::Technician)
    <header class="navbar mobile-app-header sticky-top">
        <div class="container-fluid mobile-app-header-inner py-2">
            <a class="navbar-brand d-flex align-items-center gap-2 fw-bold mb-0" href="@yield('dashboard-url')"><span class="mobile-brand-mark"><i class="bi bi-snow2" aria-hidden="true"></i></span><span>{{ __('app.name') }}<small class="d-block fw-medium">{{ $isTechnicianPortal ? 'Field workspace' : 'Customer portal' }}</small></span></a>
            <div class="d-flex align-items-center gap-2">
                <span class="mobile-user-chip d-none d-sm-inline-flex"><i class="bi bi-person-circle me-2" aria-hidden="true"></i>{{ auth()->user()?->name }}</span>
                <button class="btn btn-outline-primary d-none" type="button" data-enable-push aria-label="Enable notifications" title="Enable notifications"><i class="bi bi-bell"></i></button>
                <form method="POST" action="{{ route('logout') }}" data-ajax class="mb-0">
                    @csrf
                    <button class="btn btn-outline-secondary" type="submit" aria-label="Sign out" title="Sign out"><i class="bi bi-box-arrow-right"></i></button>
                </form>
            </div>
        </div>
    </header>
    <main class="container-fluid px-3 px-sm-4 py-4 mobile-app-main">
        @yield('content')
    </main>
    <nav class="mobile-bottom-nav d-md-none" aria-label="Portal navigation">
        <a href="@yield('dashboard-url')"><i class="bi bi-house-door" aria-hidden="true"></i><span>Home</span></a>
        @if($isTechnicianPortal)
            <a href="{{ route('technician.dashboard') }}#assigned-jobs"><i class="bi bi-briefcase" aria-hidden="true"></i><span>Jobs</span></a>
            <a href="{{ route('technician.dashboard') }}#payout-statements"><i class="bi bi-wallet2" aria-hidden="true"></i><span>Payouts</span></a>
        @else
            <a href="{{ route('customer.dashboard') }}#service-pipeline"><i class="bi bi-activity" aria-hidden="true"></i><span>Services</span></a>
            <a href="{{ route('customer.dashboard') }}#invoices"><i class="bi bi-receipt" aria-hidden="true"></i><span>Bills</span></a>
        @endif
    </nav>
    @stack('scripts')
</body>
</html>
