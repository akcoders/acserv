@extends('layouts.admin')

@section('title', 'Notifications — '.__('app.name'))

@section('content')
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
        <div><p class="text-primary fw-semibold mb-1">Engagement</p><h1 class="h2 mb-0">Notification centre</h1></div>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#templateModal">New template</button>
    </div>

    <div class="alert {{ filled(config('services.onesignal.app_id')) && filled(config('services.onesignal.api_key')) ? 'alert-success' : 'alert-warning' }} d-flex align-items-start gap-3 mb-4" role="status">
        <i class="bi bi-broadcast fs-4" aria-hidden="true"></i>
        <div><strong>OneSignal web push {{ filled(config('services.onesignal.app_id')) && filled(config('services.onesignal.api_key')) ? 'configured' : 'needs setup' }}</strong><div class="small mt-1">{{ filled(config('services.onesignal.app_id')) && filled(config('services.onesignal.api_key')) ? 'Technician and customer browsers can subscribe through the bell in their portals. Delivery appears in the log below.' : 'Add ONESIGNAL_APP_ID and ONESIGNAL_REST_API_KEY to the server .env, then rebuild the Laravel config cache. HTTPS is required.' }}</div></div>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-xl-5">
            <div class="card content-card h-100">
                <div class="card-header bg-white fw-semibold">Templates</div>
                <div class="list-group list-group-flush">
                    @forelse($templates as $template)
                        <div class="list-group-item py-3">
                            <div class="d-flex justify-content-between"><strong>{{ $template->event }}</strong><span class="badge text-bg-primary">{{ $template->channel->value }}</span></div>
                            <div class="small text-secondary">{{ $template->locale }} · {{ $template->is_active ? 'Active' : 'Inactive' }}</div>
                        </div>
                    @empty
                        <div class="p-4 text-secondary">No templates yet.</div>
                    @endforelse
                </div>
            </div>
        </div>
        <div class="col-xl-7">
            <div class="card content-card h-100">
                <div class="card-header bg-white fw-semibold">Delivery log</div>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead><tr><th>Event</th><th>Channel</th><th>Recipient</th><th>Status</th><th>Time</th></tr></thead>
                        <tbody>
                            @forelse($logs as $log)
                                <tr><td>{{ $log->event }}</td><td>{{ $log->channel->value }}</td><td>{{ $log->recipient_masked }}</td><td><span class="badge text-bg-{{ $log->status === \App\Enums\NotificationStatus::Failed ? 'danger' : 'light' }}">{{ $log->status->value }}</span></td><td>{{ $log->created_at->diffForHumans() }}</td></tr>
                            @empty
                                <tr><td colspan="5" class="text-center text-secondary py-4">No notifications queued.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="card-body border-top">{{ $logs->links() }}</div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="templateModal" tabindex="-1">
        <div class="modal-dialog modal-lg"><div class="modal-content">
            <form method="POST" action="{{ route('admin.notification-templates.store') }}" data-ajax>
                @csrf
                <div class="modal-header"><h2 class="h5 modal-title">Notification template</h2><button class="btn-close" type="button" data-bs-dismiss="modal"></button></div>
                <div class="modal-body"><div class="row g-3">
                    <div class="col-md-5"><label class="form-label">Event</label><input class="form-control" name="event" placeholder="job.assigned" required></div>
                    <div class="col-md-4"><label class="form-label">Channel</label><select class="form-select" name="channel">@foreach($channels as $channel)<option value="{{ $channel->value }}">{{ $channel->value }}</option>@endforeach</select></div>
                    <div class="col-md-3"><label class="form-label">Locale</label><input class="form-control" name="locale" value="en_IN" required></div>
                    <div class="col-12"><label class="form-label">Subject</label><input class="form-control" name="subject"></div>
                    <div class="col-12"><label class="form-label">Body</label><textarea class="form-control" name="body" rows="6" required placeholder="Job number and status variables are supported"></textarea><div class="form-text">Variables use double braces, for example job_number and status.</div></div>
                    <input type="hidden" name="is_active" value="1">
                </div></div>
                <div class="modal-footer"><button class="btn btn-primary">Save template</button></div>
            </form>
        </div></div>
    </div>
@endsection
