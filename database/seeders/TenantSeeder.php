<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\Asset;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\Tenant;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Database\Seeder;

class TenantSeeder extends Seeder
{
    public function __construct(private readonly TenantContext $tenantContext) {}

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $tenant = Tenant::factory()->create([
            'name' => 'ACServ Demo',
            'slug' => config('acserv.demo_login.workspace'),
        ]);

        $this->tenantContext->set($tenant->id);

        try {
            User::factory()->for($tenant)->create([
                'first_name' => 'Demo',
                'last_name' => 'Owner',
                'email' => config('acserv.demo_login.owner_email'),
                'role' => Role::Owner,
            ]);

            $branch = Branch::factory()->for($tenant)->create([
                'code' => 'MAIN',
                'name' => 'Main Branch',
            ]);

            $customer = Customer::factory()
                ->for($tenant)
                ->for($branch)
                ->create();

            Asset::factory()
                ->for($tenant)
                ->for($customer)
                ->for($branch)
                ->create();
        } finally {
            $this->tenantContext->clear();
        }
    }
}
