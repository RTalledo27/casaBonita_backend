<?php

namespace Modules\Security\Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;

class PermissionSeeder extends Seeder
{


    /**
     * Run the database seeds.
     */
    public function run(): void
    {

        $permissions = [
            'security.permissions.index',
            'security.permissions.show',
            'security.permissions.store',
        ];

        foreach ($permissions as $perm) {
            Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'sanctum']);
        }
    }
}
