@extends('layouts.app')

@section('title', __('app.name').' — '.__('app.tagline'))

@section('content')
    <section class="py-5 bg-white">
        <div class="container py-lg-5">
            <div class="row align-items-center g-5">
                <div class="col-lg-7">
                    <span class="badge rounded-pill text-bg-primary-subtle text-primary-emphasis px-3 py-2 mb-3">
                        <i class="bi bi-check-circle-fill me-1" aria-hidden="true"></i>
                        {{ __('app.foundation_ready') }}
                    </span>
                    <h1 class="display-3 fw-bold lh-sm mb-4">{{ __('app.tagline') }}</h1>
                    <p class="lead text-secondary mb-4">{{ __('app.description') }}</p>
                    <div class="d-flex flex-wrap gap-3" id="get-started">
                        <button class="btn btn-primary btn-lg px-4" type="button" disabled>
                            <i class="bi bi-building-add me-2" aria-hidden="true"></i>{{ __('app.get_started') }}
                        </button>
                        <span class="d-inline-flex align-items-center text-secondary small">
                            Laravel · MySQL · Bootstrap · jQuery AJAX
                        </span>
                    </div>
                </div>
                <div class="col-lg-5">
                    <div class="content-card card bg-primary text-white overflow-hidden">
                        <div class="card-body p-4 p-xl-5">
                            <div class="d-flex align-items-center justify-content-between mb-5">
                                <span class="fw-semibold">Operations overview</span>
                                <i class="bi bi-snow2 fs-2" aria-hidden="true"></i>
                            </div>
                            <div class="row g-3">
                                <div class="col-6">
                                    <div class="rounded-4 bg-white bg-opacity-10 p-3">
                                        <div class="small opacity-75">Today’s jobs</div>
                                        <div class="fs-2 fw-bold">—</div>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="rounded-4 bg-white bg-opacity-10 p-3">
                                        <div class="small opacity-75">Technicians</div>
                                        <div class="fs-2 fw-bold">—</div>
                                    </div>
                                </div>
                                <div class="col-12">
                                    <div class="rounded-4 bg-white bg-opacity-10 p-3">
                                        <div class="d-flex justify-content-between small mb-2">
                                            <span>Phase 1 foundation</span>
                                            <span>In progress</span>
                                        </div>
                                        <div class="progress" role="progressbar" aria-label="Phase 1 progress" aria-valuenow="20" aria-valuemin="0" aria-valuemax="100">
                                            <div class="progress-bar bg-info" style="width: 20%"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="py-5" id="features">
        <div class="container py-lg-4">
            <div class="row g-4">
                @foreach (__('app.modules') as $key => $module)
                    <div class="col-md-6 col-xl-3">
                        <article class="content-card card h-100">
                            <div class="card-body p-4">
                                <span class="d-inline-flex align-items-center justify-content-center rounded-3 bg-primary-subtle text-primary fs-4 mb-4" style="width: 3rem; height: 3rem">
                                    <i class="bi bi-{{ match ($key) { 'customers' => 'people', 'jobs' => 'clipboard-check', 'billing' => 'receipt', default => 'person-badge' } }}" aria-hidden="true"></i>
                                </span>
                                <h2 class="h5 mb-0">{{ $module }}</h2>
                            </div>
                        </article>
                    </div>
                @endforeach
            </div>
        </div>
    </section>
@endsection
