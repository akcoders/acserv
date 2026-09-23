<?php

namespace Tests\Feature;

use App\Enums\JobStatus;
use App\Models\Customer;
use App\Models\InventoryItem;
use App\Models\Invoice;
use App\Models\Job;
use App\Models\JobPartConsumption;
use App\Models\StockLocation;
use App\Models\TaxProfile;
use App\Models\Tenant;
use App\Services\Billing\JobInvoiceService;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class JobInvoiceServiceTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function tearDown(): void
    {
        $this->app->make(TenantContext::class)->clear();

        parent::tearDown();
    }

    public function test_completed_job_generates_one_invoice_with_labor_and_net_parts(): void
    {
        $tenant = Tenant::factory()->create();
        $this->app->make(TenantContext::class)->set($tenant->getKey());
        $customer = Customer::factory()->for($tenant)->create();
        $taxProfile = TaxProfile::query()->create([
            'name' => 'GST 18%',
            'sac_code' => '998719',
            'tax_rate' => 18,
            'is_default' => true,
        ]);
        $item = InventoryItem::query()->create([
            'tax_profile_id' => $taxProfile->getKey(),
            'sku' => 'PART-001',
            'name' => 'Capacitor',
            'hsn_code' => '8532',
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
        $job = Job::query()->create([
            'customer_id' => $customer->getKey(),
            'job_number' => 'JOB-TEST-001',
            'status' => JobStatus::Completed,
            'service_type' => 'AC repair',
            'service_cost' => 1200,
            'service_tax_rate' => 18,
        ]);
        JobPartConsumption::query()->create([
            'job_id' => $job->getKey(),
            'inventory_item_id' => $item->getKey(),
            'stock_location_id' => $location->getKey(),
            'quantity' => 2,
            'returned_quantity' => 0.5,
            'unit_price' => 300,
            'tax_rate' => 18,
        ]);

        $firstInvoice = app(JobInvoiceService::class)->generate($job);
        $secondInvoice = app(JobInvoiceService::class)->generate($job);

        $this->assertTrue($firstInvoice->is($secondInvoice));
        $this->assertSame('1650.00', $firstInvoice->subtotal);
        $this->assertSame('297.00', $firstInvoice->tax_total);
        $this->assertSame('1947.00', $firstInvoice->grand_total);
        $this->assertSame('1947.00', $firstInvoice->balance_due);
        $this->assertSame(['AC repair', 'Capacitor'], $firstInvoice->lines->pluck('description')->all());
        $this->assertSame('1.500', $firstInvoice->lines->last()->quantity);
        $this->assertDatabaseCount('invoices', 1);
        $this->assertDatabaseCount('invoice_lines', 2);
    }

    public function test_unfinished_job_does_not_generate_invoice(): void
    {
        $tenant = Tenant::factory()->create();
        $this->app->make(TenantContext::class)->set($tenant->getKey());
        $customer = Customer::factory()->for($tenant)->create();
        $job = Job::query()->create([
            'customer_id' => $customer->getKey(),
            'job_number' => 'JOB-TEST-002',
            'status' => JobStatus::InProgress,
            'service_type' => 'AC repair',
        ]);

        try {
            app(JobInvoiceService::class)->generate($job);
            $this->fail('An unfinished job should not be invoiced.');
        } catch (ValidationException $exception) {
            $this->assertSame('Finish the job before generating its invoice.', $exception->errors()['status'][0]);
        }

        $this->assertSame(0, Invoice::query()->count());
    }
}
