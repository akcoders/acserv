<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#0d6efd">
    <link rel="manifest" href="/manifest.webmanifest">

    <title>@yield('title', __('app.name'))</title>

    @vite('resources/js/app.js')
    @stack('head')
</head>
<body>
    <nav class="navbar navbar-expand-lg bg-white border-bottom sticky-top" aria-label="Primary navigation">
        <div class="container py-2">
            <a class="navbar-brand fw-bold text-primary" href="{{ route('home') }}">
                <i class="bi bi-snow2 me-2" aria-hidden="true"></i>{{ __('app.name') }}
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#primaryNavigation" aria-controls="primaryNavigation" aria-expanded="false" aria-label="{{ __('app.toggle_navigation') }}">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="primaryNavigation">
                <ul class="navbar-nav ms-auto align-items-lg-center gap-lg-2">
                    <li class="nav-item"><a class="nav-link" href="{{ route('home') }}#services">Services</a></li>
                    <li class="nav-item"><a class="nav-link" href="{{ route('home') }}#offers">Offers</a></li>
                    <li class="nav-item"><a class="nav-link" href="{{ route('home') }}#blog">Blog</a></li>
                    @auth
                        <li class="nav-item"><a class="btn btn-primary px-4" href="{{ auth()->user()->role === \App\Enums\Role::Technician ? route('technician.dashboard') : (auth()->user()->role === \App\Enums\Role::Customer ? route('customer.dashboard') : route('admin.dashboard')) }}">Dashboard</a></li>
                    @else
                        <li class="nav-item"><a class="btn btn-primary px-4" href="{{ route('login') }}">Sign in</a></li>
                    @endauth
                </ul>
            </div>
        </div>
    </nav>

    <main>
        @yield('content')
    </main>

    <footer class="border-top bg-white py-4">
        <div class="container d-flex flex-column flex-sm-row justify-content-between gap-2 text-secondary small">
            <span>&copy; {{ now()->year }} {{ __('app.name') }}</span>
            <span>{{ __('app.hosting_ready') }}</span>
        </div>
    </footer>

    @stack('scripts')
</body>
</html>
