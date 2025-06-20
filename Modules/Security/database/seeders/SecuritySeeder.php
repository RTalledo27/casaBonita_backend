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
        $adminRole = Role::firstOrCreate([
            'name' => 'admin',
            'guard_name' => 'sanctum'
        ]);
        //$sellerRole  = Role::firstOrCreate(['name' => 'vendedor', 'guard_name' => 'sanctum']);
        //$accountRole = Role::firstOrCreate(['name' => 'contador', 'guard_name' => 'sanctum']);


        Permission::firstOrCreate(['name' => 'security.access', 'guard_name' => 'sanctum']);
        Permission::firstOrCreate(['name' => 'security.users.view', 'guard_name' => 'sanctum']);
        Permission::firstOrCreate(['name' => 'security.users.index', 'guard_name' => 'sanctum']);
        Permission::firstOrCreate(['name' => 'security.users.create', 'guard_name' => 'sanctum']);
        Permission::firstOrCreate(['name' => 'security.users.update', 'guard_name' => 'sanctum']);
        Permission::firstOrCreate(['name' => 'security.users.delete', 'guard_name' => 'sanctum']);
        Permission::firstOrCreate(['name' => 'security.roles.view', 'guard_name' => 'sanctum']);
        Permission::firstOrCreate(['name' => 'security.roles.create', 'guard_name' => 'sanctum']);
        Permission::firstOrCreate(['name' => 'security.roles.update', 'guard_name' => 'sanctum']);
        Permission::firstOrCreate(['name' => 'security.roles.delete', 'guard_name' => 'sanctum']);
        Permission::firstOrCreate(['name' => 'security.permissions.view', 'guard_name' => 'sanctum']);
        $this->call(PermissionSeeder::class);


        // admin user
        $admin = User::firstOrCreate(
            ['email' => 'prueba@casabonita.com'],
            ['username' => 'admin', 'password_hash' => bcrypt('Romaim27'), 'status' => 'active']
        );
        $admin->roles()->sync([$adminRole->role_id]);
        $adminRole->syncPermissions(Permission::all());
        }
}
