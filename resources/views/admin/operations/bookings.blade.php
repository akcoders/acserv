@extends('layouts.admin')

@section('title', 'Bookings — '.__('app.name'))

@push('head')
    <style>
        .booking-calendar-card .card-body { padding: 1rem; }
        .booking-calendar-grid { display: grid; grid-template-columns: repeat(7, minmax(0, 1fr)); gap: .2rem; }
        .booking-calendar-grid .weekday { color: #8391a5; font-size: .62rem; font-weight: 800; padding: .35rem 0; text-align: center; text-transform: uppercase; }
        .booking-calendar-day { align-items: center; aspect-ratio: 1; background: #f5f8fc; border: 1px solid transparent; border-radius: .6rem; color: #34435a; display: flex; flex-direction: column; font-size: .78rem; font-weight: 700; justify-content: center; line-height: 1; min-width: 0; padding: .15rem; }
        .booking-calendar-day:hover, .booking-calendar-day:focus-visible { background: #eaf2ff; border-color: #88b9ff; color: #0b5ed7; outline: none; }
        .booking-calendar-day.is-today { border-color: #93baff; }
        .booking-calendar-day.has-bookings { background: #fff0f0; color: #b42335; }
        .booking-calendar-day.has-bookings:hover, .booking-calendar-day.has-bookings:focus-visible { background: #ffe2e2; border-color: #f0a3a3; }
        .booking-calendar-count { background: #dc3545; border-radius: 999px; color: #fff; font-size: .56rem; line-height: 1; margin-top: .15rem; min-width: .85rem; padding: .1rem .16rem; }
    </style>
@endpush

@section('content')
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
        <div><p class="text-primary fw-semibold mb-1">Operations</p><h1 class="h2 mb-0">Bookings</h1></div>
        <button class="btn btn-primary" type="button" data-bs-toggle="modal" data-bs-target="#bookingModal" id="newBookingButton"><i class="bi bi-plus-lg me-2"></i>New booking</button>
    </div>

    <div class="row g-4 align-items-start">
        <aside class="col-12 col-xl-3 col-xxl-2" aria-label="Booking calendar">
            <div class="card content-card booking-calendar-card"><div class="card-body">
                <div class="d-flex align-items-center justify-content-between gap-1 mb-3">
                    <button class="btn btn-sm btn-light" type="button" id="bookingCalendarPrevious" aria-label="Previous month"><i class="bi bi-chevron-left" aria-hidden="true"></i></button>
                    <h2 class="h6 fw-bold text-center mb-0" id="bookingCalendarMonth" aria-live="polite"></h2>
                    <button class="btn btn-sm btn-light" type="button" id="bookingCalendarNext" aria-label="Next month"><i class="bi bi-chevron-right" aria-hidden="true"></i></button>
                </div>
                <div class="booking-calendar-grid" aria-hidden="true">@foreach(['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'] as $weekday)<span class="weekday">{{ $weekday }}</span>@endforeach</div>
                <div class="booking-calendar-grid" id="bookingCalendarDays" data-calendar-url="{{ route('admin.bookings.index') }}" data-month="{{ $calendarMonth }}"></div>
                <p class="small text-secondary mb-0 mt-3"><span class="text-danger fw-bold">●</span> Red dates have bookings. Select a day to add one.</p>
            </div></div>
        </aside>

        <section class="col-12 col-xl-9 col-xxl-10" aria-label="Booking register">
            <div class="card content-card mb-4"><div class="card-body">
                <form class="row g-2" method="GET" action="{{ route('admin.bookings.index') }}">
                    <div class="col-md-6"><label class="visually-hidden" for="bookingRegisterSearch">Search bookings</label><input class="form-control" id="bookingRegisterSearch" name="search" value="{{ request('search') }}" placeholder="Booking, service, or customer"></div>
                    <div class="col-md-4"><label class="visually-hidden" for="bookingRegisterStatus">Booking status</label><select class="form-select" id="bookingRegisterStatus" name="status"><option value="">All statuses</option>@foreach($statuses as $status)<option value="{{ $status->value }}" @selected(request('status') === $status->value)>{{ str($status->value)->lower()->headline() }}</option>@endforeach</select></div>
                    <div class="col-md-2"><button class="btn btn-outline-primary w-100">Filter</button></div>
                </form>
            </div></div>

            <div class="card content-card"><div class="table-responsive"><table class="table table-hover align-middle mb-0" data-rich-table="server">
                <thead><tr><th>Booking</th><th>Customer</th><th>Service</th><th>Preferred</th><th>Status</th><th class="text-end" data-unsortable>Actions</th></tr></thead>
                <tbody>@forelse($bookings as $booking)<tr>
                    <td class="fw-semibold">{{ $booking->booking_number }}<div class="small text-secondary">{{ str($booking->channel->value)->lower()->headline() }}</div></td>
                    <td>{{ $booking->customer->name }}<div class="small text-secondary">{{ $booking->customer->phone }}</div></td>
                    <td>{{ $booking->service_type }}</td>
                    <td>{{ $booking->preferred_start_at?->format('d M Y, h:i A') ?? 'Flexible' }}</td>
                    <td><span class="badge text-bg-{{ $booking->status === \App\Enums\BookingStatus::Cancelled ? 'secondary' : 'primary' }}">{{ str($booking->status->value)->lower()->headline() }}</span></td>
                    <td class="text-end"><button class="btn btn-sm btn-outline-danger" type="button" data-confirm-ajax data-url="{{ route('admin.bookings.destroy', $booking) }}" data-title="Delete booking?">Delete</button></td>
                </tr>@empty<tr><td colspan="6" class="text-center text-secondary py-5">No bookings found.</td></tr>@endforelse</tbody>
            </table></div><div class="card-body border-top">{{ $bookings->links() }}</div></div>
        </section>
    </div>

    <div class="modal fade" id="bookingModal" tabindex="-1" aria-labelledby="bookingModalTitle" aria-hidden="true"><div class="modal-dialog modal-lg"><div class="modal-content">
        <form method="POST" action="{{ route('admin.bookings.store') }}" data-ajax>
            @csrf
            <div class="modal-header"><div><h2 class="modal-title h5 mb-0" id="bookingModalTitle">New booking</h2><small class="text-secondary" id="selectedBookingDate">Choose the preferred time below</small></div><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
            <div class="modal-body"><div class="row g-3">
                <div class="col-md-6">
                    <div class="d-flex align-items-center justify-content-between gap-2"><label class="form-label" for="bookingCustomer">Customer</label><button class="btn btn-link btn-sm text-decoration-none p-0 mb-2" type="button" id="toggleQuickCustomer" aria-controls="quickCustomerPanel" aria-expanded="false"><i class="bi bi-person-plus me-1" aria-hidden="true"></i>Add customer</button></div>
                    <input class="form-control form-control-sm mb-2" type="search" id="bookingCustomerSearch" placeholder="Find by name or phone" aria-label="Find customer">
                    <select class="form-select" id="bookingCustomer" name="customer_id" required><option value="">Select customer</option>@foreach($customers as $customer)<option value="{{ $customer->id }}">{{ $customer->name }} — {{ $customer->phone }}</option>@endforeach</select>
                    <div class="small text-secondary mt-1" id="bookingCustomerCount"></div>
                </div>
                <div class="col-md-6"><label class="form-label" for="bookingBranch">Branch</label><select class="form-select" id="bookingBranch" name="branch_id"><option value="">Auto assign</option>@foreach($branches as $branch)<option value="{{ $branch->id }}">{{ $branch->name }}</option>@endforeach</select></div>
                <div class="col-12" id="quickCustomerPanel" hidden><div class="border rounded-3 bg-light p-3">
                    <div class="d-flex justify-content-between align-items-center mb-3"><strong>Quick add customer</strong><button class="btn-close" type="button" id="closeQuickCustomer" aria-label="Close quick add"></button></div>
                    <div class="row g-2">
                        <div class="col-md-5"><label class="form-label small" for="quickCustomerName">Name</label><input class="form-control" id="quickCustomerName" data-quick-field="name" maxlength="160" autocomplete="name"><div class="invalid-feedback" data-quick-error="name"></div></div>
                        <div class="col-md-4"><label class="form-label small" for="quickCustomerPhone">Phone</label><input class="form-control" id="quickCustomerPhone" data-quick-field="phone" maxlength="20" type="tel" autocomplete="tel"><div class="invalid-feedback" data-quick-error="phone"></div></div>
                        <div class="col-md-3"><label class="form-label small" for="quickCustomerEmail">Email (optional)</label><input class="form-control" id="quickCustomerEmail" data-quick-field="email" type="email" autocomplete="email"><div class="invalid-feedback" data-quick-error="email"></div></div>
                    </div>
                    <div class="text-end mt-3"><button class="btn btn-sm btn-primary" type="button" id="saveQuickCustomer" data-store-url="{{ route('admin.customers.quick.store') }}">Save &amp; select</button></div>
                </div></div>
                <div class="col-md-6"><label class="form-label" for="bookingChannel">Channel</label><select class="form-select" id="bookingChannel" name="channel" required>@foreach(\App\Enums\BookingChannel::cases() as $channel)<option value="{{ $channel->value }}">{{ str($channel->value)->lower()->headline() }}</option>@endforeach</select></div>
                <div class="col-md-6"><label class="form-label" for="bookingServiceType">Service type</label><input class="form-control" id="bookingServiceType" name="service_type" required maxlength="100"></div>
                <div class="col-md-6"><label class="form-label" for="bookingPreferredStart">Preferred start</label><input class="form-control" id="bookingPreferredStart" type="datetime-local" name="preferred_start_at"></div>
                <div class="col-md-6"><label class="form-label" for="bookingPreferredEnd">Preferred end</label><input class="form-control" id="bookingPreferredEnd" type="datetime-local" name="preferred_end_at"></div>
                <div class="col-12"><label class="form-label" for="bookingComplaint">Complaint</label><textarea class="form-control" id="bookingComplaint" name="complaint" rows="3"></textarea></div>
                <div class="col-12"><label class="form-label" for="bookingNotes">Notes</label><textarea class="form-control" id="bookingNotes" name="notes" rows="2"></textarea></div>
            </div></div>
            <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary" type="submit">Save booking</button></div>
        </form>
    </div></div></div>
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const $ = window.jQuery;
            const calendar = $('#bookingCalendarDays');
            let month = calendar.data('month');
            let counts = {{ Js::from($calendarCounts) }};
            const monthDate = (value) => { const [year, number] = value.split('-').map(Number); return new Date(year, number - 1, 1); };

            const renderCalendar = () => {
                const first = monthDate(month);
                const year = first.getFullYear();
                const monthNumber = first.getMonth();
                const dayCount = new Date(year, monthNumber + 1, 0).getDate();
                const offset = (first.getDay() + 6) % 7;
                const today = new Date();
                const days = calendar.empty();
                $('#bookingCalendarMonth').text(new Intl.DateTimeFormat('en', { month: 'long', year: 'numeric' }).format(first));
                for (let index = 0; index < offset; index += 1) {
                    days.append($('<span>', { 'aria-hidden': 'true' }));
                }
                for (let day = 1; day <= dayCount; day += 1) {
                    const date = `${year}-${String(monthNumber + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
                    const count = Number(counts[date] ?? 0);
                    const label = new Intl.DateTimeFormat('en', { day: 'numeric', month: 'long', year: 'numeric' }).format(new Date(year, monthNumber, day));
                    const button = $('<button>', {
                        type: 'button', class: 'booking-calendar-day', 'data-booking-date': date,
                        'aria-label': `Create booking for ${label}${count ? `; ${count} existing booking${count === 1 ? '' : 's'}` : ''}`,
                        title: count ? `${count} booking${count === 1 ? '' : 's'} — click to add` : 'Click to add a booking',
                    }).append($('<span>').text(day));
                    if (count) button.addClass('has-bookings').append($('<span>', { class: 'booking-calendar-count', text: count }));
                    if (today.getFullYear() === year && today.getMonth() === monthNumber && today.getDate() === day) button.addClass('is-today').attr('aria-current', 'date');
                    days.append(button);
                }
            };

            const changeMonth = (direction) => {
                const target = monthDate(month);
                target.setMonth(target.getMonth() + direction);
                const requested = `${target.getFullYear()}-${String(target.getMonth() + 1).padStart(2, '0')}`;
                const buttons = $('#bookingCalendarPrevious, #bookingCalendarNext').prop('disabled', true);
                $.getJSON(calendar.data('calendar-url'), { calendar_month: requested })
                    .done((response) => { month = response.month; counts = response.counts; renderCalendar(); })
                    .fail(() => window.Swal.fire({ icon: 'error', title: 'Could not load calendar', text: 'Please try again.' }))
                    .always(() => buttons.prop('disabled', false));
            };

            $('#bookingCalendarPrevious').on('click', () => changeMonth(-1));
            $('#bookingCalendarNext').on('click', () => changeMonth(1));
            calendar.on('click', '[data-booking-date]', function () {
                const date = this.dataset.bookingDate;
                $('#bookingPreferredStart').val(`${date}T10:00`);
                $('#bookingPreferredEnd').val('');
                $('#selectedBookingDate').text(`Booking for ${new Intl.DateTimeFormat('en', { dateStyle: 'full' }).format(new Date(`${date}T12:00:00`))}`);
                window.bootstrap.Modal.getOrCreateInstance(document.getElementById('bookingModal')).show();
            });
            $('#newBookingButton').on('click', () => {
                $('#bookingPreferredStart, #bookingPreferredEnd').val('');
                $('#selectedBookingDate').text('Choose the preferred time below');
            });

            const customerSelect = $('#bookingCustomer');
            const customerOptions = customerSelect.find('option[value!=""]').map(function () {
                return { value: this.value, text: this.textContent };
            }).get();
            const renderCustomers = () => {
                const term = $('#bookingCustomerSearch').val().trim().toLocaleLowerCase();
                const selected = customerSelect.val();
                const matching = customerOptions.filter((option) => option.text.toLocaleLowerCase().includes(term));
                customerSelect.empty().append(new Option('Select customer', ''));
                matching.slice(0, 60).forEach((option) => customerSelect.append(new Option(option.text, option.value)));
                const selectedOption = customerOptions.find((option) => option.value === selected);
                if (selectedOption && !customerSelect.find('option').toArray().some((option) => option.value === selected)) customerSelect.append(new Option(selectedOption.text, selectedOption.value));
                customerSelect.val(selected || '');
                $('#bookingCustomerCount').text(matching.length > 60 ? `Showing first 60 of ${matching.length} matches. Type to narrow the list.` : `${matching.length} customer${matching.length === 1 ? '' : 's'} available`);
            };
            $('#bookingCustomerSearch').on('input', renderCustomers);

            const toggleQuickCustomer = (open) => {
                $('#quickCustomerPanel').prop('hidden', !open);
                $('#toggleQuickCustomer').attr('aria-expanded', String(open));
                if (open) $('#quickCustomerName').trigger('focus');
            };
            $('#toggleQuickCustomer').on('click', () => toggleQuickCustomer($('#quickCustomerPanel').prop('hidden')));
            $('#closeQuickCustomer').on('click', () => toggleQuickCustomer(false));
            $('#saveQuickCustomer').on('click', function () {
                const button = $(this);
                const payload = { name: $('#quickCustomerName').val().trim(), phone: $('#quickCustomerPhone').val().trim(), email: $('#quickCustomerEmail').val().trim() };
                $('#quickCustomerPanel [data-quick-field]').removeClass('is-invalid');
                $('#quickCustomerPanel [data-quick-error]').text('');
                button.prop('disabled', true);
                $.ajax({ url: button.data('store-url'), method: 'POST', data: payload })
                    .done((response) => {
                        const customer = response.customer;
                        customerOptions.push({ value: customer.id, text: `${customer.name} — ${customer.phone}` });
                        $('#bookingCustomerSearch').val('');
                        renderCustomers();
                        customerSelect.val(customer.id);
                        $('#quickCustomerName, #quickCustomerPhone, #quickCustomerEmail').val('');
                        toggleQuickCustomer(false);
                        void window.Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: response.message, showConfirmButton: false, timer: 1800 });
                    })
                    .fail((response) => {
                        if (response.status === 422 && response.responseJSON?.errors) {
                            Object.entries(response.responseJSON.errors).forEach(([field, messages]) => {
                                $(`#quickCustomerPanel [data-quick-field="${field}"]`).addClass('is-invalid');
                                $(`#quickCustomerPanel [data-quick-error="${field}"]`).text(messages[0]);
                            });
                            return;
                        }
                        void window.Swal.fire({ icon: 'error', title: 'Could not add customer', text: response.responseJSON?.message ?? 'Please try again.' });
                    })
                    .always(() => button.prop('disabled', false));
            });

            renderCustomers();
            renderCalendar();
        });
    </script>
@endpush
