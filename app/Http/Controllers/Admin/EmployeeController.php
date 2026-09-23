<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Role;
use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Models\EmploymentProfile;
use App\Models\TechnicianProfile;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class EmployeeController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        abort_unless($request->user()?->role?->canManageUsers(), 403);
        $data = $this->validated($request);

        $employee = DB::transaction(function () use ($request, $data): User {
            $employee = new User;
            $employee->forceFill([
                'tenant_id' => $request->user()->tenant_id,
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'] ?? null,
                'email' => Str::lower($data['email']),
                'phone' => $data['phone'] ?? null,
                'role' => $data['role'],
                'status' => $data['status'],
                'created_by' => $request->user()->getKey(),
            ])->save();

            $this->saveProfile($employee, $data);

            return $employee;
        });

        return response()->json(['message' => 'Employee added. They can sign in with their workspace and email OTP.', 'employee' => $employee->getKey(), 'reload' => true], 201);
    }

    public function update(Request $request, User $employee): JsonResponse
    {
        abort_unless($request->user()?->role?->canManageUsers(), 403);
        abort_unless($employee->tenant_id === $request->user()->tenant_id && $employee->role !== Role::Owner && $employee->role !== Role::Customer, 404);
        $data = $this->validated($request, $employee);

        DB::transaction(function () use ($employee, $data): void {
            $employee->forceFill([
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'] ?? null,
                'email' => Str::lower($data['email']),
                'phone' => $data['phone'] ?? null,
                'role' => $data['role'],
                'status' => $data['status'],
            ])->save();

            $this->saveProfile($employee, $data);
        });

        return response()->json(['message' => 'Employee updated.', 'reload' => true]);
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, ?User $employee = null): array
    {
        $tenantId = $request->user()->tenant_id;

        return $request->validate([
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['nullable', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users')->where('tenant_id', $tenantId)->ignore($employee?->getKey())],
            'phone' => ['nullable', 'string', 'max:20', Rule::unique('users')->where('tenant_id', $tenantId)->ignore($employee?->getKey())],
            'role' => ['required', Rule::in([Role::Admin->value, Role::Manager->value, Role::Dispatcher->value, Role::Technician->value, Role::Accountant->value])],
            'status' => ['required', Rule::in([UserStatus::Active->value, UserStatus::Suspended->value])],
            'employee_code' => ['nullable', 'string', 'max:40', Rule::unique('employment_profiles')->where('tenant_id', $tenantId)->ignore($employee?->employmentProfile?->getKey())],
            'designation' => ['required', 'string', 'max:120'],
            'pay_grade' => ['nullable', 'string', 'max:40'],
            'employment_type' => ['required', Rule::in(['FULL_TIME', 'PART_TIME', 'CONTRACT'])],
            'monthly_salary' => ['required', 'numeric', 'min:0', 'max:9999999999.99'],
            'incentive_per_job' => ['nullable', 'numeric', 'min:0', 'max:9999999999.99'],
            'manager_user_id' => ['nullable', Rule::exists('users', 'id')->where('tenant_id', $tenantId)->whereNot('role', Role::Customer->value)],
            'branch_id' => ['nullable', Rule::exists('branches', 'id')->where('tenant_id', $tenantId)],
            'joined_on' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);
    }

    /** @param array<string, mixed> $data */
    private function saveProfile(User $employee, array $data): void
    {
        abort_if(($data['manager_user_id'] ?? null) === $employee->getKey(), 422, 'An employee cannot report to themselves.');

        EmploymentProfile::query()->updateOrCreate(['user_id' => $employee->getKey()], [
            'employee_code' => ($data['employee_code'] ?? null) ?: ($employee->employmentProfile?->employee_code ?? 'EMP-'.Str::upper(Str::substr((string) Str::ulid(), -8))),
            'manager_user_id' => $data['manager_user_id'] ?? null,
            'designation' => $data['designation'],
            'pay_grade' => $data['pay_grade'] ?? null,
            'employment_type' => $data['employment_type'],
            'monthly_salary' => $data['monthly_salary'],
            'incentive_per_job' => $data['incentive_per_job'] ?? 0,
            'joined_on' => $data['joined_on'] ?? null,
            'notes' => $data['notes'] ?? null,
        ]);

        if ($employee->role === Role::Technician) {
            TechnicianProfile::query()->updateOrCreate(['user_id' => $employee->getKey()], [
                'branch_id' => $data['branch_id'] ?? null,
                'is_available' => $employee->status === UserStatus::Active,
                'max_daily_jobs' => $employee->technicianProfile?->max_daily_jobs ?? 6,
            ]);
        }
    }
}
