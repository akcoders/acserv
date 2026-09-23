@extends('layouts.admin')

@section('title', $job->job_number.' — '.__('app.name'))

@section('content')
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
        <div>
            <a class="small text-decoration-none" href="{{ route('admin.jobs.index') }}"><i class="bi bi-arrow-left me-1" aria-hidden="true"></i>Job pipeline</a>
            <p class="admin-eyebrow text-primary mb-1 mt-3">Service record / {{ $job->job_number }}</p>
            <h1 class="h2 fw-bold mb-1">{{ $job->service_type }}</h1>
            <div class="d-flex flex-wrap align-items-center gap-2"><span class="job-status-pill" data-status="{{ $job->status->value }}">{{ str($job->status->value)->lower()->headline() }}</span><span class="small text-secondary">{{ $job->customer?->name }} · {{ str($job->priority)->lower()->headline() }} priority</span></div>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <a class="btn btn-outline-primary" href="{{ route('admin.jobs.job-card', $job) }}"><i class="bi bi-file-earmark-pdf me-2" aria-hidden="true"></i>Download full job card</a>
            @if ($job->invoice && auth()->user()->role->canManageBilling())
                <a class="btn btn-primary" href="{{ route('admin.invoices.pdf', $job->invoice) }}"><i class="bi bi-receipt me-2" aria-hidden="true"></i>Tax invoice</a>
            @endif
        </div>
    </div>

    @if ($job->status === \App\Enums\JobStatus::PaymentPending)
        <div class="alert alert-warning border-0 rounded-4 d-flex gap-3 mb-4" role="status"><i class="bi bi-hourglass-split fs-4" aria-hidden="true"></i><div><strong>Payment review required</strong><div class="small">The technician has submitted a collection. Check the proof and verify or reject it below before the job moves to completed.</div></div></div>
    @elseif ($job->status === \App\Enums\JobStatus::AwaitingPayment)
        <div class="alert alert-info border-0 rounded-4 d-flex gap-3 mb-4" role="status"><i class="bi bi-wallet2 fs-4" aria-hidden="true"></i><div><strong>Work submitted, awaiting collection</strong><div class="small">Before/after evidence and customer sign-off are recorded. The technician must submit cash or UPI collection.</div></div></div>
    @elseif ($job->status === \App\Enums\JobStatus::Completed && in_array(auth()->user()->role, [\App\Enums\Role::Owner, \App\Enums\Role::Admin, \App\Enums\Role::Manager], true))
        <div class="card content-card border-primary mb-4"><div class="card-body d-flex flex-wrap justify-content-between align-items-center gap-3"><div><h2 class="h5 mb-1">Final work verification</h2><p class="text-secondary small mb-0">Review the photos, signatures, parts and payment below, then approve this job.</p></div><form method="POST" action="{{ route('admin.jobs.transitions.store', $job) }}" data-ajax>@csrf<input type="hidden" name="status" value="VERIFIED"><button class="btn btn-primary" type="submit"><i class="bi bi-patch-check me-2" aria-hidden="true"></i>Verify job</button></form></div></div>
    @endif

    <div class="row g-4">
        <div class="col-xxl-8">
            <section class="card content-card mb-4"><div class="card-body p-4">
                <div class="d-flex align-items-center justify-content-between gap-2 mb-3"><div><p class="admin-eyebrow text-primary mb-1">Visit context</p><h2 class="h5 mb-0">Customer & equipment</h2></div><i class="bi bi-person-vcard fs-3 text-primary" aria-hidden="true"></i></div>
                <div class="row g-3 small">
                    <div class="col-md-6"><span class="text-secondary d-block">Customer</span><strong class="fs-6">{{ $job->customer?->name ?? '—' }}</strong><div>{{ $job->customer?->phone }}</div><div>{{ $job->customer?->email }}</div></div>
                    <div class="col-md-6"><span class="text-secondary d-block">Service address</span><strong>{{ implode(', ', array_filter(array_values((array) ($job->service_address ?? $job->customer?->service_address ?? [])))) ?: 'Not recorded' }}</strong></div>
                    <div class="col-md-6"><span class="text-secondary d-block">AC unit / appliance</span><strong>{{ $job->asset?->name ?? 'Not linked' }}</strong><div>{{ trim(($job->asset?->brand ?? '').' '.($job->asset?->model ?? '')) }}</div><div>Serial: {{ $job->asset?->serial_number ?? '—' }}</div></div>
                    <div class="col-md-6"><span class="text-secondary d-block">Schedule and branch</span><strong>{{ $job->scheduled_at?->format('d M Y, h:i A') ?? 'Not scheduled' }}</strong><div>{{ $job->branch?->name ?? 'Unassigned branch' }}</div><div>Technician: {{ $job->assignments->pluck('technician.name')->filter()->join(', ') ?: 'Not assigned' }}</div></div>
                </div>
                <hr class="my-4"><div class="row g-3"><div class="col-md-4"><span class="small text-secondary d-block">Customer complaint</span><p class="mb-0">{{ $job->description ?: 'No complaint recorded.' }}</p></div><div class="col-md-4"><span class="small text-secondary d-block">Fault found / inspection</span><p class="mb-0">{{ $job->inspection_remark ?: 'Inspection pending.' }}</p></div><div class="col-md-4"><span class="small text-secondary d-block">Completion remark</span><p class="mb-0">{{ $job->completion_remark ?: 'Work not yet submitted.' }}</p></div></div>
            </div></section>

            <section class="card content-card mb-4"><div class="card-body p-4"><p class="admin-eyebrow text-primary mb-1">Field evidence</p><h2 class="h5 mb-3">Before, after & supporting photos</h2>
                <div class="row g-3">
                    @forelse ($job->evidence->filter(fn ($item) => $item->type !== \App\Enums\EvidenceType::Signature && str_starts_with((string) $item->mime_type, 'image/')) as $evidence)
                        <div class="col-md-6"><div class="border rounded-4 overflow-hidden h-100 bg-light"><a href="{{ route('admin.jobs.evidence', [$job, $evidence]) }}" target="_blank" rel="noopener"><img class="w-100 object-fit-cover" style="height: 225px" src="{{ route('admin.jobs.evidence', [$job, $evidence]) }}" alt="{{ str($evidence->type->value)->lower()->headline() }} evidence for {{ $job->job_number }}" loading="lazy"></a><div class="p-3"><div class="d-flex justify-content-between gap-2"><strong>{{ str($evidence->type->value)->lower()->headline() }} photo</strong><span class="small text-secondary">{{ $evidence->captured_at?->format('d M Y, h:i A') }}</span></div><p class="small text-secondary mb-0 mt-1">{{ $evidence->metadata['remark'] ?? ($evidence->uploader?->name ?? 'Technician') }}</p></div></div></div>
                    @empty
                        <div class="col-12"><div class="border rounded-4 p-4 text-center text-secondary">No field photos uploaded yet.</div></div>
                    @endforelse
                </div>
            </div></section>

            <section class="card content-card mb-4"><div class="card-body p-4"><p class="admin-eyebrow text-primary mb-1">Customer approval</p><h2 class="h5 mb-3">Digital signatures</h2><div class="row g-3">
                @foreach (['prework_signature_path' => 'Signed before work', 'customer_signature_path' => 'Signed after completion'] as $field => $label)
                    @php($signature = $job->evidence->firstWhere('path', $job->{$field}))
                    <div class="col-md-6"><div class="border rounded-4 p-3 h-100"><div class="fw-semibold mb-2">{{ $label }}</div>@if ($signature)<a href="{{ route('admin.jobs.evidence', [$job, $signature]) }}" target="_blank" rel="noopener"><img class="img-fluid bg-white border rounded-3 w-100 object-fit-contain" style="height: 115px" src="{{ route('admin.jobs.evidence', [$job, $signature]) }}" alt="{{ $label }} customer signature" loading="lazy"></a><div class="small text-secondary mt-2">Captured {{ $signature->captured_at?->format('d M Y, h:i A') }}</div>@else<div class="bg-light rounded-3 d-flex align-items-center justify-content-center text-secondary small" style="height: 115px">Not signed yet</div>@endif</div></div>
                @endforeach
            </div></div></section>

            <section class="card content-card mb-4"><div class="card-body p-4"><p class="admin-eyebrow text-primary mb-1">Materials & cost</p><h2 class="h5 mb-3">Parts used and service charges</h2><div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th>Item</th><th>Location</th><th class="text-end">Used</th><th class="text-end">Returned</th><th class="text-end">Billable</th><th class="text-end">Unit price</th></tr></thead><tbody>@forelse ($job->partConsumptions as $part)<tr><td class="fw-medium">{{ $part->inventoryItem?->name ?? 'Inventory item' }}</td><td>{{ $part->stockLocation?->name ?? '—' }}</td><td class="text-end">{{ $part->quantity }}</td><td class="text-end">{{ $part->returned_quantity }}</td><td class="text-end">{{ number_format(max(0, (float) $part->quantity - (float) $part->returned_quantity), 2) }}</td><td class="text-end">₹{{ number_format((float) $part->unit_price, 2) }}</td></tr>@empty<tr><td colspan="6" class="text-secondary text-center py-4">No parts used.</td></tr>@endforelse</tbody></table></div><div class="border-top mt-3 pt-3 d-flex flex-wrap justify-content-between gap-2"><span>Service labor <strong>₹{{ number_format((float) $job->service_cost, 2) }}</strong></span><span>@if ($job->invoice) Invoice {{ $job->invoice->invoice_number }} · <strong>₹{{ number_format((float) $job->invoice->grand_total, 2) }}</strong> @else Invoice generated after payment verification @endif</span></div></div></section>

            <section class="card content-card mb-4"><div class="card-body p-4"><p class="admin-eyebrow text-primary mb-1">Quality control</p><h2 class="h5 mb-3">Work checklist</h2><div class="row g-2">@forelse ($job->checklistItems as $item)<div class="col-md-6"><div class="border rounded-3 px-3 py-2 d-flex align-items-center gap-2"><i class="bi {{ $item->completed_at ? 'bi-check-circle-fill text-success' : 'bi-circle text-secondary' }}" aria-hidden="true"></i><span>{{ $item->label }}</span></div></div>@empty<div class="col-12 text-secondary">No checklist was specified.</div>@endforelse</div></div></section>
        </div>

        <div class="col-xxl-4">
            <section class="card content-card mb-4"><div class="card-body p-4"><p class="admin-eyebrow text-primary mb-1">Work timeline</p><h2 class="h5 mb-4">Job timeline</h2><div class="border-start border-2 ps-4 ms-2">@foreach ($timeline as $event)<div class="position-relative pb-4"><span class="position-absolute bg-primary border border-3 border-white rounded-circle" style="width: 16px; height: 16px; left: -33px; top: 3px" aria-hidden="true"></span><strong class="d-block">{{ $event['title'] }}</strong><div class="small text-primary">{{ $event['at']->format('d M Y, h:i A') }}</div><div class="small text-secondary mt-1">{{ $event['detail'] }}</div></div>@endforeach</div></div></section>

            <section class="card content-card mb-4"><div class="card-body p-4"><p class="admin-eyebrow text-primary mb-1">Collection control</p><h2 class="h5 mb-3">Technician payments</h2>
                @forelse ($job->paymentCollections as $collection)
                    <div class="border rounded-4 p-3 mb-3"><div class="d-flex justify-content-between align-items-start gap-2"><div><strong class="fs-5">₹{{ number_format((float) $collection->amount, 2) }}</strong><div class="small text-secondary">{{ $collection->mode->value }} · {{ $collection->technician?->name }}</div></div><span class="badge {{ $collection->status === \App\Enums\CollectionStatus::Verified ? 'text-bg-success' : ($collection->status === \App\Enums\CollectionStatus::Rejected ? 'text-bg-danger' : 'text-bg-warning') }}">{{ str($collection->status->value)->lower()->headline() }}</span></div>
                        @if ($collection->reference)<div class="small mt-2">Reference: <strong>{{ $collection->reference }}</strong></div>@endif
                        @if ($collection->proof_path && auth()->user()->role->canManageBilling())<a class="btn btn-sm btn-outline-primary mt-3" href="{{ route('admin.payment-collections.proof', $collection) }}" target="_blank" rel="noopener"><i class="bi bi-image me-1" aria-hidden="true"></i>View UPI transaction proof</a>@endif
                        @if ($collection->status === \App\Enums\CollectionStatus::Pending && auth()->user()->role->canManageBilling())
                            <div class="border-top mt-3 pt-3"><form method="POST" action="{{ route('admin.payment-collections.review', $collection) }}" data-ajax class="mb-2">@csrf<input type="hidden" name="action" value="verify"><button class="btn btn-success w-100" type="submit"><i class="bi bi-check-circle me-1" aria-hidden="true"></i>Verify collection</button></form><form method="POST" action="{{ route('admin.payment-collections.review', $collection) }}" data-ajax>@csrf<input type="hidden" name="action" value="reject"><input class="form-control form-control-sm mb-2" name="rejection_reason" placeholder="Reason for rejection" required><button class="btn btn-outline-danger w-100" type="submit">Reject collection</button></form></div>
                        @endif
                        @if ($collection->rejection_reason)<div class="small text-danger mt-2">{{ $collection->rejection_reason }}</div>@endif
                    </div>
                @empty
                    <div class="border rounded-4 p-4 text-center text-secondary small">No payment has been submitted.</div>
                @endforelse
            </div></section>
        </div>
    </div>
@endsection
