<?php

namespace Database\Factories;

use App\Enums\OtpChannel;
use App\Enums\OtpPurpose;
use App\Models\OtpChallenge;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/**
 * @extends Factory<OtpChallenge>
 */
class OtpChallengeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'channel' => OtpChannel::Email,
            'purpose' => OtpPurpose::Login,
            'destination_hash' => hash('sha256', fake()->unique()->safeEmail()),
            'code_hash' => Hash::make('123456'),
            'attempts' => 0,
            'expires_at' => now()->addMinutes(5),
            'requested_ip_hash' => hash('sha256', '127.0.0.1'),
        ];
    }
}
