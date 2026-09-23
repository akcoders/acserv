@extends('layouts.admin')

@section('title', 'Jobs — '.__('app.name'))

@section('content')
    <div class="d-flex flex-wrap justify-content-between align-items-end gap-3 mb-4">
        <div>
            <p class="admin-eyebrow text-primary mb-1">Service operations</p>
            <h1 class="h2 fw-bold mb-1">Job pipeline</h1>
            <p class="text-secondary mb-0">Follow every visit from assignment to customer sign-off and closure.</p>
        </div>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#jobModal"><i class="bi bi-plus-lg me-2" aria-hidden="true"></i>Create job</button>
    </div>

    <section class="card content-card mb-4" aria-labelledby="pipeline-board-heading">
        <div class="card-header bg-transparent border-0 px-4 pt-4 pb-2 d-flex flex-wrap align-items-center justify-content-between gap-2">
            <div>
                <p class="admin-eyebrow text-primary mb-1">Live dispatch board</p>
                <h2 class="h5 mb-0" id="pipeline-board-heading">Service stages</h2>
            </div>
            <span class="small text-secondary"><i class="bi bi-arrow-right me-1" aria-hidden="true"></i>Scroll to follow the workflow</span>
        </div>
        <div class="pipeline-board" aria-label="Job stages">
            @foreach ($statuses as $status)
                @continue($status === \App\Enums\JobStatus::Cancelled)
                <section class="pipeline-lane" data-status="{{ $status->value }}" aria-label="{{ str($status->value)->lower()->headline() }} jobs">
                    <a class="pipeline-lane-header text-decoration-none" href="{{ route('admin.jobs.index', ['status' => $status->value]) }}">
                        <span class="pipeline-lane-title"><span class="pipeline-stat-dot" aria-hidden="true"></span>{{ str($status->value)->lower()->headline() }}</span>
                        <span class="pipeline-count">{{ number_format($stageCounts[$status->value] ?? 0) }}</span>
                    </a>
                    <div class="pipeline-lane-cards">
                        @forelse ($pipelineJobs->get($status->value, collect())->take(3) as $boardJob)
                            <article class="pipeline-ticket">
                                <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
                                    <a class="text-decoration-none" href="{{ route('admin.jobs.show', $boardJob) }}"><strong>{{ $boardJob->job_number }}</strong></a>
                                    @if (in_array($boardJob->priority, ['HIGH', 'URGENT'], true))
                                        <span class="priority-dot" title="{{ str($boardJob->priority)->headline() }} priority" aria-label="{{ str($boardJob->priority)->headline() }} priority"></span>
                                    @endif
                                </div>
                                <div class="small text-secondary text-truncate">{{ $boardJob->customer?->name ?? 'Customer' }}</div>
                                <div class="small fw-medium text-truncate mt-1">{{ $boardJob->service_type }}</div>
                                <div class="small text-secondary mt-3"><i class="bi bi-person-badge me-1" aria-hidden="true"></i>{{ $boardJob->assignments->pluck('technician.name')->filter()->join(', ') ?: 'Awaiting technician' }}</div>
                            </article>
                        @empty
                            <div class="pipeline-empty">No jobs at this stage</div>
                        @endforelse
                    </div>
                    @if (($stageCounts[$status->value] ?? 0) > 3)
                        <a class="pipeline-more" href="{{ route('admin.jobs.index', ['status' => $status->value]) }}">View all {{ number_format($stageCounts[$status->value]) }} <i class="bi bi-arrow-right" aria-hidden="true"></i></a>
                    @endif
                </section>
            @endforeach
        </div>
        @if (($stageCounts['CANCELLED'] ?? 0) > 0)
            <div class="border-top px-4 py-3 small text-secondary">{{ number_format($stageCounts['CANCELLED']) }} cancelled jobs are available in the status filter below.</div>
        @endif
    </section>

    <section class="card content-card" aria-labelledby="job-list-heading">
        <div class="card-header bg-transparent border-0 px-4 pt-4 pb-3">
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
                <div>
                    <p class="admin-eyebrow text-primary mb-1">Job register</p>
                    <h2 class="h5 mb-0" id="job-list-heading">All jobs <span class="text-secondary fw-normal">({{ number_format($jobs->total()) }})</span></h2>
                </div>
                @if (request()->filled('status') || request()->filled('search'))
                    <a class="btn btn-sm btn-outline-secondary" href="{{ route('admin.jobs.index') }}">Clear filters</a>
                @endif
            </div>
            <form class="row g-2 mt-2" method="GET" action="{{ route('admin.jobs.index') }}">
                <div class="col-md-6"><label class="visually-hidden" for="job-server-search">Search all jobs</label><input class="form-control" id="job-server-search" name="search" value="{{ request('search') }}" placeholder="Search all jobs, services or customers"></div>
                <div class="col-md-4"><label class="visually-hidden" for="job-status-filter">Status</label><select class="form-select" id="job-status-filter" name="status"><option value="">All stages</option>@foreach ($statuses as $status)<option value="{{ $status->value }}" @selected(request('status') === $status->value)>{{ str($status->value)->lower()->headline() }}</option>@endforeach</select></div>
                <div class="col-md-2"><button class="btn btn-outline-primary w-100" type="submit"><i class="bi bi-funnel me-1" aria-hidden="true"></i>Filter</button></div>
            </form>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" data-rich-table="server">
                <thead><tr><th>Job</th><th>Customer</th><th>Technician</th><th>Scheduled</th><th>Stage</th><th>Evidence</th><th class="text-end" data-unsortable>Actions</th></tr></thead>
                <tbody>
                    @forelse ($jobs as $job)
                        <tr>
                            <td><a class="fw-semibold text-decoration-none" href="{{ route('admin.jobs.show', $job) }}">{{ $job->job_number }}</a><div class="small text-secondary">{{ $job->service_type }}</div></td>
                            <td><span class="fw-medium">{{ $job->customer?->name ?? '—' }}</span><div class="small text-secondary">{{ $job->customer?->phone }}</div></td>
                            <td>{{ $job->assignments->pluck('technician.name')->filter()->join(', ') ?: 'Unassigned' }}</td>
                            <td data-sort="{{ $job->scheduled_at?->timestamp ?? 0 }}">{{ $job->scheduled_at?->format('d M Y, h:i A') ?? 'Unscheduled' }}</td>
                            <td><span class="job-status-pill" data-status="{{ $job->status->value }}">{{ str($job->status->value)->lower()->headline() }}</span><div class="small text-secondary mt-1">{{ str($job->priority)->lower()->headline() }} priority</div></td>
                            <td><span class="evidence-count"><i class="bi bi-camera me-1" aria-hidden="true"></i>{{ $job->evidence_count }}</span></td>
                            <td class="text-end"><div class="btn-group btn-group-sm" role="group" aria-label="Actions for {{ $job->job_number }}"><a class="btn btn-primary" href="{{ route('admin.jobs.show', $job) }}">Open</a><button class="btn btn-outline-primary" type="button" data-bs-toggle="modal" data-bs-target="#manageJob-{{ $job->id }}">Manage</button><a class="btn btn-outline-secondary" href="{{ route('admin.jobs.job-card', $job) }}" title="Download job card PDF" aria-label="Download job card PDF for {{ $job->job_number }}"><i class="bi bi-file-earmark-pdf" aria-hidden="true"></i></a></div></td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-center text-secondary py-5">No jobs match these filters.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-body border-top">{{ $jobs->links() }}</div>
    </section>

    @foreach ($jobs as $job)
        <div class="modal fade" id="manageJob-{{ $job->id }}" tabindex="-1" aria-labelledby="manageJobTitle-{{ $job->id }}" aria-hidden="true">
            <div class="modal-dialog modal-lg modal-dialog-scrollable"><div class="modal-content">
                <div class="modal-header"><div><p class="admin-eyebrow text-primary mb-1">Job management</p><h2 class="modal-title h5 mb-0" id="manageJobTitle-{{ $job->id }}">{{ $job->job_number }}</h2></div><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
                <div class="modal-body">
                    <div class="d-flex flex-wrap gap-2 mb-4"><span class="job-status-pill" data-status="{{ $job->status->value }}">{{ str($job->status->value)->lower()->headline() }}</span><span class="badge text-bg-light">{{ $job->customer?->name }}</span><span class="badge text-bg-light">{{ $job->service_type }}</span></div>
                    <div class="row g-3">
                        <div class="col-lg-6">
                            @if (in_array($job->status, [\App\Enums\JobStatus::Created, \App\Enums\JobStatus::Assigned], true))
                                <form method="POST" action="{{ route('admin.jobs.assignments.store', $job) }}" data-ajax class="border rounded-4 p-3 h-100">
                                    @csrf
                                    <h3 class="h6 mb-2"><i class="bi bi-person-plus me-2" aria-hidden="true"></i>Assign technician</h3>
                                    <p class="small text-secondary">The technician receives the job and must accept it before continuing.</p>
                                    <label class="form-label" for="technician-{{ $job->id }}">Technician</label>
                                    <select class="form-select mb-3" id="technician-{{ $job->id }}" name="technician_id" required><option value="">Select technician</option>@foreach ($technicians as $technician)<option value="{{ $technician->id }}">{{ $technician->name }}</option>@endforeach</select>
                                    <label class="form-label" for="assignment-note-{{ $job->id }}">Dispatch note</label>
                                    <input class="form-control mb-3" id="assignment-note-{{ $job->id }}" name="notes" placeholder="Access or arrival instructions">
                                    <button class="btn btn-primary w-100" type="submit">Assign technician</button>
                                </form>
                            @else
                                <div class="border rounded-4 p-3 h-100"><h3 class="h6"><i class="bi bi-person-check me-2" aria-hidden="true"></i>Technician assigned</h3><p class="small text-secondary mb-0">Dispatch is locked after acceptance. Follow progress in the pipeline.</p></div>
                            @endif
                        </div>
                        <div class="col-lg-6">
                            @php($manualStages = match ($job->status) {
                                \App\Enums\JobStatus::Created,
                                \App\Enums\JobStatus::Assigned,
                                \App\Enums\JobStatus::Accepted,
                                \App\Enums\JobStatus::EnRoute,
                                \App\Enums\JobStatus::Reached,
                                \App\Enums\JobStatus::Inspected,
                                \App\Enums\JobStatus::Authorized,
                                \App\Enums\JobStatus::InProgress => [\App\Enums\JobStatus::Cancelled],
                                \App\Enums\JobStatus::AwaitingPayment,
                                \App\Enums\JobStatus::PaymentPending => [],
                                \App\Enums\JobStatus::Completed => [\App\Enums\JobStatus::Verified],
                                \App\Enums\JobStatus::Verified => [\App\Enums\JobStatus::Closed],
                                default => [],
                            })
                            <div class="border rounded-4 p-3 h-100">
                                <h3 class="h6 mb-2"><i class="bi bi-arrow-repeat me-2" aria-hidden="true"></i>Manage stage</h3>
                                <p class="small text-secondary">On-site stages advance from the technician phone. Managers can verify completed work, close verified jobs, or cancel active jobs.</p>
                                @if ($manualStages)
                                    <form method="POST" action="{{ route('admin.jobs.transitions.store', $job) }}" data-ajax>
                                        @csrf
                                        <label class="form-label" for="status-{{ $job->id }}">Available action</label>
                                        <select class="form-select mb-3" id="status-{{ $job->id }}" name="status" required>@foreach ($manualStages as $status)<option value="{{ $status->value }}">{{ str($status->value)->lower()->headline() }}</option>@endforeach</select>
                                        <label class="form-label" for="resolution-{{ $job->id }}">Manager note</label>
                                        <textarea class="form-control mb-3" id="resolution-{{ $job->id }}" name="resolution" rows="2" placeholder="Reason or verification note"></textarea>
                                        <button class="btn btn-outline-primary w-100" type="submit">Confirm stage</button>
                                    </form>
                                @else
                                    <div class="alert alert-light border small mb-0">{{ in_array($job->status, [\App\Enums\JobStatus::AwaitingPayment, \App\Enums\JobStatus::PaymentPending], true) ? 'Open the full job record to review collection and evidence.' : 'No further manual stage actions are available.' }}</div>
                                @endif
                            </div>
                        </div>
                    </div>
                    @if ($job->status === \App\Enums\JobStatus::Created)
                        <button class="btn btn-sm btn-link text-danger px-0 mt-3" type="button" data-confirm-ajax data-url="{{ route('admin.jobs.destroy', $job) }}" data-title="Delete unstarted job?">Delete unstarted job</button>
                    @endif
                </div>
                <div class="modal-footer"><a class="btn btn-primary" href="{{ route('admin.jobs.show', $job) }}">Open full record</a><a class="btn btn-outline-secondary" href="{{ route('admin.jobs.job-card', $job) }}"><i class="bi bi-file-earmark-pdf me-1" aria-hidden="true"></i>Job card PDF</a><button class="btn btn-light" type="button" data-bs-dismiss="modal">Close</button></div>
            </div></div>
        </div>
    @endforeach

    <div class="modal fade" id="jobModal" tabindex="-1" aria-labelledby="createJobTitle" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable"><div class="modal-content"><form method="POST" action="{{ route('admin.jobs.store') }}" data-ajax>
            @csrf
            <div class="modal-header"><div><p class="admin-eyebrow text-primary mb-1">Dispatch</p><h2 class="modal-title h5 mb-0" id="createJobTitle">Create job</h2></div><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
            <div class="modal-body"><div class="row g-3">
                <div class="col-md-6"><label class="form-label">Customer</label><select class="form-select" name="customer_id" required><option value="">Select customer</option>@foreach ($customers as $customer)<option value="{{ $customer->id }}">{{ $customer->name }} · {{ $customer->phone }}</option>@endforeach</select></div>
                <div class="col-md-6"><label class="form-label">Branch</label><select class="form-select" name="branch_id"><option value="">Select branch</option>@foreach ($branches as $branch)<option value="{{ $branch->id }}">{{ $branch->name }}</option>@endforeach</select></div>
                <div class="col-md-6"><label class="form-label">Service type</label><input class="form-control" name="service_type" placeholder="AC repair, installation…" required></div>
                <div class="col-md-3"><label class="form-label">Priority</label><select class="form-select" name="priority"><option>NORMAL</option><option>LOW</option><option>HIGH</option><option>URGENT</option></select></div>
                <div class="col-md-3"><label class="form-label">Est. minutes</label><input class="form-control" type="number" name="estimated_minutes" min="15" value="60"></div>
                <div class="col-md-6"><label class="form-label">Schedule</label><input class="form-control" type="datetime-local" name="scheduled_at"></div>
                <div class="col-12"><label class="form-label">Description</label><textarea class="form-control" name="description" rows="3" placeholder="Customer complaint and site details"></textarea></div>
                <div class="col-12"><label class="form-label">Initial checklist</label><div class="row g-2"><div class="col-md-4"><input class="form-control" name="checklist[]" placeholder="Inspect unit"></div><div class="col-md-4"><input class="form-control" name="checklist[]" placeholder="Check parts"></div><div class="col-md-4"><input class="form-control" name="checklist[]" placeholder="Test cooling"></div></div></div>
            </div></div>
            <div class="modal-footer"><button class="btn btn-light" type="button" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary" type="submit">Create job</button></div>
        </form></div></div>
    </div>
@endsection
