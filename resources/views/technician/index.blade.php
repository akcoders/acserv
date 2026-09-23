@extends('layouts.mobile')

@section('title', 'Technician — '.__('app.name'))
@section('dashboard-url', route('technician.dashboard'))

@php
    $waitingCount = $jobs->filter(fn ($job) => $job->status->value === 'ASSIGNED')->count();
    $workingCount = $jobs->filter(fn ($job) => in_array($job->status->value, ['ACCEPTED', 'EN_ROUTE', 'REACHED', 'INSPECTED', 'AUTHORIZED', 'IN_PROGRESS'], true))->count();
    $completedCount = $jobs->filter(fn ($job) => in_array($job->status->value, ['COMPLETED', 'VERIFIED'], true))->count();
    $todayCount = $jobs->filter(fn ($job) => $job->scheduled_at?->isToday())->count();
@endphp

@push('head')
<style>
    .technician-home .home-hero { background: linear-gradient(130deg, #082a53 0%, #1168c7 100%); color: #fff; }
    .technician-home .home-hero .hero-muted { color: rgba(255, 255, 255, .75); }
    .technician-home .summary-icon { width: 2.8rem; height: 2.8rem; display: inline-grid; place-items: center; border-radius: .85rem; background: #eaf4ff; color: #0d6efd; font-size: 1.35rem; }
    .technician-home .summary-card { min-height: 8rem; }
    .technician-home .job-progress { height: .4rem; min-width: 6rem; }
    .technician-home .table td { vertical-align: middle; }
    .technician-home .job-number { letter-spacing: .02em; }
    .technician-home .section-heading { border-left: 4px solid #0d6efd; padding-left: .75rem; }
    .technician-home .attendance-panel { background: #f5f9ff; border: 1px solid #dceafb; border-radius: .85rem; }
</style>
@endpush

@section('content')
<div class="technician-home mx-auto" style="max-width: 1320px">
    <div class="card content-card home-hero overflow-hidden mb-4"><div class="card-body p-4 p-md-5">
        <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap"><div><div class="hero-muted fw-semibold small text-uppercase mb-1">Field service workspace</div><h1 class="h2 mb-1">My workday</h1><div class="hero-muted">{{ now()->format('l, d F Y') }} · {{ $profile?->branch?->name ?? 'Branch not configured' }}</div></div><span class="badge rounded-pill bg-{{ $profile?->is_available ? 'success' : 'secondary' }} px-3 py-2"><i class="bi bi-circle-fill me-1" style="font-size: .5rem"></i>{{ $profile?->is_available ? 'Available' : 'Unavailable' }}</span></div>
        <div class="row g-3 mt-3"><div class="col-sm-6 col-lg-4"><div class="rounded-4 bg-white bg-opacity-10 p-3 h-100"><div class="hero-muted small">Next action</div><strong class="fs-5">{{ $waitingCount > 0 ? 'Accept a new job' : ($workingCount > 0 ? 'Continue an active job' : 'All caught up') }}</strong></div></div><div class="col-sm-6 col-lg-4"><div class="rounded-4 bg-white bg-opacity-10 p-3 h-100"><div class="hero-muted small">Attendance</div><strong class="fs-5">{{ $attendance?->checked_out_at ? 'Shift completed' : ($attendance?->checked_in_at ? 'Checked in' : 'Check in to start') }}</strong></div></div><div class="col-lg-4"><div class="rounded-4 bg-white bg-opacity-10 p-3 h-100"><div class="hero-muted small">Assigned work</div><strong class="fs-5">{{ $jobs->count() }} {{ str('job')->plural($jobs->count()) }} in your queue</strong></div></div></div>
    </div></div>

    <div class="row g-3 mb-4">
        <div class="col-6 col-lg-3"><div class="card content-card summary-card h-100"><div class="card-body"><span class="summary-icon"><i class="bi bi-inbox"></i></span><div class="h3 fw-bold mb-0 mt-2">{{ $waitingCount }}</div><div class="text-secondary small">Awaiting acceptance</div></div></div></div>
        <div class="col-6 col-lg-3"><div class="card content-card summary-card h-100"><div class="card-body"><span class="summary-icon"><i class="bi bi-tools"></i></span><div class="h3 fw-bold mb-0 mt-2">{{ $workingCount }}</div><div class="text-secondary small">Active pipeline</div></div></div></div>
        <div class="col-6 col-lg-3"><div class="card content-card summary-card h-100"><div class="card-body"><span class="summary-icon"><i class="bi bi-calendar-check"></i></span><div class="h3 fw-bold mb-0 mt-2">{{ $todayCount }}</div><div class="text-secondary small">Scheduled today</div></div></div></div>
        <div class="col-6 col-lg-3"><div class="card content-card summary-card h-100"><div class="card-body"><span class="summary-icon"><i class="bi bi-check-circle"></i></span><div class="h3 fw-bold mb-0 mt-2">{{ $completedCount }}</div><div class="text-secondary small">Completed in queue</div></div></div></div>
    </div>

    <div class="card content-card mb-4" id="assigned-jobs"><div class="card-body p-4">
        <div class="d-flex justify-content-between align-items-center gap-2 flex-wrap mb-3"><div><h2 class="h4 section-heading mb-1">Assigned jobs</h2><div class="text-secondary small">Open a job to follow its guided service pipeline.</div></div><span class="badge rounded-pill text-bg-primary px-3 py-2">{{ $jobs->count() }} total</span></div>
        @if($jobs->isNotEmpty())
            <div class="table-responsive"><table class="table table-hover align-middle w-100" data-rich-table><thead class="table-light"><tr><th>Job</th><th>Customer</th><th>Schedule</th><th>Pipeline</th><th>Next action</th><th class="text-end">Open</th></tr></thead><tbody>
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
            <div class="table-responsive"><table class="table table-hover align-middle w-100" data-rich-table><thead class="table-light"><tr><th>Cycle</th><th>Period</th><th>Net payout</th><th>Status</th><th>Actions</th></tr></thead><tbody>@foreach($payoutLines as $line)<tr><td><strong>{{ $line->cycle->cycle_number }}</strong></td><td>{{ $line->cycle->starts_on->format('d M') }} – {{ $line->cycle->ends_on->format('d M Y') }}</td><td class="fw-bold">₹{{ number_format((float) $line->net_amount, 2) }}</td><td><span class="badge text-bg-light border">{{ str($line->status->value)->headline() }}</span></td><td><a class="btn btn-sm btn-outline-primary me-1 mb-1" href="{{ route('technician.payout-lines.payslip', $line) }}"><i class="bi bi-file-earmark-pdf me-1"></i>Payslip</a>@if(!$line->disputes->contains('status', 'OPEN'))<button class="btn btn-sm btn-outline-danger mb-1" type="button" data-bs-toggle="modal" data-bs-target="#dispute-{{ $line->id }}">Dispute</button>@endif</td></tr>@endforeach</tbody></table></div>
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
