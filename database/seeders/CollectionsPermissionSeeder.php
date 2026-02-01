<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Modules\Security\Models\User;
use Modules\Security\Models\Role;

class CollectionsPermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('🏦 Creando permisos del módulo Collections...');
        
        // Permisos específicos de Collections
        $collectionsPermissions = [
            'collections.access',
            'collections.dashboard.view',
            'collections.schedules.view',
            'collections.schedules.create',
            'collections.schedules.update',
            'collections.schedules.delete',
            'collections.reports.view',
            'collections.reports.generate',
            'collections.customer-payments.view',
            'collections.customer-payments.create',
            'collections.customer-payments.update',
            'collections.customer-payments.redetect',
            'collections.accounts-receivable.view',
            'collections.accounts-receivable.overdue',
            'collections.view',
            'collections.create',
            'collections.reports',
        ];

        // Crear permisos
        foreach ($collectionsPermissions as $permission) {
            Permission::firstOrCreate([
                'name' => $permission,
                'guard_name' => 'sanctum',
            ]);
        }

        $this->command->info('   ✅ ' . count($collectionsPermissions) . ' permisos de Collections creados');

        // Asignar permisos al usuario admin
        $adminUser = User::where('email', 'admin@casabonita.com')->first();
        
        if ($adminUser) {
            $adminUser->givePermissionTo($collectionsPermissions);
            $this->command->info('   ✅ Permisos asignados al usuario admin@casabonita.com');
        } else {
            $this->command->warn('   ⚠️  Usuario admin no encontrado');
        }

        // Asignar permisos a todos los usuarios con rol admin
        $adminRole = Role::whereIn('name', ['Administrador', 'admin'])->first();
        if ($adminRole) {
            $adminRole->givePermissionTo($collectionsPermissions);
            $this->command->info("   ✅ Permisos asignados al rol {$adminRole->name}");
        }

        $this->command->info('🎯 Módulo Collections configurado correctamente!');
    }
}
