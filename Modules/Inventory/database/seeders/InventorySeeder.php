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
        Permission::firstOrCreate(['name' => 'inventory.access']);
        Permission::firstOrCreate(['name' => 'inventory.manzanas.view']);
        Permission::firstOrCreate(['name' => 'inventory.manzanas.create']);
        Permission::firstOrCreate(['name' => 'inventory.manzanas.update']);
        Permission::firstOrCreate(['name' => 'inventory.manzanas.delete']);
        Permission::firstOrCreate(['name' => 'inventory.lots.view']);
        Permission::firstOrCreate(['name' => 'inventory.lots.create']);
        Permission::firstOrCreate(['name' => 'inventory.lots.update']);
        Permission::firstOrCreate(['name' => 'inventory.lots.delete']);
        Permission::firstOrCreate(['name' => 'inventory.media.manage']);
    }
}
