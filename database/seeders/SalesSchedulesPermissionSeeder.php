<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Modules\Security\Models\User;

class SalesSchedulesPermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Crear permisos de Sales Schedules si no existen
        $permissions = [
            'sales.schedules.index',
            'sales.schedules.view', 
            'sales.schedules.store',
            'sales.schedules.update',
            'sales.schedules.destroy',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate([
                'name' => $permission,
                'guard_name' => 'sanctum'
            ]);
            $this->command->info("Permission created or found: {$permission}");
        }

        // Asignar permisos al usuario admin@casabonita.com
        $adminUser = User::where('email', 'admin@casabonita.com')->first();
        if ($adminUser) {
            foreach ($permissions as $permission) {
                $adminUser->givePermissionTo($permission);
            }
            $this->command->info('Sales Schedules permissions assigned to admin@casabonita.com');
        } else {
            $this->command->warn('User admin@casabonita.com not found');
        }

        // Asignar permisos al rol admin
        $adminRole = Role::where('name', 'admin')->first();
        if ($adminRole) {
            foreach ($permissions as $permission) {
                $adminRole->givePermissionTo($permission);
            }
            $this->command->info('Sales Schedules permissions assigned to admin role');
        } else {
            $this->command->warn('Admin role not found');
        }
    }
}