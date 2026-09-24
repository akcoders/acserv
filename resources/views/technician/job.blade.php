@extends('layouts.mobile')

@section('title', $job->job_number.' — '.__('app.name'))
@section('dashboard-url', route('technician.dashboard'))

@php
    $status = $job->status->value;
    $stages = [
        ['label' => 'Assigned', 'icon' => 'bi-inbox'],
        ['label' => 'Accepted', 'icon' => 'bi-check2-circle'],
        ['label' => 'Reached', 'icon' => 'bi-geo-alt'],
        ['label' => 'Inspected', 'icon' => 'bi-camera'],
        ['label' => 'Approved', 'icon' => 'bi-pen'],
        ['label' => 'Working', 'icon' => 'bi-tools'],
        ['label' => 'Collect', 'icon' => 'bi-cash-coin'],
        ['label' => 'Verify', 'icon' => 'bi-shield-check'],
        ['label' => 'Completed', 'icon' => 'bi-receipt'],
    ];
    $stagePosition = match ($status) {
        'ASSIGNED' => 0,
        'ACCEPTED', 'EN_ROUTE' => 1,
        'REACHED' => 2,
        'INSPECTED' => 3,
        'AUTHORIZED' => 4,
        'IN_PROGRESS' => 5,
        'AWAITING_PAYMENT' => 6,
        'PAYMENT_PENDING' => 7,
        'COMPLETED', 'VERIFIED', 'CLOSED' => 8,
        default => -1,
    };
    $address = collect($job->service_address ?? $job->customer->service_address ?? [])->filter(fn ($value) => is_scalar($value) && filled($value))->implode(', ');
    $serviceCost = (float) ($job->service_cost ?? 0);
    $serviceTaxRate = (float) ($job->service_tax_rate ?? 18);
    $partSubtotal = $job->partConsumptions->sum(fn ($part) => max(0, (float) $part->quantity - (float) $part->returned_quantity) * (float) $part->unit_price);
    $partTax = $job->partConsumptions->sum(fn ($part) => max(0, (float) $part->quantity - (float) $part->returned_quantity) * (float) $part->unit_price * (float) $part->tax_rate / 100);
    $estimate = $serviceCost * (1 + $serviceTaxRate / 100) + $partSubtotal + $partTax;
    $canEditWork = $status === 'IN_PROGRESS';
    $isFinished = in_array($status, ['COMPLETED', 'VERIFIED', 'CLOSED'], true);
    $workSubmitted = in_array($status, ['AWAITING_PAYMENT', 'PAYMENT_PENDING', 'COMPLETED', 'VERIFIED', 'CLOSED'], true);
    $lastCollection = $job->paymentCollections->sortByDesc('submitted_at')->first();
    $requiredRemaining = $job->checklistItems->filter(fn ($item) => $item->is_required && $item->completed_at === null)->count();
@endphp

@push('head')
<style>
    .technician-job .job-hero { background: radial-gradient(circle at 95% 12%, rgba(119, 231, 242, .25), transparent 20rem), linear-gradient(135deg, #082a53 0%, #126ac7 100%); color: #fff; box-shadow: 0 1rem 2.2rem rgba(8, 65, 121, .13); }
    .technician-job .job-hero .text-soft { color: rgba(255, 255, 255, .77); }
    .technician-job .job-stage-track { display: flex; gap: .45rem; overflow-x: auto; padding: .15rem .1rem .6rem; scrollbar-width: thin; scroll-snap-type: x proximity; }
    .technician-job .job-stage { min-width: 6.35rem; flex: 1 0 6.35rem; border: 1px solid #dce5f0; border-radius: .85rem; background: #fff; padding: .65rem .45rem; text-align: center; color: #7d8c9e; font-size: .76rem; font-weight: 650; scroll-snap-align: center; }
    .technician-job .job-stage i { display: block; font-size: 1.35rem; margin-bottom: .2rem; }
    .technician-job .job-stage.is-done { color: #0b815b; border-color: #c5e9d9; background: #f0fbf5; }
    .technician-job .job-stage.is-current { color: #0b61b7; border-color: #92c4f6; background: #ebf5ff; box-shadow: inset 0 0 0 1px #92c4f6; }
    .technician-job .next-step-card { border: 1px solid #b8d7f8; border-top: 4px solid #1684c5; box-shadow: 0 .8rem 2.4rem rgba(19, 103, 200, .12); }
    .technician-job .step-number { width: 2.25rem; height: 2.25rem; display: inline-grid; place-items: center; border-radius: .75rem; color: #fff; background: #0d6efd; flex: 0 0 auto; }
    .technician-job .photo-input { border: 2px dashed #b7cbe1; border-radius: .85rem; padding: 1rem; background: #f7fbff; }
    .technician-job .photo-preview { display: none; width: 100%; max-height: 13rem; object-fit: cover; border-radius: .75rem; margin-top: .75rem; }
    .technician-job .signature-pad { display: block; width: 100%; height: 11rem; border: 2px dashed #9eb8d6; border-radius: .85rem; background: #fff; touch-action: none; cursor: crosshair; }
    .technician-job .form-label { font-weight: 650; }
    .technician-job .table-responsive { border: 1px solid #e8edf5; border-radius: .8rem; }
    .technician-job .table { margin-bottom: 0; }
</style>
@endpush

@section('content')
<div class="technician-job mx-auto" style="max-width: 1020px">
    <a class="btn btn-sm btn-link px-0 mb-3" href="{{ route('technician.dashboard') }}"><i class="bi bi-arrow-left me-1"></i>Back to my jobs</a>

    <div class="card content-card job-hero mb-4 overflow-hidden"><div class="card-body p-4">
        <div class="d-flex justify-content-between align-items-start gap-3"><div><div class="small text-soft fw-semibold mb-1">FIELD JOB · {{ $job->job_number }}</div><h1 class="h3 mb-2">{{ $job->service_type }}</h1><span class="badge rounded-pill bg-white text-primary px-3 py-2">{{ str($status)->lower()->headline() }}</span></div><div class="rounded-4 bg-white bg-opacity-10 p-3"><i class="bi bi-snow2 fs-2"></i></div></div>
        <div class="row g-3 mt-3"><div class="col-sm-4"><div class="small text-soft">Scheduled</div><strong><i class="bi bi-calendar3 me-1"></i>{{ $job->scheduled_at?->format('d M Y, h:i A') ?? 'Flexible schedule' }}</strong></div><div class="col-sm-4"><div class="small text-soft">Customer</div><strong>{{ $job->customer->name }}</strong></div><div class="col-sm-4"><div class="small text-soft">Priority</div><strong>{{ str($job->priority ?? 'NORMAL')->lower()->headline() }}</strong></div></div>
    </div></div>

    <div class="card content-card mb-4"><div class="card-body p-4">
        <div class="d-flex justify-content-between align-items-center mb-2"><h2 class="h5 mb-0">Job pipeline</h2><span class="small text-secondary">{{ max(0, $stagePosition + 1) }} of {{ count($stages) }} steps</span></div>
        <div class="progress mb-3" role="progressbar" aria-label="Job progress" aria-valuenow="{{ max(0, $stagePosition + 1) }}" aria-valuemin="0" aria-valuemax="{{ count($stages) }}" style="height: 7px"><div class="progress-bar bg-success" style="width: {{ max(0, ($stagePosition + 1) / count($stages) * 100) }}%"></div></div>
        <div class="job-stage-track" aria-label="Job stages">@foreach($stages as $position => $stage)<div class="job-stage {{ $position < $stagePosition ? 'is-done' : ($position === $stagePosition ? 'is-current' : '') }}" @if($position === $stagePosition) aria-current="step" @endif><i class="bi {{ $position < $stagePosition ? 'bi-check-circle-fill' : $stage['icon'] }}"></i>{{ $stage['label'] }}</div>@endforeach</div>
    </div></div>

    <div class="row g-4"><div class="col-lg-7">
        <div class="card content-card next-step-card mb-4"><div class="card-body p-4">
            @if($status === 'ASSIGNED')
                <div class="d-flex align-items-center gap-3 mb-3"><span class="step-number">1</span><div><div class="small text-primary fw-semibold">YOUR NEXT STEP</div><h2 class="h5 mb-0">Accept this job</h2></div></div>
                <p class="text-secondary">Review the customer and work details, then accept the assignment before travelling.</p>
                <form method="POST" action="{{ route('technician.jobs.workflow.store', $job) }}" data-workflow-form>@csrf<input type="hidden" name="action" value="accept"><button class="btn btn-primary btn-lg w-100" type="submit"><i class="bi bi-check2-circle me-2"></i>Accept job</button></form>
            @elseif(in_array($status, ['ACCEPTED', 'EN_ROUTE'], true))
                <div class="d-flex align-items-center gap-3 mb-3"><span class="step-number">2</span><div><div class="small text-primary fw-semibold">YOUR NEXT STEP</div><h2 class="h5 mb-0">Confirm arrival</h2></div></div>
                <p class="text-secondary">Tap this when you have reached the customer’s service location.</p>
                <form method="POST" action="{{ route('technician.jobs.workflow.store', $job) }}" data-workflow-form>@csrf<input type="hidden" name="action" value="reached"><button class="btn btn-primary btn-lg w-100" type="submit"><i class="bi bi-geo-alt me-2"></i>I have reached</button></form>
            @elseif($status === 'REACHED')
                <div class="d-flex align-items-center gap-3 mb-3"><span class="step-number">3</span><div><div class="small text-primary fw-semibold">YOUR NEXT STEP</div><h2 class="h5 mb-0">Record the fault</h2></div></div>
                <p class="text-secondary">Take a current photo of the unit and describe what is wrong before any repair starts.</p>
                <form method="POST" action="{{ route('technician.jobs.workflow.store', $job) }}" enctype="multipart/form-data" data-workflow-form>@csrf<input type="hidden" name="action" value="inspection">
                    <div class="photo-input mb-3"><label class="form-label" for="before-photo"><i class="bi bi-camera me-1"></i>Before-work photo <span class="text-danger">*</span></label><input class="form-control" id="before-photo" type="file" name="before_photo" accept="image/*" capture="environment" required><img class="photo-preview" alt="Before-work photo preview" data-photo-preview></div>
                    <div class="mb-3"><label class="form-label" for="fault-remark">What is wrong? <span class="text-danger">*</span></label><textarea class="form-control" id="fault-remark" name="fault_remark" rows="3" maxlength="5000" placeholder="Describe the fault you found and what you recommend" required></textarea></div>
                    <button class="btn btn-primary btn-lg w-100" type="submit"><i class="bi bi-cloud-arrow-up me-2"></i>Save inspection</button>
                </form>
            @elseif($status === 'INSPECTED')
                <div class="d-flex align-items-center gap-3 mb-3"><span class="step-number">4</span><div><div class="small text-primary fw-semibold">CUSTOMER APPROVAL</div><h2 class="h5 mb-0">Get approval to start</h2></div></div>
                <p class="text-secondary">Show the customer the fault details, then ask them to sign on this phone.</p>
                <div class="alert alert-light border mb-3"><div class="small text-secondary">Fault recorded</div><strong>{{ $job->inspection_remark }}</strong></div>
                <form method="POST" action="{{ route('technician.jobs.workflow.store', $job) }}" data-workflow-form>@csrf<input type="hidden" name="action" value="authorize">
                    <label class="form-label" for="approval-signature">Customer signature <span class="text-danger">*</span></label><canvas class="signature-pad" id="approval-signature" data-signature-canvas aria-label="Customer approval signature"></canvas>
                    <div class="d-flex justify-content-between align-items-center mt-2 mb-3"><small class="text-secondary">Ask the customer to sign with a finger.</small><button class="btn btn-sm btn-outline-secondary" type="button" data-clear-signature>Clear</button></div>
                    <button class="btn btn-primary btn-lg w-100" type="submit"><i class="bi bi-pen me-2"></i>Save customer approval</button>
                </form>
            @elseif($status === 'AUTHORIZED')
                <div class="d-flex align-items-center gap-3 mb-3"><span class="step-number">5</span><div><div class="small text-primary fw-semibold">YOUR NEXT STEP</div><h2 class="h5 mb-0">Start work</h2></div></div>
                <p class="text-secondary">The customer has signed the inspection. Start the repair to unlock parts, service charges, and the work checklist.</p>
                <form method="POST" action="{{ route('technician.jobs.workflow.store', $job) }}" data-workflow-form>@csrf<input type="hidden" name="action" value="start"><button class="btn btn-primary btn-lg w-100" type="submit"><i class="bi bi-play-circle me-2"></i>Start work</button></form>
            @elseif($canEditWork)
                <div class="d-flex align-items-center gap-3 mb-3"><span class="step-number">6</span><div><div class="small text-primary fw-semibold">FINAL HANDOVER</div><h2 class="h5 mb-0">Complete the work</h2></div></div>
                <p class="text-secondary">Add parts and service charges below. When finished, take an after-work photo, describe the result, and have the customer sign.</p>
                <form method="POST" action="{{ route('technician.jobs.workflow.store', $job) }}" enctype="multipart/form-data" data-workflow-form>@csrf<input type="hidden" name="action" value="complete">
                    <div class="photo-input mb-3"><label class="form-label" for="after-photo"><i class="bi bi-camera me-1"></i>After-work photo <span class="text-danger">*</span></label><input class="form-control" id="after-photo" type="file" name="after_photo" accept="image/*" capture="environment" required><img class="photo-preview" alt="After-work photo preview" data-photo-preview></div>
                    <div class="mb-3"><label class="form-label" for="completion-remark">What work was completed? <span class="text-danger">*</span></label><textarea class="form-control" id="completion-remark" name="completion_remark" rows="3" maxlength="5000" placeholder="Describe the repair, tests, and final result" required></textarea></div>
                    <label class="form-label" for="completion-signature">Customer completion signature <span class="text-danger">*</span></label><canvas class="signature-pad" id="completion-signature" data-signature-canvas aria-label="Customer completion signature"></canvas>
                    <div class="d-flex justify-content-between align-items-center mt-2 mb-3"><small class="text-secondary">Ask the customer to confirm the finished work.</small><button class="btn btn-sm btn-outline-secondary" type="button" data-clear-signature>Clear</button></div>
                    @if($requiredRemaining > 0)<div class="alert alert-warning"><i class="bi bi-list-check me-2"></i>Complete {{ $requiredRemaining }} required checklist {{ str('item')->plural($requiredRemaining) }} below before submitting.</div>@endif
                    <button class="btn btn-success btn-lg w-100" type="submit" @disabled($requiredRemaining > 0)><i class="bi bi-check2-circle me-2"></i>Submit completed work</button>
                </form>
            @elseif($status === 'AWAITING_PAYMENT')
                <div class="d-flex align-items-center gap-3 mb-3"><span class="step-number">7</span><div><div class="small text-primary fw-semibold">CUSTOMER PAYMENT</div><h2 class="h5 mb-0">Collect ₹{{ number_format($billTotal, 2) }}</h2></div></div>
                <p class="text-secondary">Show the customer the final amount. Cash or UPI must be submitted for office verification before the invoice is issued.</p>
                @if($lastCollection?->status === \App\Enums\CollectionStatus::Rejected)<div class="alert alert-warning"><strong>Previous collection rejected:</strong> {{ $lastCollection->rejection_reason }}. Submit the payment again.</div>@endif
                <form method="POST" action="{{ route('technician.jobs.payment-collections.store', $job) }}" enctype="multipart/form-data" data-ajax data-collection-form>@csrf
                    <label class="form-label" for="payment-mode">Payment method</label><select class="form-select mb-3" id="payment-mode" name="mode" data-payment-mode required><option value="CASH">Cash collected</option><option value="UPI" @disabled(! $paymentTenant->upi_qr_path)>UPI transfer</option></select>
                    <div class="border rounded-4 p-3 mb-3 d-none" data-upi-details>
                        @if($paymentTenant->upi_qr_path)<div class="text-center"><img class="img-fluid rounded border p-2 bg-white" src="{{ route('payment-qr.image') }}" alt="Workspace UPI payment QR" style="max-height: 260px"><div class="fw-semibold mt-2">{{ $paymentTenant->upi_payee_name }} · {{ $paymentTenant->upi_id }}</div></div>@else<div class="alert alert-warning mb-0">Office has not configured a UPI QR yet. Use cash or ask the office to add a QR.</div>@endif
                        <label class="form-label mt-3" for="transaction-image">UPI transaction screenshot <span class="text-danger">*</span></label><input class="form-control" id="transaction-image" type="file" name="transaction_image" accept="image/*" capture="environment" data-transaction-image>
                    </div>
                    <label class="form-label" for="payment-reference">Reference / note (optional)</label><input class="form-control mb-3" id="payment-reference" name="reference" maxlength="191" placeholder="UPI transaction ID or cash handover note">
                    <button class="btn btn-success btn-lg w-100" type="submit"><i class="bi bi-send-check me-2"></i>Submit collection for verification</button>
                </form>
            @elseif($status === 'PAYMENT_PENDING')
                <div class="text-center py-3"><i class="bi bi-hourglass-split text-warning display-4"></i><h2 class="h4 mt-3">Waiting for office verification</h2><p class="text-secondary mb-0">{{ str($lastCollection?->mode?->value ?? 'Payment')->lower()->headline() }} collection of ₹{{ number_format((float) ($lastCollection?->amount ?? $billTotal), 2) }} was submitted. The invoice will appear after the office verifies it.</p></div>
            @elseif($isFinished)
                <div class="text-center py-3"><i class="bi bi-check-circle-fill text-success display-4"></i><h2 class="h4 mt-3">Work completed</h2><p class="text-secondary mb-3">Photos, remarks, and customer sign-off are recorded.</p>@if($job->invoice)<a class="btn btn-primary w-100" href="{{ route('technician.jobs.invoice.pdf', $job) }}"><i class="bi bi-file-earmark-pdf me-2"></i>View customer invoice · ₹{{ number_format((float) $job->invoice->grand_total, 2) }}</a>@endif</div>
            @else
                <div class="alert alert-secondary mb-0">This job is {{ str($status)->lower()->headline() }}. Contact your dispatcher for the next step.</div>
            @endif
        </div></div>

        <div class="card content-card mb-4"><div class="card-body p-4">
            <div class="d-flex justify-content-between align-items-center mb-3"><h2 class="h5 mb-0">Work checklist</h2><span class="badge text-bg-light">{{ $job->checklistItems->whereNotNull('completed_at')->count() }}/{{ $job->checklistItems->count() }}</span></div>
            <div class="list-group list-group-flush">@forelse($job->checklistItems as $item)
                @if($canEditWork)
                    <button class="list-group-item list-group-item-action d-flex gap-3 align-items-center px-0" type="button" data-confirm-ajax data-method="POST" data-url="{{ route('technician.jobs.checklist.update', [$job, $item]) }}" data-title="{{ $item->completed_at ? 'Mark this task incomplete?' : 'Complete this task?' }}" data-text="{{ $item->label }}"><i class="bi bi-{{ $item->completed_at ? 'check-circle-fill text-success' : 'circle text-secondary' }} fs-5"></i><span class="{{ $item->completed_at ? 'text-decoration-line-through text-secondary' : '' }}">{{ $item->label }}</span></button>
                @else
                    <div class="list-group-item d-flex gap-3 align-items-center px-0"><i class="bi bi-{{ $item->completed_at ? 'check-circle-fill text-success' : 'circle text-secondary' }} fs-5"></i><span class="{{ $item->completed_at ? 'text-decoration-line-through text-secondary' : '' }}">{{ $item->label }}</span></div>
                @endif
            @empty<div class="text-secondary">No tasks were added to this job.</div>@endforelse</div>
            @if(!$canEditWork && !$workSubmitted && $job->checklistItems->isNotEmpty())<small class="text-secondary">Tasks unlock when work begins.</small>@endif
        </div></div>

        @if($canEditWork || $workSubmitted)
            <div class="card content-card mb-4"><div class="card-body p-4">
                <div class="d-flex align-items-center gap-2 mb-3"><i class="bi bi-box-seam fs-4 text-primary"></i><h2 class="h5 mb-0">Parts from inventory</h2></div>
                @if($canEditWork)
                    @if($stockLocations->isNotEmpty() && $inventoryItems->isNotEmpty())
                        <form method="POST" action="{{ route('technician.jobs.parts.store', $job) }}" data-ajax class="row g-3 mb-4">@csrf
                            <div class="col-12"><label class="form-label" for="inventory-item">Part</label><select class="form-select" id="inventory-item" name="inventory_item_id" required>@foreach($inventoryItems as $item)<option value="{{ $item->id }}">{{ $item->sku }} · {{ $item->name }} · ₹{{ number_format((float) $item->sale_price, 2) }}</option>@endforeach</select></div>
                            <div class="col-sm-8"><label class="form-label" for="stock-location">Stock location</label><select class="form-select" id="stock-location" name="stock_location_id" required>@foreach($stockLocations as $location)<option value="{{ $location->id }}">{{ $location->name }}</option>@endforeach</select></div>
                            <div class="col-sm-4"><label class="form-label" for="part-quantity">Quantity</label><input class="form-control" id="part-quantity" type="number" name="quantity" min="0.001" step="0.001" value="1" required></div>
                            <div class="col-12"><button class="btn btn-outline-primary w-100" type="submit"><i class="bi bi-plus-circle me-2"></i>Add part to bill</button></div>
                        </form>
                    @else
                        <div class="alert alert-warning">{{ $stockLocations->isEmpty() ? 'No stock location is assigned to you. Ask the office to assign stock.' : 'No active inventory parts are available.' }}</div>
                    @endif
                @endif
                @if($job->partConsumptions->isNotEmpty())
                    <div class="table-responsive"><table class="table table-sm align-middle w-100" data-rich-table><thead class="table-light"><tr><th>Part</th><th class="text-end">Used</th><th class="text-end">Amount</th></tr></thead><tbody>@foreach($job->partConsumptions as $part)@php($used = max(0, (float) $part->quantity - (float) $part->returned_quantity))<tr><td><strong>{{ $part->inventoryItem?->name ?? 'Part' }}</strong><div class="small text-secondary">₹{{ number_format((float) $part->unit_price, 2) }} each · {{ number_format((float) $part->tax_rate, 0) }}% tax</div>@if($canEditWork && $used > 0)<form class="d-flex gap-1 mt-2" method="POST" action="{{ route('technician.jobs.parts.return', [$job, $part]) }}" data-ajax>@csrf<input class="form-control form-control-sm" style="max-width: 95px" type="number" name="quantity" min="0.001" step="0.001" max="{{ $used }}" value="{{ $used }}" aria-label="Quantity to return" required><button class="btn btn-sm btn-outline-warning" type="submit">Return</button></form>@endif</td><td class="text-end">{{ $used }}</td><td class="text-end fw-semibold">₹{{ number_format($used * (float) $part->unit_price * (1 + (float) $part->tax_rate / 100), 2) }}</td></tr>@endforeach</tbody></table></div>
                @else
                    <div class="small text-secondary">No parts added yet.</div>
                @endif
            </div></div>

            <div class="card content-card mb-4"><div class="card-body p-4">
                <div class="d-flex align-items-center gap-2 mb-3"><i class="bi bi-currency-rupee fs-4 text-primary"></i><h2 class="h5 mb-0">Service charge</h2></div>
                @if($canEditWork)
                    <form method="POST" action="{{ route('technician.jobs.service-cost.store', $job) }}" data-ajax class="row g-3">@csrf
                        <div class="col-sm-6"><label class="form-label" for="service-cost">Labor / service cost (₹)</label><input class="form-control" id="service-cost" type="number" name="service_cost" min="0" step="0.01" value="{{ number_format($serviceCost, 2, '.', '') }}" required></div>
                        <div class="col-sm-6"><label class="form-label">Tax rate</label><div class="form-control bg-light">{{ number_format($serviceTaxRate, 2) }}% · set by office</div></div>
                        <div class="col-12"><button class="btn btn-outline-primary w-100" type="submit"><i class="bi bi-check2 me-2"></i>Save service charge</button></div>
                    </form>
                @else<div>₹{{ number_format($serviceCost, 2) }} <span class="text-secondary small">+ {{ number_format($serviceTaxRate, 0) }}% tax</span></div>@endif
            </div></div>
        @endif
    </div>

    <div class="col-lg-5">
        <div class="card content-card mb-4"><div class="card-body p-4">
            <h2 class="h5 mb-3">Customer & location</h2><div class="mb-3"><div class="small text-secondary">Customer</div><strong>{{ $job->customer->name }}</strong></div>
            @if($job->customer->phone)<a class="btn btn-outline-primary w-100 mb-3" href="tel:{{ $job->customer->phone }}"><i class="bi bi-telephone me-2"></i>Call {{ $job->customer->phone }}</a>@endif
            @if($address)<div class="mb-3"><div class="small text-secondary">Service address</div><div>{{ $address }}</div></div><a class="btn btn-outline-secondary w-100" href="https://www.google.com/maps/search/?api=1&query={{ urlencode($address) }}" target="_blank" rel="noopener"><i class="bi bi-geo-alt me-2"></i>Open directions</a>@endif
            @if($job->asset)<hr><div class="small text-secondary">AC unit</div><strong>{{ $job->asset->name }}</strong><div class="small text-secondary">{{ $job->asset->brand }} {{ $job->asset->model }}</div>@endif
            @if($job->description)<hr><div class="small text-secondary">Reported problem</div><div>{{ $job->description }}</div>@endif
        </div></div>

        @if($canEditWork || $workSubmitted)
            <div class="card content-card mb-4"><div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-center mb-3"><h2 class="h5 mb-0">Bill preview</h2><i class="bi bi-receipt fs-4 text-primary"></i></div>
                <div class="d-flex justify-content-between mb-2"><span class="text-secondary">Service</span><strong>₹{{ number_format($serviceCost, 2) }}</strong></div><div class="d-flex justify-content-between mb-2"><span class="text-secondary">Parts</span><strong>₹{{ number_format($partSubtotal, 2) }}</strong></div><div class="d-flex justify-content-between mb-2"><span class="text-secondary">Tax</span><strong>₹{{ number_format($serviceCost * $serviceTaxRate / 100 + $partTax, 2) }}</strong></div>
                <hr><div class="d-flex justify-content-between align-items-center"><strong>{{ $isFinished && $job->invoice ? 'Invoice total' : 'Amount to collect' }}</strong><strong class="h4 text-primary mb-0">₹{{ number_format($isFinished && $job->invoice ? (float) $job->invoice->grand_total : $billTotal, 2) }}</strong></div>
                @if($isFinished && $job->invoice)<a class="btn btn-outline-primary w-100 mt-3" href="{{ route('technician.jobs.invoice.pdf', $job) }}"><i class="bi bi-file-earmark-pdf me-2"></i>Download invoice</a>@else<small class="text-secondary d-block mt-3">The final invoice is generated when the office verifies collection.</small>@endif
            </div></div>
        @endif

        <div class="card content-card mb-4"><div class="card-body p-4">
            <h2 class="h5 mb-3">Job record</h2><div class="vstack gap-2"><div class="d-flex justify-content-between"><span>Before photo & fault</span><span class="badge text-bg-{{ $stagePosition >= 3 ? 'success' : 'light' }}">{{ $stagePosition >= 3 ? 'Recorded' : 'Pending' }}</span></div><div class="d-flex justify-content-between"><span>Approval signature</span><span class="badge text-bg-{{ $stagePosition >= 4 ? 'success' : 'light' }}">{{ $stagePosition >= 4 ? 'Signed' : 'Pending' }}</span></div><div class="d-flex justify-content-between"><span>After photo & sign-off</span><span class="badge text-bg-{{ $workSubmitted ? 'success' : 'light' }}">{{ $workSubmitted ? 'Recorded' : 'Pending' }}</span></div></div>
            @if($job->evidence->isNotEmpty())<hr><div class="small text-secondary mb-2">Uploaded evidence</div><div class="d-flex flex-wrap gap-2">@foreach($job->evidence as $evidence)<a class="btn btn-sm btn-outline-secondary" href="{{ URL::temporarySignedRoute('evidence.download', now()->addMinutes(5), $evidence) }}"><i class="bi bi-image me-1"></i>{{ str($evidence->type->value)->lower()->headline() }}</a>@endforeach</div>@endif
            @if($canEditWork)<hr><form method="POST" action="{{ route('technician.jobs.evidence.store', $job) }}" enctype="multipart/form-data" data-ajax data-geolocation-form>@csrf<input type="hidden" name="type" value="DURING"><input type="hidden" name="latitude"><input type="hidden" name="longitude"><input type="hidden" name="accuracy_metres"><input type="hidden" name="captured_at"><input type="hidden" name="device_id"><input type="hidden" name="is_mock_location" value="0"><label class="form-label" for="extra-photo">Extra work photo</label><input class="form-control mb-2" id="extra-photo" type="file" name="evidence" accept="image/*" capture="environment" required><button class="btn btn-outline-secondary w-100" type="submit"><i class="bi bi-camera me-2"></i>Upload extra photo</button></form>@endif
        </div></div>
    </div></div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const stageTrack = document.querySelector('.job-stage-track');
    const currentStage = stageTrack?.querySelector('.is-current');
    if (currentStage) stageTrack.scrollLeft = currentStage.offsetLeft - stageTrack.offsetLeft - (stageTrack.clientWidth - currentStage.clientWidth) / 2;
    document.querySelectorAll('[data-collection-form]').forEach((form) => {
        const mode = form.querySelector('[data-payment-mode]');
        const details = form.querySelector('[data-upi-details]');
        const screenshot = form.querySelector('[data-transaction-image]');
        const update = () => {
            const isUpi = mode.value === 'UPI';
            details.classList.toggle('d-none', !isUpi);
            screenshot.required = isUpi;
            if (!isUpi) screenshot.value = '';
        };
        mode.addEventListener('change', update);
        update();
    });

    const deviceId = localStorage.getItem('acserv-device-id') || (window.crypto?.randomUUID?.() ?? String(Date.now()));
    localStorage.setItem('acserv-device-id', deviceId);

    document.querySelectorAll('[data-photo-preview]').forEach((preview) => {
        const input = preview.parentElement.querySelector('input[type="file"]');
        input?.addEventListener('change', () => {
            if (preview.dataset.objectUrl) URL.revokeObjectURL(preview.dataset.objectUrl);
            const file = input.files?.[0];
            preview.style.display = file ? 'block' : 'none';
            if (file) { preview.dataset.objectUrl = URL.createObjectURL(file); preview.src = preview.dataset.objectUrl; }
        });
    });

    const signaturePads = new WeakMap();
    document.querySelectorAll('[data-signature-canvas]').forEach((canvas) => {
        const bounds = canvas.getBoundingClientRect();
        const ratio = window.devicePixelRatio || 1;
        canvas.width = Math.round(bounds.width * ratio);
        canvas.height = Math.round(bounds.height * ratio);
        const context = canvas.getContext('2d');
        context.scale(ratio, ratio);
        context.strokeStyle = '#143b69';
        context.lineWidth = 2.7;
        context.lineCap = 'round';
        context.lineJoin = 'round';
        const state = { context, hasInk: false, drawing: false };
        signaturePads.set(canvas, state);
        const point = (event) => ({ x: event.clientX - canvas.getBoundingClientRect().left, y: event.clientY - canvas.getBoundingClientRect().top });
        canvas.addEventListener('pointerdown', (event) => { event.preventDefault(); canvas.setPointerCapture(event.pointerId); const start = point(event); context.beginPath(); context.moveTo(start.x, start.y); state.drawing = true; });
        canvas.addEventListener('pointermove', (event) => { if (!state.drawing) return; event.preventDefault(); const next = point(event); context.lineTo(next.x, next.y); context.stroke(); state.hasInk = true; });
        const finish = () => { state.drawing = false; };
        canvas.addEventListener('pointerup', finish);
        canvas.addEventListener('pointercancel', finish);
        canvas.parentElement.querySelector('[data-clear-signature]')?.addEventListener('click', () => { context.clearRect(0, 0, canvas.width, canvas.height); state.hasInk = false; });
    });

    const addLocation = (payload) => new Promise((resolve) => {
        if (!navigator.geolocation) { resolve(); return; }
        navigator.geolocation.getCurrentPosition((position) => { if (position.coords.accuracy <= 100) { payload.set('latitude', String(position.coords.latitude)); payload.set('longitude', String(position.coords.longitude)); payload.set('accuracy_metres', String(position.coords.accuracy)); payload.set('captured_at', new Date().toISOString()); payload.set('device_id', deviceId); } resolve(); }, resolve, { enableHighAccuracy: true, timeout: 6000, maximumAge: 0 });
    });

    document.querySelectorAll('[data-workflow-form]').forEach((form) => form.addEventListener('submit', async (event) => {
        event.preventDefault();
        if (!form.reportValidity()) return;
        const canvas = form.querySelector('[data-signature-canvas]');
        if (canvas && !signaturePads.get(canvas)?.hasInk) { await window.Swal.fire({ icon: 'warning', title: 'Customer signature needed', text: 'Ask the customer to sign on this phone before continuing.' }); return; }
        const button = form.querySelector('[type="submit"]');
        const label = button.innerHTML;
        button.disabled = true;
        button.innerHTML = '<span class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>Saving…';
        try {
            const payload = new FormData(form);
            if (canvas) { const blob = await new Promise((resolve) => canvas.toBlob(resolve, 'image/png')); if (!blob) throw new Error('Could not save the signature. Please sign again.'); payload.set('customer_signature', blob, 'signature.png'); }
            if (['reached', 'start', 'complete'].includes(payload.get('action'))) await addLocation(payload);
            const response = await window.$.ajax({ url: form.getAttribute('action'), method: 'POST', data: payload, contentType: false, processData: false });
            await window.Swal.fire({ icon: 'success', title: response.message ?? 'Step recorded', timer: 1200, showConfirmButton: false });
            window.location.reload();
        } catch (error) {
            const firstError = Object.values(error.responseJSON?.errors ?? {})[0]?.[0];
            await window.Swal.fire({ icon: 'error', title: 'Could not save this step', text: firstError ?? error.responseJSON?.message ?? error.message ?? 'Please try again.' });
            button.disabled = false;
            button.innerHTML = label;
        }
    }));

    document.querySelectorAll('[data-geolocation-form]').forEach((form) => {
        form.querySelector('[name="device_id"]').value = deviceId;
        form.addEventListener('submit', (event) => {
            if (form.dataset.locationReady === 'true') return;
            event.preventDefault();
            event.stopImmediatePropagation();
            if (!navigator.geolocation) { void window.Swal.fire('Location needed', 'Enable location to upload an extra photo.', 'warning'); return; }
            navigator.geolocation.getCurrentPosition((position) => { form.querySelector('[name="latitude"]').value = position.coords.latitude; form.querySelector('[name="longitude"]').value = position.coords.longitude; form.querySelector('[name="accuracy_metres"]').value = position.coords.accuracy; form.querySelector('[name="captured_at"]').value = new Date().toISOString(); form.dataset.locationReady = 'true'; form.requestSubmit(); }, () => void window.Swal.fire('Location needed', 'Enable precise location to upload an extra photo.', 'warning'), { enableHighAccuracy: true, timeout: 15000 });
        }, true);
    });
});
</script>
@endpush
