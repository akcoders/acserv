<?php

namespace Tests\Feature;

use App\Models\Tenant;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_the_application_returns_a_successful_response(): void
    {
        Tenant::factory()->create(['slug' => config('acserv.public_tenant_slug')]);

        $response = $this->get('/');

        $response->assertStatus(200);
    }
}
