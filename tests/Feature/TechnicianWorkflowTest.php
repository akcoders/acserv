<?php

namespace Tests\Feature;

use App\Enums\AssignmentStatus;
use App\Enums\EvidenceType;
use App\Enums\JobStatus;
use App\Models\Customer;
use App\Models\InventoryItem;
use App\Models\Job;
use App\Models\StockLocation;
use App\Models\StockMovement;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Inventory\StockService;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TechnicianWorkflowTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function tearDown(): void
    {
        $this->app->make(TenantContext::class)->clear();

        parent::tearDown();
    }

    public function test_assigned_technician_completes_ordered_workflow_and_invoice_follows_payment_verification(): void
    {
        Storage::fake('local');
        [$job, $technician] = $this->assignedJob();
        $this->actingAs($technician)->withHeaders(['Accept' => 'application/json']);

        $this->post(route('technician.jobs.workflow.store', $job), ['action' => 'accept'])->assertOk()->assertJsonPath('job.status', JobStatus::Accepted->value);
        $this->post(route('technician.jobs.workflow.store', $job), ['action' => 'reached'])->assertOk()->assertJsonPath('job.status', JobStatus::Reached->value);
        $this->post(route('technician.jobs.workflow.store', $job), [
            'action' => 'inspection',
            'before_photo' => UploadedFile::fake()->image('before.jpg'),
            'fault_remark' => 'Capacitor is worn out.',
        ])->assertOk()->assertJsonPath('job.status', JobStatus::Inspected->value);
        $this->post(route('technician.jobs.workflow.store', $job), [
            'action' => 'authorize',
            'customer_signature' => UploadedFile::fake()->image('prework.png'),
        ])->assertOk()->assertJsonPath('job.status', JobStatus::Authorized->value);
        $this->post(route('technician.jobs.workflow.store', $job), ['action' => 'start'])->assertOk()->assertJsonPath('job.status', JobStatus::InProgress->value);
        $this->post(route('technician.jobs.service-cost.store', $job), [
            'service_cost' => 1200,
            'service_tax_rate' => 0,
        ])->assertOk();
        $this->app->make(TenantContext::class)->set($technician->tenant_id);
        $item = InventoryItem::query()->create([
            'sku' => 'PART-001',
            'name' => 'Capacitor',
            'unit' => 'PCS',
            'unit_cost' => 150,
            'sale_price' => 300,
            'reorder_level' => 1,
            'is_active' => true,
        ]);
        $location = StockLocation::query()->create([
            'code' => 'MAIN',
            'name' => 'Main Warehouse',
            'type' => 'WAREHOUSE',
            'is_active' => true,
        ]);
        StockMovement::query()->create([
            'inventory_item_id' => $item->getKey(),
            'to_location_id' => $location->getKey(),
            'type' => 'OPENING',
            'quantity' => 2,
            'unit_cost' => 150,
            'moved_at' => now(),
        ]);
        $this->post(route('technician.jobs.parts.store', $job), [
            'inventory_item_id' => $item->getKey(),
            'stock_location_id' => $location->getKey(),
            'quantity' => 1,
            'unit_price' => 1,
        ])->assertCreated()->assertJsonPath('consumption.unit_price', '300.00');
        $this->app->make(TenantContext::class)->set($technician->tenant_id);
        $this->assertSame(1.0, app(StockService::class)->balance($item, $location));
        $this->post(route('technician.jobs.workflow.store', $job), [
            'action' => 'complete',
            'after_photo' => UploadedFile::fake()->image('after.jpg'),
            'completion_remark' => 'Replaced capacitor and verified cooling.',
            'customer_signature' => UploadedFile::fake()->image('completion.png'),
        ])->assertOk()->assertJsonPath('job.status', JobStatus::AwaitingPayment->value)->assertJsonPath('invoice_url', null);

        $this->post(route('technician.jobs.payment-collections.store', $job), [
            'mode' => 'CASH',
            'reference' => 'Received at site',
        ])->assertCreated()->assertJsonPath('collection.amount', '1770.00');

        $this->app->make(TenantContext::class)->set($technician->tenant_id);
        $this->assertSame(JobStatus::PaymentPending, $job->refresh()->status);
        $this->assertNull($job->invoice()->first());
        $owner = User::factory()->for($technician->tenant)->create();
        $collection = $job->paymentCollections()->firstOrFail();
        $this->actingAs($owner)->post(route('admin.payment-collections.review', $collection), [
            'action' => 'verify',
        ])->assertOk();

        $this->app->make(TenantContext::class)->set($technician->tenant_id);
        $job->refresh();
        $this->assertSame('Capacitor is worn out.', $job->inspection_remark);
        $this->assertSame('Replaced capacitor and verified cooling.', $job->resolution);
        $this->assertNotNull($job->reached_at);
        $this->assertNotNull($job->prework_signature_path);
        $this->assertNotNull($job->customer_signature_path);
        $this->assertSame(1, $job->evidence()->where('type', EvidenceType::Before)->count());
        $this->assertSame(1, $job->evidence()->where('type', EvidenceType::After)->count());
        $this->assertSame(2, $job->evidence()->where('type', EvidenceType::Signature)->count());
        $this->assertSame(AssignmentStatus::Completed, $job->assignments()->firstOrFail()->status);
        $this->assertSame(JobStatus::Completed, $job->status);
        $this->assertSame('1770.00', $job->invoice()->firstOrFail()->paid_total);
        $this->assertSame('0.00', $job->invoice()->firstOrFail()->balance_due);
        $this->actingAs($technician);
        $this->get(route('technician.jobs.invoice.pdf', $job))->assertOk()->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_technician_cannot_skip_stages_or_access_another_technicians_job(): void
    {
        [$job, $technician] = $this->assignedJob();
        $otherTechnician = User::factory()->for($technician->tenant)->technician()->create();

        $this->actingAs($technician)->postJson(route('technician.jobs.workflow.store', $job), ['action' => 'start'])
            ->assertUnprocessable();
        $this->app->make(TenantContext::class)->set($technician->tenant_id);
        $this->assertSame(JobStatus::Assigned, $job->refresh()->status);

        $this->actingAs($otherTechnician)->postJson(route('technician.jobs.workflow.store', $job), ['action' => 'accept'])
            ->assertNotFound();

        $owner = User::factory()->for($technician->tenant)->create();
        $this->actingAs($owner)->postJson(route('admin.jobs.transitions.store', $job), ['status' => JobStatus::Accepted->value])
            ->assertUnprocessable();

        $this->actingAs($technician)->postJson(route('technician.jobs.workflow.store', $job), ['action' => 'accept'])
            ->assertOk();
        $this->actingAs($owner)->postJson(route('admin.jobs.assignments.store', $job), ['technician_id' => $technician->getKey()])
            ->assertUnprocessable();
    }

    /** @return array{Job, User} */
    private function assignedJob(): array
    {
        $tenant = Tenant::factory()->create();
        $this->app->make(TenantContext::class)->set($tenant->getKey());
        $customer = Customer::factory()->for($tenant)->create();
        $technician = User::factory()->for($tenant)->technician()->create();
        $job = Job::query()->create([
            'customer_id' => $customer->getKey(),
            'job_number' => 'JOB-WORKFLOW-001',
            'status' => JobStatus::Assigned,
            'service_type' => 'AC repair',
        ]);
        $job->assignments()->create([
            'technician_id' => $technician->getKey(),
            'status' => AssignmentStatus::Assigned,
            'assigned_at' => now(),
        ]);

        return [$job, $technician];

    }
}
