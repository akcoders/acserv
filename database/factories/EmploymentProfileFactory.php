<?php

namespace Database\Factories;

use App\Models\EmploymentProfile;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EmploymentProfile>
 */
class EmploymentProfileFactory extends Factory
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
            'user_id' => User::factory(),
            'employee_code' => 'EMP-'.fake()->unique()->numerify('#####'),
            'designation' => fake()->jobTitle(),
            'pay_grade' => 'G1',
            'employment_type' => 'FULL_TIME',
            'monthly_salary' => 30000,
            'incentive_per_job' => 0,
            'joined_on' => now()->toDateString(),
        ];
    }
}
