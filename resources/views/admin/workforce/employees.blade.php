<div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-3"><div class="card content-card h-100"><div class="card-body p-4"><div class="text-secondary small fw-semibold text-uppercase">Team members</div><div class="display-6 fw-bold text-primary">{{ $employees->count() }}</div><div class="small text-secondary">Across office and field roles</div></div></div></div>
    <div class="col-sm-6 col-xl-3"><div class="card content-card h-100"><div class="card-body p-4"><div class="text-secondary small fw-semibold text-uppercase">Technicians</div><div class="display-6 fw-bold text-info">{{ $employees->where('role', \App\Enums\Role::Technician)->count() }}</div><div class="small text-secondary">Field-service workforce</div></div></div></div>
    <div class="col-sm-6 col-xl-3"><div class="card content-card h-100"><div class="card-body p-4"><div class="text-secondary small fw-semibold text-uppercase">Active access</div><div class="display-6 fw-bold text-success">{{ $employees->where('status', \App\Enums\UserStatus::Active)->count() }}</div><div class="small text-secondary">Can sign in with email OTP</div></div></div></div>
    <div class="col-sm-6 col-xl-3"><div class="card content-card h-100"><div class="card-body p-4"><div class="text-secondary small fw-semibold text-uppercase">Monthly payroll</div><div class="display-6 fw-bold">₹{{ number_format($employees->sum(fn ($employee) => (float) ($employee->employmentProfile?->monthly_salary ?? 0)), 0) }}</div><div class="small text-secondary">Configured base salaries</div></div></div></div>
</div>

<div class="card content-card">
    <div class="card-header border-0 bg-white px-4 py-4 d-flex flex-wrap justify-content-between align-items-center gap-3">
        <div><p class="admin-eyebrow text-primary mb-1">People directory</p><h2 class="h5 mb-1">Employees & technician plans</h2><div class="small text-secondary">Pay grade, reporting, role, salary and per-job incentives in one place.</div></div>
        @if(auth()->user()->role->canManageUsers())<button class="btn btn-primary" type="button" data-bs-toggle="modal" data-bs-target="#employeeModal" data-employee-add><i class="bi bi-person-plus me-1"></i>Add employee</button>@endif
    </div>
    <div class="table-responsive"><table class="table table-hover align-middle mb-0" data-rich-table><thead><tr><th>Employee</th><th>Role / designation</th><th>Reports to</th><th>Pay grade</th><th>Base salary</th><th>Incentive</th><th>Field performance</th><th>Access</th><th data-unsortable>Action</th></tr></thead><tbody>
        @forelse($employees as $employee)
            @php($profile = $employee->employmentProfile)
            @php($scorecard = $scorecards->firstWhere('user_id', $employee->getKey()))
            <tr>
                <td><strong>{{ $employee->name }}</strong><div class="small text-secondary">{{ $profile?->employee_code ?? 'No code' }} · {{ $employee->email }}</div></td>
                <td><span class="badge rounded-pill text-bg-primary-subtle text-primary-emphasis">{{ str($employee->role->value)->headline() }}</span><div class="small text-secondary mt-1">{{ $profile?->designation ?? 'Not configured' }}@if($employee->technicianProfile?->branch) · {{ $employee->technicianProfile->branch->name }}@endif</div></td>
                <td>{{ $profile?->manager?->name ?? '—' }}</td>
                <td>{{ $profile?->pay_grade ?? '—' }}</td>
                <td class="fw-semibold">₹{{ number_format((float) ($profile?->monthly_salary ?? 0), 2) }}</td>
                <td>{{ $employee->role === \App\Enums\Role::Technician ? '₹'.number_format((float) ($profile?->incentive_per_job ?? 0), 2).' / job' : '—' }}</td>
                <td>@if($scorecard)<strong class="text-primary">{{ number_format((float) $scorecard->score, 1) }}</strong><div class="small text-secondary">{{ $scorecard->completed_jobs }} jobs · {{ $scorecard->average_rating }} ★</div>@else<span class="text-secondary small">No scorecard yet</span>@endif</td>
                <td><span class="badge rounded-pill {{ $employee->status === \App\Enums\UserStatus::Active ? 'text-bg-success' : 'text-bg-secondary' }}">{{ str($employee->status->value)->headline() }}</span></td>
                <td>@if(auth()->user()->role->canManageUsers())<button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#employeeModal" data-employee-edit data-update-url="{{ route('admin.employees.update', $employee) }}" data-first-name="{{ $employee->first_name }}" data-last-name="{{ $employee->last_name }}" data-email="{{ $employee->email }}" data-phone="{{ $employee->phone }}" data-role="{{ $employee->role->value }}" data-status="{{ $employee->status->value }}" data-code="{{ $profile?->employee_code }}" data-designation="{{ $profile?->designation }}" data-grade="{{ $profile?->pay_grade }}" data-employment-type="{{ $profile?->employment_type ?? 'FULL_TIME' }}" data-salary="{{ $profile?->monthly_salary ?? 0 }}" data-incentive="{{ $profile?->incentive_per_job ?? 0 }}" data-manager="{{ $profile?->manager_user_id }}" data-branch="{{ $employee->technicianProfile?->branch_id }}" data-joined="{{ $profile?->joined_on?->format('Y-m-d') }}" data-notes="{{ $profile?->notes }}"><i class="bi bi-pencil-square me-1"></i>Edit</button>@endif</td>
            </tr>
        @empty<tr><td colspan="9" class="text-center text-secondary py-5">No employees yet. Add your first team member.</td></tr>@endforelse
    </tbody></table></div>
</div>

@if(auth()->user()->role->canManageUsers())
<div class="modal fade" id="employeeModal" tabindex="-1" aria-labelledby="employeeModalTitle" aria-hidden="true"><div class="modal-dialog modal-lg modal-dialog-scrollable"><div class="modal-content"><form method="POST" action="{{ route('admin.employees.store') }}" data-ajax data-employee-form>@csrf<input type="hidden" name="_method" value="POST"><div class="modal-header"><div><div class="small text-primary fw-semibold text-uppercase">Team management</div><h2 class="h5 modal-title" id="employeeModalTitle">Add employee</h2></div><button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Close"></button></div><div class="modal-body"><div class="row g-3">
    <div class="col-sm-6"><label class="form-label" for="employee-first-name">First name</label><input class="form-control" id="employee-first-name" name="first_name" required maxlength="100"></div>
    <div class="col-sm-6"><label class="form-label" for="employee-last-name">Last name</label><input class="form-control" id="employee-last-name" name="last_name" maxlength="100"></div>
    <div class="col-sm-6"><label class="form-label" for="employee-email">Email for OTP login</label><input class="form-control" id="employee-email" type="email" name="email" required></div>
    <div class="col-sm-6"><label class="form-label" for="employee-phone">Phone</label><input class="form-control" id="employee-phone" name="phone" maxlength="20"></div>
    <div class="col-sm-4"><label class="form-label" for="employee-role">Login role</label><select class="form-select" id="employee-role" name="role" required>@foreach([\App\Enums\Role::Admin, \App\Enums\Role::Manager, \App\Enums\Role::Dispatcher, \App\Enums\Role::Technician, \App\Enums\Role::Accountant] as $role)<option value="{{ $role->value }}">{{ str($role->value)->headline() }}</option>@endforeach</select></div>
    <div class="col-sm-4"><label class="form-label" for="employee-status">Access</label><select class="form-select" id="employee-status" name="status"><option value="ACTIVE">Active</option><option value="SUSPENDED">Suspended</option></select></div>
    <div class="col-sm-4"><label class="form-label" for="employee-code">Employee code</label><input class="form-control" id="employee-code" name="employee_code" placeholder="Auto-generated if blank" maxlength="40"></div>
    <div class="col-sm-6"><label class="form-label" for="employee-designation">Designation</label><input class="form-control" id="employee-designation" name="designation" placeholder="Senior HVAC technician" required></div>
    <div class="col-sm-3"><label class="form-label" for="employee-grade">Pay grade</label><input class="form-control" id="employee-grade" name="pay_grade" placeholder="G2"></div>
    <div class="col-sm-3"><label class="form-label" for="employee-type">Employment</label><select class="form-select" id="employee-type" name="employment_type"><option value="FULL_TIME">Full-time</option><option value="PART_TIME">Part-time</option><option value="CONTRACT">Contract</option></select></div>
    <div class="col-sm-6"><label class="form-label" for="employee-manager">Reporting manager</label><select class="form-select" id="employee-manager" name="manager_user_id"><option value="">No manager</option>@foreach($managers as $manager)<option value="{{ $manager->id }}">{{ $manager->name }}</option>@endforeach</select></div>
    <div class="col-sm-6"><label class="form-label" for="employee-branch">Technician branch</label><select class="form-select" id="employee-branch" name="branch_id"><option value="">No branch</option>@foreach($branches as $branch)<option value="{{ $branch->id }}">{{ $branch->name }}</option>@endforeach</select></div>
    <div class="col-sm-4"><label class="form-label" for="employee-salary">Monthly base salary (₹)</label><input class="form-control" id="employee-salary" name="monthly_salary" type="number" step="0.01" min="0" value="0" required></div>
    <div class="col-sm-4"><label class="form-label" for="employee-incentive">Incentive per job (₹)</label><input class="form-control" id="employee-incentive" name="incentive_per_job" type="number" step="0.01" min="0" value="0"></div>
    <div class="col-sm-4"><label class="form-label" for="employee-joined">Joined on</label><input class="form-control" id="employee-joined" name="joined_on" type="date"></div>
    <div class="col-12"><label class="form-label" for="employee-notes">Notes</label><textarea class="form-control" id="employee-notes" name="notes" rows="2"></textarea></div>
    <div class="col-12"><div class="alert alert-info small mb-0"><i class="bi bi-shield-check me-1"></i>Active employees sign in with workspace name and their email OTP. No password is required.</div></div>
</div></div><div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary" type="submit">Save employee</button></div></form></div></div></div>
@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const form = document.querySelector('[data-employee-form]');
    const title = document.getElementById('employeeModalTitle');
    if (!form) return;
    document.querySelector('[data-employee-add]')?.addEventListener('click', () => {
        form.reset();
        form.action = @json(route('admin.employees.store'));
        form.elements._method.value = 'POST';
        title.textContent = 'Add employee';
    });
    document.querySelectorAll('[data-employee-edit]').forEach((button) => button.addEventListener('click', () => {
        form.reset();
        form.action = button.dataset.updateUrl;
        form.elements._method.value = 'PUT';
        title.textContent = `Edit ${button.dataset.firstName} ${button.dataset.lastName || ''}`;
        const fields = { first_name: 'firstName', last_name: 'lastName', email: 'email', phone: 'phone', role: 'role', status: 'status', employee_code: 'code', designation: 'designation', pay_grade: 'grade', employment_type: 'employmentType', monthly_salary: 'salary', incentive_per_job: 'incentive', manager_user_id: 'manager', branch_id: 'branch', joined_on: 'joined', notes: 'notes' };
        Object.entries(fields).forEach(([name, key]) => { form.elements[name].value = button.dataset[key] || ''; });
    }));
});
</script>
@endpush
@endif
