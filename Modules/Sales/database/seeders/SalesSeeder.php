<?php

namespace Modules\Sales\Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;

class SalesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Permission::firstOrCreate(['name' => 'sales.reservations.access', 'guard_name' => 'sanctum']);
        Permission::firstOrCreate(['name' => 'sales.reservations.view', 'guard_name' => 'sanctum']);
        Permission::firstOrCreate(['name' => 'sales.reservations.create', 'guard_name' => 'sanctum']);
        Permission::firstOrCreate(['name' => 'sales.reservations.update', 'guard_name' => 'sanctum']);
        Permission::firstOrCreate(['name' => 'sales.reservations.cancel', 'guard_name' => 'sanctum']);
        Permission::firstOrCreate(['name' => 'sales.reservations.convert', 'guard_name' => 'sanctum']);
        // $this->call([]);
        Permission::firstOrCreate(['name' => 'sales.access', 'guard_name' => 'sanctum']);
        Permission::firstOrCreate(['name' => 'sales.contracts.view', 'guard_name' => 'sanctum']);
        Permission::firstOrCreate(['name' => 'sales.contracts.create', 'guard_name' => 'sanctum']);
        Permission::firstOrCreate(['name' => 'sales.contracts.update', 'guard_name' => 'sanctum']);
        Permission::firstOrCreate(['name' => 'sales.contracts.delete', 'guard_name' => 'sanctum']);
        Permission::firstOrCreate(['name' => 'sales.conversions.process', 'guard_name' => 'sanctum']);
    }
}
