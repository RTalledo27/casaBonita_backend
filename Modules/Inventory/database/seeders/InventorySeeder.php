<?php

namespace Modules\Inventory\Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;

class InventorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // $this->call([]);
        Permission::firstOrCreate(['name' => 'inventory.access', 'guard_name' => 'sanctum']);
        Permission::firstOrCreate(['name' => 'inventory.manzanas.view', 'guard_name' => 'sanctum']);
        Permission::firstOrCreate(['name' => 'inventory.manzanas.store', 'guard_name' => 'sanctum']);
        Permission::firstOrCreate(['name' => 'inventory.manzanas.update', 'guard_name' => 'sanctum']);
        Permission::firstOrCreate(['name' => 'inventory.manzanas.delete', 'guard_name' => 'sanctum']);
        Permission::firstOrCreate(['name' => 'inventory.street-types.view', 'guard_name' => 'sanctum']);
        Permission::firstOrCreate(['name' => 'inventory.street-types.store', 'guard_name' => 'sanctum']);
        Permission::firstOrCreate(['name' => 'inventory.street-types.update', 'guard_name' => 'sanctum']);
        Permission::firstOrCreate(['name' => 'inventory.street-types.delete', 'guard_name' => 'sanctum']);
        Permission::firstOrCreate(['name' => 'inventory.lots.view', 'guard_name' => 'sanctum']);
        Permission::firstOrCreate(['name' => 'inventory.lots.store', 'guard_name' => 'sanctum']);
        Permission::firstOrCreate(['name' => 'inventory.lots.update', 'guard_name' => 'sanctum']);
        Permission::firstOrCreate(['name' => 'inventory.lots.delete', 'guard_name' => 'sanctum']);
        Permission::firstOrCreate(['name' => 'inventory.media.manage', 'guard_name' => 'sanctum']);
    }
}
