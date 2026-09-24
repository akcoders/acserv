<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#0b1932">
    <link rel="manifest" href="/manifest.webmanifest">

    <title>@yield('title', __('app.name'))</title>

    @vite(['resources/js/app.js', 'resources/css/public-site.css'])
    @stack('head')
</head>
<body class="public-site">
    <a class="public-skip-link" href="#main-content">Skip to content</a>

    <div class="public-topbar">
        <div class="container d-flex align-items-center justify-content-between gap-3">
            <span><i class="bi bi-stars me-2" aria-hidden="true"></i>Thoughtful AC care, from booking to sign-off.</span>
            <a href="{{ route('home') }}#how-it-works">See how it works <i class="bi bi-arrow-up-right ms-1" aria-hidden="true"></i></a>
        </div>
    </div>

    <nav class="navbar navbar-expand-lg public-navbar sticky-top" aria-label="Primary navigation">
        <div class="container">
            <a class="navbar-brand public-brand d-inline-flex align-items-center gap-2" href="{{ route('home') }}" aria-label="{{ __('app.name') }} home">
                <span class="public-brand-mark"><i class="bi bi-snow2" aria-hidden="true"></i></span>
                <span class="public-brand-name"><span>ACServ<span class="public-brand-dot">.</span></span><small>Care you can count on</small></span>
            </a>

            <button class="navbar-toggler public-navbar-toggle" type="button" data-bs-toggle="collapse" data-bs-target="#primaryNavigation" aria-controls="primaryNavigation" aria-expanded="false" aria-label="{{ __('app.toggle_navigation') }}">
                <span class="navbar-toggler-icon"></span>
            </button>

            <div class="collapse navbar-collapse" id="primaryNavigation">
                <ul class="navbar-nav ms-auto align-items-lg-center gap-lg-3">
                    <li class="nav-item"><a class="nav-link" href="{{ route('home') }}#services">Services</a></li>
                    <li class="nav-item"><a class="nav-link" href="{{ route('home') }}#how-it-works">How it works</a></li>
                    <li class="nav-item"><a class="nav-link" href="{{ route('home') }}#book-service">Contact</a></li>
                    @auth
                        <li class="nav-item"><a class="btn public-nav-cta" href="{{ auth()->user()->role === \App\Enums\Role::Technician ? route('technician.dashboard') : (auth()->user()->role === \App\Enums\Role::Customer ? route('customer.dashboard') : route('admin.dashboard')) }}">Go to dashboard <i class="bi bi-arrow-up-right ms-1" aria-hidden="true"></i></a></li>
                    @else
                        <li class="nav-item"><a class="btn public-nav-cta" href="{{ route('login') }}">Customer sign in <i class="bi bi-arrow-up-right ms-1" aria-hidden="true"></i></a></li>
                    @endauth
                </ul>
            </div>
        </div>
    </nav>

    <main id="main-content">
        @yield('content')
    </main>

    <footer class="public-footer">
        <div class="container">
            <div class="row gy-5 justify-content-between public-footer-main">
                <div class="col-lg-5">
                    <a class="public-footer-brand d-inline-flex align-items-center gap-2 text-decoration-none" href="{{ route('home') }}">
                        <span class="public-brand-mark"><i class="bi bi-snow2" aria-hidden="true"></i></span>
                        <strong>ACServ<span class="public-brand-dot">.</span></strong>
                    </a>
                    <p class="mt-4 mb-0">Reliable AC service with a clear record of every visit, from the first request to the final sign-off.</p>
                </div>
                <div class="col-sm-6 col-lg-3">
                    <h2 class="public-footer-heading">Explore</h2>
                    <ul class="list-unstyled mb-0">
                        <li><a href="{{ route('home') }}#services">Our services</a></li>
                        <li><a href="{{ route('home') }}#how-it-works">How it works</a></li>
                        <li><a href="{{ route('home') }}#book-service">Request a callback</a></li>
                    </ul>
                </div>
                <div class="col-sm-6 col-lg-3">
                    <h2 class="public-footer-heading">Your account</h2>
                    <ul class="list-unstyled mb-0">
                        <li><a href="{{ route('login') }}">Sign in</a></li>
                        <li><a href="{{ route('home') }}#book-service">Get service help</a></li>
                    </ul>
                </div>
            </div>
            <div class="public-footer-bottom d-flex flex-column flex-sm-row justify-content-between gap-2">
                <span>&copy; {{ now()->year }} {{ __('app.name') }}. All rights reserved.</span>
                <span>Built for better service, one visit at a time.</span>
            </div>
        </div>
    </footer>

    @stack('scripts')
</body>
</html>
