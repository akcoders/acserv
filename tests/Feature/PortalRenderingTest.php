<?php

namespace Tests\Feature;

use App\Enums\AssignmentStatus;
use App\Enums\JobStatus;
use App\Models\Customer;
use App\Models\Job;
use App\Models\Tenant;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class PortalRenderingTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function tearDown(): void
    {
        $this->app->make(TenantContext::class)->clear();

        parent::tearDown();
    }

    public function test_owner_dashboard_and_pipeline_board_render(): void
    {
        $tenant = Tenant::factory()->create();
        $owner = User::factory()->for($tenant)->create();
        $customer = Customer::factory()->for($tenant)->create();
        $this->app->make(TenantContext::class)->set($tenant->getKey());
        $job = Job::query()->create([
            'customer_id' => $customer->getKey(),
            'job_number' => 'JOB-ADMIN-001',
            'status' => JobStatus::Reached,
            'service_type' => 'AC repair',
        ]);

        $this->actingAs($owner)->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Operations command center')
            ->assertSee($job->job_number);

        $this->get(route('admin.jobs.index'))
            ->assertOk()
            ->assertSee($job->job_number);
    }

    public function test_technician_worklist_and_guided_job_render(): void
    {
        $tenant = Tenant::factory()->create();
        $technician = User::factory()->for($tenant)->technician()->create();
        $customer = Customer::factory()->for($tenant)->create();
        $this->app->make(TenantContext::class)->set($tenant->getKey());
        $job = Job::query()->create([
            'customer_id' => $customer->getKey(),
            'job_number' => 'JOB-PORTAL-001',
            'status' => JobStatus::Assigned,
            'service_type' => 'AC repair',
        ]);
        $job->assignments()->create([
            'technician_id' => $technician->getKey(),
            'status' => AssignmentStatus::Assigned,
            'assigned_at' => now(),
        ]);

        $this->actingAs($technician)->get(route('technician.dashboard'))
            ->assertOk()
            ->assertSee($job->job_number);

        $this->get(route('technician.jobs.show', $job))
            ->assertOk()
            ->assertSee('Accept this job')
            ->assertSee('Job pipeline');
    }

    public function test_customer_dashboard_renders_service_pipeline_and_invoices(): void
    {
        $tenant = Tenant::factory()->create();
        $customerUser = User::factory()->for($tenant)->customer()->create();
        $customer = Customer::factory()->for($tenant)->create(['user_id' => $customerUser->getKey()]);
        $this->app->make(TenantContext::class)->set($tenant->getKey());
        $job = Job::query()->create([
            'customer_id' => $customer->getKey(),
            'job_number' => 'JOB-CUSTOMER-001',
            'status' => JobStatus::Inspected,
            'service_type' => 'AC repair',
        ]);

        $this->actingAs($customerUser)->get(route('customer.dashboard'))
            ->assertOk()
            ->assertSee('Service pipeline')
            ->assertSee('Recent invoices')
            ->assertSee($job->job_number);

        $this->app->make(TenantContext::class)->set($tenant->getKey());
        $job->update(['status' => JobStatus::AwaitingPayment]);
        $this->get(route('customer.dashboard'))
            ->assertOk()
            ->assertSee('Awaiting Payment')
            ->assertSee('The technician will collect the payment');
    }
}
