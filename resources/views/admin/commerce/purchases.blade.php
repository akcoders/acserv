@extends('layouts.admin')

@section('title', 'Purchases — '.__('app.name'))

@section('content')
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
        <div><p class="text-primary fw-semibold mb-1">Commerce / Procurement</p><h1 class="h2 mb-1">Purchases &amp; vendors</h1><p class="text-secondary mb-0">Order parts, receive goods into stock, and track supplier bills.</p></div>
        <div class="d-flex gap-2"><button class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#vendorModal"><i class="bi bi-person-plus me-1"></i> Add vendor</button><button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#purchaseModal"><i class="bi bi-plus-lg me-1"></i> New purchase</button></div>
    </div>

    <div class="row g-4">
        <div class="col-xl-8">
            <div class="card content-card h-100"><div class="card-header bg-transparent border-0 px-4 pt-4"><div class="d-flex align-items-center justify-content-between"><div><span class="text-primary fw-bold small text-uppercase">Purchase register</span><h2 class="h5 mb-0 mt-1">Orders &amp; receipts</h2></div><span class="badge rounded-pill text-bg-light">{{ $orders->total() }} orders</span></div></div>
                <div class="table-responsive"><table class="table table-hover align-middle mb-0" data-rich-table="server"><thead><tr><th>Order</th><th>Vendor</th><th>Received into</th><th>Value</th><th>Payable</th><th>Status</th><th data-unsortable></th></tr></thead><tbody>
                    @forelse ($orders as $order)
                        <tr><td><a class="fw-semibold text-decoration-none" href="{{ route('admin.purchases.show', $order) }}">{{ $order->order_number }}</a><div class="small text-secondary">{{ $order->ordered_on?->format('d M Y') }} · {{ $order->lines_count }} lines</div></td><td>{{ $order->vendor?->name }}<div class="small text-secondary">{{ $order->vendor?->code }}</div></td><td>{{ $order->stockLocation?->name }}</td><td class="fw-semibold">₹{{ number_format((float) $order->grand_total, 2) }}</td><td>@if ($order->status === 'RECEIVED') ₹{{ number_format(max(0, (float) $order->grand_total - (float) ($order->paid_amount ?? 0)), 2) }} @else <span class="text-secondary small">On receipt</span> @endif</td><td><span class="badge rounded-pill text-bg-{{ $order->status === 'RECEIVED' ? 'success' : 'warning' }}">{{ str($order->status)->headline() }}</span></td><td class="text-end"><a class="btn btn-sm btn-outline-primary" href="{{ route('admin.purchases.show', $order) }}">Open</a></td></tr>
                    @empty
                        <tr><td colspan="7" class="text-center text-secondary py-5">No purchase orders yet. Add a vendor and create your first purchase.</td></tr>
                    @endforelse
                </tbody></table></div><div class="card-body border-top">{{ $orders->links() }}</div>
            </div>
        </div>
        <div class="col-xl-4"><div class="card content-card h-100"><div class="card-body p-4"><div class="d-flex align-items-center justify-content-between mb-3"><div><span class="text-primary fw-bold small text-uppercase">Supplier directory</span><h2 class="h5 mb-0 mt-1">Vendors</h2></div><span class="badge rounded-pill text-bg-light">{{ $vendors->count() }}</span></div>
            @forelse ($vendors as $vendor)
                <div class="border rounded-3 p-3 mb-2"><div class="d-flex justify-content-between gap-2"><div><div class="fw-semibold">{{ $vendor->name }}</div><div class="small text-secondary">{{ $vendor->code }}{{ $vendor->phone ? ' · '.$vendor->phone : '' }}</div></div><span class="badge align-self-start text-bg-{{ $vendor->is_active ? 'success' : 'secondary' }}">{{ $vendor->is_active ? 'Active' : 'Inactive' }}</span></div><div class="small text-secondary mt-2">{{ $vendor->email ?: 'No email' }} · {{ $vendor->payment_terms_days }}-day terms</div><button type="button" class="btn btn-link btn-sm px-0 mt-2" data-edit-vendor data-id="{{ $vendor->id }}" data-code="{{ $vendor->code }}" data-name="{{ $vendor->name }}" data-contact="{{ $vendor->contact_name }}" data-email="{{ $vendor->email }}" data-phone="{{ $vendor->phone }}" data-gstin="{{ $vendor->gstin }}" data-address="{{ $vendor->address }}" data-terms="{{ $vendor->payment_terms_days }}" data-active="{{ $vendor->is_active ? 1 : 0 }}">Edit vendor</button></div>
            @empty
                <div class="text-center text-secondary py-5">No vendors added yet.</div>
            @endforelse
        </div></div></div>
    </div>

    <div class="modal fade" id="vendorModal" tabindex="-1"><div class="modal-dialog modal-lg"><div class="modal-content"><form method="POST" action="{{ route('admin.vendors.store') }}" data-ajax id="vendorForm">@csrf<input type="hidden" name="_method" id="vendorMethod" disabled><div class="modal-header"><h2 class="h5 modal-title" id="vendorModalTitle">Add vendor</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div><div class="modal-body"><div class="row g-3"><div class="col-md-4"><label class="form-label">Vendor code</label><input class="form-control" name="code" required maxlength="40"></div><div class="col-md-8"><label class="form-label">Business name</label><input class="form-control" name="name" required maxlength="160"></div><div class="col-md-4"><label class="form-label">Contact person</label><input class="form-control" name="contact_name" maxlength="160"></div><div class="col-md-4"><label class="form-label">Phone</label><input class="form-control" name="phone" maxlength="30"></div><div class="col-md-4"><label class="form-label">Email</label><input class="form-control" type="email" name="email"></div><div class="col-md-4"><label class="form-label">GSTIN</label><input class="form-control" name="gstin" maxlength="20"></div><div class="col-md-4"><label class="form-label">Payment terms, days</label><input class="form-control" type="number" min="0" max="365" name="payment_terms_days" value="0" required></div><div class="col-md-4"><label class="form-label">Status</label><select class="form-select" name="is_active"><option value="1">Active</option><option value="0">Inactive</option></select></div><div class="col-12"><label class="form-label">Address</label><textarea class="form-control" name="address" rows="2"></textarea></div></div></div><div class="modal-footer"><button class="btn btn-outline-secondary" type="button" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary" type="submit">Save vendor</button></div></form></div></div></div>

    <div class="modal fade" id="purchaseModal" tabindex="-1"><div class="modal-dialog modal-xl"><div class="modal-content"><form method="POST" action="{{ route('admin.purchases.store') }}" data-ajax>@csrf<div class="modal-header"><div><h2 class="h5 modal-title">New purchase order</h2><div class="small text-secondary">Stock increases only after you receive these goods.</div></div><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div><div class="modal-body"><div class="row g-3 mb-4"><div class="col-md-4"><label class="form-label">Vendor</label><select class="form-select" name="vendor_id" required><option value="">Select vendor</option>@foreach ($vendors->where('is_active', true) as $vendor)<option value="{{ $vendor->id }}">{{ $vendor->name }}</option>@endforeach</select><button type="button" class="btn btn-link btn-sm px-0" data-bs-toggle="modal" data-bs-target="#vendorModal">+ Add vendor</button></div><div class="col-md-4"><label class="form-label">Receive at location</label><select class="form-select" name="stock_location_id" required><option value="">Select location</option>@foreach ($locations as $location)<option value="{{ $location->id }}">{{ $location->name }}</option>@endforeach</select></div><div class="col-md-4"><label class="form-label">Order date</label><input class="form-control" type="date" name="ordered_on" value="{{ today()->toDateString() }}" required></div><div class="col-md-6"><label class="form-label">Supplier bill number <span class="text-secondary">(optional)</span></label><input class="form-control" name="supplier_invoice_number"></div><div class="col-md-6"><label class="form-label">Notes</label><input class="form-control" name="notes"></div></div>
            <div class="d-flex justify-content-between align-items-center mb-2"><h3 class="h6 mb-0">Items ordered</h3><button type="button" class="btn btn-sm btn-outline-primary" id="addPurchaseLine"><i class="bi bi-plus-lg"></i> Add line</button></div><div id="purchaseLines" class="vstack gap-2"><div class="row g-2 align-items-end purchase-line"><div class="col-md-5"><label class="form-label">Inventory item</label><select class="form-select" name="lines[0][inventory_item_id]" required><option value="">Select item</option>@foreach ($items as $item)<option value="{{ $item->id }}" data-cost="{{ $item->unit_cost }}">{{ $item->sku }} — {{ $item->name }}</option>@endforeach</select></div><div class="col-md-2"><label class="form-label">Quantity</label><input class="form-control" name="lines[0][quantity]" type="number" step="0.001" min="0.001" required></div><div class="col-md-2"><label class="form-label">Unit cost ₹</label><input class="form-control" name="lines[0][unit_cost]" type="number" step="0.01" min="0" required></div><div class="col-md-2"><label class="form-label">Tax %</label><input class="form-control" name="lines[0][tax_rate]" type="number" step="0.01" min="0" max="100" value="0"></div><div class="col-md-1"><button type="button" class="btn btn-outline-danger w-100 remove-purchase-line" aria-label="Remove line"><i class="bi bi-trash"></i></button></div></div></div>
        </div><div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary" type="submit">Create order</button></div></form></div></div></div>
@endsection

@push('scripts')
    <script>
        (() => {
            const vendorForm = document.getElementById('vendorForm');
            document.querySelectorAll('[data-edit-vendor]').forEach(button => button.addEventListener('click', () => {
                vendorForm.action = '{{ route('admin.vendors.store') }}/' + button.dataset.id;
                document.getElementById('vendorMethod').disabled = false;
                document.getElementById('vendorMethod').value = 'PUT';
                document.getElementById('vendorModalTitle').textContent = 'Edit vendor';
                for (const [field, value] of Object.entries({code: button.dataset.code, name: button.dataset.name, contact_name: button.dataset.contact, email: button.dataset.email, phone: button.dataset.phone, gstin: button.dataset.gstin, address: button.dataset.address, payment_terms_days: button.dataset.terms, is_active: button.dataset.active})) vendorForm.elements[field].value = value || '';
                window.bootstrap.Modal.getOrCreateInstance(document.getElementById('vendorModal')).show();
            }));
            document.getElementById('vendorModal').addEventListener('hidden.bs.modal', () => { vendorForm.reset(); vendorForm.action = '{{ route('admin.vendors.store') }}'; document.getElementById('vendorMethod').disabled = true; document.getElementById('vendorModalTitle').textContent = 'Add vendor'; });
            let nextLine = 1;
            document.getElementById('addPurchaseLine').addEventListener('click', () => {
                const first = document.querySelector('.purchase-line');
                if (document.querySelectorAll('.purchase-line').length >= 30) return;
                const row = first.cloneNode(true);
                row.querySelectorAll('input, select').forEach(input => { input.name = input.name.replace(/lines\[\d+\]/, 'lines[' + nextLine + ']'); input.value = input.name.includes('tax_rate') ? '0' : ''; });
                document.getElementById('purchaseLines').append(row);
                nextLine++;
            });
            document.getElementById('purchaseLines').addEventListener('click', event => { if (event.target.closest('.remove-purchase-line') && document.querySelectorAll('.purchase-line').length > 1) event.target.closest('.purchase-line').remove(); });
            document.getElementById('purchaseLines').addEventListener('change', event => { if (event.target.matches('select[name*="inventory_item_id"]')) event.target.closest('.purchase-line').querySelector('input[name*="unit_cost"]').value = event.target.selectedOptions[0]?.dataset.cost || '0'; });
        })();
    </script>
@endpush
