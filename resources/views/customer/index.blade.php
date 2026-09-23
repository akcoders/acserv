@extends('layouts.mobile')

@section('title', 'Customer — '.__('app.name'))
@section('dashboard-url', route('customer.dashboard'))

@section('content')
    <div class="rounded-4 bg-dark text-white p-4 p-lg-5 mb-4 shadow-sm">
        <div class="row align-items-center g-4">
            <div class="col-lg-8">
                <div class="small text-uppercase fw-semibold text-info mb-2">Your service workspace</div>
                <h1 class="h2 fw-bold mb-2">Welcome back, {{ $customer->name }}</h1>
                <p class="text-white-50 mb-4">Book a visit, follow every stage of the repair, and pay your bill in one place.</p>
                <div class="d-flex flex-wrap gap-2">
                    <button class="btn btn-info fw-semibold" data-bs-toggle="modal" data-bs-target="#bookingModal"><i class="bi bi-calendar-plus me-2"></i>Book a service</button>
                    <button class="btn btn-outline-light" data-bs-toggle="modal" data-bs-target="#assetModal"><i class="bi bi-plus-circle me-2"></i>Add an AC unit</button>
                </div>
            </div>
            <div class="col-lg-4 d-none d-lg-block text-center"><i class="bi bi-snow2 display-1 text-info" aria-hidden="true"></i></div>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-6 col-lg-4"><div class="card content-card h-100"><div class="card-body d-flex align-items-center gap-3"><span class="rounded-3 bg-primary-subtle text-primary p-3"><i class="bi bi-snow fs-4"></i></span><div><div class="h3 fw-bold mb-0">{{ $assets->count() }}</div><div class="small text-secondary">AC units</div></div></div></div></div>
        <div class="col-6 col-lg-4"><div class="card content-card h-100"><div class="card-body d-flex align-items-center gap-3"><span class="rounded-3 bg-warning-subtle text-warning-emphasis p-3"><i class="bi bi-tools fs-4"></i></span><div><div class="h3 fw-bold mb-0">{{ $activeJobCount }}</div><div class="small text-secondary">Active jobs</div></div></div></div></div>
        <div class="col-12 col-lg-4"><div class="card content-card h-100"><div class="card-body d-flex align-items-center gap-3"><span class="rounded-3 bg-success-subtle text-success p-3"><i class="bi bi-receipt fs-4"></i></span><div><div class="h3 fw-bold mb-0">₹{{ number_format($outstandingBalance, 2) }}</div><div class="small text-secondary">Balance due</div></div></div></div></div>
    </div>

    <div class="row g-4 align-items-start">
        <div class="col-xl-7">
            <section class="card content-card mb-4" id="service-pipeline">
                <div class="card-header bg-white border-0 pt-4 px-4 d-flex justify-content-between align-items-center">
                    <div><div class="small text-uppercase fw-semibold text-primary">Live updates</div><h2 class="h5 fw-bold mb-0">Service pipeline</h2></div>
                    <span class="badge text-bg-primary rounded-pill">{{ $jobs->count() }} recent</span>
                </div>
                <div class="card-body px-4 pb-4 vstack gap-3">
                    @forelse($jobs as $job)
                        @php
                            $pipeline = ['CREATED', 'ASSIGNED', 'ACCEPTED', 'EN_ROUTE', 'REACHED', 'INSPECTED', 'AUTHORIZED', 'IN_PROGRESS', 'AWAITING_PAYMENT', 'PAYMENT_PENDING', 'COMPLETED'];
                            $stageIndex = array_search($job->status->value, $pipeline, true);
                            $progress = in_array($job->status->value, ['VERIFIED', 'CLOSED'], true) ? 100 : ($stageIndex === false ? 0 : (int) round($stageIndex / (count($pipeline) - 1) * 100));
                            $isFinished = in_array($job->status, [\App\Enums\JobStatus::Completed, \App\Enums\JobStatus::Verified, \App\Enums\JobStatus::Closed], true);
                        @endphp
                        <article class="border rounded-4 p-3 p-sm-4" data-track-url="{{ route('customer.jobs.tracking', $job) }}" data-status="{{ $job->status->value }}">
                            <div class="d-flex align-items-start justify-content-between gap-3">
                                <div><div class="small text-secondary mb-1">{{ $job->job_number }} · {{ $job->asset?->name ?? 'Service visit' }}</div><h3 class="h6 fw-bold mb-0">{{ $job->service_type }}</h3></div>
                                <span class="badge rounded-pill {{ $isFinished ? 'text-bg-success' : ($job->status === \App\Enums\JobStatus::Cancelled ? 'text-bg-secondary' : 'text-bg-primary') }} job-status">{{ str($job->status->value)->lower()->headline() }}</span>
                            </div>
                            <div class="d-flex justify-content-between small text-secondary mt-3"><span>Job progress</span><strong class="job-progress-label">{{ $progress }}%</strong></div>
                            <div class="progress mt-1" role="progressbar" aria-label="Job progress" aria-valuenow="{{ $progress }}" aria-valuemin="0" aria-valuemax="100" style="height: 0.55rem"><div class="progress-bar job-progress-bar" style="width: {{ $progress }}%"></div></div>
                            <div class="d-flex flex-wrap gap-3 small text-secondary mt-3">
                                <span><i class="bi bi-person-badge me-1"></i>{{ $job->assignments->pluck('technician.name')->filter()->join(', ') ?: 'Technician pending' }}</span>
                                @if($job->scheduled_at)<span><i class="bi bi-calendar-event me-1"></i>{{ $job->scheduled_at->format('d M, g:i A') }}</span>@endif
                            </div>
                            @if($job->status === \App\Enums\JobStatus::AwaitingPayment)
                                <div class="alert alert-info py-2 mt-3 mb-0 small">Service work is finished. The technician will collect the payment and submit it for verification.</div>
                            @elseif($job->status === \App\Enums\JobStatus::PaymentPending)
                                <div class="alert alert-warning py-2 mt-3 mb-0 small">Payment was submitted and is awaiting office verification. Your invoice will appear after verification.</div>
                            @endif
                            @if($job->invoice)
                                <div class="alert alert-success py-2 mt-3 mb-0 small d-flex justify-content-between align-items-center gap-2"><span><i class="bi bi-receipt me-1"></i>Bill {{ $job->invoice->invoice_number }} · ₹{{ number_format((float) $job->invoice->grand_total, 2) }}</span><a href="{{ route('customer.invoices.pdf', $job->invoice) }}" class="alert-link text-nowrap">View bill</a></div>
                            @endif
                            @if($isFinished)
                                <form class="row g-2 mt-3" method="POST" action="{{ route('customer.feedback.store') }}" data-ajax>
                                    @csrf
                                    <input type="hidden" name="job_id" value="{{ $job->id }}">
                                    <div class="col-sm-4"><select class="form-select" name="rating" aria-label="Service rating">@for($rating=5;$rating>=1;$rating--)<option value="{{ $rating }}">{{ $rating }} star</option>@endfor</select></div>
                                    <div class="col-sm-8"><input class="form-control" name="comment" placeholder="How was the service?"></div>
                                    <div class="col-12"><button class="btn btn-sm btn-outline-primary w-100">Submit feedback</button></div>
                                </form>
                            @endif
                        </article>
                    @empty
                        <div class="text-center py-5"><i class="bi bi-calendar2-check display-5 text-primary"></i><h3 class="h6 mt-3">No service jobs yet</h3><p class="text-secondary mb-0">Book your first visit to see its progress here.</p></div>
                    @endforelse
                </div>
            </section>
        </div>
        <div class="col-xl-5">
            <section class="card content-card mb-4">
                <div class="card-header bg-white border-0 pt-4 px-4"><div class="small text-uppercase fw-semibold text-primary">Equipment</div><h2 class="h5 fw-bold mb-0">Your AC units</h2></div>
                <div class="card-body px-4 pb-4 vstack gap-3">
                    @forelse($assets as $asset)
                        <div class="border rounded-4 p-3"><div class="d-flex justify-content-between align-items-start gap-2"><div><strong>{{ $asset->name }}</strong><div class="small text-secondary mt-1">{{ $asset->brand }} {{ $asset->model }} @if($asset->capacity)· {{ $asset->capacity }}@endif</div></div><span class="rounded-3 bg-primary-subtle text-primary px-2 py-1"><i class="bi bi-snow"></i></span></div>@foreach($asset->warranties as $warranty)<a class="small d-block mt-2" href="{{ route('customer.warranties.certificate', $warranty) }}"><i class="bi bi-patch-check me-1"></i>Warranty until {{ $warranty->ends_on->format('d M Y') }}</a>@endforeach</div>
                    @empty
                        <p class="text-secondary mb-0">Add your first AC unit to keep its service history together.</p>
                    @endforelse
                </div>
            </section>
            @if($reminders->isNotEmpty())
                <div class="alert alert-info rounded-4"><h2 class="h6 fw-bold"><i class="bi bi-bell me-1"></i>Upcoming reminders</h2>@foreach($reminders as $reminder)<div>{{ str($reminder->type)->headline() }} due {{ $reminder->due_on->format('d M Y') }}</div>@endforeach</div>
            @endif
        </div>
    </div>

    <section class="card content-card mb-4" id="invoices">
        <div class="card-header bg-white border-0 pt-4 px-4"><div class="small text-uppercase fw-semibold text-primary">Payments</div><h2 class="h5 fw-bold mb-0">Recent invoices</h2></div>
        <div class="card-body px-4 pb-4"><div class="table-responsive"><table class="table table-hover align-middle mb-0" data-rich-table><thead><tr><th>Invoice</th><th>Issued</th><th>Status</th><th>Total</th><th>Due</th><th class="text-end">Actions</th></tr></thead><tbody>
            @forelse($invoices as $invoice)
                <tr><td class="fw-semibold">{{ $invoice->invoice_number }}</td><td>{{ $invoice->issued_on?->format('d M Y') }}</td><td><span class="badge {{ (float) $invoice->balance_due > 0 ? 'text-bg-warning' : 'text-bg-success' }}">{{ str($invoice->status->value)->headline() }}</span></td><td>₹{{ number_format((float) $invoice->grand_total, 2) }}</td><td>₹{{ number_format((float) $invoice->balance_due, 2) }}</td><td class="text-end"><div class="btn-group btn-group-sm"><a class="btn btn-outline-primary" href="{{ route('customer.invoices.pdf', $invoice) }}">PDF</a>@if((float) $invoice->balance_due > 0)<button class="btn btn-primary" data-online-payment data-url="{{ route('customer.invoices.gateway-order', $invoice) }}" data-amount="{{ $invoice->balance_due }}">Pay now</button>@endif</div></td></tr>
            @empty
                <tr><td colspan="6" class="text-center text-secondary py-4">No invoices yet.</td></tr>
            @endforelse
        </tbody></table></div></div>
    </section>
    <div class="card content-card"><div class="card-body"><h2 class="h5">Notification preferences</h2><form method="POST" action="{{ route('customer.notification-preferences.store') }}" data-ajax>@csrf<div class="row g-2"><div class="col-md-4"><select class="form-select" name="channel">@foreach(\App\Enums\NotificationChannel::cases() as $channel)<option value="{{ $channel->value }}">{{ $channel->value }}</option>@endforeach</select></div><div class="col-md-4"><input class="form-control" type="time" name="quiet_starts_at" title="Quiet hours start"></div><div class="col-md-4"><input class="form-control" type="time" name="quiet_ends_at" title="Quiet hours end"></div><input type="hidden" name="event" value="*"><input type="hidden" name="is_enabled" value="1"><input type="hidden" name="timezone" value="Asia/Kolkata"><div class="col-12"><button class="btn btn-outline-primary w-100">Save preference</button></div></div></form></div></div>

    <div class="modal fade" id="bookingModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content"><form method="POST" action="{{ route('customer.bookings.store') }}" data-ajax data-offline-queue>@csrf<div class="modal-header"><h2 class="h5 modal-title">Book AC service</h2><button class="btn-close" type="button" data-bs-dismiss="modal"></button></div><div class="modal-body vstack gap-3"><div><label class="form-label">AC unit</label><select class="form-select" name="asset_id"><option value="">General service</option>@foreach($assets as $asset)<option value="{{ $asset->id }}">{{ $asset->name }} — {{ $asset->brand }}</option>@endforeach</select></div><div><label class="form-label">Service type</label><select class="form-select" name="service_type"><option>AC servicing</option><option>Repair</option><option>Installation</option><option>Uninstallation</option><option>Inspection</option></select></div><div><label class="form-label">Preferred time</label><input class="form-control" type="datetime-local" name="preferred_start_at" required></div><div><label class="form-label">Problem</label><textarea class="form-control" name="complaint" rows="3"></textarea></div></div><div class="modal-footer"><button class="btn btn-primary">Submit booking</button></div></form></div></div></div>
    <div class="modal fade" id="assetModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content"><form method="POST" action="{{ route('customer.assets.store') }}" data-ajax>@csrf<div class="modal-header"><h2 class="h5 modal-title">Add AC unit</h2><button class="btn-close" type="button" data-bs-dismiss="modal"></button></div><div class="modal-body"><div class="row g-3"><div class="col-12"><label class="form-label">Display name</label><input class="form-control" name="name" placeholder="Living room AC" required></div><div class="col-6"><label class="form-label">Brand</label><input class="form-control" name="brand" required></div><div class="col-6"><label class="form-label">Model</label><input class="form-control" name="model"></div><div class="col-6"><label class="form-label">Serial number</label><input class="form-control" name="serial_number"></div><div class="col-6"><label class="form-label">Capacity</label><input class="form-control" name="capacity" placeholder="1.5 ton"></div><div class="col-12"><label class="form-label">Installation date</label><input class="form-control" type="date" name="install_date"></div></div></div><div class="modal-footer"><button class="btn btn-primary">Add asset</button></div></form></div></div></div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const loadRazorpay = () => new Promise((resolve, reject) => {
        if (window.Razorpay) return resolve();
        const script = document.createElement('script');
        script.src = 'https://checkout.razorpay.com/v1/checkout.js';
        script.onload = resolve;
        script.onerror = () => reject(new Error('The payment window could not be loaded.'));
        document.head.appendChild(script);
    });
    document.querySelectorAll('[data-online-payment]').forEach((button) => button.addEventListener('click', async () => {
        button.disabled = true;
        try {
            const orderResponse = await window.$.post(button.dataset.url, { amount: button.dataset.amount });
            await loadRazorpay();
            const checkout = new window.Razorpay({
                key: orderResponse.key_id,
                order_id: orderResponse.order.id,
                amount: orderResponse.order.amount,
                currency: orderResponse.order.currency,
                name: 'ACServ',
                prefill: orderResponse.customer,
                handler: async (payment) => {
                    await window.$.post(orderResponse.confirm_url, payment);
                    await window.Swal.fire({ icon: 'success', title: 'Payment verified' });
                    window.location.reload();
                },
            });
            checkout.open();
        } catch (error) {
            await window.Swal.fire({ icon: 'error', title: 'Payment could not start', text: error.responseJSON?.message ?? error.message });
        } finally {
            button.disabled = false;
        }
    }));
    const refreshTracking = () => document.querySelectorAll('[data-track-url]').forEach(async (card) => {
        try {
            const response = await fetch(card.dataset.trackUrl, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
            if (!response.ok) return;
            const job = await response.json();
            const previousStatus = card.dataset.status;
            card.dataset.status = job.status;
            card.querySelector('.job-status').textContent = job.status.replaceAll('_', ' ').toLowerCase().replace(/\b\w/g, (letter) => letter.toUpperCase());
            const pipeline = ['CREATED', 'ASSIGNED', 'ACCEPTED', 'EN_ROUTE', 'REACHED', 'INSPECTED', 'AUTHORIZED', 'IN_PROGRESS', 'AWAITING_PAYMENT', 'PAYMENT_PENDING', 'COMPLETED'];
            const stageIndex = pipeline.indexOf(job.status);
            const progress = ['VERIFIED', 'CLOSED'].includes(job.status) ? 100 : Math.max(0, Math.round(stageIndex / (pipeline.length - 1) * 100));
            card.querySelector('.job-progress-bar').style.width = `${progress}%`;
            card.querySelector('.job-progress-label').textContent = `${progress}%`;
            card.querySelector('.progress').setAttribute('aria-valuenow', progress);
            if (previousStatus !== job.status && (job.invoice_number || ['AWAITING_PAYMENT', 'PAYMENT_PENDING'].includes(job.status))) window.location.reload();
        } catch {}
    });
    window.setInterval(refreshTracking, 30000);
});
</script>
@endpush
