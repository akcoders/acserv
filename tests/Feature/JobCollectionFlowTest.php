<?php

namespace Tests\Feature;

use App\Enums\AssignmentStatus;
use App\Enums\CollectionStatus;
use App\Enums\JobStatus;
use App\Models\Customer;
use App\Models\Job;
use App\Models\Tenant;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class JobCollectionFlowTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function tearDown(): void
    {
        $this->app->make(TenantContext::class)->clear();

        parent::tearDown();
    }

    public function test_upi_requires_qr_and_screenshot_then_admin_verification_creates_paid_invoice_once(): void
    {
        Storage::fake('local');
        [$job, $technician, $owner] = $this->readyJob();
        $this->actingAs($technician)->postJson(route('technician.jobs.payment-collections.store', $job), [
            'mode' => 'UPI',
        ])->assertUnprocessable()->assertJsonValidationErrors('transaction_image');
        $this->actingAs($technician)->withHeaders(['Accept' => 'application/json'])->post(route('technician.jobs.payment-collections.store', $job), [
            'mode' => 'UPI',
            'transaction_image' => UploadedFile::fake()->image('transaction-before-qr.jpg'),
        ])->assertUnprocessable()->assertJsonValidationErrors('transaction_image');

        $this->actingAs($technician)->postJson(route('admin.payment-settings.upi.update'), [
            'upi_id' => 'wrong@upi',
        ])->assertForbidden();

        $this->actingAs($owner)->post(route('admin.payment-settings.upi.update'), [
            'upi_id' => 'acserv@upi',
            'upi_payee_name' => 'ACServ',
            'qr_image' => UploadedFile::fake()->image('qr.png'),
        ])->assertOk();
        $this->app->make(TenantContext::class)->set($owner->tenant_id);
        Storage::disk('local')->assertExists($owner->tenant->fresh()->upi_qr_path);
        $this->actingAs($owner)->get(route('admin.billing.index'))->assertOk()->assertSee('acserv@upi');

        $this->actingAs($technician)->get(route('payment-qr.image'))->assertOk();
        $this->actingAs($technician)->withHeaders(['Accept' => 'application/json'])->post(route('technician.jobs.payment-collections.store', $job), [
            'mode' => 'UPI',
            'reference' => 'UPI-123456',
            'transaction_image' => UploadedFile::fake()->image('transaction.jpg'),
        ])->assertCreated()->assertJsonPath('collection.status', CollectionStatus::Pending->value)->assertJsonPath('collection.amount', '118.00');
        $this->actingAs($technician)->get(route('technician.jobs.show', $job))->assertOk()->assertSee('Waiting for office verification');
        $this->actingAs($owner)->get(route('admin.billing.index'))->assertOk()->assertSee('JOB-COLLECTION-001');

        $this->app->make(TenantContext::class)->set($owner->tenant_id);
        $collection = $job->paymentCollections()->firstOrFail();
        Storage::disk('local')->assertExists($collection->proof_path);
        $this->actingAs($technician)->get(route('admin.payment-collections.proof', $collection))->assertForbidden();
        $otherTenant = Tenant::factory()->create();
        $otherOwner = User::factory()->for($otherTenant)->create();
        $this->actingAs($otherOwner)->get(route('admin.payment-collections.proof', $collection))->assertNotFound();
        $this->actingAs($owner)->get(route('admin.payment-collections.proof', $collection))->assertOk();
        $this->actingAs($owner)->postJson(route('admin.payment-collections.review', $collection), ['action' => 'verify'])->assertOk();

        $this->app->make(TenantContext::class)->set($owner->tenant_id);
        $this->assertSame(JobStatus::Completed, $job->refresh()->status);
        $this->assertSame(CollectionStatus::Verified, $collection->refresh()->status);
        $this->assertSame('118.00', $job->invoice()->firstOrFail()->paid_total);
        $this->assertDatabaseCount('invoices', 1);
        $this->assertDatabaseCount('payments', 1);
        $this->actingAs($owner)->postJson(route('admin.payment-collections.review', $collection), ['action' => 'verify'])->assertUnprocessable();
        $this->assertDatabaseCount('payments', 1);
    }

    public function test_rejected_collection_returns_job_to_collection_stage_without_invoice(): void
    {
        [$job, $technician, $owner] = $this->readyJob();
        $this->actingAs($technician)->postJson(route('technician.jobs.payment-collections.store', $job), [
            'mode' => 'CASH',
        ])->assertCreated();
        $this->app->make(TenantContext::class)->set($owner->tenant_id);
        $collection = $job->paymentCollections()->firstOrFail();

        $this->actingAs($owner)->postJson(route('admin.payment-collections.review', $collection), ['action' => 'reject'])
            ->assertUnprocessable()->assertJsonValidationErrors('rejection_reason');
        $this->actingAs($owner)->postJson(route('admin.payment-collections.review', $collection), [
            'action' => 'reject',
            'rejection_reason' => 'Cash was not received by the office.',
        ])->assertOk();

        $this->app->make(TenantContext::class)->set($owner->tenant_id);
        $this->assertSame(JobStatus::AwaitingPayment, $job->refresh()->status);
        $this->assertSame(CollectionStatus::Rejected, $collection->refresh()->status);
        $this->assertNull($job->invoice()->first());
        $this->actingAs($technician)->postJson(route('technician.jobs.payment-collections.store', $job), ['mode' => 'CASH'])->assertCreated();
    }

    /** @return array{Job, User, User} */
    private function readyJob(): array
    {
        $tenant = Tenant::factory()->create();
        $this->app->make(TenantContext::class)->set($tenant->getKey());
        $customer = Customer::factory()->for($tenant)->create();
        $technician = User::factory()->for($tenant)->technician()->create();
        $owner = User::factory()->for($tenant)->create();
        $job = Job::query()->create([
            'customer_id' => $customer->getKey(),
            'job_number' => 'JOB-COLLECTION-001',
            'status' => JobStatus::AwaitingPayment,
            'service_type' => 'Service visit',
            'service_cost' => 100,
            'service_tax_rate' => 18,
            'completed_at' => now(),
        ]);
        $job->assignments()->create([
            'technician_id' => $technician->getKey(),
            'status' => AssignmentStatus::Completed,
            'assigned_at' => now()->subHour(),
            'accepted_at' => now()->subHour(),
            'completed_at' => now(),
        ]);

        return [$job, $technician, $owner];
    }
}
