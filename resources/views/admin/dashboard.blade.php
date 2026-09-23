@extends('layouts.admin')

@section('title', 'Dashboard — '.__('app.name'))

@section('content')
    <section class="admin-hero mb-4" aria-labelledby="dashboard-heading">
        <div class="admin-hero-orb" aria-hidden="true"></div>
        <div class="position-relative d-flex flex-wrap align-items-end justify-content-between gap-4">
            <div>
                <div class="admin-eyebrow text-white-50 mb-2">{{ $tenantName }} · Live workspace</div>
                <h1 class="display-6 fw-bold mb-2" id="dashboard-heading">Operations command center</h1>
                <p class="text-white-50 mb-0">Track every service visit from dispatch to payment in one view.</p>
            </div>
            <div class="d-flex flex-wrap gap-2">
                @if (auth()->user()->role->canManageJobs())
                    <a class="btn btn-light" href="{{ route('admin.jobs.index') }}"><i class="bi bi-kanban me-2" aria-hidden="true"></i>Open job pipeline</a>
                    <a class="btn btn-outline-light" href="{{ route('admin.bookings.index') }}"><i class="bi bi-calendar2-week me-2" aria-hidden="true"></i>Bookings</a>
                @elseif (auth()->user()->role->canManageBilling())
                    <a class="btn btn-light" href="{{ route('admin.billing.index') }}"><i class="bi bi-receipt me-2" aria-hidden="true"></i>Open billing</a>
                @endif
            </div>
        </div>
    </section>

    <div class="row g-3 g-xl-4 mb-4">
        <div class="col-sm-6 col-xxl-3">
            <article class="card content-card metric-card metric-card-primary h-100">
                <div class="card-body p-4">
                    <div class="metric-icon"><i class="bi bi-activity" aria-hidden="true"></i></div>
                    <div class="metric-label">Active jobs</div>
                    <div class="metric-value">{{ number_format($analytics['jobs']['active']) }}</div>
                    <div class="metric-context">{{ number_format($todayJobs) }} scheduled today</div>
                </div>
            </article>
        </div>
        <div class="col-sm-6 col-xxl-3">
            <article class="card content-card metric-card metric-card-warning h-100">
                <div class="card-body p-4">
                    <div class="metric-icon"><i class="bi bi-exclamation-triangle" aria-hidden="true"></i></div>
                    <div class="metric-label">Needs attention</div>
                    <div class="metric-value">{{ number_format($analytics['jobs']['overdue']) }}</div>
                    <div class="metric-context">Jobs past their scheduled time</div>
                </div>
            </article>
        </div>
        <div class="col-sm-6 col-xxl-3">
            <article class="card content-card metric-card metric-card-success h-100">
                <div class="card-body p-4">
                    <div class="metric-icon"><i class="bi bi-check2-circle" aria-hidden="true"></i></div>
                    <div class="metric-label">Completed this month</div>
                    <div class="metric-value">{{ number_format($analytics['jobs']['completed_this_month']) }}</div>
                    <div class="metric-context">Work finished by technicians</div>
                </div>
            </article>
        </div>
        <div class="col-sm-6 col-xxl-3">
            <article class="card content-card metric-card metric-card-violet h-100">
                <div class="card-body p-4">
                    <div class="metric-icon"><i class="bi {{ auth()->user()->role->canManageBilling() ? 'bi-currency-rupee' : 'bi-people' }}" aria-hidden="true"></i></div>
                    @if (auth()->user()->role->canManageBilling())
                        <div class="metric-label">Outstanding</div>
                        <div class="metric-value">₹{{ number_format($analytics['revenue']['outstanding'], 0) }}</div>
                        <div class="metric-context">Open invoice balance</div>
                    @else
                        <div class="metric-label">Customers</div>
                        <div class="metric-value">{{ number_format($analytics['customers']['total']) }}</div>
                        <div class="metric-context">{{ number_format($analytics['customers']['new_this_month']) }} new this month</div>
                    @endif
                </div>
            </article>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-xxl-8">
            <section class="card content-card h-100" aria-labelledby="pipeline-heading">
                <div class="card-body p-4">
                    <div class="d-flex flex-wrap align-items-start justify-content-between gap-2 mb-4">
                        <div>
                            <p class="admin-eyebrow text-primary mb-1">Live operations</p>
                            <h2 class="h4 mb-1" id="pipeline-heading">Job pipeline</h2>
                            <p class="text-secondary small mb-0">Every technician action moves a job to its next stage.</p>
                        </div>
                        @if (auth()->user()->role->canManageJobs())
                            <a class="btn btn-sm btn-outline-primary" href="{{ route('admin.jobs.index') }}">View board <i class="bi bi-arrow-up-right ms-1" aria-hidden="true"></i></a>
                        @endif
                    </div>
                    @php($totalJobs = max(1, array_sum($statusCounts)))
                    <div class="pipeline-overview mb-4" role="img" aria-label="Distribution of jobs across pipeline stages">
                        @foreach ($pipelineStatuses as $status)
                            @if (($statusCounts[$status->value] ?? 0) > 0)
                                <span class="pipeline-overview-segment" data-status="{{ $status->value }}" style="width: {{ (($statusCounts[$status->value] ?? 0) / $totalJobs) * 100 }}%"></span>
                            @endif
                        @endforeach
                    </div>
                    <div class="row row-cols-2 row-cols-md-3 row-cols-xl-4 g-3">
                        @foreach ($pipelineStatuses as $status)
                            <div class="col">
                                <div class="pipeline-stat" data-status="{{ $status->value }}">
                                    <span class="pipeline-stat-dot" aria-hidden="true"></span>
                                    <span class="text-truncate">{{ str($status->value)->lower()->headline() }}</span>
                                    <strong class="ms-auto">{{ number_format($statusCounts[$status->value] ?? 0) }}</strong>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </section>
        </div>
        <div class="col-xxl-4">
            <section class="card content-card h-100" aria-labelledby="pulse-heading">
                <div class="card-body p-4">
                    <p class="admin-eyebrow text-primary mb-1">Business pulse</p>
                    <h2 class="h4 mb-4" id="pulse-heading">At a glance</h2>
                    @if (auth()->user()->role->canManageBilling())
                        <div class="pulse-line"><span>Invoiced this month</span><strong>₹{{ number_format($analytics['revenue']['invoiced_this_month'], 0) }}</strong></div>
                        <div class="pulse-line"><span>Collected this month</span><strong>₹{{ number_format($analytics['revenue']['collected_this_month'], 0) }}</strong></div>
                        @php($collectionPercent = $analytics['revenue']['invoiced_this_month'] > 0 ? min(100, round(($analytics['revenue']['collected_this_month'] / $analytics['revenue']['invoiced_this_month']) * 100)) : 0)
                        <div class="d-flex justify-content-between small mt-3 mb-2"><span class="text-secondary">Collection progress</span><strong>{{ $collectionPercent }}%</strong></div>
                        <div class="progress admin-progress" role="progressbar" aria-label="Collection progress" aria-valuenow="{{ $collectionPercent }}" aria-valuemin="0" aria-valuemax="100"><div class="progress-bar" style="width: {{ $collectionPercent }}%"></div></div>
                        <hr class="my-4">
                    @endif
                    <div class="pulse-line"><span>Technicians</span><strong>{{ number_format($analytics['workforce']['technicians']) }}</strong></div>
                    <div class="pulse-line"><span>Present today</span><strong>{{ number_format($analytics['workforce']['present_today']) }}</strong></div>
                    <div class="pulse-line"><span>Inventory items</span><strong>{{ number_format($analytics['inventory']['items']) }}</strong></div>
                    <div class="pulse-line"><span>New customers</span><strong>{{ number_format($analytics['customers']['new_this_month']) }}</strong></div>
                </div>
            </section>
        </div>
    </div>

    <section class="card content-card" aria-labelledby="recent-jobs-heading">
        <div class="card-header bg-transparent border-0 px-4 pt-4 pb-2 d-flex justify-content-between align-items-center gap-2">
            <div><p class="admin-eyebrow text-primary mb-1">Latest activity</p><h2 class="h4 mb-0" id="recent-jobs-heading">Recent jobs</h2></div>
            @if (auth()->user()->role->canManageJobs())
                <a class="btn btn-sm btn-outline-primary" href="{{ route('admin.jobs.index') }}">All jobs</a>
            @endif
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" data-rich-table="server">
                <thead><tr><th>Job</th><th>Customer</th><th>Technician</th><th>Schedule</th><th>Status</th></tr></thead>
                <tbody>
                    @forelse ($recentJobs as $job)
                        <tr>
                            <td><strong>{{ $job->job_number }}</strong><div class="small text-secondary">{{ $job->service_type }}</div></td>
                            <td>{{ $job->customer?->name ?? '—' }}</td>
                            <td>{{ $job->assignments->pluck('technician.name')->filter()->join(', ') ?: 'Unassigned' }}</td>
                            <td data-sort="{{ $job->scheduled_at?->timestamp ?? 0 }}">{{ $job->scheduled_at?->format('d M Y, h:i A') ?? 'Unscheduled' }}</td>
                            <td><span class="job-status-pill" data-status="{{ $job->status->value }}">{{ str($job->status->value)->lower()->headline() }}</span></td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center text-secondary py-5">No jobs yet. Create a booking or a job to start the pipeline.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
@endsection
