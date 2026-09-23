<?php

namespace Database\Seeders;

use App\Enums\StockMovementType;
use App\Models\AccountEntry;
use App\Models\EmploymentProfile;
use App\Models\InventoryItem;
use App\Models\PurchaseOrder;
use App\Models\StockLocation;
use App\Models\StockMovement;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Vendor;
use App\Support\TenantContext;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CommerceDemoSeeder extends Seeder
{
    public function __construct(private readonly TenantContext $tenantContext) {}

    public function run(): void
    {
        $tenant = Tenant::query()->where('slug', config('acserv.demo_login.workspace'))->firstOrFail();
        $previousTenantId = $this->tenantContext->id();
        $this->tenantContext->set($tenant->getKey());

        try {
            DB::transaction(function () use ($tenant): void {
                $owner = User::query()->forTenant($tenant)->where('email', config('acserv.demo_login.owner_email'))->firstOrFail();
                $technician = User::query()->forTenant($tenant)->where('email', config('acserv.demo_login.technician_email'))->firstOrFail();
                $item = InventoryItem::query()->where('sku', 'CAP-35UF')->firstOrFail();
                $location = StockLocation::query()->where('code', 'WH-MAIN')->firstOrFail();

                EmploymentProfile::query()->firstOrCreate(
                    ['user_id' => $technician->getKey()],
                    [
                        'manager_user_id' => $owner->getKey(),
                        'employee_code' => 'TECH-DEMO-001',
                        'designation' => 'Senior AC Technician',
                        'pay_grade' => 'T2',
                        'employment_type' => 'FULL_TIME',
                        'monthly_salary' => 28000,
                        'incentive_per_job' => 200,
                        'joined_on' => today()->subMonths(6),
                    ],
                );

                $vendor = Vendor::query()->firstOrCreate(
                    ['code' => 'VEN-DEMO-001'],
                    [
                        'name' => 'CoolTech Spare Parts',
                        'contact_name' => 'Supply Desk',
                        'phone' => '+919999999904',
                        'payment_terms_days' => 15,
                        'is_active' => true,
                    ],
                );

                $purchase = PurchaseOrder::query()->firstOrCreate(
                    ['order_number' => 'PO-DEMO-001'],
                    [
                        'vendor_id' => $vendor->getKey(),
                        'stock_location_id' => $location->getKey(),
                        'status' => 'RECEIVED',
                        'supplier_invoice_number' => 'SUP-DEMO-001',
                        'ordered_on' => today()->subDays(3),
                        'due_on' => today()->addDays(12),
                        'received_at' => now()->subDay(),
                        'subtotal' => 1200,
                        'tax_total' => 216,
                        'grand_total' => 1416,
                        'notes' => 'Demo restock of service capacitors.',
                    ],
                );

                $purchase->lines()->firstOrCreate(
                    ['sort_order' => 0],
                    [
                        'inventory_item_id' => $item->getKey(),
                        'quantity' => 4,
                        'unit_cost' => 300,
                        'tax_rate' => 18,
                        'subtotal' => 1200,
                        'tax_amount' => 216,
                        'line_total' => 1416,
                    ],
                );

                $receipt = StockMovement::query()->firstOrCreate(
                    ['idempotency_key' => 'demo-purchase:PO-DEMO-001'],
                    [
                        'inventory_item_id' => $item->getKey(),
                        'to_location_id' => $location->getKey(),
                        'type' => StockMovementType::Purchase,
                        'quantity' => 4,
                        'unit_cost' => 300,
                        'reference' => $purchase->order_number,
                        'moved_at' => $purchase->received_at,
                    ],
                );

                if ($receipt->wasRecentlyCreated) {
                    $item->update(['unit_cost' => 300]);
                }

                AccountEntry::query()->firstOrCreate(
                    ['entry_number' => 'ACC-DEMO-001'],
                    [
                        'purchase_order_id' => $purchase->getKey(),
                        'type' => 'PURCHASE_PAYMENT',
                        'category' => 'INVENTORY_PURCHASE',
                        'description' => 'Part payment for '.$purchase->order_number,
                        'amount' => 500,
                        'payment_method' => 'UPI',
                        'reference' => 'DEMO-UPI-001',
                        'entry_date' => today(),
                    ],
                );

                AccountEntry::query()->firstOrCreate(
                    ['entry_number' => 'ACC-DEMO-002'],
                    [
                        'type' => 'EXPENSE',
                        'category' => 'FIELD_TRAVEL',
                        'description' => 'Technician travel and parking',
                        'amount' => 180,
                        'payment_method' => 'CASH',
                        'reference' => 'DEMO-EXPENSE-001',
                        'entry_date' => today(),
                    ],
                );
            });
        } finally {
            if ($previousTenantId === null) {
                $this->tenantContext->clear();
            } else {
                $this->tenantContext->set($previousTenantId);
            }
        }
    }
}
