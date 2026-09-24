<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#0b2342">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="push-public-key" content="{{ config('services.push.public_key') }}">
    <meta name="push-subscription-url" content="{{ route('push-subscriptions.store') }}">
    @if(filled(config('services.onesignal.app_id')) && filled(config('services.onesignal.api_key')))
        <meta name="onesignal-app-id" content="{{ config('services.onesignal.app_id') }}">
        <meta name="onesignal-external-id" content="{{ auth()->id() }}">
    @endif
    <link rel="manifest" href="/manifest.webmanifest">
    <link rel="apple-touch-icon" href="/icons/icon-192.png">
    <title>@yield('title', __('app.name'))</title>
    @vite('resources/js/app.js')
    @stack('head')
</head>
@php($isTechnicianPortal = auth()->user()?->role === \App\Enums\Role::Technician)
<body class="mobile-app-body {{ $isTechnicianPortal ? 'technician-portal' : 'customer-portal' }}">
    <header class="navbar mobile-app-header sticky-top">
        <div class="container-fluid mobile-app-header-inner py-2">
            <a class="navbar-brand d-flex align-items-center gap-2 fw-bold mb-0" href="@yield('dashboard-url')"><span class="mobile-brand-mark"><i class="bi bi-snow2" aria-hidden="true"></i></span><span>{{ __('app.name') }}<small class="d-block fw-medium">{{ $isTechnicianPortal ? 'Field workspace' : 'Customer portal' }}</small></span></a>
            <div class="d-flex align-items-center gap-2">
                <span class="mobile-user-chip d-none d-sm-inline-flex"><span class="mobile-user-avatar me-2" aria-hidden="true">{{ str(auth()->user()?->name)->substr(0, 1)->upper() }}</span>{{ auth()->user()?->name }}</span>
                <button class="btn btn-outline-primary d-none" type="button" data-enable-push aria-label="Enable notifications" title="Enable notifications"><i class="bi bi-bell"></i></button>
                <form method="POST" action="{{ route('logout') }}" data-ajax class="mb-0">
                    @csrf
                    <button class="btn mobile-header-action" type="submit" aria-label="Sign out" title="Sign out"><i class="bi bi-box-arrow-right"></i></button>
                </form>
            </div>
        </div>
    </header>
    <main class="container-fluid px-3 px-sm-4 py-4 mobile-app-main">
        @yield('content')
    </main>
    <nav class="mobile-bottom-nav d-md-none" aria-label="Portal navigation">
        <a class="{{ request()->routeIs('technician.dashboard', 'customer.dashboard') ? 'is-current' : '' }}" href="@yield('dashboard-url')" @if(request()->routeIs('technician.dashboard', 'customer.dashboard')) aria-current="page" @endif><i class="bi bi-house-door" aria-hidden="true"></i><span>Home</span></a>
        @if($isTechnicianPortal)
            <a class="{{ request()->routeIs('technician.jobs.*') ? 'is-current' : '' }}" href="{{ route('technician.dashboard') }}#assigned-jobs" @if(request()->routeIs('technician.jobs.*')) aria-current="page" @endif><i class="bi bi-briefcase" aria-hidden="true"></i><span>Jobs</span></a>
            <a href="{{ route('technician.dashboard') }}#payout-statements"><i class="bi bi-wallet2" aria-hidden="true"></i><span>Payouts</span></a>
        @else
            <a href="{{ route('customer.dashboard') }}#service-pipeline"><i class="bi bi-activity" aria-hidden="true"></i><span>Services</span></a>
            <a href="{{ route('customer.dashboard') }}#invoices"><i class="bi bi-receipt" aria-hidden="true"></i><span>Bills</span></a>
        @endif
    </nav>
    <div class="offcanvas offcanvas-bottom mobile-install-sheet" tabindex="-1" id="appInstallSheet" aria-labelledby="appInstallSheetTitle">
        <div class="offcanvas-header pb-1">
            <div class="d-flex align-items-center gap-3"><span class="mobile-install-icon"><i class="bi bi-phone" aria-hidden="true"></i></span><div><div class="small text-primary fw-bold text-uppercase">Quick access</div><h2 class="h5 fw-bold mb-0" id="appInstallSheetTitle">Add ACServ to your phone</h2></div></div>
            <button class="btn-close" type="button" data-bs-dismiss="offcanvas" aria-label="Close"></button>
        </div>
        <div class="offcanvas-body pt-2">
            <div class="d-none" data-install-platform="android"><p class="text-secondary mb-2">On Android Chrome:</p><ol class="mobile-install-steps"><li>Tap Chrome’s <strong>⋮</strong> menu at the top right.</li><li>Choose <strong>Install app</strong> or <strong>Add to Home screen</strong>.</li><li>Confirm <strong>Install</strong>.</li></ol></div>
            <div class="d-none" data-install-platform="ios"><p class="text-secondary mb-2">On iPhone or iPad:</p><ol class="mobile-install-steps"><li>Open this page in <strong>Safari</strong>.</li><li>Tap <strong>Share</strong>, then <strong>Add to Home Screen</strong>.</li><li>Tap <strong>Add</strong>.</li></ol></div>
            <div class="d-none" data-install-platform="desktop"><p class="text-secondary mb-2">On your computer:</p><ol class="mobile-install-steps"><li>Look for the <strong>Install</strong> icon in the browser address bar.</li><li>Or open the browser menu and choose <strong>Install ACServ</strong>.</li></ol></div>
            <button class="btn btn-primary w-100 mt-2" type="button" data-bs-dismiss="offcanvas">Got it</button>
        </div>
    </div>
    @stack('scripts')
</body>
</html>
