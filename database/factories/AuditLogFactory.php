<?php

namespace Database\Factories;

use App\Enums\AuditAction;
use App\Models\AuditLog;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AuditLog>
 */
class AuditLogFactory extends Factory
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
            'entity' => 'customer',
            'entity_id' => fake()->uuid(),
            'action' => AuditAction::Create,
            'diff' => ['after' => ['name' => fake()->name()]],
            'ip_hash' => hash('sha256', '127.0.0.1'),
            'user_agent' => 'PHPUnit',
        ];
    }
}
