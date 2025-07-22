<?php

namespace Modules\Security\Database\Seeders;

use Illuminate\Database\Seeder;

class SecurityDatabaseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // $this->call([]);
        $this->call(PermissionSeeder::class);
        $this->call(RoleSeeder::class);
        $this->call(SecuritySeeder::class);
        //$this->call(UserSeeder::class);
    }
}
