<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Modules\CRM\Database\Seeders\CRMDatabaseSeeder;
use Modules\Inventory\Database\Seeders\InventoryDatabaseSeeder;
use Modules\Security\Database\Seeders\SecuritySeeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        //$this->call(SecuritySeeder::class);
        //CRMDatabaseSeeder::class);
        //$this->call(CRMDatabaseSeeder::class);
        //$this->call(InventoryDatabaseSeeder::class);
        $this->call(CompleteTestSeeder::class);
    }
}
