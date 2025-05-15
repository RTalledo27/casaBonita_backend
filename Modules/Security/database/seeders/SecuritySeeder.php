<?php

namespace Modules\Security\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Security\Models\Role;
use Modules\Security\Models\User;

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

        // admin user
        $admin = User::firstOrCreate(
            ['email' => 'prueba@casabonita.com'],
            ['username' => 'admin', 'password_hash' => bcrypt('Romaim27'), 'status' => 'active']
        );
        $admin->roles()->sync([$adminRole->role_id]);
    }
}
