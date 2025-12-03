<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Modules\Security\Models\User;
use Modules\Security\Models\Role;
use Spatie\Permission\Models\Permission;

class AdminUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('🚀 Iniciando creación de usuario administrador...');

        // Crear todos los permisos necesarios
        $this->createPermissions();

        // Crear rol de administrador
        $adminRole = $this->createAdminRole();

        // Crear usuario administrador
        $adminUser = $this->createAdminUser();

        // Asignar rol al usuario
        $adminUser->assignRole($adminRole);

        $this->command->info('✅ Usuario administrador creado exitosamente!');
        $this->command->info('📋 Detalles del usuario:');
        $this->command->line('   • ID: ' . $adminUser->id);
        $this->command->line('   • Usuario: ' . $adminUser->username);
        $this->command->line('   • Email: ' . $adminUser->email);
        $this->command->line('   • Rol: ' . $adminRole->name);
        $this->command->line('   • Permisos: ' . $adminRole->permissions->count() . ' permisos asignados');
    }

    /**
     * Crear todos los permisos del sistema
     */
    private function createPermissions(): void
    {
        $this->command->info('🔐 Creando permisos del sistema...');

        $permissions = [
            //MODULE SECURITY - PERMISSIONS
            'security.access', //Permiso para acceder a la sección de seguridad
            'security.permissions.view',
            'security.permissions.store',
            'security.permissions.update',
            'security.permissions.destroy',
            //MODULE SECURITY - ROLES
            'security.roles.view',
            'security.roles.store',
            'security.roles.update',
            'security.roles.destroy',
            //MODULE SECURITY - USERS
            'security.users.index',
            'security.users.store',
            'security.users.update',
            'security.users.destroy',
            'security.users.change-password',
            'security.users.toggle-status',

            //MODULE CRM - ADDRESS
            'crm.addresses.view',
            'crm.addresses.store',
            'crm.addresses.update',
            'crm.addresses.destroy',
            //MODULE CRM - CLIENTS
            'crm.clients.view',
            'crm.clients.store',
            'crm.clients.update',
            'crm.clients.delete',
            'crm.clients.spouses.view',
            'crm.clients.spouses.store',
            'crm.clients.spouses.delete',
            'crm.clients.export',
            //MODULE CRM - INTERACTIONS
            'crm.access', //Permiso para acceder a la sección de CRM
            'crm.interactions.view',
            'crm.interactions.store',
            'crm.interactions.update',
            'crm.interactions.delete',

            // MODULE INVENTORY
            'inventory.access',
            'inventory.manzanas.view',
            'inventory.manzanas.store',
            'inventory.manzanas.update',
            'inventory.manzanas.delete',
            'inventory.street-types.view',
            'inventory.street-types.store',
            'inventory.street-types.update',
            'inventory.street-types.delete',
            'inventory.lots.view',
            'inventory.lots.store',
            'inventory.lots.update',
            'inventory.lots.delete',
            'inventory.media.manage',
            'inventory.media.index',
            'inventory.media.store',
            'inventory.media.update',
            'inventory.media.destroy',

            // MODULE SALES
            'sales.reservations.access',
            'sales.reservations.view',
            'sales.reservations.store',
            'sales.reservations.update',
            'sales.reservations.cancel',
            'sales.reservations.convert',
            'sales.access',
            'sales.contracts.view',
            'sales.contracts.store',
            'sales.contracts.update',
            'sales.contracts.delete',
            'sales.conversions.process',

            // MODULE SERVICE DESK - TICKETS
            'service-desk.tickets.view',
            'service-desk.tickets.store',
            'service-desk.tickets.update',
            'service-desk.tickets.delete',
            'service-desk.tickets.assign',
            'service-desk.tickets.actions',
            'service-desk.tickets.close',

            // MODULE SERVICE DESK - ACCIONES (HISTORIAL)
            'service-desk.actions.view',
            'service-desk.actions.store',
            'service-desk.actions.update',
            'service-desk.actions.delete',

            // MODULE HUMAN RESOURCES
            'hr.access',
            'hr.employees.view',
            'hr.employees.store',
            'hr.employees.update',
            'hr.employees.destroy',
            'hr.employees.generate-user',
            'hr.employees.dashboard',
            'hr.commissions.view',
            'hr.commissions.store',
            'hr.commissions.update',
            'hr.commissions.destroy',
            'hr.commissions.pay',
            'hr.commissions.process',
            'hr.commissions.split-payment',
            'hr.commission-verifications.view',
            'hr.commission-verifications.verify',
            'hr.commission-verifications.reverse',
            'hr.commission-verifications.process',
            'hr.commission-verifications.stats',
            'hr.payroll.view',
            'hr.payroll.generate',
            'hr.payroll.process',
            'hr.payroll.approve',
            'hr.bonuses.view',
            'hr.bonuses.store',
            'hr.bonuses.update',
            'hr.bonuses.destroy',
            'hr.bonuses.process',
            'hr.bonus-types.view',
            'hr.bonus-types.store',
            'hr.bonus-types.update',
            'hr.bonus-types.destroy',
            'hr.bonus-goals.view',
            'hr.bonus-goals.store',
            'hr.bonus-goals.update',
            'hr.bonus-goals.destroy',
            'hr.teams.view',
            'hr.teams.store',
            'hr.teams.update',
            'hr.teams.destroy',
            'hr.teams.assign-leader',
            'hr.teams.toggle-status',
            'hr.employee-import.validate',
            'hr.employee-import.import',
            'hr.employee-import.template',

            // MODULE COLLECTIONS
            'collections.access',
            'collections.dashboard.view',
            'collections.schedules.create',
            'collections.schedules.view',
            'collections.followups.view',
            'collections.followups.store',
            'collections.followups.update',
            'collections.customer-payments.view',
            'collections.customer-payments.create',
            'collections.customer-payments.update',
            'collections.customer-payments.delete',
            'collections.customer-payments.redetect',
            'collections.customer-payments.stats',
            'collections.accounts-receivable.view',
            'collections.accounts-receivable.create',
            'collections.accounts-receivable.update',
            'collections.accounts-receivable.delete',
            'collections.accounts-receivable.overdue',
            'collections.accounts-receivable.assign_collector',
            'collections.receivables.view',
            'collections.receivables.create',
            'collections.receivables.update',
            'collections.receivables.delete',
            'collections.hr-integration.view',
            'collections.hr-integration.sync',
            'collections.hr-integration.process',
            'collections.hr-integration.mark',
            'collections.reports.view',
            'collections.alerts.view',
            'collections.collectors.view',
            'collections.view',
            'collections.create',
            'collections.reports',

            // MODULE REPORTS
            'reports.access',
            'reports.view', 
            'reports.view_dashboard',
            'reports.view_sales',
            'reports.view_payments', 
            'reports.view_projections',
            'reports.export',
            'reports.create',
            'reports.edit',
            'reports.delete',
            'reports.admin'
        ];

        $createdCount = 0;
        foreach ($permissions as $permission) {
            Permission::firstOrCreate(
                ['name' => $permission],
                ['guard_name' => 'sanctum']
            );
            $createdCount++;
        }

        $this->command->line("   ✓ {$createdCount} permisos creados/verificados");
    }

    /**
     * Crear rol de administrador con todos los permisos
     */
    private function createAdminRole(): Role
    {
        $this->command->info('👑 Creando rol de administrador...');

        // Crear o actualizar rol de administrador
        $adminRole = Role::firstOrCreate(
            ['name' => 'Administrador'],
            [
                'guard_name' => 'sanctum',
                'description' => 'Rol con acceso completo al sistema'
            ]
        );

        // Asegurar que el rol se haya guardado correctamente
        $adminRole->refresh();
        
        // Verificar que el rol tiene un ID válido (la tabla roles usa role_id)
        if (!$adminRole->role_id) {
            throw new \Exception('Error: No se pudo crear el rol de administrador');
        }

        // Asignar todos los permisos al rol
        $allPermissions = Permission::where('guard_name', 'sanctum')->get();
        
        if ($allPermissions->count() > 0) {
            $adminRole->syncPermissions($allPermissions);
            $this->command->line("   ✓ Rol 'Administrador' creado con {$allPermissions->count()} permisos");
        } else {
            $this->command->warn('   ⚠ No se encontraron permisos para asignar al rol');
        }

        return $adminRole;
    }

    /**
     * Crear usuario administrador
     */
    private function createAdminUser(): User
    {
        $this->command->info('👤 Creando usuario administrador...');

        // Eliminar usuario admin existente si existe
        User::where('email', 'admin@casabonita.com')->delete();

        // Crear nuevo usuario administrador
        $adminUser = User::create([
            'username' => 'admin',
            'email' => 'admin@casabonita.com',
            'password_hash' => Hash::make('admin123'),
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->command->line("   ✓ Usuario administrador creado (ID: {$adminUser->id})");

        return $adminUser;
    }
}
