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
        Permission::firstOrCreate(['name' => 'sales.reservations.access']);
        Permission::firstOrCreate(['name' => 'sales.reservations.view']);
        Permission::firstOrCreate(['name' => 'sales.reservations.create']);
        Permission::firstOrCreate(['name' => 'sales.reservations.update']);
        Permission::firstOrCreate(['name' => 'sales.reservations.cancel']);
        Permission::firstOrCreate(['name' => 'sales.reservations.convert']);
        // $this->call([]);
        Permission::firstOrCreate(['name' => 'sales.access']);
        Permission::firstOrCreate(['name' => 'sales.contracts.view']);
        Permission::firstOrCreate(['name' => 'sales.contracts.create']);
        Permission::firstOrCreate(['name' => 'sales.contracts.update']);
        Permission::firstOrCreate(['name' => 'sales.contracts.delete']);
        Permission::firstOrCreate(['name' => 'sales.conversions.process']);
    }
}
