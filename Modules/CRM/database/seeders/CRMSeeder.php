<?php

namespace Modules\CRM\Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;

class CRMSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // $this->call([]);
        Permission::firstOrCreate(['name' => 'crm.access', 'guard_name' => 'sanctum']);
        Permission::firstOrCreate(['name' => 'crm.clients.view', 'guard_name' => 'sanctum']);
        Permission::firstOrCreate(['name' => 'crm.clients.create', 'guard_name' => 'sanctum']);
        Permission::firstOrCreate(['name' => 'crm.clients.update', 'guard_name' => 'sanctum']);
        Permission::firstOrCreate(['name' => 'crm.clients.destroy', 'guard_name' => 'sanctum']);
        Permission::firstOrCreate(['name' => 'crm.spouses.manage', 'guard_name' => 'sanctum']);
        Permission::firstOrCreate(['name' => 'crm.addresses.manage', 'guard_name' => 'sanctum']);
        Permission::firstOrCreate(['name' => 'crm.interactions.view', 'guard_name' => 'sanctum']);
        Permission::firstOrCreate(['name' => 'crm.interactions.create', 'guard_name' => 'sanctum']);
        Permission::firstOrCreate(['name' => 'crm.interactions.update', 'guard_name' => 'sanctum']);
        Permission::firstOrCreate(['name' => 'crm.interactions.destroy', 'guard_name' => 'sanctum']);
    }
}
