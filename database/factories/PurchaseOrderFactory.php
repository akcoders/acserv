<?php

namespace Database\Factories;

use App\Models\PurchaseOrder;
use App\Models\StockLocation;
use App\Models\Tenant;
use App\Models\Vendor;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PurchaseOrder>
 */
class PurchaseOrderFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'vendor_id' => fn (array $attributes) => Vendor::factory()->create(['tenant_id' => $attributes['tenant_id']])->getKey(),
            'stock_location_id' => fn (array $attributes) => StockLocation::factory()->create(['tenant_id' => $attributes['tenant_id']])->getKey(),
            'order_number' => fake()->unique()->bothify('PO-########'),
            'status' => 'ORDERED',
            'ordered_on' => today(),
            'subtotal' => 0,
            'tax_total' => 0,
            'grand_total' => 0,
        ];
    }
}
