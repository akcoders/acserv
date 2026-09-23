<?php

namespace Database\Factories;

use App\Models\StockLocation;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StockLocation>
 */
class StockLocationFactory extends Factory
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
            'code' => fake()->unique()->bothify('LOC-####'),
            'name' => fake()->streetName().' warehouse',
            'type' => 'WAREHOUSE',
            'is_active' => true,
        ];
    }
}
