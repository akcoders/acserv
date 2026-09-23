<?php

namespace Database\Factories;

use App\Models\InventoryItem;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InventoryItem>
 */
class InventoryItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'sku' => fake()->unique()->bothify('PART-######'),
            'name' => fake()->words(3, true),
            'unit' => 'PCS',
            'unit_cost' => 100,
            'sale_price' => 180,
            'reorder_level' => 2,
            'is_active' => true,
        ];
    }
}
