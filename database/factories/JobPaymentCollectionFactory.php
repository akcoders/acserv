<?php

namespace Database\Factories;

use App\Enums\CollectionStatus;
use App\Enums\PaymentMode;
use App\Models\JobPaymentCollection;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<JobPaymentCollection>
 */
class JobPaymentCollectionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'mode' => PaymentMode::Cash,
            'status' => CollectionStatus::Pending,
            'amount' => 500,
            'submitted_at' => now(),
        ];
    }
}
