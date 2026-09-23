<?php

namespace Tests\Feature;

use App\Enums\JobStatus;
use App\Enums\Role;
use App\Models\Customer;
use App\Models\Feedback;
use App\Models\Job;
use App\Models\Tenant;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class FeedbackManagementTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function tearDown(): void
    {
        $this->app->make(TenantContext::class)->clear();

        parent::tearDown();
    }

    public function test_manager_can_resolve_escalated_feedback_and_other_workspaces_cannot_access_it(): void
    {
        $tenant = Tenant::factory()->create();
        $manager = User::factory()->for($tenant)->create(['role' => Role::Manager]);
        $this->app->make(TenantContext::class)->set($tenant->getKey());
        $customer = Customer::factory()->for($tenant)->create();
        $job = Job::query()->create(['customer_id' => $customer->getKey(), 'job_number' => 'JOB-FEEDBACK-001', 'status' => JobStatus::Completed, 'service_type' => 'AC repair']);
        $feedback = Feedback::query()->create(['customer_id' => $customer->getKey(), 'job_id' => $job->getKey(), 'rating' => 1, 'comment' => 'Unit stopped again', 'status' => 'ESCALATED', 'escalated_at' => now()]);

        $this->actingAs($manager)->get(route('admin.feedback.index'))->assertOk()->assertSee('Unit stopped again')->assertSee('Needs attention');
        $this->postJson(route('admin.feedback.review', $feedback), ['status' => 'RESOLVED'])->assertUnprocessable()->assertJsonValidationErrors('resolution');
        $this->postJson(route('admin.feedback.review', $feedback), ['status' => 'RESOLVED', 'resolution' => 'Replaced faulty contactor under warranty.'])->assertOk();
        $this->app->make(TenantContext::class)->set($tenant->getKey());
        $this->assertSame('RESOLVED', $feedback->fresh()->status);
        $this->assertSame($manager->getKey(), $feedback->fresh()->resolved_by);

        $otherTenant = Tenant::factory()->create();
        $otherManager = User::factory()->for($otherTenant)->create(['role' => Role::Manager]);
        $this->actingAs($otherManager)->postJson(route('admin.feedback.review', $feedback), ['status' => 'RESOLVED', 'resolution' => 'Unauthorized'])->assertNotFound();
    }

    public function test_technician_cannot_open_feedback_management(): void
    {
        $tenant = Tenant::factory()->create();
        $technician = User::factory()->for($tenant)->technician()->create();

        $this->actingAs($technician)->get(route('admin.feedback.index'))->assertForbidden();
    }
}
