@extends('layouts.admin')

@section('title', 'Bookings — '.__('app.name'))

@section('content')
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
        <div><p class="text-primary fw-semibold mb-1">Operations</p><h1 class="h2 mb-0">Bookings</h1></div>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#bookingModal"><i class="bi bi-plus-lg me-2"></i>New booking</button>
    </div>

    <div class="card content-card mb-4"><div class="card-body">
        <form class="row g-2" method="GET">
            <div class="col-md-6"><input class="form-control" name="search" value="{{ request('search') }}" placeholder="Booking, service, or customer"></div>
            <div class="col-md-4"><select class="form-select" name="status"><option value="">All statuses</option>@foreach($statuses as $status)<option value="{{ $status->value }}" @selected(request('status') === $status->value)>{{ str($status->value)->lower()->headline() }}</option>@endforeach</select></div>
            <div class="col-md-2"><button class="btn btn-outline-primary w-100">Filter</button></div>
        </form>
    </div></div>

    <div class="card content-card"><div class="table-responsive"><table class="table table-hover align-middle mb-0">
        <thead><tr><th>Booking</th><th>Customer</th><th>Service</th><th>Preferred</th><th>Status</th><th class="text-end">Actions</th></tr></thead>
        <tbody>@forelse($bookings as $booking)<tr>
            <td class="fw-semibold">{{ $booking->booking_number }}<div class="small text-secondary">{{ str($booking->channel->value)->lower()->headline() }}</div></td>
            <td>{{ $booking->customer->name }}<div class="small text-secondary">{{ $booking->customer->phone }}</div></td>
            <td>{{ $booking->service_type }}</td>
            <td>{{ $booking->preferred_start_at?->format('d M Y, h:i A') ?? 'Flexible' }}</td>
            <td><span class="badge text-bg-{{ $booking->status === \App\Enums\BookingStatus::Cancelled ? 'secondary' : 'primary' }}">{{ str($booking->status->value)->lower()->headline() }}</span></td>
            <td class="text-end"><button class="btn btn-sm btn-outline-danger" data-confirm-ajax data-url="{{ route('admin.bookings.destroy', $booking) }}" data-title="Delete booking?">Delete</button></td>
        </tr>@empty<tr><td colspan="6" class="text-center text-secondary py-5">No bookings found.</td></tr>@endforelse</tbody>
    </table></div><div class="card-body border-top">{{ $bookings->links() }}</div></div>

    <div class="modal fade" id="bookingModal" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-lg"><div class="modal-content">
        <form method="POST" action="{{ route('admin.bookings.store') }}" data-ajax>
            @csrf
            <div class="modal-header"><h2 class="modal-title h5">New booking</h2><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body"><div class="row g-3">
                <div class="col-md-6"><label class="form-label">Customer</label><select class="form-select" name="customer_id" required><option value="">Select</option>@foreach($customers as $customer)<option value="{{ $customer->id }}">{{ $customer->name }} — {{ $customer->phone }}</option>@endforeach</select></div>
                <div class="col-md-6"><label class="form-label">Branch</label><select class="form-select" name="branch_id"><option value="">Auto assign</option>@foreach($branches as $branch)<option value="{{ $branch->id }}">{{ $branch->name }}</option>@endforeach</select></div>
                <div class="col-md-6"><label class="form-label">Channel</label><select class="form-select" name="channel" required>@foreach(\App\Enums\BookingChannel::cases() as $channel)<option value="{{ $channel->value }}">{{ str($channel->value)->lower()->headline() }}</option>@endforeach</select></div>
                <div class="col-md-6"><label class="form-label">Service type</label><input class="form-control" name="service_type" required maxlength="100"></div>
                <div class="col-md-6"><label class="form-label">Preferred start</label><input class="form-control" type="datetime-local" name="preferred_start_at"></div>
                <div class="col-md-6"><label class="form-label">Preferred end</label><input class="form-control" type="datetime-local" name="preferred_end_at"></div>
                <div class="col-12"><label class="form-label">Complaint</label><textarea class="form-control" name="complaint" rows="3"></textarea></div>
                <div class="col-12"><label class="form-label">Notes</label><textarea class="form-control" name="notes" rows="2"></textarea></div>
            </div></div>
            <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary" type="submit">Save booking</button></div>
        </form>
    </div></div></div>
@endsection
