<?php

namespace Tests\Feature;

use App\Enums\DocumentStatus;
use App\Enums\JobStatus;
use App\Enums\PaymentMode;
use App\Enums\PaymentStatus;
use App\Enums\PayoutStatus;
use App\Models\AccountEntry;
use App\Models\Customer;
use App\Models\InventoryItem;
use App\Models\Invoice;
use App\Models\Job;
use App\Models\Payment;
use App\Models\PayoutCycle;
use App\Models\PurchaseOrder;
use App\Models\StockLocation;
use App\Models\StockMovement;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Vendor;
use App\Services\Accounting\AccountingService;
use App\Services\Inventory\StockService;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class PurchaseAccountingTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function tearDown(): void
    {
        $this->app->make(TenantContext::class)->clear();

        parent::tearDown();
    }

    public function test_receiving_purchase_increases_stock_once_and_vendor_payments_reduce_payable(): void
    {
        [$tenant, $owner] = $this->owner();
        $item = InventoryItem::factory()->for($tenant)->create(['unit_cost' => 80]);
        $location = StockLocation::factory()->for($tenant)->create();

        $this->actingAs($owner)->postJson(route('admin.vendors.store'), [
            'code' => 'SUP-01', 'name' => 'Cool Parts', 'payment_terms_days' => 15, 'is_active' => true,
        ])->assertCreated();
        $this->app->make(TenantContext::class)->set($tenant->getKey());
        $vendor = Vendor::query()->where('code', 'SUP-01')->firstOrFail();
        $this->actingAs($owner)->postJson(route('admin.purchases.store'), [
            'vendor_id' => $vendor->getKey(),
            'stock_location_id' => $location->getKey(),
            'ordered_on' => today()->toDateString(),
            'lines' => [['inventory_item_id' => $item->getKey(), 'quantity' => 2, 'unit_cost' => 100, 'tax_rate' => 18]],
        ])->assertCreated();
        $this->app->make(TenantContext::class)->set($tenant->getKey());
        $order = PurchaseOrder::query()->firstOrFail();

        $this->assertSame('236.00', $order->grand_total);
        $this->assertSame(0.0, app(StockService::class)->balance($item, $location));
        $this->actingAs($owner)->postJson(route('admin.account-entries.store'), [
            'type' => 'PURCHASE_PAYMENT', 'purchase_order_id' => $order->getKey(), 'amount' => 100,
            'payment_method' => 'BANK', 'entry_date' => today()->toDateString(),
        ])->assertUnprocessable()->assertJsonValidationErrors('purchase_order_id');

        $this->postJson(route('admin.purchases.receive', $order), ['supplier_invoice_number' => 'BILL-9'])->assertOk();
        $this->app->make(TenantContext::class)->set($tenant->getKey());
        $this->assertSame(2.0, app(StockService::class)->balance($item, $location));
        $this->assertSame('100.00', $item->refresh()->unit_cost);
        $this->assertSame('BILL-9', $order->refresh()->supplier_invoice_number);
        $this->postJson(route('admin.purchases.receive', $order))->assertUnprocessable();
        $this->assertDatabaseCount('stock_movements', 1);

        $this->postJson(route('admin.account-entries.store'), [
            'type' => 'PURCHASE_PAYMENT', 'purchase_order_id' => $order->getKey(), 'amount' => 100,
            'payment_method' => 'BANK', 'entry_date' => today()->toDateString(),
        ])->assertCreated();
        $this->postJson(route('admin.account-entries.store'), [
            'type' => 'PURCHASE_PAYMENT', 'purchase_order_id' => $order->getKey(), 'amount' => 137,
            'payment_method' => 'BANK', 'entry_date' => today()->toDateString(),
        ])->assertUnprocessable()->assertJsonValidationErrors('amount');
        $this->app->make(TenantContext::class)->set($tenant->getKey());
        $this->assertSame(100.0, (float) $order->payments()->sum('amount'));
        $this->get(route('admin.purchases.show', $order))->assertOk()->assertSee('136.00')->assertSee('Pay supplier');
        $this->get(route('admin.purchases.index'))->assertOk()->assertSee('Cool Parts');
    }

    public function test_purchase_and_account_routes_reject_other_tenants_and_technicians(): void
    {
        [$tenant, $owner] = $this->owner();
        $vendor = Vendor::factory()->for($tenant)->create();
        $location = StockLocation::factory()->for($tenant)->create();
        $item = InventoryItem::factory()->for($tenant)->create();
        $order = PurchaseOrder::factory()->for($tenant)->create(['vendor_id' => $vendor->getKey(), 'stock_location_id' => $location->getKey()]);
        $technician = User::factory()->for($tenant)->technician()->create();

        $this->actingAs($technician)->get(route('admin.purchases.index'))->assertForbidden();
        $this->actingAs($technician)->postJson(route('admin.account-entries.store'), [])->assertForbidden();
        $this->actingAs($owner)->postJson(route('admin.purchases.store'), [
            'vendor_id' => $vendor->getKey(), 'stock_location_id' => $location->getKey(),
            'ordered_on' => today()->toDateString(), 'lines' => [['inventory_item_id' => $item->getKey(), 'quantity' => 0, 'unit_cost' => 100]],
        ])->assertUnprocessable()->assertJsonValidationErrors('lines.0.quantity');

        $otherTenant = Tenant::factory()->create();
        $otherOwner = User::factory()->for($otherTenant)->create();
        $this->actingAs($otherOwner)->get(route('admin.purchases.show', $order))->assertNotFound();
        $this->actingAs($otherOwner)->postJson(route('admin.purchases.receive', $order))->assertNotFound();
        $this->actingAs($otherOwner)->postJson(route('admin.purchases.store'), [
            'vendor_id' => $vendor->getKey(), 'stock_location_id' => $location->getKey(),
            'ordered_on' => today()->toDateString(), 'lines' => [['inventory_item_id' => $item->getKey(), 'quantity' => 1, 'unit_cost' => 100]],
        ])->assertUnprocessable()->assertJsonValidationErrors(['vendor_id', 'stock_location_id', 'lines.0.inventory_item_id']);
    }

    public function test_profit_loss_counts_invoices_parts_payroll_and_expenses_without_purchase_double_counting(): void
    {
        [$tenant, $owner] = $this->owner();
        $customer = Customer::factory()->for($tenant)->create();
        $item = InventoryItem::factory()->for($tenant)->create(['unit_cost' => 100]);
        $location = StockLocation::factory()->for($tenant)->create();
        $job = Job::query()->create([
            'customer_id' => $customer->getKey(), 'job_number' => 'JOB-ACCOUNT-1',
            'status' => JobStatus::InProgress, 'service_type' => 'Repair',
        ]);
        StockMovement::query()->create([
            'inventory_item_id' => $item->getKey(), 'to_location_id' => $location->getKey(),
            'type' => 'PURCHASE', 'quantity' => 2, 'unit_cost' => 100, 'moved_at' => now(),
        ]);
        $consumption = app(StockService::class)->consume($job, $item, $location, 1, 180);
        Invoice::query()->create([
            'job_id' => $job->getKey(), 'customer_id' => $customer->getKey(), 'invoice_number' => 'INV-ACCOUNT-1',
            'status' => DocumentStatus::Sent, 'issued_on' => today(), 'subtotal' => 200, 'discount_total' => 0,
            'tax_total' => 36, 'grand_total' => 236, 'paid_total' => 100, 'balance_due' => 136,
        ]);
        $invoice = Invoice::query()->firstOrFail();
        Payment::query()->create([
            'invoice_id' => $invoice->getKey(), 'payment_number' => 'PAY-ACCOUNT-1', 'mode' => PaymentMode::Cash,
            'status' => PaymentStatus::Paid, 'amount' => 100, 'paid_at' => now(),
        ]);
        AccountEntry::factory()->for($tenant)->create(['type' => 'EXPENSE', 'amount' => 20, 'entry_date' => today()]);
        AccountEntry::factory()->for($tenant)->create(['type' => 'OTHER_INCOME', 'category' => 'OTHER_INCOME', 'amount' => 10, 'entry_date' => today()]);
        $payout = PayoutCycle::query()->create([
            'cycle_number' => 'PAYOUT-ACCOUNT-1', 'type' => 'MONTHLY', 'starts_on' => today()->startOfMonth(),
            'ends_on' => today(), 'status' => PayoutStatus::Processed, 'gross_total' => 30, 'net_total' => 30,
        ]);

        $statement = app(AccountingService::class)->statement(today()->startOfMonth()->toDateString(), today()->toDateString());

        $this->assertSame(200.0, $statement['sales']);
        $this->assertSame(100.0, $statement['parts_cost']);
        $this->assertSame(30.0, $statement['payroll_cost']);
        $this->assertSame(60.0, $statement['net_profit']);
        $this->assertSame(100.0, $statement['customer_receipts']);
        $this->actingAs($owner)->get(route('admin.accounts.index'))->assertOk()->assertSee('Profit &amp; loss statement', false);

        $this->app->make(TenantContext::class)->set($tenant->getKey());
        app(StockService::class)->returnConsumption($consumption, 0.5);
        $payout->update(['status' => PayoutStatus::Paid, 'paid_at' => now()]);
        $updatedStatement = app(AccountingService::class)->statement(today()->startOfMonth()->toDateString(), today()->toDateString());

        $this->assertSame(50.0, $updatedStatement['parts_cost']);
        $this->assertSame(30.0, $updatedStatement['payroll_paid']);
        $this->assertSame(110.0, $updatedStatement['net_profit']);
    }

    /** @return array{Tenant, User} */
    private function owner(): array
    {
        $tenant = Tenant::factory()->create();
        $owner = User::factory()->for($tenant)->create();
        $this->app->make(TenantContext::class)->set($tenant->getKey());

        return [$tenant, $owner];
    }
}
