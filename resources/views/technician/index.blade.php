@extends('layouts.mobile')

@section('title', 'Technician — '.__('app.name'))
@section('dashboard-url', route('technician.dashboard'))

@php
    $waitingCount = $jobs->filter(fn ($job) => $job->status->value === 'ASSIGNED')->count();
    $workingCount = $jobs->filter(fn ($job) => in_array($job->status->value, ['ACCEPTED', 'EN_ROUTE', 'REACHED', 'INSPECTED', 'AUTHORIZED', 'IN_PROGRESS'], true))->count();
    $completedCount = $jobs->filter(fn ($job) => in_array($job->status->value, ['COMPLETED', 'VERIFIED'], true))->count();
    $todayCount = $jobs->filter(fn ($job) => $job->scheduled_at?->isToday())->count();
    $nextJob = $jobs->first(fn ($job) => ! in_array($job->status->value, ['COMPLETED', 'VERIFIED'], true));
    $firstName = trim((string) str(auth()->user()?->name)->before(' '));
    $greeting = match (true) { now()->hour < 12 => 'Good morning', now()->hour < 17 => 'Good afternoon', default => 'Good evening' };
@endphp

@push('head')
<style>
    .technician-home .home-hero { position: relative; border: 0; background: radial-gradient(circle at 90% 10%, rgba(119, 231, 242, .28), transparent 24rem), linear-gradient(125deg, #09213d 0%, #104f88 64%, #147d9d 100%); color: #fff; box-shadow: 0 1.25rem 2.75rem rgba(9, 50, 88, .19); }
    .technician-home .home-hero::after { content: ''; position: absolute; width: 18rem; height: 18rem; border: 1px solid rgba(255,255,255,.15); border-radius: 50%; right: -5rem; top: -9rem; box-shadow: 0 0 0 4rem rgba(255,255,255,.035); pointer-events: none; }
    .technician-home .home-hero .card-body { position: relative; z-index: 1; }
    .technician-home .home-hero .hero-muted { color: rgba(255, 255, 255, .75); }
    .technician-home .hero-kicker { letter-spacing: .14em; color: #91e9ff; }
    .technician-home .hero-stat { border: 1px solid rgba(255,255,255,.21); background: rgba(255,255,255,.1); backdrop-filter: blur(5px); }
    .technician-home .hero-stat-icon { display: inline-grid; width: 2rem; height: 2rem; place-items: center; border-radius: .6rem; background: rgba(255,255,255,.16); color: #a4f2ff; }
    .technician-home .home-hero h1 { max-width: 40rem; letter-spacing: -.045em; line-height: 1.1; }
    .technician-home .summary-icon { width: 2.8rem; height: 2.8rem; display: inline-grid; place-items: center; border-radius: .85rem; background: #eaf4ff; color: #0d6efd; font-size: 1.35rem; }
    .technician-home .summary-card { min-height: 8rem; border: 1px solid #dce9f6; box-shadow: 0 8px 24px rgba(13,47,87,.04); }
    .technician-home .row > :nth-child(2) > .summary-card .summary-icon { background: #e9f7f7; color: #0d8c9c; }
    .technician-home .row > :nth-child(3) > .summary-card .summary-icon { background: #fff3e3; color: #b87318; }
    .technician-home .row > :nth-child(4) > .summary-card .summary-icon { background: #e9f8ef; color: #19865e; }
    .technician-home .job-progress { height: .4rem; min-width: 6rem; }
    .technician-home .table td { vertical-align: middle; }
    .technician-home .job-number { letter-spacing: .02em; }
    .technician-home .section-heading { border-left: 4px solid #0d6efd; padding-left: .75rem; }
    .technician-home .attendance-panel { background: #f5f9ff; border: 1px solid #dceafb; border-radius: .85rem; }
    .technician-home .priority-card { border: 1px solid #b8d9f7; border-left: 4px solid #1583bd; background: linear-gradient(115deg, #f2f9ff, #fff); box-shadow: 0 .6rem 1.8rem rgba(16, 82, 138, .055); }
    .technician-home .job-mobile-card { border: 1px solid #dce9f6; border-radius: 1.1rem; background: #fff; box-shadow: 0 6px 18px rgba(15,53,95,.045); }
    .technician-home .job-mobile-card.is-new { border-color: #e8c774; background: linear-gradient(130deg, #fffaf0, #fff); }
    .technician-home .job-mobile-card .job-progress { height: .45rem; }
    .technician-home .utility-card { border: 1px solid #dce9f6; }
    @media (max-width: 575.98px) { .technician-home .home-hero .card-body { padding: 1.4rem !important; } .technician-home .home-hero h1 { font-size: 2rem; } .technician-home .summary-card .card-body { padding: .9rem !important; } .technician-home .summary-card { min-height: 7rem; } .technician-home .summary-card .h3 { font-size: 1.35rem; } .technician-home .hero-stat { padding: .75rem !important; } .technician-home .hero-stat strong { font-size: .88rem !important; } }
</style>
@endpush

@section('content')
<div class="technician-home mx-auto" style="max-width: 1320px">
    <div class="card content-card home-hero overflow-hidden mb-4"><div class="card-body p-4 p-md-5">
        <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap"><div><div class="hero-kicker fw-bold small text-uppercase mb-2">Your field desk</div><h1 class="display-6 fw-bold mb-2">{{ $greeting }}, {{ $firstName ?: 'technician' }}.</h1><div class="hero-muted">{{ now()->format('l, d F Y') }} · {{ $profile?->branch?->name ?? 'Branch not configured' }}</div></div><div class="d-flex flex-wrap gap-2"><span class="badge rounded-pill bg-white text-primary px-3 py-2" data-connectivity>Checking connection</span><span class="badge rounded-pill bg-{{ $profile?->is_available ? 'success' : 'secondary' }} px-3 py-2"><i class="bi bi-circle-fill me-1" style="font-size: .5rem"></i>{{ $profile?->is_available ? 'Available' : 'Unavailable' }}</span></div></div>
        <div class="d-flex flex-wrap gap-2 mt-4"><a class="btn btn-info fw-semibold" href="#assigned-jobs"><i class="bi bi-briefcase me-1"></i>Open my jobs</a><button class="btn btn-outline-light" type="button" data-install-app><i class="bi bi-phone me-1"></i>Install field app</button></div>
        <div class="row g-2 g-sm-3 mt-3"><div class="col-6 col-lg-4"><div class="hero-stat rounded-4 p-3 h-100"><span class="hero-stat-icon mb-2"><i class="bi bi-lightning-charge"></i></span><div class="hero-muted small">Next action</div><strong class="fs-5">{{ $waitingCount > 0 ? 'Accept a new job' : ($workingCount > 0 ? 'Continue an active job' : 'All caught up') }}</strong></div></div><div class="col-6 col-lg-4"><div class="hero-stat rounded-4 p-3 h-100"><span class="hero-stat-icon mb-2"><i class="bi bi-clock-history"></i></span><div class="hero-muted small">Attendance</div><strong class="fs-5">{{ $attendance?->checked_out_at ? 'Shift completed' : ($attendance?->checked_in_at ? 'Checked in' : 'Check in to start') }}</strong></div></div><div class="col-12 col-lg-4"><div class="hero-stat rounded-4 p-3 h-100"><span class="hero-stat-icon mb-2"><i class="bi bi-briefcase"></i></span><div class="hero-muted small">Assigned work</div><strong class="fs-5">{{ $jobs->count() }} {{ str('job')->plural($jobs->count()) }} in your queue</strong></div></div></div>
    </div></div>

    <div class="row g-3 mb-4">
        <div class="col-6 col-lg-3"><div class="card content-card summary-card h-100"><div class="card-body"><span class="summary-icon"><i class="bi bi-inbox"></i></span><div class="h3 fw-bold mb-0 mt-2">{{ $waitingCount }}</div><div class="text-secondary small">Awaiting acceptance</div></div></div></div>
        <div class="col-6 col-lg-3"><div class="card content-card summary-card h-100"><div class="card-body"><span class="summary-icon"><i class="bi bi-tools"></i></span><div class="h3 fw-bold mb-0 mt-2">{{ $workingCount }}</div><div class="text-secondary small">Active pipeline</div></div></div></div>
        <div class="col-6 col-lg-3"><div class="card content-card summary-card h-100"><div class="card-body"><span class="summary-icon"><i class="bi bi-calendar-check"></i></span><div class="h3 fw-bold mb-0 mt-2">{{ $todayCount }}</div><div class="text-secondary small">Scheduled today</div></div></div></div>
        <div class="col-6 col-lg-3"><div class="card content-card summary-card h-100"><div class="card-body"><span class="summary-icon"><i class="bi bi-check-circle"></i></span><div class="h3 fw-bold mb-0 mt-2">{{ $completedCount }}</div><div class="text-secondary small">Completed in queue</div></div></div></div>
    </div>

    @if($nextJob)
        <section class="card priority-card rounded-4 mb-4"><div class="card-body p-3 p-sm-4"><div class="d-flex align-items-center justify-content-between gap-3 flex-wrap"><div><div class="small fw-bold text-primary text-uppercase mb-1" style="letter-spacing: .1em"><i class="bi bi-lightning-charge-fill me-1"></i>Focus next</div><h2 class="h5 fw-bold mb-1">{{ $nextJob->service_type }}</h2><div class="text-secondary small">{{ $nextJob->job_number }} · {{ $nextJob->customer->name }} @if($nextJob->scheduled_at)· {{ $nextJob->scheduled_at->format('d M, g:i A') }}@endif</div></div><a class="btn btn-primary" href="{{ route('technician.jobs.show', $nextJob) }}">{{ $nextJob->status->value === 'ASSIGNED' ? 'Review and accept' : 'Continue job' }} <i class="bi bi-arrow-right ms-1"></i></a></div></div></section>
    @endif

    <div class="card content-card mb-4" id="assigned-jobs"><div class="card-body p-4">
        <div class="d-flex justify-content-between align-items-center gap-2 flex-wrap mb-3"><div><h2 class="h4 section-heading mb-1">Assigned jobs</h2><div class="text-secondary small">Open a job to follow its guided service pipeline.</div></div><span class="badge rounded-pill text-bg-primary px-3 py-2">{{ $jobs->count() }} total</span></div>
        @if($jobs->isNotEmpty())
            <div class="vstack gap-3 d-lg-none">
                @foreach($jobs as $job)
                    @php
                        $mobileStatus = $job->status->value;
                        $mobileProgress = match ($mobileStatus) {
                            'ASSIGNED' => 14, 'ACCEPTED', 'EN_ROUTE' => 29, 'REACHED' => 43, 'INSPECTED' => 57, 'AUTHORIZED' => 71, 'IN_PROGRESS' => 86, default => 100,
                        };
                        $mobileAction = match ($mobileStatus) {
                            'ASSIGNED' => 'Review and accept', 'ACCEPTED', 'EN_ROUTE' => 'Confirm arrival', 'REACHED' => 'Inspect & photograph', 'INSPECTED' => 'Get approval', 'AUTHORIZED' => 'Start work', 'IN_PROGRESS' => 'Finish & sign off', default => 'View job',
                        };
                    @endphp
                    <article class="job-mobile-card p-3 {{ $mobileStatus === 'ASSIGNED' ? 'is-new' : '' }}">
                        <div class="d-flex justify-content-between align-items-start gap-2"><div><div class="small fw-bold text-primary mb-1">{{ $job->job_number }}</div><h3 class="h6 fw-bold mb-0">{{ $job->service_type }}</h3></div><span class="badge rounded-pill text-bg-{{ $mobileStatus === 'ASSIGNED' ? 'warning' : ($mobileProgress === 100 ? 'success' : 'primary') }}">{{ str($mobileStatus)->headline() }}</span></div>
                        <div class="d-flex flex-wrap gap-2 small text-secondary mt-3"><span><i class="bi bi-person me-1"></i>{{ $job->customer->name }}</span><span><i class="bi bi-calendar-event me-1"></i>{{ $job->scheduled_at?->format('d M, g:i A') ?? 'Flexible schedule' }}</span></div>
                        <div class="d-flex justify-content-between small mt-3 mb-1"><span class="text-secondary">Pipeline progress</span><strong>{{ $mobileProgress }}%</strong></div><div class="progress job-progress" role="progressbar" aria-label="{{ $job->job_number }} progress" aria-valuenow="{{ $mobileProgress }}" aria-valuemin="0" aria-valuemax="100"><div class="progress-bar {{ $mobileProgress === 100 ? 'bg-success' : '' }}" style="width: {{ $mobileProgress }}%"></div></div>
                        <div class="d-flex gap-2 mt-3"><a class="btn btn-primary flex-grow-1" href="{{ route('technician.jobs.show', $job) }}">{{ $mobileAction }} <i class="bi bi-arrow-right ms-1"></i></a>@if($job->customer->phone)<a class="btn btn-outline-primary" href="tel:{{ $job->customer->phone }}" aria-label="Call {{ $job->customer->name }}"><i class="bi bi-telephone"></i></a>@endif</div>
                    </article>
                @endforeach
            </div>
            <div class="table-responsive d-none d-lg-block"><table class="table table-hover align-middle w-100" data-rich-table><thead class="table-light"><tr><th>Job</th><th>Customer</th><th>Schedule</th><th>Pipeline</th><th>Next action</th><th class="text-end">Open</th></tr></thead><tbody>
                @foreach($jobs as $job)
                    @php
                        $jobStatus = $job->status->value;
                        $progress = match ($jobStatus) {
                            'ASSIGNED' => 14, 'ACCEPTED', 'EN_ROUTE' => 29, 'REACHED' => 43, 'INSPECTED' => 57, 'AUTHORIZED' => 71, 'IN_PROGRESS' => 86, default => 100,
                        };
                        $nextAction = match ($jobStatus) {
                            'ASSIGNED' => 'Accept job', 'ACCEPTED', 'EN_ROUTE' => 'Confirm arrival', 'REACHED' => 'Inspect & photograph', 'INSPECTED' => 'Get customer approval', 'AUTHORIZED' => 'Start work', 'IN_PROGRESS' => 'Finish & sign off', default => 'View invoice',
                        };
                    @endphp
                    <tr><td><div class="job-number small fw-bold text-primary">{{ $job->job_number }}</div><strong>{{ $job->service_type }}</strong><div class="small text-secondary">{{ str($job->priority ?? 'NORMAL')->headline() }} priority</div></td><td><strong>{{ $job->customer->name }}</strong><div class="small text-secondary">{{ $job->asset?->name ?? 'General service' }}</div></td><td data-order="{{ $job->scheduled_at?->timestamp ?? 9999999999 }}">{{ $job->scheduled_at?->format('d M Y') ?? 'Flexible' }}<div class="small text-secondary">{{ $job->scheduled_at?->format('h:i A') }}</div></td><td data-order="{{ $progress }}"><div class="d-flex align-items-center justify-content-between gap-2 mb-1"><span class="small fw-semibold">{{ str($jobStatus)->headline() }}</span><span class="small text-secondary">{{ $progress }}%</span></div><div class="progress job-progress"><div class="progress-bar {{ $progress === 100 ? 'bg-success' : '' }}" style="width: {{ $progress }}%"></div></div></td><td><span class="badge text-bg-{{ $jobStatus === 'ASSIGNED' ? 'warning' : ($progress === 100 ? 'success' : 'info') }}">{{ $nextAction }}</span></td><td class="text-end"><a class="btn btn-sm btn-primary" href="{{ route('technician.jobs.show', $job) }}" aria-label="Open {{ $job->job_number }}"><i class="bi bi-arrow-right me-1"></i>Open</a></td></tr>
                @endforeach
            </tbody></table></div>
        @else
            <div class="text-center text-secondary py-5"><i class="bi bi-check2-circle display-5 d-block mb-2 text-success"></i><strong>No jobs in your queue</strong><div class="small">New assignments will appear here.</div></div>
        @endif
    </div></div>

    <div class="row g-4"><div class="col-lg-6">
        <div class="card content-card h-100"><div class="card-body p-4"><div class="d-flex justify-content-between align-items-start mb-3"><div><h2 class="h5 section-heading mb-1">Attendance</h2><div class="small text-secondary">{{ $profile?->branch?->name ?? 'Branch not configured' }}</div></div><i class="bi bi-geo-alt fs-2 text-primary"></i></div>
            <div class="attendance-panel p-3 mb-3"><div class="d-flex justify-content-between"><span class="text-secondary">Today’s status</span><strong>{{ $attendance?->checked_out_at ? 'Shift complete' : ($attendance?->checked_in_at ? 'On shift' : 'Not checked in') }}</strong></div>@if($attendance?->checked_in_at)<div class="small text-secondary mt-2">Checked in {{ $attendance->checked_in_at->format('h:i A') }}@if($attendance->checked_out_at) · Checked out {{ $attendance->checked_out_at->format('h:i A') }}@endif</div>@endif</div>
            @if($profile?->branch)
                <form method="POST" action="{{ route('technician.attendance.store') }}" enctype="multipart/form-data" data-ajax data-offline-queue data-geolocation-form>@csrf<input type="hidden" name="action" value="{{ $attendance?->checked_in_at && !$attendance?->checked_out_at ? 'check-out' : 'check-in' }}"><input type="hidden" name="latitude"><input type="hidden" name="longitude"><input type="hidden" name="accuracy_metres"><input type="hidden" name="device_id"><input type="hidden" name="is_mock_location" value="0"><label class="form-label fw-semibold" for="attendance-selfie">Live selfie</label><input class="form-control mb-3" id="attendance-selfie" type="file" name="selfie" accept="image/*" capture="user" required><button class="btn btn-{{ $attendance?->checked_in_at ? 'outline-danger' : 'success' }} w-100" type="submit" @disabled($attendance?->checked_out_at)>{{ $attendance?->checked_out_at ? 'Shift completed' : ($attendance?->checked_in_at ? 'Check out' : 'Check in') }}</button></form>
            @else<div class="alert alert-warning mb-0">Ask your manager to assign your branch before checking in.</div>@endif
        </div></div>
    </div><div class="col-lg-6">
        <div class="card content-card h-100"><div class="card-body p-4"><h2 class="h5 section-heading mb-3">Apply for leave</h2>
            @if($leaveBalances->isNotEmpty())<div class="d-flex flex-wrap gap-2 mb-3">@foreach($leaveBalances as $balance)<span class="badge rounded-pill text-bg-light border px-3 py-2">{{ str($balance->leave_type)->headline() }}: {{ (float) $balance->opening_days + (float) $balance->accrued_days - (float) $balance->used_days }} days</span>@endforeach</div>@endif
            <form method="POST" action="{{ route('technician.leave-requests.store') }}" data-ajax data-offline-queue class="row g-3">@csrf<div class="col-12"><label class="form-label fw-semibold" for="leave-type">Leave type</label><select class="form-select" id="leave-type" name="leave_type"><option value="CASUAL">Casual</option><option value="SICK">Sick</option><option value="EARNED">Earned</option></select></div><div class="col-6"><label class="form-label fw-semibold" for="leave-start">From</label><input class="form-control" id="leave-start" type="date" name="starts_on" required></div><div class="col-6"><label class="form-label fw-semibold" for="leave-end">To</label><input class="form-control" id="leave-end" type="date" name="ends_on" required></div><div class="col-12"><label class="form-label fw-semibold" for="leave-reason">Reason</label><textarea class="form-control" id="leave-reason" name="reason" rows="2" required></textarea></div><div class="col-12"><button class="btn btn-outline-primary w-100" type="submit"><i class="bi bi-send me-2"></i>Submit leave request</button></div></form>
            @if($leaveRequests->isNotEmpty())<hr><div class="small fw-semibold mb-2">Recent requests</div><div class="table-responsive"><table class="table table-sm align-middle w-100" data-rich-table><thead><tr><th>Dates</th><th>Type</th><th>Status</th></tr></thead><tbody>@foreach($leaveRequests as $leave)<tr><td>{{ $leave->starts_on->format('d M') }} – {{ $leave->ends_on->format('d M Y') }}</td><td>{{ str($leave->leave_type)->headline() }}</td><td><span class="badge text-bg-light border">{{ str($leave->status->value)->headline() }}</span></td></tr>@endforeach</tbody></table></div>@endif
        </div></div>
    </div></div>

    <div class="card content-card mt-4" id="payout-statements"><div class="card-body p-4"><div class="d-flex justify-content-between align-items-center mb-3"><h2 class="h5 section-heading mb-0">Payout statements</h2><span class="badge rounded-pill text-bg-light">{{ $payoutLines->count() }}</span></div>
        @if($payoutLines->isNotEmpty())
            <div class="vstack gap-2 d-lg-none">@foreach($payoutLines as $line)<div class="utility-card rounded-4 p-3"><div class="d-flex justify-content-between align-items-start gap-2"><div><div class="small text-secondary">{{ $line->cycle->cycle_number }}</div><strong class="fs-5">₹{{ number_format((float) $line->net_amount, 2) }}</strong></div><span class="badge text-bg-light border">{{ str($line->status->value)->headline() }}</span></div><div class="small text-secondary mt-2">{{ $line->cycle->starts_on->format('d M') }} – {{ $line->cycle->ends_on->format('d M Y') }}</div><div class="d-flex gap-2 mt-3"><a class="btn btn-sm btn-outline-primary flex-grow-1" href="{{ route('technician.payout-lines.payslip', $line) }}"><i class="bi bi-file-earmark-pdf me-1"></i>Download payslip</a>@if(!$line->disputes->contains('status', 'OPEN'))<button class="btn btn-sm btn-outline-danger" type="button" data-bs-toggle="modal" data-bs-target="#dispute-{{ $line->id }}">Dispute</button>@endif</div></div>@endforeach</div>
            <div class="table-responsive d-none d-lg-block"><table class="table table-hover align-middle w-100" data-rich-table><thead class="table-light"><tr><th>Cycle</th><th>Period</th><th>Net payout</th><th>Status</th><th>Actions</th></tr></thead><tbody>@foreach($payoutLines as $line)<tr><td><strong>{{ $line->cycle->cycle_number }}</strong></td><td>{{ $line->cycle->starts_on->format('d M') }} – {{ $line->cycle->ends_on->format('d M Y') }}</td><td class="fw-bold">₹{{ number_format((float) $line->net_amount, 2) }}</td><td><span class="badge text-bg-light border">{{ str($line->status->value)->headline() }}</span></td><td><a class="btn btn-sm btn-outline-primary me-1 mb-1" href="{{ route('technician.payout-lines.payslip', $line) }}"><i class="bi bi-file-earmark-pdf me-1"></i>Payslip</a>@if(!$line->disputes->contains('status', 'OPEN'))<button class="btn btn-sm btn-outline-danger mb-1" type="button" data-bs-toggle="modal" data-bs-target="#dispute-{{ $line->id }}">Dispute</button>@endif</td></tr>@endforeach</tbody></table></div>
            @foreach($payoutLines as $line)
                @if(!$line->disputes->contains('status', 'OPEN'))<div class="modal fade" id="dispute-{{ $line->id }}" tabindex="-1" aria-labelledby="dispute-title-{{ $line->id }}" aria-hidden="true"><div class="modal-dialog modal-dialog-centered"><div class="modal-content"><form method="POST" action="{{ route('technician.payout-lines.disputes.store', $line) }}" data-ajax>@csrf<div class="modal-header"><h3 class="h5 modal-title" id="dispute-title-{{ $line->id }}">Dispute {{ $line->cycle->cycle_number }}</h3><button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Close"></button></div><div class="modal-body"><label class="form-label" for="dispute-reason-{{ $line->id }}">Describe the discrepancy</label><textarea class="form-control" id="dispute-reason-{{ $line->id }}" name="reason" rows="4" required></textarea></div><div class="modal-footer"><button class="btn btn-outline-secondary" type="button" data-bs-dismiss="modal">Cancel</button><button class="btn btn-danger" type="submit">Submit dispute</button></div></form></div></div></div>@endif
            @endforeach
        @else<div class="text-secondary">No payout statements yet.</div>@endif
    </div></div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const connectivity = document.querySelector('[data-connectivity]');
    const updateConnectivity = () => {
        connectivity.textContent = navigator.onLine ? 'Online · ready to sync' : 'Offline · actions will queue';
        connectivity.classList.toggle('text-primary', navigator.onLine);
        connectivity.classList.toggle('text-danger', !navigator.onLine);
    };
    updateConnectivity();
    window.addEventListener('online', updateConnectivity);
    window.addEventListener('offline', updateConnectivity);
    const deviceId = localStorage.getItem('acserv-device-id') || (window.crypto?.randomUUID?.() ?? String(Date.now()));
    localStorage.setItem('acserv-device-id', deviceId);
    document.querySelectorAll('[data-geolocation-form]').forEach((form) => {
        form.querySelector('[name="device_id"]').value = deviceId;
        form.addEventListener('submit', (event) => {
            if (form.dataset.locationReady === 'true') return;
            event.preventDefault();
            event.stopImmediatePropagation();
            if (!navigator.geolocation) { void window.Swal.fire('Location required', 'Enable precise location to continue.', 'warning'); return; }
            navigator.geolocation.getCurrentPosition((position) => {
                form.querySelector('[name="latitude"]').value = position.coords.latitude;
                form.querySelector('[name="longitude"]').value = position.coords.longitude;
                form.querySelector('[name="accuracy_metres"]').value = position.coords.accuracy;
                form.dataset.locationReady = 'true';
                form.requestSubmit();
            }, () => void window.Swal.fire('Location required', 'Enable precise location to continue.', 'warning'), { enableHighAccuracy: true, timeout: 15000 });
        }, true);
    });
});
</script>
@endpush
