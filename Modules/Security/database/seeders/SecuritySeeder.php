<?php

namespace Modules\Security\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Security\Models\Role;
use Modules\Security\Models\User;
use Spatie\Permission\Models\Permission;

class SecuritySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {

        $this->call(UserEmployeeSeeder::class);
    }
}
