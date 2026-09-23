<?php

namespace Database\Factories;

use App\Models\Tenant;
use App\Models\Vendor;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Vendor>
 */
class VendorFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'code' => fake()->unique()->bothify('VEN-####'),
            'name' => fake()->company(),
            'contact_name' => fake()->name(),
            'email' => fake()->companyEmail(),
            'phone' => fake()->phoneNumber(),
            'payment_terms_days' => 30,
            'is_active' => true,
        ];
    }
}
