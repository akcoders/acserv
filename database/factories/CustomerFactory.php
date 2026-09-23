<?php

namespace Database\Factories;

use App\Enums\CustomerType;
use App\Models\Customer;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Customer>
 */
class CustomerFactory extends Factory
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
            'customer_number' => fake()->unique()->bothify('CUS-######'),
            'type' => CustomerType::Residential,
            'name' => fake()->name(),
            'email' => fake()->safeEmail(),
            'phone' => '+91'.fake()->numerify('##########'),
            'service_address' => [
                'line_1' => fake()->streetAddress(),
                'city' => fake()->city(),
                'state' => fake()->state(),
                'postal_code' => fake()->postcode(),
                'country' => 'IN',
            ],
        ];
    }
}
