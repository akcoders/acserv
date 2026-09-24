@extends('layouts.mobile')

@section('title', 'Customer — '.__('app.name'))
@section('dashboard-url', route('customer.dashboard'))

@push('head')
<style>
    .customer-home { max-width: 1320px; margin-inline: auto; }
    .customer-home .customer-hero { position: relative; overflow: hidden; background: radial-gradient(circle at 88% 15%, rgba(111, 229, 236, .25), transparent 19rem), linear-gradient(125deg, #09213c 4%, #0b4e79 58%, #137f9e); border: 0; color: #fff; box-shadow: 0 1.25rem 2.75rem rgba(9, 50, 88, .17) !important; }
    .customer-home .customer-hero::before, .customer-home .customer-hero::after { content: ''; position: absolute; border: 1px solid rgba(255,255,255,.13); border-radius: 50%; pointer-events: none; }
    .customer-home .customer-hero::before { width: 22rem; height: 22rem; right: -5rem; top: -12rem; box-shadow: 0 0 0 4rem rgba(255,255,255,.035); }
    .customer-home .customer-hero::after { width: 17rem; height: 17rem; right: 10%; bottom: -13rem; }
    .customer-home .hero-content { position: relative; z-index: 1; }
    .customer-home .hero-kicker { color: #8ce8f5; letter-spacing: .13em; }
    .customer-home .hero-description { max-width: 37rem; color: rgba(255,255,255,.76); }
    .customer-home .customer-hero h1 { letter-spacing: -.045em; line-height: 1.1; }
    .customer-home .hero-visual { position: relative; display: grid; place-items: center; min-height: 10rem; }
    .customer-home .hero-visual i { font-size: 6rem; line-height: 1; color: #b9f7ff; filter: drop-shadow(0 14px 22px rgba(1,19,37,.24)); }
    .customer-home .hero-visual span { position: absolute; bottom: -.2rem; right: 8%; padding: .45rem .85rem; border: 1px solid rgba(255,255,255,.25); border-radius: 999px; background: rgba(255,255,255,.14); font-size: .75rem; font-weight: 700; backdrop-filter: blur(6px); }
    .customer-home .metric-card { border: 1px solid #e0eaf4; box-shadow: 0 10px 28px rgba(17,54,92,.045); }
    .customer-home .metric-card:nth-of-type(1) { background: linear-gradient(125deg, #fff, #f6fbff); }
    .customer-home .metric-icon { display: grid; place-items: center; width: 3.2rem; height: 3.2rem; flex: 0 0 auto; border-radius: 1rem; font-size: 1.4rem; }
    .customer-home .section-kicker { font-size: .7rem; text-transform: uppercase; letter-spacing: .13em; font-weight: 800; color: #087f9b; }
    .customer-home .service-card { border: 1px solid #dce8f4; box-shadow: 0 7px 20px rgba(18,49,85,.035); transition: transform .18s ease, box-shadow .18s ease; }
    .customer-home .service-card:hover { transform: translateY(-2px); box-shadow: 0 12px 26px rgba(18,49,85,.09); }
    .customer-home .service-card .progress { background: #e9eff6; }
    .customer-home .service-card .progress-bar { background: linear-gradient(90deg, #087fc6, #18b9ad); }
    .customer-home .feedback-panel { background: #f3f8fe; border: 1px solid #dceaf8; }
    .customer-home .quick-link { display: flex; align-items: center; gap: .85rem; padding: .85rem 1rem; color: #19334f; text-decoration: none; border: 1px solid #dce8f4; border-radius: 1rem; background: #fff; transition: border-color .18s ease, transform .18s ease; }
    .customer-home .quick-link:hover { color: #075c91; border-color: #71c5ea; transform: translateY(-1px); }
    .customer-home .quick-link .quick-icon { display: grid; place-items: center; width: 2.4rem; height: 2.4rem; flex: 0 0 auto; border-radius: .8rem; background: #eaf6fc; color: #087da4; font-size: 1.1rem; }
    .customer-home .equipment-card { border: 1px solid #e0eaf4; background: linear-gradient(120deg, #fff, #f8fbff); }
    .customer-home .invoice-mobile-card { border: 1px solid #dce9f4; border-radius: 1rem; background: linear-gradient(125deg, #fff, #f8fbff); padding: 1rem; }
    @media (max-width: 575.98px) { .customer-home .customer-hero .card-body { padding: 1.5rem !important; } .customer-home .customer-hero h1 { font-size: 2rem; } .customer-home .metric-card .card-body { padding: .9rem !important; } .customer-home .metric-icon { width: 2.6rem; height: 2.6rem; font-size: 1.1rem; } .customer-home .metric-value { font-size: 1.25rem !important; } .customer-home .service-card { padding: 1rem !important; } }
</style>
@endpush

@section('content')
<div class="customer-home">
    <div class="card customer-hero rounded-4 mb-4 shadow-sm"><div class="card-body p-4 p-lg-5 hero-content">
        <div class="row align-items-center g-4">
            <div class="col-lg-8">
                <div class="hero-kicker small text-uppercase fw-bold mb-2">Your comfort, in control</div>
                <h1 class="display-6 fw-bold mb-2">Hello, {{ $customer->name }}</h1>
                <p class="hero-description mb-4">Everything for your AC care in one place. Book a visit, follow the technician’s progress and keep every bill close at hand.</p>
                <div class="d-flex flex-wrap gap-2">
                    <button class="btn btn-info fw-semibold px-3" data-bs-toggle="modal" data-bs-target="#bookingModal"><i class="bi bi-calendar-plus me-2"></i>Book a service</button>
                    <button class="btn btn-outline-light px-3" data-bs-toggle="modal" data-bs-target="#assetModal"><i class="bi bi-plus-circle me-2"></i>Add an AC unit</button>
                </div>
            </div>
            <div class="col-lg-4 d-none d-lg-block"><div class="hero-visual"><i class="bi bi-snow2" aria-hidden="true"></i><span><i class="bi bi-shield-check me-1" style="font-size: .75rem"></i>Service you can follow</span></div></div>
        </div>
    </div></div>

    <div class="row g-3 mb-4">
        <div class="col-6 col-lg-4"><div class="card content-card metric-card h-100"><div class="card-body d-flex align-items-center gap-3"><span class="metric-icon bg-primary-subtle text-primary"><i class="bi bi-snow"></i></span><div><div class="h3 metric-value fw-bold mb-0">{{ $assets->count() }}</div><div class="small text-secondary">AC units</div></div></div></div></div>
        <div class="col-6 col-lg-4"><div class="card content-card metric-card h-100"><div class="card-body d-flex align-items-center gap-3"><span class="metric-icon bg-warning-subtle text-warning-emphasis"><i class="bi bi-tools"></i></span><div><div class="h3 metric-value fw-bold mb-0">{{ $activeJobCount }}</div><div class="small text-secondary">Active jobs</div></div></div></div></div>
        <div class="col-12 col-lg-4"><div class="card content-card metric-card h-100"><div class="card-body d-flex align-items-center gap-3"><span class="metric-icon bg-success-subtle text-success"><i class="bi bi-receipt"></i></span><div><div class="h3 metric-value fw-bold mb-0">₹{{ number_format($outstandingBalance, 2) }}</div><div class="small text-secondary">Balance due</div></div></div></div></div>
    </div>

    <div class="row g-4 align-items-start">
        <div class="col-xl-7">
            <section class="card content-card mb-4" id="service-pipeline">
                <div class="card-header bg-white border-0 pt-4 px-4 d-flex justify-content-between align-items-center">
                    <div><div class="section-kicker mb-1">Live updates</div><h2 class="h5 fw-bold mb-0">Service pipeline</h2><p class="small text-secondary mb-0 mt-1">Know exactly where every visit stands.</p></div>
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
                        <article class="service-card rounded-4 p-3 p-sm-4" data-track-url="{{ route('customer.jobs.tracking', $job) }}" data-status="{{ $job->status->value }}">
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
                            @if($isFinished && $job->feedback)
                                <div class="feedback-panel rounded-4 p-3 mt-3"><div class="d-flex align-items-center gap-2"><i class="bi bi-check-circle-fill text-success"></i><strong class="small">Thanks for sharing your feedback</strong><span class="ms-auto text-warning" aria-label="{{ $job->feedback->rating }} out of 5 stars">{{ str_repeat('★', (int) $job->feedback->rating) }}</span></div>@if($job->feedback->comment)<p class="small text-secondary mt-2 mb-0">“{{ $job->feedback->comment }}”</p>@endif</div>
                            @elseif($isFinished)
                                <form class="feedback-panel rounded-4 p-3 mt-3" method="POST" action="{{ route('customer.feedback.store') }}" data-ajax>
                                    @csrf
                                    <input type="hidden" name="job_id" value="{{ $job->id }}">
                                    <div class="d-flex align-items-center gap-2 mb-2"><i class="bi bi-chat-heart text-primary"></i><strong class="small">How did we do?</strong></div>
                                    <div class="row g-2">
                                        <div class="col-sm-5"><label class="visually-hidden" for="rating-{{ $job->id }}">Service rating</label><select class="form-select form-select-sm" id="rating-{{ $job->id }}" name="rating" required>@for($rating=5;$rating>=1;$rating--)<option value="{{ $rating }}">{{ str_repeat('★', $rating) }} {{ $rating }} / 5</option>@endfor</select></div>
                                        <div class="col-sm-7"><label class="visually-hidden" for="feedback-{{ $job->id }}">Your feedback</label><input class="form-control form-control-sm" id="feedback-{{ $job->id }}" name="comment" placeholder="Tell us what went well"></div>
                                        <div class="col-12"><button class="btn btn-sm btn-primary w-100" type="submit"><i class="bi bi-send me-1"></i>Send feedback</button></div>
                                    </div>
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
                <div class="card-body p-4">
                    <div class="section-kicker mb-1">Shortcuts</div><h2 class="h5 fw-bold mb-3">What would you like to do?</h2>
                    <div class="vstack gap-2">
                        <button class="quick-link text-start w-100" type="button" data-bs-toggle="modal" data-bs-target="#bookingModal"><span class="quick-icon"><i class="bi bi-calendar-plus"></i></span><span class="flex-grow-1"><strong class="d-block">Schedule a visit</strong><small class="text-secondary">Choose a time that works for you</small></span><i class="bi bi-chevron-right text-secondary"></i></button>
                        <a class="quick-link" href="#invoices"><span class="quick-icon"><i class="bi bi-receipt"></i></span><span class="flex-grow-1"><strong class="d-block">View your bills</strong><small class="text-secondary">Invoices and payment details</small></span><i class="bi bi-chevron-right text-secondary"></i></a>
                        <button class="quick-link text-start w-100" type="button" data-install-app><span class="quick-icon"><i class="bi bi-phone"></i></span><span class="flex-grow-1"><strong class="d-block">Add this app to your phone</strong><small class="text-secondary">Get one-tap access to your services</small></span><i class="bi bi-chevron-right text-secondary"></i></button>
                    </div>
                </div>
            </section>
            @if($bookings->isNotEmpty())
                <section class="card content-card mb-4">
                    <div class="card-body p-4"><div class="section-kicker mb-1">Your requests</div><h2 class="h5 fw-bold mb-3">Recent bookings</h2><div class="vstack gap-2">
                        @foreach($bookings->take(3) as $booking)
                            <div class="d-flex align-items-center justify-content-between gap-3 border-bottom pb-2"><div class="min-w-0"><strong class="d-block text-truncate">{{ $booking->service_type }}</strong><small class="text-secondary">{{ $booking->preferred_start_at?->format('d M Y, g:i A') ?? 'Time to be confirmed' }}</small></div><span class="badge rounded-pill text-bg-light border">{{ str($booking->status->value)->headline() }}</span></div>
                        @endforeach
                    </div></div>
                </section>
            @endif
            <section class="card content-card mb-4">
                <div class="card-header bg-white border-0 pt-4 px-4"><div class="section-kicker">Equipment</div><h2 class="h5 fw-bold mb-0">Your AC units</h2></div>
                <div class="card-body px-4 pb-4 vstack gap-3">
                    @forelse($assets as $asset)
                        <div class="equipment-card rounded-4 p-3"><div class="d-flex justify-content-between align-items-start gap-2"><div><strong>{{ $asset->name }}</strong><div class="small text-secondary mt-1">{{ $asset->brand }} {{ $asset->model }} @if($asset->capacity)· {{ $asset->capacity }}@endif</div></div><span class="rounded-3 bg-primary-subtle text-primary px-2 py-1"><i class="bi bi-snow"></i></span></div>@foreach($asset->warranties as $warranty)<a class="small d-block mt-2" href="{{ route('customer.warranties.certificate', $warranty) }}"><i class="bi bi-patch-check me-1"></i>Warranty until {{ $warranty->ends_on->format('d M Y') }}</a>@endforeach</div>
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
        <div class="card-header bg-white border-0 pt-4 px-4"><div class="section-kicker">Payments</div><h2 class="h5 fw-bold mb-0">Recent invoices</h2></div>
        <div class="card-body px-4 pb-4">
            <div class="vstack gap-3 d-md-none">
                @forelse($invoices as $invoice)
                    <article class="invoice-mobile-card">
                        <div class="d-flex justify-content-between align-items-start gap-2"><div><div class="small text-secondary">{{ $invoice->issued_on?->format('d M Y') ?? 'Invoice' }}</div><strong>{{ $invoice->invoice_number }}</strong></div><span class="badge {{ (float) $invoice->balance_due > 0 ? 'text-bg-warning' : 'text-bg-success' }}">{{ str($invoice->status->value)->headline() }}</span></div>
                        <div class="d-flex justify-content-between align-items-end border-top mt-3 pt-3"><div><div class="small text-secondary">Total</div><strong class="fs-5">₹{{ number_format((float) $invoice->grand_total, 2) }}</strong></div><div class="text-end"><div class="small text-secondary">Balance due</div><strong class="{{ (float) $invoice->balance_due > 0 ? 'text-danger' : 'text-success' }}">₹{{ number_format((float) $invoice->balance_due, 2) }}</strong></div></div>
                        <div class="d-flex gap-2 mt-3"><a class="btn btn-outline-primary flex-grow-1" href="{{ route('customer.invoices.pdf', $invoice) }}"><i class="bi bi-file-earmark-pdf me-1"></i>View PDF</a>@if((float) $invoice->balance_due > 0)<button class="btn btn-primary flex-grow-1" data-online-payment data-url="{{ route('customer.invoices.gateway-order', $invoice) }}" data-amount="{{ $invoice->balance_due }}">Pay now</button>@endif</div>
                    </article>
                @empty
                    <div class="text-center text-secondary py-4">No invoices yet.</div>
                @endforelse
            </div>
            <div class="table-responsive d-none d-md-block"><table class="table table-hover align-middle mb-0" data-rich-table><thead><tr><th>Invoice</th><th>Issued</th><th>Status</th><th>Total</th><th>Due</th><th class="text-end">Actions</th></tr></thead><tbody>
            @forelse($invoices as $invoice)
                <tr><td class="fw-semibold">{{ $invoice->invoice_number }}</td><td>{{ $invoice->issued_on?->format('d M Y') }}</td><td><span class="badge {{ (float) $invoice->balance_due > 0 ? 'text-bg-warning' : 'text-bg-success' }}">{{ str($invoice->status->value)->headline() }}</span></td><td>₹{{ number_format((float) $invoice->grand_total, 2) }}</td><td>₹{{ number_format((float) $invoice->balance_due, 2) }}</td><td class="text-end"><div class="btn-group btn-group-sm"><a class="btn btn-outline-primary" href="{{ route('customer.invoices.pdf', $invoice) }}">PDF</a>@if((float) $invoice->balance_due > 0)<button class="btn btn-primary" data-online-payment data-url="{{ route('customer.invoices.gateway-order', $invoice) }}" data-amount="{{ $invoice->balance_due }}">Pay now</button>@endif</div></td></tr>
            @empty
                <tr><td colspan="6" class="text-center text-secondary py-4">No invoices yet.</td></tr>
            @endforelse
        </tbody></table></div></div>
    </section>
    <section class="card content-card mb-4"><div class="card-body p-4"><div class="d-flex align-items-start gap-3 mb-3"><span class="metric-icon bg-primary-subtle text-primary"><i class="bi bi-bell"></i></span><div><div class="section-kicker mb-1">Stay informed</div><h2 class="h5 fw-bold mb-1">Notification preferences</h2><p class="small text-secondary mb-0">Choose a channel and quiet hours for service updates.</p></div></div><form method="POST" action="{{ route('customer.notification-preferences.store') }}" data-ajax>@csrf<div class="row g-3"><div class="col-md-4"><label class="form-label small fw-semibold" for="notification-channel">Channel</label><select class="form-select" id="notification-channel" name="channel">@foreach(\App\Enums\NotificationChannel::cases() as $channel)<option value="{{ $channel->value }}">{{ str($channel->value)->headline() }}</option>@endforeach</select></div><div class="col-md-4"><label class="form-label small fw-semibold" for="quiet-start">Quiet hours start</label><input class="form-control" id="quiet-start" type="time" name="quiet_starts_at"></div><div class="col-md-4"><label class="form-label small fw-semibold" for="quiet-end">Quiet hours end</label><input class="form-control" id="quiet-end" type="time" name="quiet_ends_at"></div><input type="hidden" name="event" value="*"><input type="hidden" name="is_enabled" value="1"><input type="hidden" name="timezone" value="Asia/Kolkata"><div class="col-12"><button class="btn btn-outline-primary" type="submit">Save preference</button></div></div></form>@if($preferences->isNotEmpty())<div class="border-top mt-3 pt-3 small text-secondary">Saved channels: @foreach($preferences as $preference)<span class="badge text-bg-light border me-1">{{ str($preference->channel->value)->headline() }}</span>@endforeach</div>@endif</div></section>

    <div class="modal fade" id="bookingModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content"><form method="POST" action="{{ route('customer.bookings.store') }}" data-ajax data-offline-queue>@csrf<div class="modal-header"><h2 class="h5 modal-title">Book AC service</h2><button class="btn-close" type="button" data-bs-dismiss="modal"></button></div><div class="modal-body vstack gap-3"><div><label class="form-label">AC unit</label><select class="form-select" name="asset_id"><option value="">General service</option>@foreach($assets as $asset)<option value="{{ $asset->id }}">{{ $asset->name }} — {{ $asset->brand }}</option>@endforeach</select></div><div><label class="form-label">Service type</label><select class="form-select" name="service_type"><option>AC servicing</option><option>Repair</option><option>Installation</option><option>Uninstallation</option><option>Inspection</option></select></div><div><label class="form-label">Preferred time</label><input class="form-control" type="datetime-local" name="preferred_start_at" required></div><div><label class="form-label">Problem</label><textarea class="form-control" name="complaint" rows="3"></textarea></div></div><div class="modal-footer"><button class="btn btn-primary">Submit booking</button></div></form></div></div></div>
    <div class="modal fade" id="assetModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content"><form method="POST" action="{{ route('customer.assets.store') }}" data-ajax>@csrf<div class="modal-header"><h2 class="h5 modal-title">Add AC unit</h2><button class="btn-close" type="button" data-bs-dismiss="modal"></button></div><div class="modal-body"><div class="row g-3"><div class="col-12"><label class="form-label">Display name</label><input class="form-control" name="name" placeholder="Living room AC" required></div><div class="col-6"><label class="form-label">Brand</label><input class="form-control" name="brand" required></div><div class="col-6"><label class="form-label">Model</label><input class="form-control" name="model"></div><div class="col-6"><label class="form-label">Serial number</label><input class="form-control" name="serial_number"></div><div class="col-6"><label class="form-label">Capacity</label><input class="form-control" name="capacity" placeholder="1.5 ton"></div><div class="col-12"><label class="form-label">Installation date</label><input class="form-control" type="date" name="install_date"></div></div></div><div class="modal-footer"><button class="btn btn-primary">Add asset</button></div></form></div></div></div>
</div>
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
            if (previousStatus !== job.status) window.location.reload();
        } catch {}
    });
    window.setInterval(refreshTracking, 30000);
});
</script>
@endpush
