<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\EmploymentProfile;
use App\Models\PayoutLine;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Workforce\PayoutService;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class EmployeeManagementTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function tearDown(): void
    {
        $this->app->make(TenantContext::class)->clear();

        parent::tearDown();
    }

    public function test_owner_can_manage_employee_profile_and_monthly_salary_slip(): void
    {
        $tenant = Tenant::factory()->create();
        $owner = User::factory()->for($tenant)->create();
        $this->app->make(TenantContext::class)->set($tenant->getKey());
        $this->actingAs($owner);

        $payload = [
            'first_name' => 'Aarav',
            'last_name' => 'Sharma',
            'email' => 'aarav@example.test',
            'phone' => '+919999999910',
            'role' => Role::Technician->value,
            'status' => 'ACTIVE',
            'employee_code' => 'TECH-1001',
            'designation' => 'Senior Technician',
            'pay_grade' => 'T2',
            'employment_type' => 'FULL_TIME',
            'monthly_salary' => 30000,
            'incentive_per_job' => 250,
            'manager_user_id' => $owner->getKey(),
            'joined_on' => '2026-09-01',
        ];

        $this->postJson(route('admin.employees.store'), $payload)->assertCreated()->assertJsonPath('message', 'Employee added. They can sign in with their workspace and email OTP.');
        $this->app->make(TenantContext::class)->set($tenant->getKey());
        $employee = User::query()->forTenant($tenant)->where('email', 'aarav@example.test')->firstOrFail();
        $profile = EmploymentProfile::query()->where('user_id', $employee->getKey())->firstOrFail();
        $this->assertSame('TECH-1001', $profile->employee_code);
        $this->assertSame('30000.00', $profile->monthly_salary);
        $this->assertNotNull($employee->technicianProfile);
        $this->get(route('admin.workforce.index'))->assertOk()->assertSee('Aarav Sharma')->assertSee('Senior Technician');

        $this->putJson(route('admin.employees.update', $employee), [...$payload, 'monthly_salary' => 36000, 'pay_grade' => 'T3'])->assertOk();
        $this->app->make(TenantContext::class)->set($tenant->getKey());
        $this->assertSame('36000.00', $profile->fresh()->monthly_salary);

        $cycle = $this->app->make(PayoutService::class)->generate('MONTHLY', '2026-09-01', '2026-09-30', [
            'base_hourly_rate' => 0,
            'per_job_rate' => 0,
            'rating_incentive' => 0,
            'low_rating_penalty' => 0,
            'fixed_deduction' => 0,
        ]);
        $line = PayoutLine::query()->where('payout_cycle_id', $cycle->getKey())->where('user_id', $employee->getKey())->firstOrFail();
        $this->assertSame('36000.00', $line->net_amount);
        $this->get(route('admin.payout-lines.payslip', [$cycle, $line]))->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->postJson(route('admin.payout-cycles.store'), [
            'type' => 'MONTHLY',
            'starts_on' => '2026-09-01',
            'ends_on' => '2026-09-30',
            'base_hourly_rate' => 0,
            'per_job_rate' => 0,
        ])->assertUnprocessable()->assertJsonValidationErrors('starts_on');
    }

    public function test_manager_cannot_create_employee_or_edit_someone_from_another_workspace(): void
    {
        $tenant = Tenant::factory()->create();
        $manager = User::factory()->for($tenant)->create(['role' => Role::Manager]);
        $owner = User::factory()->for($tenant)->create();
        $otherTenant = Tenant::factory()->create();
        $otherEmployee = User::factory()->for($otherTenant)->technician()->create();
        $this->app->make(TenantContext::class)->set($tenant->getKey());

        $payload = [
            'first_name' => 'Riya',
            'email' => 'riya@example.test',
            'role' => Role::Technician->value,
            'status' => 'ACTIVE',
            'designation' => 'Technician',
            'employment_type' => 'FULL_TIME',
            'monthly_salary' => 10000,
        ];

        $this->actingAs($manager)->postJson(route('admin.employees.store'), $payload)->assertForbidden();
        $this->actingAs($owner)->putJson(route('admin.employees.update', $otherEmployee), $payload)->assertNotFound();
    }
}
