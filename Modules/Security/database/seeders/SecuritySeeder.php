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


        // roles
        $adminRole   = Role::firstOrCreate(['name' => 'admin']);
        $sellerRole  = Role::firstOrCreate(['name' => 'vendedor']);
        $accountRole = Role::firstOrCreate(['name' => 'contador']);


        Permission::firstOrCreate(['name' => 'security.access']);
        Permission::firstOrCreate(['name' => 'security.users.view']);
        Permission::firstOrCreate(['name' => 'security.users.index']);
        Permission::firstOrCreate(['name' => 'security.users.create']);
        Permission::firstOrCreate(['name' => 'security.users.update']);
        Permission::firstOrCreate(['name' => 'security.users.delete']);
        Permission::firstOrCreate(['name' => 'security.roles.view']);
        Permission::firstOrCreate(['name' => 'security.roles.create']);
        Permission::firstOrCreate(['name' => 'security.roles.update']);
        Permission::firstOrCreate(['name' => 'security.roles.delete']);
        Permission::firstOrCreate(['name' => 'security.permissions.view']);
        $this->call(PermissionSeeder::class);


        // admin user
        $admin = User::firstOrCreate(
            ['email' => 'prueba@casabonita.com'],
            ['username' => 'admin', 'password_hash' => bcrypt('Romaim27'), 'status' => 'active']
        );
        $admin->roles()->sync([$adminRole->role_id]);
    }
}
