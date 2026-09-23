<?php

namespace Database\Factories;

use App\Enums\AssetStatus;
use App\Models\Asset;
use App\Models\Customer;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Asset>
 */
class AssetFactory extends Factory
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
            'customer_id' => Customer::factory(),
            'name' => fake()->randomElement(['Bedroom AC', 'Living Room AC', 'Office AC']),
            'brand' => fake()->randomElement(['Daikin', 'LG', 'Samsung', 'Voltas', 'Blue Star']),
            'model' => strtoupper(fake()->bothify('AC-####??')),
            'serial_number' => fake()->unique()->bothify('SN-########'),
            'capacity' => fake()->randomElement(['1 Ton', '1.5 Ton', '2 Ton']),
            'asset_type' => fake()->randomElement(['SPLIT', 'WINDOW', 'CASSETTE']),
            'install_date' => fake()->dateTimeBetween('-5 years', 'now'),
            'status' => AssetStatus::Active,
        ];
    }
}
