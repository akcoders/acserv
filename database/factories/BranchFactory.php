<?php

namespace Database\Factories;

use App\Models\Branch;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Branch>
 */
class BranchFactory extends Factory
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
            'code' => fake()->unique()->bothify('BR-###'),
            'name' => fake()->city().' Branch',
            'email' => fake()->companyEmail(),
            'phone' => '+91'.fake()->numerify('##########'),
            'address' => [
                'line_1' => fake()->streetAddress(),
                'city' => fake()->city(),
                'state' => fake()->state(),
                'postal_code' => fake()->postcode(),
                'country' => 'IN',
            ],
            'latitude' => fake()->latitude(8, 37),
            'longitude' => fake()->longitude(68, 97),
            'is_active' => true,
        ];
    }
}
