<?php

namespace Modules\Integrations\Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;

class IntegrationsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // $this->call([]);
        Permission::firstOrCreate(['name' => 'integrations.access', 'guard_name' => 'sanctum']);
        Permission::firstOrCreate(['name' => 'integrations.api.sunat', 'guard_name' => 'sanctum']);
        Permission::firstOrCreate(['name' => 'integrations.api.payment', 'guard_name' => 'sanctum']);
      }
}
