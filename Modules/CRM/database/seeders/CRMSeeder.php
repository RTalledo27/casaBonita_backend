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
        Permission::firstOrCreate(['name' => 'crm.access']);
        Permission::firstOrCreate(['name' => 'crm.clients.view']);
        Permission::firstOrCreate(['name' => 'crm.clients.create']);
        Permission::firstOrCreate(['name' => 'crm.clients.update']);
        Permission::firstOrCreate(['name' => 'crm.clients.delete']);
        Permission::firstOrCreate(['name' => 'crm.spouses.manage']);
        Permission::firstOrCreate(['name' => 'crm.addresses.manage']);
        Permission::firstOrCreate(['name' => 'crm.interactions.view']);
        Permission::firstOrCreate(['name' => 'crm.interactions.create']);
        Permission::firstOrCreate(['name' => 'crm.interactions.update']);
        Permission::firstOrCreate(['name' => 'crm.interactions.delete']);
    }
}
