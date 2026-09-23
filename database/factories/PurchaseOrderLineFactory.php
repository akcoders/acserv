<?php

namespace Database\Factories;

use App\Models\InventoryItem;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PurchaseOrderLine>
 */
class PurchaseOrderLineFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'purchase_order_id' => fn (array $attributes) => PurchaseOrder::factory()->create(['tenant_id' => $attributes['tenant_id']])->getKey(),
            'inventory_item_id' => fn (array $attributes) => InventoryItem::factory()->create(['tenant_id' => $attributes['tenant_id']])->getKey(),
            'quantity' => 1,
            'unit_cost' => 100,
            'tax_rate' => 0,
            'subtotal' => 100,
            'tax_amount' => 0,
            'line_total' => 100,
        ];
    }
}
