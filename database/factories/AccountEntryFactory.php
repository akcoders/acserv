<?php

namespace Database\Factories;

use App\Models\AccountEntry;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AccountEntry>
 */
class AccountEntryFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'entry_number' => fake()->unique()->bothify('ACC-########'),
            'type' => 'EXPENSE',
            'category' => 'OTHER',
            'description' => fake()->sentence(),
            'amount' => 100,
            'payment_method' => 'BANK',
            'entry_date' => today(),
        ];
    }
}
