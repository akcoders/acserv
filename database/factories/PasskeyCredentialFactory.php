<?php

namespace Database\Factories;

use App\Models\PasskeyCredential;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PasskeyCredential>
 */
class PasskeyCredentialFactory extends Factory
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
            'credential_id' => fake()->unique()->regexify('[A-Za-z0-9_-]{64}'),
            'public_key' => fake()->sha256(),
            'sign_count' => 0,
            'transports' => ['internal'],
            'aaguid' => fake()->uuid(),
            'name' => 'Test passkey',
        ];
    }
}
