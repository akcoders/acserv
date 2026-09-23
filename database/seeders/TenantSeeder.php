<?php

namespace Database\Seeders;

use App\Enums\AssetStatus;
use App\Enums\CustomerType;
use App\Enums\Role;
use App\Enums\TenantStatus;
use App\Enums\UserStatus;
use App\Models\Tenant;
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
        $tenant = Tenant::query()->create([
            'name' => 'ACServ Demo',
            'slug' => config('acserv.demo_login.workspace'),
            'status' => TenantStatus::Active,
            'timezone' => 'Asia/Kolkata',
            'locale' => 'en_IN',
        ]);

        $this->tenantContext->set($tenant->id);

        try {
            $owner = $tenant->users()->make([
                'first_name' => 'Demo',
                'last_name' => 'Owner',
                'email' => config('acserv.demo_login.owner_email'),
                'phone' => '+919999999900',
                'role' => Role::Owner,
                'status' => UserStatus::Active,
            ]);
            $owner->forceFill(['email_verified_at' => now(), 'phone_verified_at' => now()])->save();

            $branch = $tenant->branches()->create([
                'code' => 'MAIN',
                'name' => 'Main Branch',
                'email' => config('acserv.demo_login.owner_email'),
                'phone' => '+919999999900',
                'address' => [
                    'line_1' => '24 MG Road',
                    'city' => 'Mumbai',
                    'state' => 'Maharashtra',
                    'postal_code' => '400001',
                    'country' => 'IN',
                ],
                'latitude' => 19.0760000,
                'longitude' => 72.8777000,
                'is_active' => true,
            ]);

            $customer = $branch->customers()->create([
                'customer_number' => 'CUS-DEMO-001',
                'type' => CustomerType::Residential,
                'name' => 'Priya Mehta',
                'email' => config('acserv.demo_login.customer_email'),
                'phone' => '+919999999902',
                'service_address' => [
                    'line_1' => '24 MG Road',
                    'city' => 'Mumbai',
                    'state' => 'Maharashtra',
                    'postal_code' => '400001',
                    'country' => 'IN',
                ],
            ]);

            $customer->assets()->create([
                'branch_id' => $branch->getKey(),
                'name' => 'Living Room AC',
                'brand' => 'Samsung',
                'model' => 'AC-5287KV',
                'serial_number' => 'SN-DEMO-001',
                'capacity' => '1.5 Ton',
                'asset_type' => 'SPLIT',
                'install_date' => today()->subYears(2),
                'status' => AssetStatus::Active,
            ]);
        } finally {
            $this->tenantContext->clear();
        }
    }
}
