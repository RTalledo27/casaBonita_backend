<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Modules\Security\Models\User;

class ReportsPermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('🔐 Creando permisos del módulo Reports...');

        // Definir permisos del módulo Reports
        $reportsPermissions = [
            'reports.access',                            // Acceso general al módulo Reports
            'reports.view',                              // Ver reportes
            'reports.view_dashboard',                    // Ver dashboard de reportes
            'reports.view_sales',                        // Ver reportes de ventas
            'reports.view_payments',                     // Ver cronogramas de pagos
            'reports.view_projections',                  // Ver reportes proyectados
            'reports.export',                            // Exportar reportes
        ];

        // Crear permisos si no existen
        $createdCount = 0;
        foreach ($reportsPermissions as $permission) {
            $perm = Permission::firstOrCreate(
                ['name' => $permission],
                ['guard_name' => 'sanctum']
            );
            if ($perm->wasRecentlyCreated) {
                $createdCount++;
            }
        }

        $this->command->info("   ✅ {$createdCount} permisos de Reports creados");

        // Asignar permisos al usuario admin
        $adminUser = User::where('email', 'admin@casabonita.com')->first();
        if ($adminUser) {
            $adminUser->givePermissionTo($reportsPermissions);
            $this->command->info('   ✅ Permisos asignados al usuario admin@casabonita.com');
        } else {
            $this->command->warn('   ⚠️  Usuario admin no encontrado');
        }

        // Asignar permisos al rol admin
        $adminRole = Role::where('name', 'admin')->first();
        if ($adminRole) {
            $adminRole->givePermissionTo($reportsPermissions);
            $this->command->info('   ✅ Permisos asignados al rol admin');
        } else {
            $this->command->warn('   ⚠️  Rol admin no encontrado');
        }

        $this->command->info('✅ Seeder de permisos Reports completado');
    }
}